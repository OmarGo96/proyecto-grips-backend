<?php

namespace App\Helpers;

use App\Enums\NotificationPriority;
use App\Models\PreSolicitudes;
use Barryvdh\DomPDF\PDF;

class GeneratePreSol
{
    public static function generate($type, $solicitud) : PDF {
        $pdf = \PDF::loadView('mails.pre-solicitud', compact('type', 'solicitud'));
        return $pdf;
    }

    public static function getPreSolQuery() {
        return PreSolicitudes::select(
            'id', 'datosIniciales', 'vehiculo_id',
            'tiposervicio_id', 'solicitud', 'telefono',
            'tipopago_id', 'lat', 'lon',
            'photosVehiculoDir', 'comprobantePagoDir', 'fecha_hora_reservacion',
            'fecha_hora_extension', 'urgencyEmit', 'partner_id', 'operator_id',
            'assignment_datetime', 'cotizacion',
            'tiempoEstimadoArribo', 'operator_initCords', 'status', 'cms_padsolicitudes_id'
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
    }

     /**
     * @uses Endpoints: operators, partners
     */
    public static function preparePreSolicitudResponse($solicitudes) {
        $response = new \stdClass();
        $response->model = 'cms_pre_solicitudes';
        $response->pre_sol_id = $solicitudes->id;
        $response->status = $solicitudes->status;
        $response->cords = ['lat' => $solicitudes->lat, 'lon' => $solicitudes->lon, 'distance' => round($solicitudes->distance , 2) ];
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
                $company = (isset($resOdoo->data['company_id'])) ? $resOdoo->data['company_id'][1] : null;

                $response->complementData['fleetData'] = ['fleet' => $gruaData, 'operator' => $operator, 'company' => $company];
            }
        }
        return $response;
    }

     /**
     * @uses Endpoints: operators, partners
     */
    public static function prepareSolicitudResponse($solicitudes) {
        $response = new \stdClass();
        $response->model = 'cms_padsolicitudes';
        $response->id = $solicitudes->id;
        $response->folio = (isset($solicitudes->name)) ? $solicitudes->name : null;
        $response->status = $solicitudes->state;
        $response->cords = ['lat' => $solicitudes->latitud_ub, 'lon' => $solicitudes->longitud_ub, 'distance' => isset($solicitudes->distance) ? round($solicitudes->distance , 2) : null ];
        $response->user_data = $solicitudes->partner->name;
        $response->customer_sign = (isset($solicitudes->customer_sign) && $solicitudes->customer_sign) ? 'data:image/png;base64,'.stream_get_contents($solicitudes->customer_sign) : null;
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
            'from' => $solicitudes->seencuentra,
            'to' => $solicitudes->selleva,
        ];
        $response->date = $solicitudes->fecha_hora_reservacion;
        //Obtenermos cotizacion si existe
        $oodo = new Odoo();
        $resOdoo = $oodo->getPadSolicitudesLine($solicitudes->id);
        $cotizacion = null;
        if ($resOdoo->ok === true) {
            $cotizacion = CobroHelper::getCotizacionLine($resOdoo->data);
        }
        $response->complementData =
        [
            'preguntas' => $solicitudes->preguntas,
            'solicitud' => [
                'seencuentra' => $solicitudes->seencuentra,
                'referencias' => $solicitudes->referencias,
                'selleva' => $solicitudes->selleva,
                'tipopago_id' => $solicitudes->tipopago_id,
                'fecha_hora' => $solicitudes->fecha_hora_reservacion,
                'telefono' => $solicitudes->telefono
            ],
            'telefono' => $solicitudes->telefono,
            'lat' => $solicitudes->latitud_ub,
            'lon' => $solicitudes->longitud_ub,
            'fecha_hora_reservacion' => $solicitudes->fecha_hora_reservacion,
            'urgencyEmit' => NotificationPriority::URGENT,
            'vehiculo' => $solicitudes->vehiculo,
            'tiposervicio' => $solicitudes->servicio,
            'tipopago' => $solicitudes->tipopago,
            'partner' => $solicitudes->partner,
            'assignment_datetime' => $solicitudes->fecha_hora_reservacion,
            'cotizacion' => $cotizacion,
            'tiempoEstimadoArribo' => $solicitudes->tmestimadoarribo,
        ];

        $_preguntas = [];
        for ($i = 0; $i < count($solicitudes->preguntas); $i++) {
            array_push($_preguntas, [

                'pregunta_id' => $solicitudes->preguntas[$i]->preguntasSol['id'],
                'pregunta_label' => $solicitudes->preguntas[$i]->preguntasSol['name'],
                'pregunta_response' => $solicitudes->preguntas[$i]['valor']
            ]);
        }
        $response->complementData['preguntas'] = $_preguntas;


        if (isset($solicitudes->operador) && $solicitudes->operador->id) {
            $odoo = new Odoo();
            $resOdoo = $odoo->getFleetOperator($solicitudes->operador->id);
            //dd($resOdoo);
            if ($resOdoo->ok === true) {

                $gruaData = [
                    'grua' => $resOdoo->data['name'],
                    'license_plate' => $resOdoo->data['license_plate'],
                    'tipogrua_id' => $resOdoo->data['tipogrua_id'][1]
                ];

                $operator = ['name' => $resOdoo->data['driver_id'][1]];
                $company = (isset($resOdoo->data['company_id'])) ? $resOdoo->data['company_id'][1] : null;

                $response->complementData['fleetData'] = ['fleet' => $gruaData, 'operator' => $operator, 'company' => $company];
            }
        }
        return $response;
    }

}
