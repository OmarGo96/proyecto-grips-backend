<?php

namespace App\Helpers;

use App\Models\ConfirmacionesPagoPartner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayHelper
{


    public static function createClient($name, $lastname, $telephone, $email, $address = null) {

        $data = ['name' => $name, 'lastname' => $lastname, 'telephone' => $telephone, 'email' => $email, 'address' => $address];
        $validate = Validator::make($data, [
            'name' => 'required|string|max:100',
            'lastname' => 'nullable|string|max:100',
            'telephone' => 'nullable|max:15',
            'email' => 'required|string|max:150|email',
            'address' => 'nullable',
            'address.state' => 'nullable|string|max:100',
            'address.city' => 'nullable|string|max:100',
            'address.street' => 'nullable|string|max:100',
            'address.zip' => 'nullable|string|max:5',
            'address.neighborhood' => 'nullable|string|max:100',
        ]);

        if ($validate->fails()) {
            return (object) [
                'ok' => false,
                'errors' => $validate->errors()->all()
            ];
        }

        if($telephone == '' || is_null($telephone)) {
            $telephone = '9999999999';
        }

        $loginRes = self::login();
        if($loginRes->ok === false) {
            return (object) $loginRes;
        }

        $url = config('app.openpay_url').'clients/create';

        $response = Http::withHeaders([
            'Authorization' => $loginRes->token
        ])->post($url, [
            'name' => $name,
            'lastname' => (is_null($lastname) || $lastname === "") ? " " : $lastname,
            'telephone' => $telephone,
            'email' => $email,
            'address' => $address
        ]);

        return (object) $response->object();
    }

    public static function getClientCards(string $customer_id) {
        $loginRes = self::login();
        if($loginRes->ok === false) {
            return (object) $loginRes;
        }
        $url = config('app.openpay_url').'cards/show/'.$customer_id;

        $response = Http::withHeaders([
            'Authorization' => $loginRes->token
        ])->get($url);

        return (object) $response->object();
    }

    public static function saveClientCard(string $customer_id, string $card_number, string $holder_name, string $year, string $month, string $cvv2) {
        $loginRes = self::login();
        if($loginRes->ok === false) {
            return (object) $loginRes;
        }
        $url = config('app.openpay_url').'cards/create/'.$customer_id;

        $response = Http::withHeaders([
            'Authorization' => $loginRes->token
        ])->post($url, [
            'card_number' => $card_number,
            'holder_name' => $holder_name,
            'year' => $year,
            'month' => $month,
            'cvv2' => $cvv2
        ]);

        return (object) $response->object();
    }

    public static function deleteClientCard(string $cardToken) {
        $loginRes = self::login();
        if($loginRes->ok === false) {
            return (object) $loginRes;
        }
        $url = config('app.openpay_url').'cards/delete/'.$cardToken;

        $response = Http::withHeaders([
            'Authorization' => $loginRes->token
        ])->delete($url);

        return (object) $response->object();
    }

    public static function pay(string $card_id, string $amount, string $currency, $order, int $model_id, int $partner_id) {
        $loginRes = self::login();
        if($loginRes->ok === false) {
            return (object) $loginRes;
        }
        $url = config('app.openpay_url').'payments/create';
        $orderData = $order.'_'.Carbon::now()->timestamp;

        try {
            $response = Http::withHeaders([
                'Authorization' => $loginRes->token
            ])->post($url, [
                'card' => $card_id,
                'amount' => $amount,
                'currency' => $currency,
                'order' => $orderData
            ]);

            $res = $response->object();

             //Guardamos transacción en cms_confirmaciones_pago_partner
             $confirmacionPagoParner = new ConfirmacionesPagoPartner();
             $confirmacionPagoParner->transaction_datetime = GeneralUseHelper::validDateOODO();
             $confirmacionPagoParner->partner_id = $partner_id;
             $confirmacionPagoParner->pago_aprobado = $res->ok;
             $confirmacionPagoParner->model_id = $model_id;
            if ($res->ok === true) {
                $confirmacionPagoParner->message_solicitud = $res->message;
                $confirmacionPagoParner->openpay_solicitud_id = $res->order;
                $confirmacionPagoParner->authorization = $res->authorization;
                $confirmacionPagoParner->operation_type = $res->operation_type;
                $confirmacionPagoParner->transaction_type = $res->transaction_type;
            } else {
                $confirmacionPagoParner->message_solicitud = $res->errors;
                $confirmacionPagoParner->openpay_solicitud_id = $orderData;
            }
            $confirmacionPagoParner->save();

            $res = $response->object();
            $res->confirmacion_pago_id = $confirmacionPagoParner->id;
            $res->order = $orderData;
            $res->authorization = (isset($res->authorization)) ? $res->authorization : null;
            return (object) $res;
        } catch(\Throwable $e) {
            Log::debug($e);
            $confirmacionPagoParner = new ConfirmacionesPagoPartner();
            $confirmacionPagoParner->transaction_datetime = GeneralUseHelper::validDateOODO();
            $confirmacionPagoParner->partner_id = $partner_id;
            $confirmacionPagoParner->pago_aprobado = false;
            $confirmacionPagoParner->message_solicitud = 'Error Conexión Servicio';
            $confirmacionPagoParner->openpay_solicitud_id = $orderData;
            $confirmacionPagoParner->authorization = null;
            $confirmacionPagoParner->operation_type = null;
            $confirmacionPagoParner->transaction_type = null;
            $confirmacionPagoParner->model_id = $model_id;
            $confirmacionPagoParner->save();

            return (object) [
                'ok' => false,
                'order' => $orderData,
                'confirmacion_pago_id' => $confirmacionPagoParner->id,
                'authorization' => null,
                'errors' => [['message' => 'Error conexión servicio bancario.']]
            ];
        }

    }

    private static function login() {
        $url = config('app.openpay_url').'session/login';

        $response = Http::post($url, [
            'username' => config('app.openpay_user'),
            'password' => config('app.openpay_psw')
        ]);
        return (object) $response->object();
    }
}
