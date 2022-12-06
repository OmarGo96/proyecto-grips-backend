<?php

namespace App\Http\Controllers;

use App\Enums\AudienceEnum;
use App\Enums\JsonResponse;
use App\Enums\PaymentGatewayEnum;
use App\Enums\PreSolicitudStatus;
use App\Helpers\GeneralUseHelper;
use App\Helpers\PaymentGatewayHelper;
use App\Models\ConfirmacionesPagoPartner;
use App\Models\PreSolicitudes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionsController extends Controller
{
    public function RecibePaymentRequest(Request $request) {
        $irModelId = GeneralUseHelper::getIrModelId('cms.padsolicitudes');
        $user = $request->user;
        if (!$user->openpay_customer_id) {
             // Registramos
             $createClient = PaymentGatewayHelper::createClient(
                $user->name,
                '',
                $user->mobile,
                $user->email
            );
            if ($createClient->ok === false) {
                return response()->json([
                    'ok' => false,
                    'type' => 'not-register',
                    'errors' => ['No se pudo registrar al cliente en la pasarela de pago']
                ], JsonResponse::BAD_REQUEST);
            }

            $user->openpay_customer_id = $createClient->customer_id;
            $user->save();
        }
        $validateRequest = Validator::make($request->all(), [
            'model' => 'required|string',
            'model_id' => 'required|numeric',
            'card_token' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string'
        ]);

        if ($validateRequest->fails()) {
            return response()->json([
                'ok' => false,
                'step' => PaymentGatewayEnum::VAL_INVALIDREQUEST,
                'errors' => $validateRequest->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

         $canGoToPay = null;
         $transactionOK = null;
         $errors = [];
         $order_indentify = null;
         $authorization = null;
         $confirmacion_pago_id = null;

         $validModels = ['cms_pre_solicitudes', 'cms_padsolicitudes'];
         if (!in_array($request->model, $validModels)) {
             return response()->json([
                 'ok' => false,
                 'step' => PaymentGatewayEnum::STEP_MODELVALIDATION,
                 'errors' => ['No es posible procesar con este tipo de modelo de datos.']
             ], JsonResponse::BAD_REQUEST);
         }

         switch($request->model) {
             case 'cms_pre_solicitudes':
                 $data = PreSolicitudes::where('id', $request->model_id)->first();
                 if (!$data) {
                    return response()->json([
                        'ok' => false,
                        'step' => PaymentGatewayEnum::STEP_MODELNOTFOUND,
                        'errors' => ['No se encontro al información solicitada']
                    ], JsonResponse::BAD_REQUEST);
                 }
                 $cancelStatus = [PreSolicitudStatus::EXPIRADA, PreSolicitudStatus::CANCELADA];
                 $validStatus = [PreSolicitudStatus::OPERADORASIGNADO, PreSolicitudStatus::PAGOFALLIDO];
                 if (in_array($data->status, $cancelStatus)) {
                    array_push($errors, 'Esta solicitud ya fue cancelada o expiro por tiempo de inactividad');
                 } else if (in_array($data->status, $validStatus)) {
                     $canGoToPay = true;
                 } else {
                     array_push($errors, 'Esta transacción ya no puede ser procesada');
                 }

             break;
             default:
                array_push($errors, 'No es posible procesar con este tipo de modelo de datos.');
             break;
         }

         if (count($errors) > 0) {
            return response()->json([
                'ok' => false,
                'step' => PaymentGatewayEnum::STEP_MODELVALIDATION,
                'errors' => $errors
            ], JsonResponse::BAD_REQUEST);
         }

         if ($canGoToPay == true) {

             //Mandamos a realizar el cobro
             $order = $irModelId.'_'.$data->id;
             $cobroRes = PaymentGatewayHelper::pay(
                $request->card_token,
                $request->amount,
                $request->currency,
                $order,
                $irModelId,
                $user->id
             );

             $order_indentify = $cobroRes->order;
             $authorization = $cobroRes->authorization;
             $confirmacion_pago_id = $cobroRes->confirmacion_pago_id;

             if ($cobroRes->ok === false) {
                $transactionOK = false;
                array_push($errors, $cobroRes->errors);
             } else {
                $transactionOK = true;
             }
         } else {
            array_push($errors, 'Algo salio mal al momento de realizar la cobranza.');
            $transactionOK = false;
         }

         $finalResponse = new \stdClass();

         if ($transactionOK) {
            $data->status = PreSolicitudStatus::PAGADO;
            $data->save();
            $request->request->remove('model');
            $request->request->remove('model_id');
            $request->merge([
                'pre_sol_id' => $data->id,
                'confirmacion_pago_id' => $confirmacion_pago_id,
                'pago_confirmado_app' => $transactionOK
            ]);
            $preSolicitudesController = new PreSolicitudesController();
            $resSol = $preSolicitudesController->proccessPreSolToPadSolicitudes($request);


            if ($resSol->original['ok'] === true) {
                $finalResponse->ok = true;
                $finalResponse->step = PaymentGatewayEnum::STEP_CREATEPADSOLICITUDES;
                $finalResponse->message = 'Gracias por su pago';
                $finalResponse->data = [
                    'authorization' => $authorization,
                    'order' => $order_indentify
                ];
            } else {

                $finalResponse->ok = false;
                $finalResponse->step = PaymentGatewayEnum::STEP_CREATEPADSOLICITUDES;
                array_push($errors, $resSol->original['errors']);
                $finalResponse->errors = $errors;
                $finalResponse->data = [
                    'order' => $order_indentify
                ];
            }
        } else {
            $data->status = PreSolicitudStatus::PAGOFALLIDO;
            $data->save();
            $finalResponse->ok = false;
            $finalResponse->step = PaymentGatewayEnum::STEP_PAY;
            $finalResponse->errors = $errors;
            $finalResponse->data = [
                'authorization' => $authorization,
                'order' => $order_indentify
            ];
        }

        return response()->json($finalResponse, $finalResponse->ok == true ? JsonResponse::OK : JsonResponse::BAD_REQUEST);
    }

    public function ReviewTransaction(Request $request) {
        $validate = Validator::make($request->all(), [
            'order_indentify' => 'required'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validate->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $response = $this->ReviewTransactionInfo($request->order_indentify, $request->audience);

        return response()->json($response, 200);

    }

    private function ReviewTransactionInfo($order_indentify, $audience) {
        $path = '/';
        if ($audience === AudienceEnum::USERS) {
            $path = '/partners';
        } else if ($audience === AudienceEnum::OPERATOR) {
            $path = '/operators';
        }

        $message = 'En breve un operador estará en su locación';

        $confirmacionPago = ConfirmacionesPagoPartner::where('openpay_solicitud_id', $order_indentify)->first();

        if ($confirmacionPago->pago_aprobado === false) {
            if (isset($confirmacionPago->message_solicitud[0]['message'])) {
                $message = $confirmacionPago->message_solicitud[0]['message'];
                if (isset($confirmacionPago->message_solicitud[0]['error'])) {
                    $message .= ''.$confirmacionPago->message_solicitud[0]['error'];
                }
            } else {
                $message = 'Hubo un problema al finalizar la transacción';
            }
        }

        $finalResponse = new \stdClass();
        $finalResponse->ok = $confirmacionPago->pago_aprobado;
        $finalResponse->order = $order_indentify;
        $finalResponse->authCode = $confirmacionPago->authorization;
        $finalResponse->message = $confirmacionPago->pago_aprobado = $message;
        $finalResponse->finalMessage = 'Gracias por su preferencia';
        $finalResponse->returnPath = $path.'/servicios';

        return (object) $finalResponse;
    }
}
