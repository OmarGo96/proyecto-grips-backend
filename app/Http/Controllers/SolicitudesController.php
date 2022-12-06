<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Enums\NotificationPriority;
use App\Enums\PreSolicitudStatus;
use App\Enums\SolicitudStatus;
use App\Helpers\GeneralUseHelper;
use App\Helpers\GeneratePreSol;
use App\Helpers\Odoo;
use App\Helpers\PushNotificationsHelper;
use App\Mail\PreSolicitudMail;
use App\Models\AccountPreRequestLineTax;
use App\Models\AccountRequestLineTax;
use App\Models\AppFailedJobs;
use App\Models\Company;
use App\Models\FleetVehicle;
use App\Models\Operadores;
use App\Models\PadSolicitudes;
use App\Models\PadSolicitudesLine;
use App\Models\PadVehiculos;
use App\Models\PreSolicitudes;
use App\Models\PreSolicitudesLine;
use App\Models\ProgramacionLlamadas;
use App\Models\RespuestasSolicitud;
use App\Models\RutaOperador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SolicitudesController extends Controller
{
    #region PARTNERS FUNCTIONS

    // función para registrar nueva solicitud
    public function register(Request $request) {

        $validateData = PadSolicitudes::validateBeforeSave($request->all());

        if ($validateData !== true) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData
            ], JsonResponse::BAD_REQUEST);
        }

        $user = $request->user;
        if (!$user) {
            return response()->json([
                'ok' => false,
                'errors' => ['Usuario no encontrado']
            ], JsonResponse::BAD_REQUEST);
        }

        // validamos que el vehiculo sea del usuario
        $checkVehiculo = PadVehiculos::where('id', '=', $request->datosIniciales['vehiculo_id'])
            ->where('partner_id', '=', $user->id)
            ->where('active', '=', true)
            ->first();
        if (!$checkVehiculo || is_null($checkVehiculo)) {
            return response()->json([
                'ok' => false,
                'errors' => ['El vehículo seleccionado no pertenece a esta cuenta']
            ], JsonResponse::BAD_REQUEST);
        }

        $datosIniciales = (object) $request->datosIniciales;
        $preguntas = $request->datosIniciales['preguntas'];

        if ($request->has('idsolicitud') && is_null($request->idsolicitud) === false) {
            $solicitud = PadSolicitudes::where('id', '=', $request->idsolicitud)->first();
        } else {
            $solicitud = new PadSolicitudes();
        }

        $solicitud->fecha = Carbon::now()->format('Y-m-d');

        $solicitud->partner_id = $user->id;
        $solicitud->solicito = $user->name;
        $solicitud->telefono = isset($request->telefono) ? (string) $request->telefono : (string)$user->mobile;

        $solicitud->vehiculo_id = $datosIniciales->vehiculo_id;

        $solicitud->tiposervicio_id = $datosIniciales->tiposervicio_id;

        $solicitud->state = $request->status;

        $solicitud->latitud_ub = isset($request->lat) ? $request->lat : null;
        $solicitud->longitud_ub = isset($request->lon) ? $request->lon : null;

        if ($request->has('calcObjs') && (is_null($request->calcObjs) === false)) {

            $calcObjs = $request->calcObjs;
            $solicitud->currency_id = $calcObjs[0]['currency_id'][0];
            $solicitud->pricelist_id = $calcObjs[0]['pricelist_id'][0];
            $companyPartnerId = Company::getCompanyByGeoLocation($calcObjs[0]['partner_id'][0], $solicitud->latitud_ub, $solicitud->longitud_ub);

            if (!$companyPartnerId) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['Hubo un error al guardar su solicitud, intente de nuevo']
                ], JsonResponse::BAD_REQUEST);
            }
             // TODO: Hay que asignar a la compañia mas cercana por georeferencia.
            $solicitud->company_id = $companyPartnerId->id;
        }

        $type = $request->type;

        $succesMsg = 'Su solicitud ha sido recibida';

        if ($request->has('cotizacion')) {
            if ($request->cotizacion !== null) {
                $calculator = $request->cotizacion;

                $solicitud->amount_untaxed = $calculator['subtotal'];
                $solicitud->amount_tax = $calculator['tax_amount'];
                $solicitud->amount_total = $calculator['total'];
            }
        }

        if ($request->has('operator_data')) {
            $solicitud->grua_id = $request->operator_data['grua_id'];
            $solicitud->operator_id = $request->operator_data['operator_id'];
            $solicitud->tipogrua_id = $request->operator_data['tipogrua_id'];
        }

        switch($type) {
            case 'cotizar':
                $succesMsg = 'Su cotización ha sido recibida';
                break;
            case 'programar':
                $succesMsg = 'Se ha programado su solicitud';

                $programar = (object) $request->solicitud;

                $solicitud->seencuentra = $programar->seencuentra;
                $solicitud->referencias = $programar->referencias;
                $solicitud->selleva = $programar->selleva;
                $solicitud->tipopago_id = $programar->tipopago_id;
                $solicitud->fecha_hora_reservacion = $programar->fecha_hora;

                break;
            case 'solicitar':
            case 'call_request':
                $succesMsg = 'Su solicitud ha sido recibida';

                $programar = (object) $request->solicitud;

                $solicitud->seencuentra = $programar->seencuentra;
                $solicitud->referencias = $programar->referencias;
                $solicitud->selleva = $programar->selleva;
                $solicitud->tipopago_id = $programar->tipopago_id;
                $solicitud->fecha_hora_reservacion = Carbon::now()->addMinutes(1)->format('Y-m-d H:i:s.u');
                if ($request->has('tiempoArribo')) {
                    $solicitud->tmestimadoarribo = $request->tiempoArribo;
                }
                break;
        }

        if($request->has('confirmacion_pago_id')) {
            $solicitud->confirmacion_pago_id = $request->confirmacion_pago_id;
        }
        if($request->has('pago_confirmado_app')) {
            $solicitud->pago_confirmado_app = $request->pago_confirmado_app;
        }

        // enviamos a guardar con webservice odoo
        $oddoSol = new Odoo();
        $responseSol = $oddoSol->createUpdateSolicitud($solicitud);

        if ($responseSol->ok === true) {

            // preparamos preguntas para guardar
            for ($i = 0; $i < count($preguntas); $i++) {
                if (isset($preguntas[$i]['pregunta_response'])) {
                    if ($request->has('idsolicitud') && is_null($request->idsolicitud) === false) {
                        $respuestasPreg = RespuestasSolicitud::where('request_id', '=', $request->idsolicitud)->where('pregunta_id', '=', $preguntas[$i]['pregunta_id'])->first();
                    } else {
                        $respuestasPreg = new RespuestasSolicitud();
                        $respuestasPreg->request_id = $responseSol->data;
                    }


                    $respuestasPreg->pregunta_id = $preguntas[$i]['pregunta_id'];
                    $respuestasPreg->valor = $preguntas[$i]['pregunta_response'];

                    $oddoPregRes = new Odoo();
                    $responsePregRes = $oddoPregRes->saveUpdateRespuestaPreg($respuestasPreg);

                    if ($responsePregRes->ok  === false) {
                        return response()->json([
                            'ok' => false,
                            'errors' => ['No se pudo guardar su solicitud']
                        ], JsonResponse::BAD_REQUEST);
                    }
                }
            }

            // insertamos foto de vehículo
            if ($request->has('photosVehiculo')) {
                $photosVehiculo = (array) $request->photosVehiculo;
                //dd($photosVehiculo);
                for ($i = 0; $i < count($photosVehiculo); $i++) {
                    // guardamos
                    $fileName = 'foto_vehiculo_'.$responseSol->data.'_'.Carbon::now()->unix().'.jpg';
                    $oodo = new Odoo();
                    try {
                        $imgBase64 = explode(",", $photosVehiculo[$i])[1];
                    } catch (Exception $e) {
                        try {
                            $imgBase64 = $photosVehiculo[$i];
                        } catch (Exception $e) {

                        }
                    }
                    $imgSaved = $oodo->saveImg($imgBase64, $fileName);

                    if ($imgSaved->ok === true) {
                        DB::table('cms_padsolicitudes_docts_ubicacion')
                        ->insert([
                            'request_id' => $responseSol->data,
                            'attachment_id' => $imgSaved->data
                        ]);
                    }
                }
            }

            // insertamos comprobane de pago
            if ($request->has('comprobantePago')) {
                $comprobantePago = (array) $request->comprobantePago;

                for ($i = 0; $i < count($comprobantePago); $i++) {
                    // guardamos
                    $fileName = 'pago_'.$responseSol->data.'_'.Carbon::now()->unix().'.jpg';
                    $oodo = new Odoo();
                    try {
                        $imgBase64 = explode(",", $comprobantePago[$i])[1];
                    } catch (Exception $e) {
                        try {
                            $imgBase64 = $comprobantePago[$i];
                        } catch (Exception $e) {

                        }
                    }
                    $imgSaved = $oodo->saveImg($imgBase64, $fileName);

                    if ($imgSaved->ok === true) {
                        DB::table('cms_padsolicitudes_pagos')
                        ->insert([
                            'request_id' => $responseSol->data,
                            'attachment_id' => $imgSaved->data
                        ]);
                    }
                }
            }


            // Bloqueamos la grua
            $fleet = FleetVehicle::where('id', $solicitud->grua_id)->first();
            if ($fleet) {
                $fleet->bloqueado_x_op = true;
                $fleet->save();
            }

            // bloqueadmos al operador
            $operadorData = Operadores::where('id', $solicitud->operador_id)->first();
            if ($operadorData) {
                $operadorData->bloqueado_x_op = true;
                $operadorData->save();
            }


            // Procesamos la solicitud
            $savedSolicitud = PadSolicitudes::where('id', $responseSol->data)->first();
            $rPoodo = new Odoo();
            $rPoodoRes = $rPoodo->requestProcced('cms.padsolicitudes', $savedSolicitud);

            if ($rPoodoRes->ok !== true) {
                AppFailedJobs::create('Fail requestProcced function', 'requestProcced', json_encode($rPoodoRes));
                Log::debug(json_encode($rPoodoRes));
            }

              // Se crea el registro en tabla cms_padoperaciones con los ids correspondientes
              if ($type !== 'call_request') {
                $oddoPadOperaciones = new Odoo();
                $oddoPadOperacionesRes = $oddoPadOperaciones->createPadOperaciones(
                    $responseSol->data,
                    $solicitud->vehiculo_id,
                    $rPoodoRes->data,
                    $solicitud->company_id,
                    $solicitud->amount_untaxed,
                    $solicitud->amount_tax,
                    $solicitud->amount_total
                );

                if ($oddoPadOperacionesRes->ok === false) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['Hubo un error durante el proceso, pero su petición fue recibida']
                    ], JsonResponse::BAD_REQUEST);
                }
            }

            if ($request->has('pre_sol_id')) {
                //Insertamos en cms_padsolicitudes_line
                try {
                    $preSolicitudesLine = PreSolicitudesLine::where('request_id', $request->pre_sol_id)->get();

                    for ($i = 0; $i < count($preSolicitudesLine); $i++) {
                        $padSolicitudesLine = new PadSolicitudesLine();

                        $padSolicitudesLine->request_id = $responseSol->data;
                        $padSolicitudesLine->sequence = $i;
                        $padSolicitudesLine->name = $preSolicitudesLine[$i]->name;
                        $padSolicitudesLine->state = $savedSolicitud->state;
                        $padSolicitudesLine->uom_id = $preSolicitudesLine[$i]->uom_id;
                        $padSolicitudesLine->product_id = $preSolicitudesLine[$i]->product_id;
                        $padSolicitudesLine->quantity = $preSolicitudesLine[$i]->quantity;
                        $padSolicitudesLine->discount = $preSolicitudesLine[$i]->discount;
                        $padSolicitudesLine->price_unit = $preSolicitudesLine[$i]->price_unit;
                        $padSolicitudesLine->price_subtotal = $preSolicitudesLine[$i]->price_subtotal;
                        $padSolicitudesLine->price_tax = $preSolicitudesLine[$i]->price_tax;
                        $padSolicitudesLine->price_total = $preSolicitudesLine[$i]->price_total;
                        $padSolicitudesLine->company_id = $preSolicitudesLine[$i]->company_id;
                        $padSolicitudesLine->currency_id = $preSolicitudesLine[$i]->currency_id;
                        $padSolicitudesLine->account_id = null; // TODO: revisar que valor se debe enviar
                        $padSolicitudesLine->create_date = $preSolicitudesLine[$i]->create_date;
                        $padSolicitudesLine->write_date = $preSolicitudesLine[$i]->write_date;
                        $padSolicitudesLine->save();

                        // Insertamos en account_request_line_tax
                        $accPreRequestLineTax = AccountPreRequestLineTax::where('request_line_id', $preSolicitudesLine[$i]->id)->get();
                        if (isset($accPreRequestLineTax) && count($accPreRequestLineTax) > 0) {
                            for ($j = 0; $j < count($accPreRequestLineTax); $j++) {
                                $accRequestLineTax = new AccountRequestLineTax();
                                $accRequestLineTax->request_line_id = $padSolicitudesLine->id;
                                $accRequestLineTax->tax_id = $accPreRequestLineTax[$j]->tax_id;
                                $accRequestLineTax->save();
                            }
                        }
                    }
                } catch(\ErrorException $e) {
                    Log::debug($e);
                }
            }

            // Procesamos compute_taxes para que calcule el total de servicios de la solcitud
            $cTodoo = new Odoo();
            $cToddoRes = $cTodoo->computeTaxes('cms.padsolicitudes', $savedSolicitud->id);

            if ($cToddoRes->ok !== true) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['Hubo un error durante el proceso, pero su petición fue recibida']
                ], JsonResponse::BAD_REQUEST);
            }

            try {
                $oddo = new Odoo();
                $responseO = $oddo->getSolicitudData($responseSol->data, $user->id);

                $pdf = GeneratePreSol::generate('cotizacion', (object) $responseO->data);
                Mail::to($user->email)->send(new PreSolicitudMail('cotizacion', $responseO->data, $pdf));
            } catch (\Exception $e) {
                Log::debug($e);
            }

            return response()->json([
                'ok' => true,
                'idsolicitud' => $responseSol->data,
                'message' => $succesMsg
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['No se pudo guardar su solicitud']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    // funcion para adjuntar comprobante de pago
    public function attachWTPayment(Request $request) {
        $validateData = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'comprobantePago' => 'required'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitud = PadSolicitudes::where('id', '=', $request->id)->first();

        if (!$solicitud) {
        return response()->json([
                'ok' => false,
                'errors' => ['No se encontro la solicitud']
            ], JsonResponse::BAD_REQUEST);
        }

        // insertamos comprobane de pago
        $comprobantePago = (array) $request->comprobantePago;

        for ($i = 0; $i < count($comprobantePago); $i++) {
            // guardamos
            $fileName = 'pago_'.$solicitud->id.'_'.Carbon::now()->unix().'.jpg';
            $oodo = new Odoo();
            try {
                $imgBase64 = explode(",", $comprobantePago[$i])[1];
            } catch (Exception $e) {
                try {
                    $imgBase64 = $comprobantePago[$i];
                } catch (Exception $e) {

                }
            }
            $imgSaved = $oodo->saveImg($imgBase64, $fileName);

            if ($imgSaved->ok === true) {
                DB::table('cms_padsolicitudes_pagos')
                ->insert([
                    'request_id' => $solicitud->id,
                    'attachment_id' => $imgSaved->data
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Se ha recibido su comprobante de pago'
        ], JsonResponse::OK);
    }

    // funcion para obtener ticket de pago desde oddo
    public function getPaymentTicket(Request $request) {
        $validateData = Validator::make($request->all(), [
            'id' => 'required|exists:cms_padsolicitudes,id'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }
        $user = $request->user;
        // TODO: descomentar cuando sirva
        //$solicitud = PadSolicitudes::where('partner_id', '=', $user->id)->where('state', '=', SolicitudStatus::PAGADA)->where('id', '=', $request->id)->first();
        $solicitud = PadSolicitudes::where('state', '=', SolicitudStatus::PAGADA)->where('id', '=', $request->id)->first();

        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['No fue posible ubicar los datos de la solicitud']
            ], JsonResponse::BAD_REQUEST);
        }

        $irAttachment = DB::table('ir_attachment')->where('res_name', '=', $solicitud->name)->where('res_model', '=', 'cms.padpagos')->first();

        if (!$irAttachment) {
            return response()->json([
                'ok' => false,
                'errors' => ['Aún no cuenta con ticket para ser descargado']
            ], JsonResponse::BAD_REQUEST);
        }

        $odoo = new Odoo();
        $response = $odoo->getPaymentTicket($irAttachment->id);



        if ($response->ok === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['No se pudo procesar el ticket para ser descargado']
            ], JsonResponse::BAD_REQUEST);
        }

        return response($response->data, 200, ['Content-type' => 'application/pdf']);
    }

    // función para obtener solicitudes en proceso
    public function listServices(Request $request)
    {
        $user = $request->user;
        if (!$user) {
            return response()->json([
                'ok' => true,
                'errors' => ['Cliente no reconocido']
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudes = PadSolicitudes::select(
                'id',
                'name',
                'vehiculo_id',
                'fecha',
                'tiposervicio_id',
                'folio_ss',
                'tipopago_id',
                'telefono',
                'seencuentra',
                'referencias',
                'selleva',
                'grua_id',
                'tipogrua_id',
                'operador_id',
                'partner_id',
                'tmestimadoarribo',
                'tmrealarribo',
                'fechahorarealarribo',
                'state',
                'observaciones',
                'fecha_hora_reservacion',
                'amount_untaxed',
                'amount_tax',
                'amount_total',
                'latitud_ub',
                'longitud_ub',
                'customer_sign'
            )
            ->with(
                [
                    'servicio',
                    'vehiculo:id,marca_id,tipovehiculo_id,clase_id,anio,colorvehiculo_id,placas,noserie,alias',
                    'vehiculo.marca:id,name',
                    'vehiculo.tipo:id,name,icon_name',
                    'vehiculo.clase:id,name',
                    'vehiculo.color:id,name',
                    'grua:id,name',
                    'tipogrua:id,name',
                    'operador:id,empleado_id',
                    'operador.employee:id,name',
                    'tipopago',
                    'pagos',
                    'partner:id,name,mobile',
                    'preguntas.preguntasSol'
                ]
            );


        $seccion = $request->seccion;
        switch($seccion) {
            case 'proceso':
                $statusIn =
                [
                    SolicitudStatus::RESERVADA,
                    SolicitudStatus::ARRIBADA,
                    SolicitudStatus::VALIDARPAGO,
                    SolicitudStatus::PAIDVALID,
                    SolicitudStatus::ENCIERRO,
                    SolicitudStatus::LIBERADA,
                    SolicitudStatus::BORRADOR,
                    SolicitudStatus::ONTRANSIT,
                    SolicitudStatus::ABIERTA,
                    SolicitudStatus::BORRADOR
                ];
                $solicitudes->whereIn('state', $statusIn);
                $solicitudes->whereDate('fecha', '>=', Carbon::now()->subHours(16));
                break;
            case 'historico':
                $statusIn =
                [
                    SolicitudStatus::CANCELADA,
                    SolicitudStatus::CERRADA,
                    SolicitudStatus::FACTURADA,
                    SolicitudStatus::PAGADA,
                ];
                $solicitudes->whereIn('state', $statusIn);
                break;
            case 'detallado':
                if ($request->has('solicitud_id')) {
                    $solicitudes->where('id', '=', $request->solicitud_id);
                    if ($request->has('now')) {

                        $solicitudes->whereDate('fecha', '>=', Carbon::now()->subHours(8));
                        $validStatus = [
                            SolicitudStatus::RESERVADA,
                            SolicitudStatus::BORRADOR,
                            SolicitudStatus::ARRIBADA,
                            SolicitudStatus::ABIERTA,
                            SolicitudStatus::ONTRANSIT
                        ];
                        $solicitudes->whereIn('state', $validStatus);
                    }

                } else {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['Debe proporcionar la id de la solicitud']
                    ], JsonResponse::BAD_REQUEST);
                }

            break;
        }

        $audience = $request->audience;
        if ($audience === 1) {
            $partnerId = $request->user->id;
            $solicitudes->where('partner_id', $partnerId);
        } else if ($audience === 2) {
            $operadorUserId = $request->user->id;
            $solicitudes->where('operador_id', $operadorUserId);
        }

        $data = $solicitudes->get();

        if ($data && count($data) === 0) {
            return response()->json([
                'ok' => false,
                'errors' => ['No hay resultados para mostrar']
            ], JsonResponse::OK);
        }

        $preSolArray = [];
        for ($i = 0; $i < count($data); $i++) {
            // si ya esta cancelada ubicamos si tiene pre_solicitud y ajustamos status
            if ($data[$i]->state === SolicitudStatus::CANCELADA) {
                $_preSol = PreSolicitudes::where('cms_padsolicitudes_id', $data[$i]->id)->first();
                if ($_preSol) {
                    $_preSol->status = PreSolicitudStatus::CANCELADA;
                    $_preSol->save();
                }
            } else { // si esta en otro estatus diferente entonces cambiamos a 4
                $_preSol = PreSolicitudes::where('cms_padsolicitudes_id', $data[$i]->id)->first();
                if ($_preSol) {
                    $_preSol->status = PreSolicitudStatus::PADSTATUS;
                    $_preSol->save();
                }
            }

            array_push($preSolArray, GeneratePreSol::prepareSolicitudResponse($data[$i]));
        }


        return response()->json([
            'ok' => true,
            'solicitudes' => ($seccion === 'detallado') ? $preSolArray[0] : $preSolArray
        ], JsonResponse::OK);
    }

    // función para agendar una llamda
    public function progCall(Request $request) {
        $user = $request->user;

        $validateData = Validator::make($request->all(), [
            'telefono' => 'required|numeric',
            'fechahoraprogramada' => 'required',
            'comment' => 'required|string',
            'sys_comment' => 'required|string'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $odoo = new Odoo();
        $response = $odoo->programCall($request, $user->id);


        $prog = new ProgramacionLlamadas();
        $prog->partner_id = $user->id;
        $prog->telefono = $request->telefono;
        $prog->fechahoraprogramada = $request->fechahoraprogramada;
        $prog->partner_comment = $request->comment;
        $prog->system_comment = $request->sys_comment;

        if ($response->ok === true) {
            return response()->json([
                'ok' => true,
                'message' => 'Se ha agendado tu requisición, en breve nos comunicaremos contigo'
            ], JsonResponse::OK);
        }

        return response()->json([
            'ok' => false,
            'errors' => ['Hubo un error al momento de guardar tu requisición, intenta nuevamente']
        ], JsonResponse::BAD_REQUEST);
    }

    public function testPreMailSol(Request $request) {

        $oddo = new Odoo();
        $responseO = $oddo->getSolicitudData($request->solicitud_id, $request->partner_id);

        if ($responseO->ok === false) {
            return response()->json([
                'ok' => true,
                'errors' => ['No se pudo obtener información, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }

        $pdf = GeneratePreSol::generate('cotizacion', (object) $responseO->data);
        Mail::to('mikeangeloo87@outlook.com')->send(new PreSolicitudMail('cotizacion', $responseO->data, $pdf));

        return response()->json([
            'ok' => true,
            'data' => $responseO->data
        ], JsonResponse::OK);
    }

    #endregion

    #region OPERATOR FUNCTIONS

    public function changeSolicitudState(Request $request) {

        $validateData = Validator::make($request->all(), [
            'status' => 'required|string',
            'solicitud_id' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $validStatusChange = ['on_transit', 'arrived', 'closed'];

        if (in_array($request->status, $validStatusChange) === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['El estatus enviado es inválido para este endpoint']
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;

        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

         //verificamos que la solicitud le pertenezca al operador que hace el request
         $userId = $request->user->id;
         $resUserId = $request->user->res_users->id;
         if($solicitud->operador_id != $userId) {
             return response()->json([
                 'ok' => false,
                 'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
             ], JsonResponse::BAD_REQUEST);
         }

         switch ($request->status) {
            case 'on_transit':
                // validamos datos que se ocupan
                $validateDataOnTransit = Validator::make($request->all(), [
                    'partner_id' => 'required|numeric',
                    'lat_operador' => 'required|numeric',
                    'lon_operador' => 'required|numeric',
                    'lat_partner' => 'required|numeric',
                    'lon_partner' => 'required|numeric',
                    'distance' => 'required',
                    'duration' => 'required',
                    'duration_in_traffic' => 'required',
                    'start_address' => 'required',
                    'start_location' => 'required',
                    'end_address' => 'required',
                    'end_location' => 'required',
                    'full_directions' => 'required'
                ]);

                if ($validateDataOnTransit->fails()) {
                    return response()->json([
                        'ok' => false,
                        'errors' => $validateDataOnTransit->errors()->all()
                    ], JsonResponse::BAD_REQUEST);
                }
                DB::beginTransaction();
                $rutaOperador = RutaOperador::where('request_id', $solicitudId)->where('operador_id', $userId)->first();
                if (!$rutaOperador) {
                    $rutaOperador = new RutaOperador();
                }

                $rutaOperador->request_id  = $solicitudId;
                $rutaOperador->operador_id = $userId;
                $rutaOperador->partner_id  = $request->partner_id;
                $rutaOperador->lat_operador  = $request->lat_operador;
                $rutaOperador->lon_operador  = $request->lon_operador;
                $rutaOperador->lat_partner  = $request->lat_partner;
                $rutaOperador->lon_partner  = $request->lon_partner;
                $rutaOperador->distance  = $request->distance;
                $rutaOperador->duration  = $request->duration;
                $rutaOperador->duration_in_traffic  = $request->duration_in_traffic;
                $rutaOperador->start_address  = $request->start_address;
                $rutaOperador->start_location  = $request->start_location;
                $rutaOperador->end_address  = $request->end_address;
                $rutaOperador->end_location  = $request->end_location;
                $rutaOperador->full_directions  = $request->full_directions;

                $solicitud->state = $request->status;

                if ($solicitud->save() && $rutaOperador->save()) {
                    DB::commit();

                    // Enviamos notificacion al cliente
                    $_msg = 'De click en más detalles para ver información de su solicitud.';
                    if (isset($request->duration) && isset($request->duration->text)) {
                        $_msg = 'El tiempo de llegada es de aproximadamente: '.$request->duration->text.' De click en más detalles para ver información de su solicitud.';
                    }
                    $sendNotification = PushNotificationsHelper::pushToUsers(
                        $request->partner_id,
                        1,
                        'cms_padsolicitudes',
                        $solicitudId,
                        NotificationPriority::URGENT,
                        'El operador esta en camino.',
                        $_msg,
                        'partners',
                        'servicios',
                        $solicitudId
                    );

                    if ($sendNotification->ok !== true) {
                        Log::debug(["Error on send notification --->", json_encode($sendNotification)]);
                        return response()->json([
                            'ok' => true,
                            'message' => 'Cambio recibido, sin embargo no fue posible enviar la notificación al cliente',
                        ], JsonResponse::OK);
                    }

                    return response()->json([
                        'ok' => true,
                        'message' => 'Estatus actualizado correctamente'
                    ], JsonResponse::OK);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'ok' => false,
                        'errors' => ['Se presento un error durante la actualización']
                    ], JsonResponse::BAD_REQUEST);
                }
                break;
            case 'arrived':
                // Validamos datos para cambio de estatus
                $validateDataArrived = Validator::make($request->all(), [
                    'tmrealarribo' => 'required|string',
                ]);
                if ($validateDataArrived->fails()) {
                    return response()->json([
                        'ok' => false,
                        'errors' => $validateDataArrived->errors()->all()
                    ], JsonResponse::BAD_REQUEST);
                }

                $soli = new \stdClass();
                $soli->id = $solicitudId;
                $soli->tmrealarribo = $request->tmrealarribo;
                $soli->fechahorarealarribo = GeneralUseHelper::validDateOODO();
                $soli->user_arrive_id = $resUserId;
                $oodo = new Odoo();

                $res = $oodo->changeStatus($soli, $request->status);
                if ($res->ok === false) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['Hubo un error al momento de cambiar el estatus']
                    ], JsonResponse::BAD_REQUEST);
                }
                return response()->json([
                    'ok' => true,
                    'message' => 'Estatus actualizado correctamente'
                ], JsonResponse::OK);

                break;
            case 'closed':
                return response()->json([
                    'ok' => false,
                    'errors' => 'Solicitud en desarrollo, no disponible'
                ], 400);
                break;
         }

    }

    public function attachArrivedPhotos(Request $request) {

        $validateData = Validator::make($request->all(), [
            'solicitud_id' => 'required|numeric',
            'pictures' => 'required|array',
            'section' => 'required'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $validTypes = ['doctsveh', 'doctscliente', 'front', 'rear', 'side_a', 'side_b'];
        if (in_array($request->section, $validTypes) === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Debe proporcionar una sección valida']
            ], JsonResponse::BAD_REQUEST);
        }

        if (count($request->pictures) == 0) {
            return response()->json([
                'ok' => false,
                'errors' => ['Debe proporcionar por lo menos 1 foto']
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;

        // Validamos que exista solicitud y en que estatus se encuentra
        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

        //verificamos que la solicitud le pertenezca al operador que hace el request
        $userId = $request->user->id;
        $resUserId = $request->user->res_users->id;
        if($solicitud->operador_id != $userId) {
            return response()->json([
                'ok' => false,
                'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
            ], JsonResponse::BAD_REQUEST);
        }

        $pictures = $request->pictures;
        $totalOk = 0;
        $totalErr = 0;
        $totalPic = count($pictures);
        $_responseData = [];
        //Hacemos recorriod y bucle de guardado
        for($i = 0; $i < count($pictures); $i++) {
            try {
                $oodo = new Odoo();

                // guardamos
                $fileName = 'datos_vehiculo_sec_'.$request->section.'_'.$solicitudId.'_'.Carbon::now()->unix().'.jpg';
                $oodo = new Odoo();
                try {
                    $imgBase64 = explode(",", $pictures[0])[1];
                } catch (Exception $e) {
                    try {
                        $imgBase64 = $pictures[0];
                    } catch (Exception $e) {

                    }
                }
                $resData = $oodo->saveDocsPartner($imgBase64, $fileName);

                if ($resData->ok === false) {
                    $totalErr ++;
                    continue;
                }

                switch($request->section) {
                    case 'doctsveh':
                        DB::table('m2m_padsolicitudes_arrive_doctsveh_rel')
                        ->insert([
                            'm2m_id' => $solicitudId,
                            'attachment_id' => $resData->data
                        ]);
                        break;
                    case 'doctscliente':
                        DB::table('m2m_padsolicitudes_doctscliente_rel')
                        ->insert([
                            'm2m_id' => $solicitudId,
                            'attachment_id' => $resData->data
                        ]);
                        break;
                    case 'front':
                        DB::table('m2m_padsolicitudes_front_rel')
                        ->insert([
                            'm2m_id' => $solicitudId,
                            'attachment_id' => $resData->data
                        ]);
                        break;
                    case 'rear':
                        DB::table('m2m_padsolicitudes_rear_rel')
                        ->insert([
                            'm2m_id' => $solicitudId,
                            'attachment_id' => $resData->data
                        ]);
                        break;
                    case 'side_a':
                        DB::table('m2m_padsolicitudes_side_a_rel')
                        ->insert([
                            'm2m_id' => $solicitudId,
                            'attachment_id' => $resData->data
                        ]);
                        break;
                    case 'side_b':
                        DB::table('m2m_padsolicitudes_side_b_rel')
                        ->insert([
                            'm2m_id' => $solicitudId,
                            'attachment_id' => $resData->data
                        ]);
                        break;
                }

                $totalOk ++;

                // Guardamos objeto
                array_push($_responseData, [
                    'solicitud_id' => $solicitudId,
                    'attachment_id' => $resData->data
                ]);
            } catch (\Exception $e) {
                Log::debug($e);
                $totalErr ++;
            }
        }

        if ($totalErr === $totalPic) {
            return response()->json([
                'ok' => false,
                'errors' => ['Se presentaron errores al momento de guardar la información']
            ], JsonResponse::BAD_REQUEST);
        }

        $message = [
            'totalPic' => $totalPic,
            'totalOk' => $totalOk,
            'totalErr' => $totalErr
        ];

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $_responseData
        ], JsonResponse::OK);
    }

    public function updateVehiclePlatesSerie(Request $request) {
        $user_id = $request->user->id;
        $validateData = Validator::make($request->all(), [
            'solicitud_id' => 'required',
            'vehiculo_id' => 'required',
            'placas' => 'nullable|string|max:10',
            'noserie' => 'nullable|string|max:32'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;
        $vehiculoId = $request->vehiculo_id;

        // ubicamos solicitud para validar
        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

         //verificamos que la solicitud le pertenezca al operador que hace el request
         $userId = $request->user->id;
         if($solicitud->operador_id != $userId) {
             return response()->json([
                 'ok' => false,
                 'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
             ], JsonResponse::BAD_REQUEST);
         }

         // validamos que el id del vehiculo sea el mismo de la solicitud
         if ($solicitud->vehiculo_id !== $vehiculoId) {
            return response()->json([
                'ok' => false,
                'errors' => ['Este véhiculo no esta ligado a la solicitud']
            ], JsonResponse::BAD_REQUEST);
         }

         // ubicamos vehiculo
         $vehiculo = PadVehiculos::where('id', $vehiculoId)->first();

         if(!$vehiculo) {
            return response()->json([
                'ok' => false,
                'errors' => ['El véhiculo que esta queriendo editar ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
         }

         if ($request->has('placas')) {
             $vehiculo->placas = $request->placas;
         }

         if ($request->has('noserie')) {
             $vehiculo->noserie = $request->noserie;
         }

         if ($vehiculo->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Registro actualizado correctamente'
            ], JsonResponse::OK);
         } else {
            return response()->json([
                'ok' => false,
                'errors' => ['No se puedo registrar la actualización, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
         }
    }

    public function captureSignatureAndFinish(Request $request) {
        $userId = $request->user->id;

        $validateData = Validator::make($request->all(), [
            'customer_sign' => 'required|string',
            'solicitud_id' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;

        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

         //verificamos que la solicitud le pertenezca al operador que hace el request
         $userId = $request->user->id;
         if($solicitud->operador_id != $userId) {
             return response()->json([
                 'ok' => false,
                 'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
             ], JsonResponse::BAD_REQUEST);
         }


         try {
            $oodo = new Odoo();
            try {
                $imgBase64 = explode(",", $request->customer_sign)[1];
            } catch (Exception $e) {
                try {
                    $imgBase64 = $request->customer_sign;
                } catch (Exception $e) {

                }
            }
            $resData = $oodo->saveCustomerSignature($imgBase64, $solicitudId);

            if ($resData->ok === false) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['Hubo un error al momento de capturar la firma']
                ], JsonResponse::BAD_REQUEST);
            }

        } catch (\Exception $e) {
            Log::debug($e);
            return response()->json([
                'ok' => false,
                'errors' => ['Hubo un error al momento de capturar la firma']
            ], JsonResponse::BAD_REQUEST);
        }

        //Colocamos la solicitud como cerrada
        $solicitud->state = SolicitudStatus::CERRADA;
        $solicitud->save();

        return response()->json([
            'ok' => true,
            'message' => 'Datos guardados correctamente'
        ], JsonResponse::OK);
    }

    public function getArrivedPhotos(Request $request) {

        $validateData = Validator::make($request->all(), [
            'solicitud_id' => 'required|numeric',
            'section' => 'required'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $validTypes = ['doctsveh', 'doctscliente', 'front', 'rear', 'side_a', 'side_b'];
        if (in_array($request->section, $validTypes) === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Debe proporcionar una sección valida']
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;

        // Validamos que exista solicitud y en que estatus se encuentra
        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

        //verificamos que la solicitud le pertenezca al operador que hace el request
        $userId = $request->user->id;
        if($solicitud->operador_id != $userId) {
            return response()->json([
                'ok' => false,
                'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
            ], JsonResponse::BAD_REQUEST);
        }

        $docsData = null;

        switch($request->section) {
            case 'doctsveh':
                $docsData = DB::table('m2m_padsolicitudes_arrive_doctsveh_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->get();
                break;
            case 'doctscliente':
                $docsData = DB::table('m2m_padsolicitudes_doctscliente_rel')
                ->where([
                    'm2m_id' => $solicitudId,
                ])->orderBy('attachment_id', 'ASC')->get();
                break;
            case 'front':
                $docsData = DB::table('m2m_padsolicitudes_front_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->get();
                break;
            case 'rear':
                $docsData = DB::table('m2m_padsolicitudes_rear_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->get();
                break;
            case 'side_a':
                $docsData = DB::table('m2m_padsolicitudes_side_a_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->get();
                break;
            case 'side_b':
                $docsData = DB::table('m2m_padsolicitudes_side_b_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->get();
                break;
        }
        $pictures = [];
        $errorsFound = 0;
        $totalData = count($docsData);

        //Hacemos recorriod y bucle
        for($i = 0; $i < count($docsData); $i++) {
            try {
                $oodo = new Odoo();

                $resData = $oodo->getDocPartner($docsData[$i]->attachment_id);
                if ($resData->ok === false) {
                    $errorsFound ++;
                    continue;
                }
                array_push($pictures, [
                    'tipo_documento' => $request->section,
                    'attachment_id' => $docsData[$i]->attachment_id,
                    'solicitud_id' => $solicitudId,
                    'image' => $resData->data
                ]);

            } catch (\Exception $e) {
                Log::debug($e);
                $errorsFound ++;
                continue;
            }
        }

        $status = 'success';
        if ($errorsFound !== 0) {
            $status = 'warning';
        }
        $message = [
            'status' => $status,
            'total_images' => $totalData,
            'total_errors' => $errorsFound
        ];

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $pictures
        ], JsonResponse::OK);
    }

    public function unLinkArrivedPhoto(Request $request) {

        $validateData = Validator::make($request->all(), [
            'solicitud_id' => 'required|numeric',
            'section' => 'required',
            'attachment_id' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $validTypes = ['doctsveh', 'doctscliente', 'front', 'rear', 'side_a', 'side_b'];
        if (in_array($request->section, $validTypes) === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Debe proporcionar una sección valida']
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;
        $attachmentId = $request->attachment_id;

        // Validamos que exista solicitud y en que estatus se encuentra
        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

        //verificamos que la solicitud le pertenezca al operador que hace el request
        $userId = $request->user->id;
        if($solicitud->operador_id != $userId) {
            return response()->json([
                'ok' => false,
                'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
            ], JsonResponse::BAD_REQUEST);
        }

        $docsData = null;

        switch($request->section) {
            case 'doctsveh':
                $docsData = DB::table('m2m_padsolicitudes_arrive_doctsveh_rel')
                ->where([
                    'm2m_id' => $solicitudId,
                    'attachment_id' => $attachmentId
                ])->first();
                break;
            case 'doctscliente':
                $docsData = DB::table('m2m_padsolicitudes_doctscliente_rel')
                ->where([
                    'm2m_id' => $solicitudId,
                ])->orderBy('attachment_id', 'ASC')->first();
                break;
            case 'front':
                $docsData = DB::table('m2m_padsolicitudes_front_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->first();
                break;
            case 'rear':
                $docsData = DB::table('m2m_padsolicitudes_rear_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->first();
                break;
            case 'side_a':
                $docsData = DB::table('m2m_padsolicitudes_side_a_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->first();
                break;
            case 'side_b':
                $docsData = DB::table('m2m_padsolicitudes_side_b_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->orderBy('attachment_id', 'ASC')->first();
                break;
        }

        if (!$docsData) {
            return response()->json([
                'ok' => false,
                'errors' => ['El recurso ya fue eliminado o no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

        $oddo = new Odoo();
        $resData = $oddo->unLinkDocPartner($docsData->attachment_id);

        if ($resData->ok === false || $resData->data === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Se presento un error al momento de realizar su petición, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Registro eliminado correctamente'
        ], JsonResponse::OK);
    }

    public function canCaptureSignature(Request $request) {
        $validateData = Validator::make($request->all(), [
            'solicitud_id' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $solicitudId = $request->solicitud_id;

        // Validamos que exista solicitud y en que estatus se encuentra
        $inValidStatus = [SolicitudStatus::CANCELADA];
        $solicitud = PadSolicitudes::where('id', $solicitudId)->whereNotIn('state', $inValidStatus)->first();
        if (!$solicitud) {
            return response()->json([
                'ok' => false,
                'errors' => ['La solicitud ya no esta disponible']
            ], JsonResponse::BAD_REQUEST);
        }

        //verificamos que la solicitud le pertenezca al operador que hace el request
        $userId = $request->user->id;
        if($solicitud->operador_id != $userId) {
            return response()->json([
                'ok' => false,
                'errors' => ['El operador asignado a la solicitud es diferente al que realiza la petición']
            ], JsonResponse::BAD_REQUEST);
        }

        if($solicitud !== SolicitudStatus::ABIERTA) {
            return response()->json([
                'ok' => false,
                'errors' => ['El agente en cabina no ha colocado la solicitud lista para el cierre.']
            ], JsonResponse::BAD_REQUEST);
        }

        $docsData = null;
        $okFound = true;
        $side = null;

        $doctsveh = DB::table('m2m_padsolicitudes_arrive_doctsveh_rel')
                ->where([
                    'm2m_id' => $solicitudId
                ])->first();
        if (!$doctsveh) {
            $okFound = false;
            $side = 'Documentos del vehículo';
        }

        $doctscliente = DB::table('m2m_padsolicitudes_doctscliente_rel')
        ->where([
            'm2m_id' => $solicitudId,
        ])->orderBy('attachment_id', 'ASC')->first();

        if (!$doctscliente) {
            $okFound = false;
            $side = 'Documentos del cliente';
        }

        $front_rel = DB::table('m2m_padsolicitudes_front_rel')
        ->where([
            'm2m_id' => $solicitudId
        ])->orderBy('attachment_id', 'ASC')->first();
        if (!$front_rel) {
            $okFound = false;
            $side = 'Lado Frontal';
        }

        $rear_rel = DB::table('m2m_padsolicitudes_rear_rel')
        ->where([
            'm2m_id' => $solicitudId
        ])->orderBy('attachment_id', 'ASC')->first();
        if (!$rear_rel) {
            $okFound = false;
            $side = 'Lado trasero';
        }

        $side_a_rel = DB::table('m2m_padsolicitudes_side_a_rel')
        ->where([
            'm2m_id' => $solicitudId
        ])->orderBy('attachment_id', 'ASC')->first();
        if (!$side_a_rel) {
            $okFound = false;
            $side = 'Lado Chofer';
        }

        $side_b_rel = DB::table('m2m_padsolicitudes_side_b_rel')
        ->where([
            'm2m_id' => $solicitudId
        ])->orderBy('attachment_id', 'ASC')->first();
        if (!$side_b_rel) {
            $okFound = false;
            $side = 'Lado Copiloto';
        }

        if ($okFound === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Debe capturar por lo menos 1 imagen: '.$side]
            ], JsonResponse::BAD_REQUEST);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Puede capturar firma'
        ], JsonResponse::OK);
    }
    #endregion


    public function testing(Request $request) {

        $test = Company::getCompanyByGeoLocation('142', '20.63138976787419', '-87.0782647394289');
        dd($test);
    }
}
