<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PadVehiculos;
use App\Enums\JsonResponse;
use App\Enums\PreSolicitudStatus;
use App\Enums\SolicitudStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VehiculosController extends Controller
{
    // Función para registrar vehículo
    public function saveUpdateVehiculo(Request $request)
    {
        $validateData = PadVehiculos::validateBeforeSave($request->all());

        if ($validateData !== true) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData
            ], JsonResponse::BAD_REQUEST);
        }

        DB::beginTransaction();

        $user = $request->user;

        if (isset($request->id) && $request->id > 0) {
            $vehiculo = PadVehiculos::where('id', '=', $request->id)->first();
            if (!$vehiculo) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['El vehículo proporcionado no se encuentra registrado']
                ], JsonResponse::BAD_REQUEST);
            }
        } else {
            $vehiculo = new PadVehiculos();
        }


        $vehiculo->partner_id = $user->id;
        $vehiculo->marca_id = $request->marca_id;
        $vehiculo->clase_id = $request->clase_id;
        $vehiculo->tipovehiculo_id = $request->tipovehiculo_id;
        $vehiculo->anio = $request->anio;
        $vehiculo->colorvehiculo_id = $request->colorvehiculo_id;
        $vehiculo->placas = $request->placas;
        $vehiculo->noserie = $request->noserie;
        $vehiculo->alias = $request->alias;

        $vehiculo->active = true;
        // TODO: por el momento es 2 por econogruas
        $vehiculo->company_id = 2;

        if ($vehiculo->save()) {

            $vehiculo->name = $vehiculo->placas . '-' . $request->clase . '-' . $request->anio . '-' . $request->color . '-' . sprintf("%08d", $vehiculo->id);
            $vehiculo->save();
            DB::commit();
            if (isset($request->id) && $request->id > 0) {
                $message = 'Vehículo actualizado correctamente';
            } else {
                $message = 'Vehículo registrado correctamente';
            }
            return response()->json([
                'ok' => true,
                'message' => $message
            ], JsonResponse::OK);
        } else {
            DB::rollback();
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal, intenta nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    // función para obtener vehículos por usuario que realiza la petición
    public function listVehiculos(Request $request)
    {
        $user = $request->user;

        $ignoreSolicitudSts = [SolicitudStatus::BORRADOR, SolicitudStatus::RESERVADA, SolicitudStatus::ARRIBADA, SolicitudStatus::VALIDARPAGO, SolicitudStatus::ENCIERRO];
        $ignogePreSolSts = [PreSolicitudStatus::NUEVA, PreSolicitudStatus::OPERADORASIGNADO, PreSolicitudStatus::CLIENTEACEPTA];
        $vehiculos = PadVehiculos::query()
            ->where('partner_id', '=', $user->id)
            ->where('active', '=', true);

        if ($request->has('makeSolicitud')) {
            if ($request->makeSolicitud == 'true') {
                $vehiculos->whereDoesntHave('solicitudes', function (Builder $query) use ($ignoreSolicitudSts) {
                    return $query->whereIn('state', $ignoreSolicitudSts);
                });
                $vehiculos->whereDoesntHave('preSolicitudes', function (Builder $query) use ($ignogePreSolSts) {
                    return $query->whereIn('status', $ignogePreSolSts);
                });
            }
        }

        $vehiculos->select('id', 'partner_id', 'marca_id', 'tipovehiculo_id', 'clase_id', 'anio', 'colorvehiculo_id', 'placas', 'noserie', 'alias');


        $data = $vehiculos->with(
            [
                'marca:id,name',
                'tipo:id,name,icon_name',
                'clase:id,name',
                'color:id,name'
            ]
        )->get();

        return response()->json([
            'ok' => true,
            'count' => count($data),
            'vehiculos' => $data
        ], JsonResponse::OK);
    }

    // función para obtener datos de un vehpiculo
    public function showVehiculo(Request $request)
    {
        $validateData = PadVehiculos::validateBeforeGet($request->all());

        if ($validateData !== true) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData
            ], JsonResponse::BAD_REQUEST);
        }

        $user = $request->user;

        $vehiculo = PadVehiculos::select('id', 'partner_id', 'marca_id', 'tipovehiculo_id', 'clase_id', 'anio', 'colorvehiculo_id', 'placas', 'noserie', 'alias')
            ->where('id', '=', $request->id)->where('partner_id', '=', $user->id)
            ->where('active', '=', true)
            ->first();

        if (!$vehiculo) {
            return response()->json([
                'ok' => false,
                'errors' => ['No se encontro el vehículo, intente de nuevo']
            ], JsonResponse::BAD_REQUEST);
        }

        $vehiculo->load('marca:id,name');
        $vehiculo->load('tipo:id,name,icon_name');
        $vehiculo->load('clase:id,name');
        $vehiculo->load('color:id,name');



        return response()->json([
            'ok' => true,
            'vehiculo' => $vehiculo
        ], JsonResponse::OK);
    }

    // función para borrar (desactivar) un vehículo
    public function deleteVehiculo(Request $request)
    {
        $validateData = PadVehiculos::validateBeforeGet($request->all());

        if ($validateData !== true) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData
            ], JsonResponse::BAD_REQUEST);
        }

        $user = $request->user;

        $vehiculo = PadVehiculos::where('id', '=', $request->id)->where('partner_id', '=', $user->id)
            ->where('active', '=', true)
            ->first();

        if (!$vehiculo) {
            return response()->json([
                'ok' => false,
                'errors' => ['No se encontro el vehículo, intente de nuevo']
            ], JsonResponse::BAD_REQUEST);
        }

        $vehiculo->active = false;

        if ($vehiculo->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Vehículo borrado exitosamente'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }
}
