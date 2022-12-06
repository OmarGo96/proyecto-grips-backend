<?php

namespace App\Helpers;

use App\Enums\NotificationStatus;
use App\Models\AppNotifications;
use App\Models\AppNotificationsLog;
use App\Models\AppUserDevices;
use App\Models\Operadores;
use App\Models\User;
use App\Models\UserTracking;
use Carbon\Carbon;
use Google_Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PushNotificationsHelper
{

    /**
     * @description método para enviar notificaciones a determinada audiencia
     * @param foreign_id id de la tabla partners u operators dependiendo la audiencia
     * @param audience 1 = Partners, 2 = Operators
     * @param model modelo tabla relacionado ej. cms_pre_solicitudes
     * @param model_id llave primaria del modelo tabla relacionada
     * @param priority tipo de notificación 1 = normal, 2 = importante, 3 = urgente
     * @param title titulo de la notificación max: 50 carácteres
     * @param body cuerpo de la notificación max: 130 carácteres
     * @param module modulo del front al que pertenece ej. partners u operators
     * @param section sección del front que abrira ej. cms_pre_solicitudes
     * @param idobject ide del objecto
     * @param _notificationData extra data de notifications table
     *
     */
    public static function pushToUsers($foreign_id, $audience, $model, $model_id, $priority, $title, $body, $module, $section, $idobject, $_notificationData = null) {
        $params = [
            'foreign_id' => $foreign_id,
            'audience' => $audience,
            'model' => $model,
            'model_id' => $model_id,
            'priority' => $priority,
            'title' => $title,
            'body' => $body,
            'module' => $module,
            'section' => $section,
            'idobject' => $idobject
        ];
        // Validamos request data
        $validateData = Validator::make($params, [
            'foreign_id' => 'required|numeric',
            'audience' => 'required|numeric',
            'model' => 'required|string',
            'model_id' => 'required',
            'priority' => 'required|numeric',
            'title' => 'required|string|max:50',
            'body' => 'required|string|max:130',
            'module' => 'required',
            'section' => 'required',
            'idobject' => 'required'
        ]);

        if ($validateData->fails()) {
            return (object) [
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ];
        }
        $validModels = ['cms_pre_solicitudes', 'cms_padsolicitudes'];
        $validModules = ['partners', 'operador', 'operators'];
        $validSection = ['cms_pre_solicitudes', 'servicios', 'cms_padsolicitudes'];

        if (in_array($model, $validModels) === false) {
            return response()->json([
                'ok' => false,
                'errors' => [
                                'El modelo de datos es incorrecto.',
                                ['Modelos validos: ' => $validModels]
                            ]
            ], 400);
        }

        if (in_array($module, $validModules) === false) {
            return response()->json([
                'ok' => false,
                'errors' => [
                                'El modulo de datos es incorrecto.',
                                ['Modulos validos: ' => $validModules]
                            ]
            ], 400);
        }

        if (in_array($section, $validSection) === false) {
            return response()->json([
                'ok' => false,
                'errors' => [
                                'La sección de datos es incorrecto.',
                                ['Secciones validos: ' => $validSection]
                            ]
            ], 400);
        }

        if (isset($_notificationData)) {
            $notiData = $_notificationData;
        } else {
            // Guardamos pre-notificación antes que nada
            $preNotificationQuery = self::saveNotification($foreign_id, $audience, $model, $model_id, $priority, $title, $body, $module, $section, $idobject);
            if ($preNotificationQuery->ok !== true) {
                return (object) $preNotificationQuery;
            }
            $notiData = $preNotificationQuery->data;
        }


        $request =
        [
            'foreign_id' => $foreign_id,
            'audience' => $audience,
            'model' => $model,
            'model_id' => $model_id,
            'priority' => $priority,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'module' => $module,
                    'section' => $section,
                    'idobject' => (string) $idobject
                ]
            ]
        ];

        $errors = [];
        if ($request['audience'] === 1) {
            $user = User::where('id', '=', $request['foreign_id'])->with('user_devices')->first();
        } else if ($request['audience'] === 2) {
            $user = Operadores::where('id', '=', $request['foreign_id'])->with('user_devices')->first();
        }

        if (!$user) {
            array_push($errors, 'No se encontro el usuario');
        }
        if (is_null($user['user_devices']) || count($user['user_devices']) === 0) {
            array_push($errors, 'No existe un fcm token registrado, intente notificar por otro medio');
        }

        $FcmToken = [];

        for ($i = 0; $i < count($user['user_devices']); $i++) {
            if ($user['user_devices'][$i]->fcm_token) {
                $FcmToken[$i] = [
                    'id' => $user['user_devices'][$i]['id'],
                    'fcm_token' => $user['user_devices'][$i]['fcm_token'],
                    'foreign_id' => $user['user_devices'][$i]['foreign_id']
                ];
            }
        }

        if (count($FcmToken) === 0) {
            array_push($errors, 'No existe un fcm token registrado, intente notificar por otro medio');
        }

        if (count($errors) > 0) {
            return (object) [
                'ok' => false,
                'errors' => $errors
            ];
        }

        $fcmKeys = storage_path('fcm-sdk-keys/fcm-sdk-keys.json');

        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->setAuthConfig($fcmKeys);

        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        try {
            $httpClient = $client->authorize();
        } catch (\Exception $e) {
            array_push($errors, 'No se pudo autorizar con FCM');
        }

        // Firebase project ID
        $projectId = config('app.fcm-idproject');

        $notification = (object) $request['notification'];


        $urlAction = null;

        if (isset($request['notification']) && isset($request['notification']['data'])) {
            $dataParams = new \stdClass();
            $validateData = Validator::make($request['notification']['data'], [
                'module' => 'required|string',
                'section' => 'required|string',
                'idobject' => 'required|string'
            ]);

            if ($validateData->fails()) {
                return (object) [
                    'ok' => false,
                    'errors' => $validateData->errors()->all()
                ];
            }

            $notificationData = (object) $request['notification']['data'];

            $urlAction = '/'.$notificationData->module.'/'.$notificationData->section.'/'.$notificationData->idobject;
            $dataParams->url = $urlAction;
            $dataParams->module = $notificationData->module;
            $dataParams->section = $notificationData->section;
            $dataParams->idobject = $notificationData->idobject;
            $dataParams->notification_id = (string) $preNotificationQuery->data->id;
        } else {
            $dataParams = null;
        }


        for ($i = 0; $i < count($FcmToken); $i++) {


            if ($request['audience'] === 1) {
                $id = $user['id'];
            } else if ($request['audience'] === 2) {
                $id = $user['id'];
            }
            // Creamos el mensaje de la notificación
            $message = [
                "message" => [
                    "token" => $FcmToken[$i]['fcm_token'],
                    "data" => $dataParams,
                    "notification" => [
                        "body" => $notification->body,
                        "title" => $notification->title
                    ],
                    "android" => [
                        "notification" => [
                            "sound" => "default",
                            "notification_priority" => "PRIORITY_HIGH"
                        ]
                    ],
                    "webpush" => [
                        "fcm_options" => [
                            "link" => (isset($urlAction)) ? $urlAction : null
                        ]
                    ]
                ]
            ];

            // Guardamos notificacion Log antes que nada
            $notificationLogQuery = self::saveNotification($foreign_id, $audience, $model, $model_id, $priority, $title, $body, $module, $section, $idobject, true, $notiData->id);
            $notificationLogData = $notificationLogQuery->dataLog;

            $notificationLogData->encoded_notification = $message;
            $notificationLogData->save();

            // Envíamos la notificación push
            try {
                $response = $httpClient->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", ['json' => $message]);
                $notificationLogData->result = 'SUCCES NOTIFICATION EMITED';
                $notificationLogData->save();
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if($e->getCode() === 404) { // not found
                    self::deleteToken($FcmToken[$i]['id']);
                } else {
                    Log::debug($e);
                    array_push($errors, 'No se pudo enviar la notificación push');
                    $notificationLogData->haveError = -1;
                    $notificationLogData->result = json_encode($e);
                    $notificationLogData->save();
                }
            }
        }

        if (count($errors) > 0) {
            return (object) [
                'ok' => false,
                'errors' => $errors
            ];
        }

        return (object) [
            'ok' => true,
            'message' => 'Notificación enviada correctamente'
        ];
    }

    public static function pushToOperatorsDistance($lat, $lon, $distance, $request) {
        $validateData = Validator::make($request, [
            'model' => 'required|string',
            'model_id' => 'required',
            'priority' => 'required|numeric',
            'title' => 'required|string|max:50',
            'body' => 'required|string|max:130',
            'module' => 'required',
            'section' => 'required',
            'idobject' => 'required'
        ]);

        if ($validateData->fails()) {
            return (object) [
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ];
        }

        $errors = [];

        $limitBox = GeoDistance::getLimites($lat, $lon, $distance);

        $operatorsTracking = UserTracking::whereRaw(GeoDistance::getGeoQueryRaw($lat, $lon, $distance))
                             ->whereRaw(GeoDistance::getLimitQueryRaw($limitBox))
                             ->with('user_devices')
                             ->where('available', 1)
                             ->get();

        if (!$operatorsTracking || count($operatorsTracking) === 0) {
            return (object) [
                'ok' => false,
                'errors' => ['No existen operadores registrados para notificar']
            ];
        }

        for ($i = 0; $i < count($operatorsTracking); $i++) {
            try {
                $foreign_id = $operatorsTracking[$i]->foreign_id;
                $audience = 2;
                $model = $request['model'];
                $model_id = $request['model_id'];
                $priority = $request['priority'];
                $title = $request['title'];
                $body = $request['body'];
                $module = $request['module'];
                $section = $request['section'];
                $idobject = $request['idobject'];

                // Enviamos notificación
                $notificationQuery = self::pushToUsers($foreign_id, $audience, $model, $model_id, $priority, $title, $body, $module, $section, $idobject);
                if ($notificationQuery->ok !== true) {
                    array_push($errors, 'No se pudo enviar la notificación push');
                    continue;
                }
            } catch (\Exception $e) {
                Log::debug($e);
                array_push($errors, 'No se pudo enviar la notificación push');
                continue;
            }
        }

        $message = 'Notificación enviada correctamente';
        if (count($errors) === count($operatorsTracking)) {
            $message = 'En cuanto esté disponible alguno de nuestros operadores se les hará llegar una notificación';
        }

        return (object) [
            'ok' => true,
            'message' => $message
        ];
    }

    public static function inactiveModelRelation($model, $model_id) {
        $notifications = AppNotifications::where('model', $model)->where('model_id', $model_id)->get();
        for ($i = 0; $i < count($notifications); $i++) {
            $notifications[$i]->status = NotificationStatus::SYSTEMCANCEL;
            $notifications[$i]->active = false;
            $notifications[$i]->save();
        }
    }

    public static function deleteToken($id) {
        $fmc = AppUserDevices::where('id', $id)->first();
        if ($fmc) {
            if ($fmc->delete()) {
                return (object) ['ok' => true, 'message' => 'FMC Token eliminado correctamente'];
            } else {
                return (object) ['ok' => false, 'errors' => ['Hubo un error al momento de elimiar el FCM Token']];
            }
        }
        return (object) ['ok' => true, 'message' => 'No se encontraron registros por borrar'];
    }

    private static function saveNotification($foreign_id, $audience, $model, $model_id, $priority, $title, $body, $module, $section, $idobject, $isLog = null, $notification_id = null) {
        $params = [
            'title' => $title,
            'body' => $body,
            'foreign_id' => $foreign_id,
            'audience' => $audience,
            'priority' => $priority,
            'model' => $model,
            'model_id' => $model_id,
            'module' => $module,
            'section' => $section,
            'idobject' => $idobject
        ];
        // Validamos request data
        $validateData = Validator::make($params, [
            'title' => 'required|string|max:50',
            'body' => 'required|string|max:130',
            'foreign_id' => 'required|numeric',
            'audience' => 'required|numeric',
            'priority' => 'required|numeric',
            'model' => 'required|string',
            'model_id' => 'required',
            'module' => 'required',
            'section' => 'required',
            'idobject' => 'required',
            'notification_id' => 'nullable|numeric'
        ]);

        if ($validateData->fails()) {
            return (object) [
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ];
        }

        if ($isLog === null) {
            $notification = new AppNotifications();
            $notification->title = $params['title'];
            $notification->body = $params['body'];
            $notification->foreign_id = $params['foreign_id'];
            $notification->status = NotificationStatus::EMITED;
            $notification->date_reg = Carbon::now();
            $notification->audience = $params['audience'];
            $notification->active = true;
            $notification->priority = $params['priority'];
            $notification->model = $params['model'];
            $notification->model_id = $params['model_id'];
            $notification->exp_date = Carbon::now()->addHours(6);
            $notification->module = $params['module'];
            $notification->section = $params['section'];
            $notification->idobject = $params['idobject'];

            if ($notification->save()) {
                return (object) ['ok' => true, 'data' => $notification];
            } else {
                return (object) ['ok' => false, 'errors' => ['No se pudo guardar la información']];
            }

        } else {
            $notificationLog = new AppNotificationsLog();
            $notificationLog->title = $params['title'];
            $notificationLog->body = $params['body'];
            $notificationLog->foreign_id = $params['foreign_id'];
            $notificationLog->date_reg = Carbon::now();
            $notificationLog->result = 'NEW NOTIFICATION EMITED';
            $notificationLog->audience = $params['audience'];
            $notificationLog->active = true;
            $notificationLog->priority = $params['priority'];
            $notificationLog->model = $params['model'];
            $notificationLog->model_id = $params['model_id'];
            $notificationLog->exp_date = Carbon::now()->addHours(6);
            $notificationLog->module = $params['module'];
            $notificationLog->section = $params['section'];
            $notificationLog->idobject = $params['idobject'];
            $notificationLog->notification_id = $notification_id;

            if ($notificationLog->save()) {
                return (object) ['ok' => true, 'dataLog' => $notificationLog];
            } else {
                return (object) ['ok' => false, 'errors' => ['No se pudo guardar la información']];
            }
        }
    }
}
