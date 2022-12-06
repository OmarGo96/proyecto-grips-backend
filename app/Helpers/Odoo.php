<?php

namespace App\Helpers;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Ripoo\OdooClient;

class Odoo
{
    public $host;
    private $db;
    private $user;
    private $password;

    function __construct()
    {
        $this->host = env('ODOO_HOST');
        $this->db = env('ODOO_DB');
        $this->user = env('ODOO_USER');
        $this->password = env('ODOO_PASSWORD');
    }

    public function saveImg($base64Img, $fileName) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );

             $client->create('ir.attachment',
             [
                 'datas' => $base64Img,
                 'name' => $fileName,
                 'datas_fname' => $fileName,
                 'res_model' => 'cms.padsolicitudes'
              ]);
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function programCall($data, $partner_id, $request_id = null) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->create('cms.programacion.llamadas', [
                'partner_id' => $partner_id,
                'telefono' => $data->telefono,
                'fechahoraprogramada' => $data->fechahoraprogramada,
                'partner_comment' => $data->comment,
                'system_comment' => $data->sys_comment,
                'request_id' => (isset($request_id)) ? $request_id : null
            ]);
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function createUpdateSolicitud($solicitud) {

        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            if (isset($solicitud->id)) {

                $client->write('cms.padsolicitudes',
                [
                    $solicitud->id
                ],
                [
                    'fecha' => $solicitud->fecha,
                    'partner_id' => $solicitud->partner_id,
                    'solicito' => $solicitud->solicito,
                    'telefono' => $solicitud->telefono,
                    'vehiculo_id' => $solicitud->vehiculo_id,
                    'tiposervicio_id' => $solicitud->tiposervicio_id,
                    'state' => $solicitud->state,
                    'company_id' => $solicitud->company_id,
                    'currency_id' => $solicitud->currency_id,
                    'pricelist_id' => $solicitud->pricelist_id,

                    'amount_untaxed' => (isset ($solicitud->amount_untaxed)) ? $solicitud->amount_untaxed : null,
                    'amount_tax' => (isset ($solicitud->amount_tax)) ? $solicitud->amount_tax : null,
                    'amount_total' => (isset ($solicitud->amount_total)) ? $solicitud->amount_total : null,
                    'pricelist_id' => (isset ($solicitud->pricelist_id)) ? $solicitud->pricelist_id : null,

                    'seencuentra' => (isset($solicitud->seencuentra)) ? $solicitud->seencuentra : null,
                    'referencias' => (isset($solicitud->referencias)) ? $solicitud->referencias : null,
                    'observaciones' => (isset($solicitud->observaciones)) ? $solicitud->observaciones : null,
                    'selleva' => (isset($solicitud->selleva)) ? $solicitud->selleva : null,
                    'tipopago_id' => (isset($solicitud->tipopago_id)) ? $solicitud->tipopago_id : null,
                    (isset($solicitud->fecha_hora_reservacion) ? 'fecha_hora_reservacion' : '') => (isset($solicitud->fecha_hora_reservacion) ? $solicitud->fecha_hora_reservacion : ""),

                    'tmestimadoarribo' => (isset($solicitud->tmestimadoarribo)) ? $solicitud->tmestimadoarribo : null,

                    'grua_id' => (isset($solicitud->grua_id)) ? $solicitud->grua_id : null,
                    'operador_id' => (isset($solicitud->operator_id)) ? $solicitud->operator_id : null,
                    'tipogrua_id' => (isset($solicitud->tipogrua_id)) ? $solicitud->tipogrua_id : null,
                    'noexpediente' => 'PARTICULAR',
                    'op_asignado' => (isset($solicitud->operator_id)) ? true : false,

                    'latitud_ub' => (isset($solicitud->latitud_ub)) ? $solicitud->latitud_ub : null,
                    'longitud_ub' => (isset($solicitud->longitud_ub)) ? $solicitud->longitud_ub : null,
                    'confirmacion_pago_id' => (isset($solicitud->confirmacion_pago_id)) ? $solicitud->confirmacion_pago_id : null,
                    'pago_confirmado_app' => (isset($solicitud->pago_confirmado_app)) ? $solicitud->pago_confirmado_app : null
                ]);
            } else {
                $client->create('cms.padsolicitudes', [
                    'fecha' => $solicitud->fecha,
                    'partner_id' => $solicitud->partner_id,
                    'solicito' => $solicitud->solicito,
                    'telefono' => $solicitud->telefono,
                    'vehiculo_id' => $solicitud->vehiculo_id,
                    'tiposervicio_id' => $solicitud->tiposervicio_id,
                    'state' => $solicitud->state,
                    'company_id' => (isset($solicitud->company_id)) ? $solicitud->company_id : 2,
                    'currency_id' => (isset($solicitud->currency_id)) ? $solicitud->currency_id : 33,
                    'pricelist_id' => (isset($solicitud->pricelist_id)) ? $solicitud->pricelist_id : 1,

                    'amount_untaxed' => (isset ($solicitud->amount_untaxed)) ? $solicitud->amount_untaxed : null,
                    'amount_tax' => (isset ($solicitud->amount_tax)) ? $solicitud->amount_tax : null,
                    'amount_total' => (isset ($solicitud->amount_total)) ? $solicitud->amount_total : null,

                    'seencuentra' => (isset($solicitud->seencuentra)) ? $solicitud->seencuentra : null,
                    'referencias' => (isset($solicitud->referencias)) ? $solicitud->referencias : null,
                    'observaciones' => (isset($solicitud->observaciones)) ? $solicitud->observaciones : null,
                    'selleva' => (isset($solicitud->selleva)) ? $solicitud->selleva : null,
                    'tipopago_id' => (isset($solicitud->tipopago_id)) ? $solicitud->tipopago_id : null,
                    (isset($solicitud->fecha_hora_reservacion) ? 'fecha_hora_reservacion' : '') => (isset($solicitud->fecha_hora_reservacion) ? $solicitud->fecha_hora_reservacion : ""),

                    'tmestimadoarribo' => (isset($solicitud->tmestimadoarribo)) ? $solicitud->tmestimadoarribo : null,

                    'grua_id' => (isset($solicitud->grua_id)) ? $solicitud->grua_id : null,
                    'operador_id' => (isset($solicitud->operator_id)) ? $solicitud->operator_id : null,
                    'tipogrua_id' => (isset($solicitud->tipogrua_id)) ? $solicitud->tipogrua_id : null,
                    'noexpediente' => 'PARTICULAR',
                    'op_asignado' => (isset($solicitud->operator_id)) ? true : false,

                    'latitud_ub' => (isset($solicitud->latitud_ub)) ? $solicitud->latitud_ub : null,
                    'longitud_ub' => (isset($solicitud->longitud_ub)) ? $solicitud->longitud_ub : null,
                    'creada_de_app' => true,
                    'confirmacion_pago_id' => (isset($solicitud->confirmacion_pago_id)) ? $solicitud->confirmacion_pago_id : null,
                    'pago_confirmado_app' => (isset($solicitud->pago_confirmado_app)) ? $solicitud->pago_confirmado_app : null
                ]);
            }
            Log::debug($client->response);

            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function createPadOperaciones($solicitud_id, $vehiculo_id, $folio_solicitud, $company_id, $amount_untaxed, $amount_tax, $amount_total) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );

             $client->create('cms.padoperaciones',
             [
                 'request_id' => $solicitud_id,
                 'vehiculo_id' => $vehiculo_id,
                 'name' => $folio_solicitud,
                 'company_id' => $company_id,
                 'amount_untaxed' => (isset ($amount_untaxed)) ? $amount_untaxed : null,
                 'amount_tax' => (isset ($amount_tax)) ? $amount_tax : null,
                 'amount_total' => (isset ($amount_total)) ? $amount_total : null,
              ]);
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getSquencePadSolicitudes($solicitud) {
        $companyId = ($solicitud->company_id) ? $solicitud->company_id : 2;
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password
            );

            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('ir.sequence',
            [
                ['code', '=', "cms.padsolicitudes"],
                ['company_id', '=', $companyId]
            ],
            [],
            0
           );
            return (object) ['ok' => true, 'data' => $client->response[0]];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function requestProcced(string $model, $solicitud) {
        if (!isset($solicitud)) {
            return (object) ['ok' => false, 'error' => 'Solicitud no encontrada'];
        }
        if ($solicitud->state !== 'reserved') {
            return (object) ['ok' => false, 'error' => 'La solicitud no puede ser procesada por estatus inválido'];
        }
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password
            );

            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
           $client->model_execute_kw($model,
            "request_proced",
            [[$solicitud->id]]
           );
           //dd($client);
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function computeTaxes(string $model, $modelId) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password
            );

            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );

           $client->model_execute_kw($model,
            "compute_taxes",
            [[$modelId]]
           );

            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getConceptoCobros($empresaId, $tiposervicio_id = null) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password
            );

            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('cms.conceptocobro.partner',
            [
                ['req_servicio', '=', true],
                ['partner_id', '=', $empresaId],
                (isset($product_id)) ? ['tiposervicio_id', '=', $tiposervicio_id] : [1, '=', 1]
            ],
            [

            ]
           );
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getPadSolicitudesLine($requestId) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password
            );

            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('cms.padsolicitudes.line',
            [
                ['request_id', '=', $requestId]
            ],
            [

            ]
           );
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function changeStatus($solicitud, $status) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password
            );

            switch ($status) {
                case 'arrived':
                    $client->write('cms.padsolicitudes',
                    [
                        $solicitud->id
                    ],
                    [
                        'state' => $status,
                        'arribada' => true,
                        'tmrealarribo' => $solicitud->tmrealarribo,
                        'fechahorarealarribo' => $solicitud->fechahorarealarribo,
                        'user_arrive_id' => $solicitud->user_arrive_id,
                        'evidencia' => true
                    ]);
                    break;
                case 'closed':
                    break;
            }
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function saveUpdateRespuestaPreg($respuestasPreg) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            if ($respuestasPreg->id) {
                $client->write('cms.respuestas.solicitud',
                [
                    $respuestasPreg->id
                ],
                [
                    'request_id' => $respuestasPreg->request_id,
                    'pregunta_id' => $respuestasPreg->pregunta_id,
                    'valor' => $respuestasPreg->valor
                ]);
            } else {
                $client->create('cms.respuestas.solicitud', [
                    'request_id' => $respuestasPreg->request_id,
                    'pregunta_id' => $respuestasPreg->pregunta_id,
                    'valor' => $respuestasPreg->valor
                ]);
            }

            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getPaymentTicket($attachment_id) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('ir.attachment',
            [
                ['id', '=', $attachment_id],
                ['res_model', '=', 'cms.padpagos']
            ],
            [],
            0
           );
           $decode = base64_decode($client->response[0]['datas']);
            return (object) ['ok' => true, 'data' => $decode];
        } catch (\Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getSolicitudData($idsolicitud, $partner_id) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('cms.padsolicitudes',
            [
                ['id', '=', $idsolicitud],
                ['partner_id', '=', $partner_id]
            ],
            [],
            0
           );
           return (object) ['ok' => true, 'data' => $client->response[0]];
        } catch(Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }


    public function getEmployeeData($empleado_id) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('hr.employee',
            [
                ['id', '=', $empleado_id],
            ],
            ['name', 'image_small', 'mobile_phone', 'work_email', 'company_id'],
            0
           );
           //dd($client);
           return (object) ['ok' => true, 'data' => $client->response[0]];
        } catch(Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getFleetOperator($idOperador, $params = null) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            //TODO habilitar disponbible_app cuando este en la tabla
            $client->search_read('fleet.vehicle',
            [
                ['driver_id', '=', $idOperador],
                isset($params) ? $params : [1, '=', 1]
                //['bloqueado_x_op', '=', false]
                //['disponible_app', '=', true]
            ],
            ['id','name', 'company_id', 'license_plate', 'driver_id', 'tipogrua_id', 'display_name'],
            0
           );
           return (object) ['ok' => true, 'data' => $client->response[0]];
        } catch(Exception $e) {
            Log::debug('Error en getFleetOperator con Operador id:' .$idOperador);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function saveDocsPartner($base64Img, $fileName) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );

             $client->create('ir.attachment',
             [
                 'datas' => $base64Img,
                 'name' => $fileName,
                 'datas_fname' => $fileName,
                 'res_model' => 'cms.padsolicitudes',
                 'public' => true
              ]);
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function getDocPartner($attachment_id) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->search_read('ir.attachment',
            [
                ['id', '=', $attachment_id]
            ],
            [],
            0
           );
           $mimeType = 'data:'.$client->response[0]['mimetype'];
           $prefix = ';base64,';
           $_data = $mimeType.$prefix.$client->response[0]['thumbnail'];
            return (object) ['ok' => true, 'data' => $_data];
        } catch (\Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function unLinkDocPartner($attachment_id) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );
            $client->unlink(
                'ir.attachment',
                [$attachment_id]
           );
           $_data = $client->response;
            return (object) ['ok' => true, 'data' => $_data];
        } catch (\Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }

    public function saveCustomerSignature($base64Img, $solicitudId) {
        try {
            $client = new OdooClient(
                $this->host,
                $this->db,
                $this->user,
                $this->password

            );

            $client->write('cms.padsolicitudes',
            [
                $solicitudId
            ],
            [
                'customer_sign' => $base64Img,
                'fecha_hora_firmacliente' => GeneralUseHelper::validDateOODO(),
                'firmada' => true
            ]);
            return (object) ['ok' => true, 'data' => $client->response];
        } catch (Exception $e) {
            Log::debug($e);
            return (object) ['ok' => false, 'error' => $e];
        }
    }
}
