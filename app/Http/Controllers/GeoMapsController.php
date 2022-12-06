<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Helpers\CobroHelper;
use App\Helpers\GeneralUseHelper;
use App\Helpers\GeoDistance;
use App\Helpers\Odoo;
use App\Models\Company;
use App\Models\TipoServicio;
use App\Models\UserTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GeoMapsController extends Controller
{
    public function GetFleetOperators(Request $request) {
        $validateReq = Validator::make($request->all(), [
            'origin_lat' => 'required|numeric',
            'origin_long' => 'required|numeric',
            'radio' => 'required|numeric'
        ]);

        if ($validateReq->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateReq->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }
        $ignoreIds = [];
        if ($request->has('ignoreIds') && count($request->ignoreIds) > 0) {
            $ignoreIds = $request->ignoreIds;
        }
        $limitBox = GeoDistance::getLimites($request->origin_lat, $request->origin_long, $request->radio);

        $operatorsTracking = UserTracking::whereRaw(GeoDistance::getGeoQueryRaw($request->origin_lat, $request->origin_long, $request->radio))
                             ->whereRaw(GeoDistance::getLimitQueryRaw($limitBox))
                             ->with('user_devices')
                             ->where('available', 1)
                             ->whereNotIn('foreign_id', $ignoreIds)
                             ->get();

        if (!$operatorsTracking || count($operatorsTracking) === 0) {
            return response()->json([
                'ok' => false,
                'errors' =>['No hay operadores disponibles por el momento, intente incrementar el radio de busqueda']
            ], JsonResponse::BAD_REQUEST);
        }


        $operatorsPinDataArr = [];
        $odoo = new Odoo();
        $errors = [];

        $tipoServicioId = TipoServicio::select('id')->whereRaw("upper(name) = upper('local')")->first();

            for ($i = 0; $i < count($operatorsTracking); $i++) {
                try {
                    $resOdoo = $odoo->getFleetOperator($operatorsTracking[$i]->foreign_id, ['bloqueado_x_op', '=', false]);

                    if ($resOdoo->ok === true) {

                        //Validamos que tengamos id de company
                        $companyId = 0;
                        if (isset($resOdoo->data['company_id']) && $resOdoo->data['company_id'][0]) {
                            $companyId = $resOdoo->data['company_id'][0];
                        } else {
                            $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' not found company id related to this operator.'];
                            array_push($errors, $error);
                            continue;
                        }


                        // Validamos que se encuentre la compañia
                        $companyPartnerId = Company::select('partner_id')->where('id', $companyId)->first();
                        //dd($companyPartnerId->partner_id);
                        if (!$companyPartnerId) {
                            $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' not found company model related to this operator.'];
                            array_push($errors, $error);
                            continue;
                        }

                        //Obtenermos distancia
                        $directions= GeoDistance::getDirectionsGoogleMaps(
                                [
                                    "lat" => $request->origin_lat,
                                    "long" => $request->origin_long
                                ],
                                [
                                    "lat" => $operatorsTracking[$i]->lat,
                                    "long" => $operatorsTracking[$i]->lon
                                ],
                                'driving'
                            );
                        $origin = null;
                        $distance = null;
                        $arrive = null;
                        $quotation = null;

                        if ($directions->ok == true) {
                            $distance = isset($directions->data->routes[0]->legs[0]->distance->text) ? $directions->data->routes[0]->legs[0]->distance->text : null;
                            $arrive = isset($directions->data->routes[0]->legs[0]->duration->text) ? $directions->data->routes[0]->legs[0]->duration->text : null;
                            $origin = isset($directions->data->routes[0]->legs[0]->end_address) ? $directions->data->routes[0]->legs[0]->end_address : null;
                        }

                        $distanceAvgMts = isset($directions->data->routes[0]->legs[0]->distance->value) ? $directions->data->routes[0]->legs[0]->distance->value : null;
                        if(!$distanceAvgMts) {
                            $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' not found distance avarage.'];
                            array_push($errors, $error);
                            continue;
                        }
                        $distanceAvgKm = round($distanceAvgMts / 1000, 2);
                        if ($distanceAvgKm == 0) {
                            $distanceAvgKm = 1;
                        }

                        // Obtenemos listado de conceptos de cobro por compañia
                        $conceptosCobro = $odoo->getConceptoCobros($companyPartnerId->partner_id, $tipoServicioId->id);
                        //dd($conceptosCobro);
                        if (!$conceptosCobro) {
                            $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' not found any chargable items.'];
                            array_push($errors, $error);
                            continue;
                        }
                        if ($conceptosCobro->ok === false) {
                            $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' error during get chargable items.'];
                            array_push($errors, $error);
                            continue;
                        }

                        $quotation = CobroHelper::serviceCostQuoteOnMap($conceptosCobro->data, $distanceAvgKm);
                        $calculetedConcepts = $conceptosCobro->data;

                        if ($quotation == false) {
                            $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' price relation can not be null or zero.'];
                            array_push($errors, $error);
                            continue;
                        }

                        $crane_number =  GeneralUseHelper::multiexplode(array("[","]"), $resOdoo->data['display_name']);


                        $operatorPinData = new \stdClass();
                        $operatorPinData->position = [
                            'lat' => $operatorsTracking[$i]->lat,
                            'long' => $operatorsTracking[$i]->lon
                        ];
                        $operatorPinData->id = $operatorsTracking[$i]->foreign_id;
                        $operatorPinData->data = [
                            'company_logo' => null,
                            'company_name' => null,
                            'crane_number' => $crane_number[1],
                            'plates' => $resOdoo->data['license_plate'],
                            'op_profile_img' => null,
                            'op_name' => $resOdoo->data['driver_id'][1],
                            'origin' => $origin,
                            'distance' => $distance,
                            'arrive' => $arrive,
                            'price' => $quotation->costo,
                            'cotizacion' => $quotation->cotizacion,
                            'calcObjs' => $calculetedConcepts,
                            'distanciaKm' => $distanceAvgKm
                        ];
                        array_push($operatorsPinDataArr, $operatorPinData);
                    }
                    else {
                        $error = ['Operator Id: '.$operatorsTracking[$i]->foreign_id. ' not found or not related to a vehicle'];
                        array_push($errors, $error);
                    }

                } catch (\Throwable $e) {
                    Log::debug($e);
                    $error = ['Throwable error at operator Id: '.$operatorsTracking[$i]->foreign_id];
                    array_push($errors, $error);
                    continue;
                }
            }

        Log::debug($errors);

        return response()->json([
            'ok' => true,
            'errors_found' => count($errors),
            'errors' => $errors,
            'total' => count($operatorsPinDataArr),
            'data' => $operatorsPinDataArr
        ], JsonResponse::OK);
    }
}
