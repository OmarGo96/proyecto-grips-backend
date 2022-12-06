<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Enums\NotificationPriority;
use App\Enums\PreSolicitudStatus;
use App\Enums\SolicitudStatus;
use App\Helpers\GeneralUseHelper;
use App\Helpers\GeneratePreSol;
use App\Helpers\GeoDistance;
use App\Helpers\Odoo;
use App\Helpers\PushNotificationsHelper;
use App\Models\AccountPreRequestLineTax;
use App\Models\AppNotifications;
use App\Models\Company;
use App\Models\FleetVehicle;
use App\Models\Operadores;
use App\Models\PadSolicitudes;
use App\Models\PadVehiculos;
use App\Models\PreSolicitudes;
use App\Models\PreSolicitudesLine;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PreSolicitudesController extends Controller
{
    /**
     * @uses Endpoints: partners
     */
    public function newPreSolicitud(Request $request)
    {
        $user = $request->user;

        $validateData = PreSolicitudes::validateBeforeSave($request->all());

        if ($validateData !== true) {
            return response()->json($validateData, JsonResponse::BAD_REQUEST);
        }


        $body = (object) $request->all();

        // validamos que el vehículo no este enrolado con otra solicitud
        $vehiculoId = $body->datosIniciales['vehiculo_id'];

        $foundPreSol = PreSolicitudes::where('vehiculo_id', $vehiculoId)->whereIn('status', [PreSolicitudStatus::NUEVA, PreSolicitudStatus::OPERADORASIGNADO])
            ->where('partner_id', $user->id)
            ->where('date_reg', Carbon::now())
            ->first();
        if ($foundPreSol) {
            return response()->json([
                'ok' => false,
                'errors' => ['Ya existe una solicitud realizada para este vehículo, intente con otro diferente']
            ], JsonResponse::BAD_REQUEST);
        }

        $body->solicitud['fecha_hora'] = Carbon::now()->format('Y-m-d H:i:s.u');

        $preSol = new PreSolicitudes();
        $preSol->status_sol = $body->status;
        $preSol->type = $body->type;
        $preSol->datosIniciales = $body->datosIniciales;
        $preSol->vehiculo_id = $body->datosIniciales['vehiculo_id'];
        $preSol->tiposervicio_id = $body->datosIniciales['tiposervicio_id'];
        $preSol->cotizacion = isset($body->cotizacion) ? $body->cotizacion : null;
        $preSol->solicitud = $body->solicitud;
        $preSol->telefono = $body->telefono;
        $preSol->lat = $body->lat;
        $preSol->lon = $body->lon;
        $preSol->fecha_hora_reservacion =  $body->solicitud['fecha_hora'];
        $preSol->tipopago_id = $body->solicitud['tipopago_id'];
        $preSol->date_reg = Carbon::now();
        $preSol->fecha_hora_extension = $body->solicitud['fecha_hora'];

        $preSol->status = PreSolicitudStatus::NUEVA;
        $preSol->partner_id = $user->id;

        $preSol->currency_id = $request->calcObjs[0]['currency_id'][0];
        $preSol->pricelist_id = $request->calcObjs[0]['pricelist_id'][0];
        // Validamos que se encuentre la compañia
        $companyPartnerId = Company::select('id', 'partner_id')->where('partner_id', $request->calcObjs[0]['partner_id'][0])->first();

        if (!$companyPartnerId) {
            return response()->json([
                'ok' => false,
                'errors' => ['Hubo un error al guardar su solicitud, intente de nuevo']
            ], JsonResponse::BAD_REQUEST);
        }
        $preSol->company_id = $companyPartnerId->id;

        if ($request->has('photosVehiculo')) {
            for ($i = 0; $i < count($request->photosVehiculo); $i++) {
                $fileName = '/partner/' . $user->id . '/foto_vehiculo_' . Carbon::now()->unix() . '.jpg';

                try {
                    $encodedImg = explode(",", $request->photosVehiculo[$i])[1];
                } catch (Exception $e) {
                    try {
                        $encodedImg = $request->photosVehiculo[$i];
                    } catch (Exception $e) {
                        Log::debug($e);
                    }
                }
                $decodedImg = base64_decode($encodedImg);

                Storage::disk('images-app')->put($fileName, $decodedImg);
                $preSol->photosVehiculoDir = $fileName;
            }
        }

        if ($request->has('comprobantePago')) {
            for ($i = 0; $i < count($request->comprobantePago); $i++) {
                $fileName = '/partner/' . $user->id . '/comprobante_pago_' . Carbon::now()->unix() . '.jpg';

                try {
                    $encodedImg = explode(",", $request->comprobantePago[$i])[1];
                } catch (Exception $e) {
                    try {
                        $encodedImg = $request->comprobantePago[$i];
                    } catch (Exception $e) {
                        Log::debug($e);
                    }
                }
                $decodedImg = base64_decode($encodedImg);

                Storage::disk('images-app')->put($fileName, $decodedImg);
                $preSol->comprobantePagoDir = $fileName;
            }
        }

        // Verificamos información del operador seleccionado si existe
        if ($request->has('selectedOperator')) {

            $selectedOperator = (object) $request->selectedOperator;
            //Revisamos si el operador puede atender solicitud
            $rev = $this->canOperatorAttend($selectedOperator->id);
            if ($rev->ok === false) {
                return response()->json($rev, JsonResponse::BAD_REQUEST);
            }
            // Colocamos a la presolitud la asiganación del operador
            $preSol->operator_id = $selectedOperator->id;
            $preSol->assignment_datetime = Carbon::now()->format('Y-m-d H:i:s.u');
            $preSol->operator_initCords =
            [
                'lat' => $selectedOperator->position['lat'],
                'lon' => $selectedOperator->position['long']
            ];
            $preSol->tiempoEstimadoArribo = $selectedOperator->data['arrive'];

            $preSol->status = PreSolicitudStatus::OPERADORASIGNADO;
        }

        if ($request->has('cotizacion')) {
            $preSol->cotizacion = $request->cotizacion;

            $preSol->amount_untaxed = $request->cotizacion['calculator']['subtotal'];
            $preSol->amount_tax = $request->cotizacion['calculator']['tax_amount'];
            $preSol->amount_total = $request->cotizacion['calculator']['total'];
        }


        unset($body->photosVehiculo);
        unset($body->comprobantePago);
        $preSol->json_solicitud = $body;

        DB::beginTransaction();
        if ($preSol->save()) {

            //TODO: Bloqueamos a la grúa temporalmente
            $blockFleet = FleetVehicle::blockUnblockFleetVehicle($preSol->operator_id, false);
            if ($blockFleet->ok === false) {
            }

             // Guardamos en cms_pre_solicitudes_line
            if ($request->has('calcObjs')) {
                $quantity = 1;
                try {
                    for ($i = 0; $i < count($request->calcObjs); $i++) {

                        if (isset($request->calcObjs[$i]['uom_id']) && isset($request->calcObjs[$i]['uom_id'][1]) && ($request->calcObjs[$i]['uom_id'][1] == 'km' || $request->calcObjs[$i]['uom_id'][1] == 'KM')) {
                            $quantity = $request->distanciaKm;
                        } else {
                            $quantity = 1;
                        }

                        $cmsPreSolLine = new PreSolicitudesLine();
                        $cmsPreSolLine->request_id = $preSol->id;
                        $cmsPreSolLine->sequence = $i;
                        $cmsPreSolLine->name = $request->calcObjs[$i]['product_id'][1];
                        $cmsPreSolLine->uom_id = $request->calcObjs[$i]['uom_id'][0];
                        $cmsPreSolLine->product_id = $request->calcObjs[$i]['product_id'][0];
                        $cmsPreSolLine->quantity = $quantity;
                        $cmsPreSolLine->discount = 0;
                        $cmsPreSolLine->price_unit = $request->calcObjs[$i]['price_unit'];
                        $cmsPreSolLine->price_subtotal = round(($request->calcObjs[$i]['price_unit']) * ($quantity) , 2);
                        $cmsPreSolLine->price_tax = round($request->calcObjs[$i]['amount_tax'] * $quantity, 2);
                        $cmsPreSolLine->price_total = round($request->calcObjs[$i]['amount_total'] * $quantity , 2);
                        $cmsPreSolLine->company_id = $request->calcObjs[$i]['company_id'][0];
                        $cmsPreSolLine->currency_id = $request->calcObjs[$i]['currency_id'][0];
                        $cmsPreSolLine->create_date = GeneralUseHelper::validDateOODO();
                        $cmsPreSolLine->write_date = GeneralUseHelper::validDateOODO();

                        $cmsPreSolLine->save();

                        if (isset($request->calcObjs[$i]['tax_ids'])) {
                            for ($j = 0; $j < count($request->calcObjs[$i]['tax_ids']); $j++) {
                                $accPreRequestLineTax = new AccountPreRequestLineTax();
                                $accPreRequestLineTax->request_line_id = $cmsPreSolLine->id;
                                $accPreRequestLineTax->tax_id = $request->calcObjs[$i]['tax_ids'][$j];
                                $accPreRequestLineTax->save();
                            }
                        }
                    }
                } catch(\Throwable $e) {
                    DB::rollBack();
                    Log::debug($e);
                    return response()->json([
                        'ok' => false,
                        'errors' => ['Hubo un error al guardar tu solicitud, intenta de nuevo.']
                    ], JsonResponse::BAD_REQUEST);
                }

            }
            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Tu solicitud fue guardada correctamente',
                'id' => $preSol->id
            ], JsonResponse::OK);
        } else {
            DB::rollBack();
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal, intenta nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    /**
     * @uses Endpoints: operators
     */
    public function getPreSolicitudGeo(Request $request)
    {
        return response()->json([
            'ok' => false,
            'errors' => ['Método obsoleto']
        ], JsonResponse::BAD_REQUEST);

        $validateData = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'distance' => 'nullable|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $lat = $request->lat;
        $lon = $request->lon;
        $distance = 10;

        if ($request->has('distance')) {
            $distance = $request->distance;
        }


        $limitBox = GeoDistance::getLimites($lat, $lon, $distance);

        $solicitudes = PreSolicitudes::select(
            'id',
            'datosIniciales',
            'vehiculo_id',
            'tiposervicio_id',
            'solicitud',
            'telefono',
            'tipopago_id',
            'lat',
            'lon',
            'photosVehiculoDir',
            'comprobantePagoDir',
            'fecha_hora_reservacion',
            'fecha_hora_extension',
            'urgencyEmit',
            'partner_id',
            'operator_id',
            'assignment_datetime',
            'cotizacion',
            'tiempoEstimadoArribo',
            'operator_initCords',
            'status',
            DB::raw('(6371 * ACOS(SIN(RADIANS(lat)) * SIN(RADIANS(' . $lat . ')) + COS(RADIANS(lon - ' . $lon . ')) * COS(RADIANS(lat)) * COS(RADIANS(' . $lat . ')))) as distance')
        )
            ->where('status', '=', 1)
            ->whereRaw('(lat BETWEEN ' . $limitBox->min_lat . ' AND ' . $limitBox->max_lat . ')
                       AND (lon BETWEEN ' . $limitBox->min_lng . ' AND ' . $limitBox->max_lng . ')')
            ->with(
                [
                    'vehiculo:id,marca_id,tipovehiculo_id,clase_id,anio,colorvehiculo_id,placas,noserie,alias',
                    'vehiculo.marca:id,name',
                    'vehiculo.tipo:id,name,icon_name',
                    'vehiculo.clase:id,name',
                    'vehiculo.color:id,name',
                    'tiposervicio:id,name',
                    'tipopago:id,name,banco',
                    'partner:id,name,mobile'
                ]
            )

            ->whereRaw('(6371 * ACOS(SIN(RADIANS(lat)) * SIN(RADIANS(' . $lat . ')) + COS(RADIANS(lon - ' . $lon . ')) * COS(RADIANS(lat)) * COS(RADIANS(' . $lat . ')))) < ' . $distance)
            ->whereRaw("to_char(fecha_hora_extension::TIMESTAMP at time zone 'Etc/GMT-5', 'YYYYMMDD') = to_char(now()::TIMESTAMP at time zone 'Etc/GMT-5', 'YYYYMMDD')")
            ->get();


        $res = [];
        for ($i = 0; $i < count($solicitudes); $i++) {
            $response = GeneratePreSol::preparePreSolicitudResponse($solicitudes[$i]);
            array_push($res, $response);
        }

        return response()->json([
            'ok' => true,
            'solicitudes' => $res
        ], JsonResponse::OK);
    }

    public function getPreSolicitudData(Request $request)
    {

        $idSol = (int) $request->id;

        $solicitud = PreSolicitudes::select(
            'id',
            'datosIniciales',
            'vehiculo_id',
            'tiposervicio_id',
            'solicitud',
            'telefono',
            'tipopago_id',
            'lat',
            'lon',
            'photosVehiculoDir',
            'comprobantePagoDir',
            'fecha_hora_reservacion',
            'fecha_hora_extension',
            'urgencyEmit',
            'partner_id',
            'operator_id',
            'assignment_datetime',
            'cotizacion',
            'tiempoEstimadoArribo',
            'operator_initCords',
            'status'
        )
            ->with([
                'vehiculo:id,marca_id,tipovehiculo_id,clase_id,anio,colorvehiculo_id,placas,noserie,alias',
                'vehiculo.marca:id,name',
                'vehiculo.tipo:id,name,icon_name',
                'vehiculo.clase:id,name',
                'vehiculo.color:id,name',
                'tiposervicio:id,name',
                'tipopago:id,name,banco',
                'partner:id,name,mobile',
                'operator'
            ])
            ->where('id', $idSol)
            ->first();

        $res = GeneratePreSol::preparePreSolicitudResponse($solicitud);

        return response()->json([
            'ok' => true,
            'solicitud' => $res
        ], JsonResponse::OK);
    }

    public function getPreSolictudByAudience(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'seccion' => 'required|string',
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $presolQ = PreSolicitudes::select(
            'id',
            'datosIniciales',
            'vehiculo_id',
            'tiposervicio_id',
            'solicitud',
            'telefono',
            'tipopago_id',
            'lat',
            'lon',
            'photosVehiculoDir',
            'comprobantePagoDir',
            'fecha_hora_reservacion',
            'fecha_hora_extension',
            'urgencyEmit',
            'partner_id',
            'operator_id',
            'assignment_datetime',
            'cotizacion',
            'tiempoEstimadoArribo',
            'operator_initCords',
            'status'
        )
            ->with([
                'vehiculo:id,marca_id,tipovehiculo_id,clase_id,anio,colorvehiculo_id,placas,noserie,alias',
                'vehiculo.marca:id,name',
                'vehiculo.tipo:id,name,icon_name',
                'vehiculo.clase:id,name',
                'vehiculo.color:id,name',
                'tiposervicio:id,name',
                'tipopago:id,name,banco',
                'partner:id,name,mobile',
                'operator'
            ]);
        $audience = $request->audience;
        if ($audience === 1) {
            $partnerId = $request->user->id;
            $presolQ->where('partner_id', $partnerId);
        } else if ($audience === 2) {
            $operadorUserId = $request->user->id;
            $presolQ->where('operator_id', $operadorUserId);
        }

        $seccion = $request->seccion;
        switch ($seccion) {
            case 'proceso':
                $statusIn =
                    [
                        PreSolicitudStatus::NUEVA,
                        PreSolicitudStatus::OPERADORASIGNADO,
                        PreSolicitudStatus::PAGADO
                    ];
                $presolQ->whereIn('status', $statusIn);
                $presolQ->whereRaw("to_char(fecha_hora_extension::TIMESTAMP at time zone 'Etc/GMT-5', 'YYYYMMDD') = to_char(now()::TIMESTAMP at time zone 'Etc/GMT-5', 'YYYYMMDD')");
                $presolQ->orderBy('id', 'DESC');
                $data = $presolQ->get();
                break;
            case 'historico':
                $statusIn =
                    [
                        PreSolicitudStatus::EXPIRADA,
                        PreSolicitudStatus::CANCELADA,
                    ];
                $presolQ->whereIn('status', $statusIn);
                $data = $presolQ->get();
                break;
            default:
                return response()->json([
                    'ok' => false,
                    'errors' => ['Section operation invalid']
                ], JsonResponse::BAD_REQUEST);
                break;
        }
        $preSolArray = [];
        for ($i = 0; $i < count($data); $i++) {
            array_push($preSolArray, GeneratePreSol::preparePreSolicitudResponse($data[$i]));
        }

        return response()->json([
            'ok' => true,
            'preSolicitudes' => $preSolArray
        ], JsonResponse::OK);
    }

    /**
     * @uses Endpoints: operators
     */
    public function getPreSolFiles(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'partner_id' => 'required|numeric',
            'pre_sol_id' => 'required|numeric',
            'type' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $preSol = PreSolicitudes::where('id', '=', $request->pre_sol_id)
            ->where('partner_id', $request->partner_id)
            ->first();

        if (!$preSol) {
            return response()->json([
                'ok' => false,
                'errors' => ['No data to display']
            ], JsonResponse::BAD_REQUEST);
        }
        try {
            if ($request->type == 1) {
                $from = 'photosVehiculoDir';
            }
            if ($request->type == 2) {
                $from = 'comprobantePagoDir';
            }

            return Storage::disk('images-app')->response($preSol[$from], 'test', [], 'inline');
        } catch (Exception $e) {
            Log::debug($e);
            return response()->json([
                'ok' => false,
                'errors' => ['No data to display']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    /**
     * @deprecated
     * @uses Endpoints: operators
     */
    public function attendRequest(Request $request)
    {
        $operadorUser = $request->user;
        $validateData = Validator::make($request->all(), [
            'partner_id' => 'required|numeric',
            'pre_sol_id' => 'required|numeric',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        if ($this->canOperatorAttend($operadorUser->id) === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['No es posible atender otro servicio, mientras tiene uno en proceso']
            ], JsonResponse::BAD_REQUEST);
        }

        $preSol = PreSolicitudes::where('id', $request->pre_sol_id)->where('partner_id', $request->partner_id)->where('status', 1)->first();
        if (!$preSol) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no se encuentra vigente']
            ], JsonResponse::BAD_REQUEST);
        }

        $preSol->operator_id = $operadorUser->id;
        $preSol->assignment_datetime = Carbon::now()->format('Y-m-d H:i:s.u');


        $preSol->tiempoEstimadoArribo = '00:45';
        $preSol->operator_initCords =
            [
                'lat' => $request->lat,
                'lon' => $request->lon
            ];
        $preSol->status = PreSolicitudStatus::OPERADORASIGNADO;

        // Enviamos notificación al cliente
        $sendNotification = PushNotificationsHelper::pushToUsers(
            $request->partner_id,
            1,
            'cms_pre_solicitudes',
            $preSol->id,
            NotificationPriority::URGENT,
            'Su servicio esta siendo atendido.',
            'De click en más detalles para ver el desglose o de click en aceptar servicio para confirmar su solicitud.',
            'partners',
            'cms_pre_solicitudes',
            $preSol->id
        );
        // TODO: agregar un log de errores para reprocesar notificaciones
        if ($sendNotification->ok !== true) {
            //Fecha actual como nueva extensión de expiración
            $preSol->fecha_hora_extension = Carbon::now()->format('Y-m-d H:i:s.u');
            $preSol->save();
            Log::debug(["Error on send notification --->", json_encode($sendNotification)]);
            return response()->json([
                'ok' => true,
                'message' => 'Asignación recibida, sin embargo no fue posible enviar la notificación al cliente',
            ], JsonResponse::OK);
        }

        //Fecha actual como nueva extensión de expiración
        $preSol->fecha_hora_extension = Carbon::now()->format('Y-m-d H:i:s.u');
        if ($preSol->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Asignación recibida, en breve recibira notificación por parte del cliente',
            ], JsonResponse::OK);
        }
    }

    /**
     * @uses Endpoints: operators
     */
    public function inProgress(Request $request)
    {
        $operatorUser = $request->user;
        $audience = $request->audience;

        $presolQ = GeneratePreSol::getPreSolQuery();
        if ($audience === 1) {
            $validStatus = [PreSolicitudStatus::CANCELADA, PreSolicitudStatus::EXPIRADA];
            $presolQ->whereNotIn('status', $validStatus)
                ->where('partner_id', $operatorUser->id);

        } else if ($audience === 2) {
            $validStatus = [PreSolicitudStatus::CANCELADA, PreSolicitudStatus::EXPIRADA];
            $presolQ->whereNotIn('status', $validStatus)
                ->where('operator_id', $operatorUser->id)
                ->whereDate('fecha_hora_extension', '>=', Carbon::now()->subHours(2));
        }
        // Verificamos si tenememos en request model y id
        if ($request->has('model') && $request->has('id')) {
            if ($request->model === 'cms_pre_solicitudes') {
                $presolQ->where('id', $request->id);
            } else if ($request->model === 'cms_padsolicitudes') {
                $res = $request;
                $res->merge([
                    'seccion' => 'detallado',
                    'solicitud_id' => $request->id,
                    'now' => true
                ]);

                $solicitudesController = new SolicitudesController();
                $resSol = $solicitudesController->listServices($res);
                if ($resSol->original['ok'] === true) {
                    return response()->json($resSol->original, JsonResponse::OK);
                } else {
                    return response()->json($resSol->original, JsonResponse::BAD_REQUEST);
                }
            }
        }

        $presolQ->orderBy('id', 'DESC');

        $preSolicitud = $presolQ->first();

        // Si la solicitud es estatus 3
        if ($preSolicitud && ($preSolicitud->status === PreSolicitudStatus::PADSTATUS)) {
            $res = $request;
            $res->merge([
                'seccion' => 'detallado',
                'solicitud_id' => $preSolicitud->cms_padsolicitudes_id,
                'now' => true
            ]);

            $solicitudesController = new SolicitudesController();
            $resSol = $solicitudesController->listServices($res);

            if ($resSol->original['ok'] === true) {
                if ($resSol->original['solicitudes']->status === SolicitudStatus::CANCELADA) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['No hay solicitudes disponibles en transito']
                    ], JsonResponse::BAD_REQUEST);
                }
                return response()->json($resSol->original, JsonResponse::OK);
            } else {
                return response()->json($resSol->original, JsonResponse::BAD_REQUEST);
            }
        }

        if (!$preSolicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['No hay resultados para mostrar']
            ], JsonResponse::BAD_REQUEST);
        }

        $res = GeneratePreSol::preparePreSolicitudResponse($preSolicitud);

        return response()->json([
            'ok' => true,
            'solicitudes' => $res
        ], JsonResponse::OK);
    }

    public function proccessPreSolToPadSolicitudes(Request $request)
    {

        $user = $request->user;
        $validateData = Validator::make($request->all(), [
            'pre_sol_id' => 'required|numeric',
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $foundPreSol = PreSolicitudes::where('id', $request->pre_sol_id)
                        ->where('partner_id', $user->id)
                        ->where('status', PreSolicitudStatus::PAGADO)
                        ->first();

        if (!$foundPreSol) {
            return response()->json([
                'ok' => false,
                'errors' => ['No se encontro la solicitud o ya fue procesada']
            ], JsonResponse::BAD_REQUEST);
        }

        if ($foundPreSol->status === PreSolicitudStatus::EXPIRADA || $foundPreSol->status === PreSolicitudStatus::CANCELADA) {
            $message = '';
            if ($foundPreSol->status === PreSolicitudStatus::EXPIRADA) {
                $message = 'Esta solicitud expiro por tiempo de inactividad';
            } else if ($foundPreSol->status === PreSolicitudStatus::CANCELADA) {
                $message = 'Esta solicitud se encuentra cancelada';
            }
            return response()->json([
                'ok' => false,
                'errors' => [$message]
            ], JsonResponse::BAD_REQUEST);
        }

        $res = $request;
        $res->request->add($foundPreSol->json_solicitud);

        $res->merge(['type' => 'solicitar']);

        if (isset($foundPreSol->cotizacion)) {
            $res->merge(['cotizacion' => [
                "subtotal" => $foundPreSol->cotizacion['calculator']['subtotal'],
                "tax_amount" => $foundPreSol->cotizacion['calculator']['tax_amount'],
                "total" => $foundPreSol->cotizacion['calculator']['total']
            ]]);
        }
        if (Storage::disk('images-app')->exists($foundPreSol->photosVehiculoDir)) {

            $vehiculoImg = Storage::disk('images-app')->get($foundPreSol->photosVehiculoDir);
            $encodedVehiculoImg = base64_encode($vehiculoImg);
            $res->merge(['photosVehiculo' => [$encodedVehiculoImg]]);
        }

        if (Storage::disk('images-app')->exists($foundPreSol->comprobantePagoDir)) {
            $comprobanteImg = Storage::disk('images-app')->get($foundPreSol->comprobantePagoDir);
            $encodedComprobanteImg = base64_encode($comprobanteImg);
            $res->merge(['comprobantePago' => [$encodedComprobanteImg]]);
        }


        $odoo = new Odoo();
        $feetData = $odoo->getFleetOperator($foundPreSol->operator_id);
        if ($feetData->ok === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Operador no disponible']
            ], JsonResponse::BAD_REQUEST);
        }
        try {
            $operatorData = [
                'grua_id' => $feetData->data['id'],
                'operator_id' => $foundPreSol->operator_id,
                'tipogrua_id' => $feetData->data['tipogrua_id'][0]
            ];
            $res->merge(['operator_data' => $operatorData]);
        } catch (\Exception $e) {
            Log::debug($e);
            return response()->json([
                'ok' => false,
                'errors' => ['No fue posible asignar su solicitud, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }

        $operador = Operadores::where('id', $foundPreSol->operator_id)->first();

        if ($foundPreSol->tiempoEstimadoArribo) {
            $res->merge(['tiempoArribo' => $foundPreSol->tiempoEstimadoArribo]);
        }

        if($request->has('confirmacion_pago_id')) {
            $res->merge(['confirmacion_pago_id' => $request->confirmacion_pago_id]);
        }
        if($request->has('pago_confirmado_app')) {
            $res->merge(['pago_confirmado_app' => $request->pago_confirmado_app]);
        }

        $solicitudesController = new SolicitudesController();
        $resSol = $solicitudesController->register($res);
        if ($resSol->original['ok'] === true) {
            // Cambiamos estatus de preSolicitud
            $foundPreSol->status = PreSolicitudStatus::PADSTATUS;
            $foundPreSol->cms_padsolicitudes_id = $resSol->original['idsolicitud'];
            $foundPreSol->save();

            // Enviamos notificación al operador
            $sendNotification = PushNotificationsHelper::pushToUsers(
                $operador->id,
                2,
                'cms_padsolicitudes',
                $resSol->original['idsolicitud'],
                NotificationPriority::URGENT,
                'Hay un nuevo servicio por atender.',
                'De click en más detalles para ver el desglose.',
                'operador',
                'servicios',
                $resSol->original['idsolicitud']
            );

            if ($sendNotification->ok !== true) {
                Log::debug(["Error on send notification --->", json_encode($sendNotification)]);
                return response()->json([
                    'ok' => true,
                    'message' => 'Asignación recibida, sin embargo no fue posible enviar la notificación al operador',
                ], JsonResponse::OK);
            }

            // todas las notificaciones relacionadas a la pre solicitud las invalidamos
            PushNotificationsHelper::inactiveModelRelation('cms_pre_solicitudes', $foundPreSol->id);

            return response()->json($resSol->original, JsonResponse::OK);
        }
        return response()->json($resSol->original, JsonResponse::BAD_REQUEST);

    }

    #region PRIVATE METHODS

    /**
     * @uses Endpoints: operators, partners
     */
    private function canOperatorAttend($operatorId)
    {

        $errorsOp = [];

        //Revisamos en FleetVehicle
        $fleet = FleetVehicle::where('driver_id', $operatorId)->first();
        if (!$fleet) {
            array_push($errorsOp, 'No existe grúa para este operador');
        }

        $preSol = PreSolicitudes::where('operator_id', '=', $operatorId)->where('status', '=', PreSolicitudStatus::OPERADORASIGNADO)->get();

        if ($preSol && count($preSol) > 0) {
            array_push($errorsOp, 'Este operador ya esta enrolado en otra solicitud');
        }

        if (count($errorsOp) > 0) {
            return (object) [
                'ok' => false,
                'errors' => $errorsOp
            ];
        }
        return (object) ['ok' => true];
    }


    /**
     * @uses Endpoints: operators
     */
    private function solicitudResponse($solicitudes)
    {
        $response = new \stdClass();
        $response->pre_sol_id = $solicitudes->id;
        $response->status = $solicitudes->status;
        $response->cords = ['lat' => $solicitudes->lat, 'lon' => $solicitudes->lon, 'distance' => round($solicitudes->distance, 2)];
        $response->user_data = $solicitudes->partner->name;
        $response->car_details =
            [
                'brand' => $solicitudes->vehiculo->marca->name,
                'model' => $solicitudes->vehiculo->clase->name,
                'year' => $solicitudes->vehiculo->anio,
                'color' => $solicitudes->vehiculo->color->name,
                'plates' => $solicitudes->vehiculo->placas
            ];
        $response->service_details =
            [
                'from' => $solicitudes->solicitud['seencuentra'],
                'to' => $solicitudes->solicitud['selleva'],
            ];
        $response->date = $solicitudes->fecha_hora_extension;
        $response->complementData =
            [
                'preguntas' => $solicitudes->datosIniciales['preguntas'],
                'solicitud' => $solicitudes->solicitud,
                'telefono' => $solicitudes->telefono,
                'lat' => $solicitudes->lat,
                'lon' => $solicitudes->lon,
                'fecha_hora_reservacion' => $solicitudes->fecha_hora_reservacion,
                'urgencyEmit' => $solicitudes->urgencyEmit,
                'vehiculo' => $solicitudes->vehiculo,
                'tiposervicio' => $solicitudes->tiposervicio,
                'tipopago' => $solicitudes->tipopago,
                'partner' => $solicitudes->partner,
                'assignment_datetime' => $solicitudes->assignment_datetime,
                'cotizacion' => $solicitudes->cotizacion,
                'tiempoEstimadoArribo' => $solicitudes->tiempoEstimadoArribo,
            ];


        if (isset($solicitudes->operator) && $solicitudes->operator['id']) {
            $odoo = new Odoo();
            $resOdoo = $odoo->getFleetOperator($solicitudes->operator['id']);
            if ($resOdoo->ok === true) {

                $gruaData = [
                    'grua' => $resOdoo->data['name'],
                    'license_plate' => $resOdoo->data['license_plate'],
                    'tipogrua_id' => $resOdoo->data['tipogrua_id'][1]
                ];

                $operator = ['name' => $resOdoo->data['driver_id'][1]];
                $company = $resOdoo->data['company_id'][1];

                $response->complementData['fleetData'] = ['fleet' => $gruaData, 'operator' => $operator, 'company' => $company];
            }
        }
        return $response;
    }
    #endregion

    public function rePublishPreSol(Request $request) {

        return response()->json([
            'ok' => false,
            'errors' => ['No es posible procesar su solicitud. Método en desarrollo']
        ], JsonResponse::BAD_REQUEST);
        //1. Validamos request
        $validate = Validator::make($request->all(), [
            'pre_solicitud_id' => 'required|exists:cms_pre_solicitudes,id',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validate->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $userId = $request->user->id;
        $preSol = PreSolicitudes::where('id', $request->pre_solicitud_id)
                  ->where('status', PreSolicitudStatus::EXPIRADA)
                  ->where('partner_id', $userId)->first();

        //2. Validamos que se haya encontrado la pre-solicitud
        if (!$preSol) {
            return response()->json([
                'ok' => false,
                'errors' => ['La pre-solicitud no fue encontrada']
            ], JsonResponse::BAD_REQUEST);
        }

        //Validamos que exita el véhiculo
        $vehiculo = PadVehiculos::query()->where('id', $preSol->vehiculo_id)->where('active', true);

        $ignoreSolicitudSts = [SolicitudStatus::BORRADOR, SolicitudStatus::RESERVADA, SolicitudStatus::ARRIBADA, SolicitudStatus::VALIDARPAGO, SolicitudStatus::ENCIERRO];
        $ignogePreSolSts = [PreSolicitudStatus::NUEVA, PreSolicitudStatus::OPERADORASIGNADO, PreSolicitudStatus::CLIENTEACEPTA];

        $vehiculo->whereDoesntHave('solicitudes', function (Builder $query) use ($ignoreSolicitudSts) {
            return $query->whereIn('state', $ignoreSolicitudSts);
        });
        $vehiculo->whereDoesntHave('preSolicitudes', function (Builder $query) use ($ignogePreSolSts) {
            return $query->whereIn('status', $ignogePreSolSts);
        });

        $vehiculoData = $vehiculo->first();

        if (!$vehiculoData) {
            return response()->json([
                'ok' => false,
                'errors' => ['El véhiculo enrolado con esta solicitud ya no esta habilitado o se encuentra en adjunto a una soliciud en curso']
            ], JsonResponse::BAD_REQUEST);
        }

        // Validamos que la pre solicitud no tenga una solicitud en curso
        if ($preSol->cms_padsolicitudes_id) {
            $solicitud = PadSolicitudes::where('id', $preSol->cms_padsolicitudes_id)->first();
            if ($solicitud) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['Esta pre-soliciud ya no puede ser publicada nuevamente']
                ], JsonResponse::BAD_REQUEST);
            }
        }

        // .Asignamos valores
        $fecha_hora_reservacion = Carbon::now()->format('Y-m-d H:i:s.u');

        $preSol->fecha_hora_reservacion = $fecha_hora_reservacion;
        $json_solicitud_data = $preSol->json_solicitud;
        $json_solicitud_data['solicitud']['fecha_hora'] = $fecha_hora_reservacion;
        $json_solicitud_data['lat'] = $request->lat;
        $json_solicitud_data['lon'] = $request->lon;

        $solicitud_data = $preSol->solicitud;
        $solicitud_data['fecha_hora'] = $fecha_hora_reservacion;

        $preSol->lat = $request->lat;
        $preSol->lon = $request->lon;
        $preSol->solicitud = $solicitud_data;
        $preSol->json_solicitud = $json_solicitud_data;
        $preSol->fecha_hora_extension = $fecha_hora_reservacion;
        $preSol->operator_id = null;
        $preSol->cotizacion = null;
        $preSol->assignment_datetime = null;
        $preSol->tiempoEstimadoArribo = null;
        $preSol->operator_initCords = null;
        $preSol->status = PreSolicitudStatus::NUEVA;


        if ($preSol->save()) {

            // Enviamos notificación push a operadores cercanos
            $notificationRequest =
                [
                    'model' => 'cms_pre_solicitudes',
                    'model_id' => $preSol->id,
                    'priority' => NotificationPriority::URGENT,
                    'title' => 'Hay un nuevo servicio por atender.',
                    'body' => 'De click en más detalles para ver información o atender la solicitud y posteriormente notificar al cliente.',
                    'module' => 'operators',
                    'section' => 'cms_pre_solicitudes',
                    'idobject' => (string) $preSol->id
                ];

            $sendNotification = PushNotificationsHelper::pushToOperatorsDistance($preSol->lat, $preSol->lon, 10, $notificationRequest);
            // dd($sendNotification);
            if ($sendNotification->ok !== true) {
                Log::debug(["Error on send notification --->", json_encode($sendNotification)]);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Tu solicitud fue nuevamente publicada.'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal, intenta nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    public function resetPreSol($partnerId = null)
    {
        try {
            $partner_id = (isset($partnerId)) ? $partnerId : 156;
            $presolQ = PreSolicitudes::where('partner_id', $partner_id)->where('status', '!=', 3)->get();
            if ($presolQ && count($presolQ) > 0) {
                for ($i = 0; $i < count($presolQ); $i++) {
                    $presolQ[$i]->status = PreSolicitudStatus::NUEVA;
                    $presolQ[$i]->operator_id = null;
                    $presolQ[$i]->assignment_datetime = null;
                    $presolQ[$i]->tiempoEstimadoArribo = null;
                    $presolQ[$i]->operator_initCords = null;
                    $presolQ[$i]->date_reg = Carbon::now();
                    $presolQ[$i]->cotizacion = null;
                    $presolQ[$i]->fecha_hora_reservacion = Carbon::now()->subMinutes(rand(0, 3));
                    $presolQ[$i]->fecha_hora_extension = $presolQ[$i]->fecha_hora_reservacion;
                    $presolQ[$i]->save();
                }
            }

            $appNotificationsQ = AppNotifications::select();
            $appNotificationsQ->delete();

            return response()->json([
                'ok' => true,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::debug($e);
            return response()->json([
                'ok' => false,
                'errors' => [$e->getMessage()]
            ], 400);
        }
    }
}
