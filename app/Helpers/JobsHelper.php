<?php

namespace App\Helpers;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\PreNotificationStatus;
use App\Enums\PreSolicitudStatus;
use App\Models\AppFailedJobs;
use App\Models\AppNotifications;
use App\Models\AppNotificationsPre;
use App\Models\FleetVehicle;
use App\Models\Operadores;
use App\Models\PreSolicitudes;
use Carbon\Carbon;
use Google\Service\HangoutsChat\Card;
use Google_Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobsHelper
{

    public static function rePushNotification($priority) {
        $errors = [];

        $preNotifications = AppNotifications::where('priority', $priority)
                         ->where('attempts', '<=', 3)
                         ->where('active', true)
                         ->where('status', NotificationStatus::EMITED)
                         ->get();
        for ($i = 0; $i < count($preNotifications); $i++) {
            if ($preNotifications[$i]->exp_date <= Carbon::now() || $preNotifications[$i]->attempts > 3) {
                $preNotifications[$i]->status = NotificationStatus::SYSTEMCANCEL;
                $preNotifications[$i]->active = false;
                $preNotifications[$i]->attempts ++;
                $preNotifications[$i]->save();
                continue;
            }

            try {
                // emitimos a usuario
                $sendNotification = PushNotificationsHelper::pushToUsers(
                    $preNotifications[$i]->foreign_id,
                    $preNotifications[$i]->audience,
                    $preNotifications[$i]->model,
                    $preNotifications[$i]->model_id,
                    NotificationPriority::URGENT,
                    $preNotifications[$i]->title,
                    $preNotifications[$i]->body,
                    $preNotifications[$i]->module,
                    $preNotifications[$i]->section,
                    $preNotifications[$i]->idobject,
                    $preNotifications[$i]
                );
            } catch (\Exception $e) {
                Log::debug($e);
                array_push($errors, 'No se pudo enviar la notificación push');

                if ($preNotifications[$i]->attempts >= 3) { // desactivamos emision
                    $preNotifications[$i]->active = false;
                    $preNotifications[$i]->status = NotificationStatus::SYSTEMCANCEL;
                    $preNotifications[$i]->save();
                }

                AppFailedJobs::create('PushNotifications UnRead', 'SEND FCM', $e->getMessage());
            }
            $preNotifications[$i]->attempts ++;
            $preNotifications[$i]->save();

        }
        return (object) [
            'data' => $preNotifications
        ];
    }

    public static function expireNotAttendedPreSol($minutes, $jobname, $status, $notify = false) {
        $errors = [];
        $preSolQ = PreSolicitudes::where('status', $status)->where('fecha_hora_extension', '<=', (new Carbon())->subMinutes($minutes));
        $titleN = 'Su servicio ha expirado por inactividad.';
        $bodyN = 'Se agoto el tiempo de espera para que fuera atendido su servicio.';
        try {
            $preSol = $preSolQ->get();
            if ($minutes < 5) {
                $titleN = 'Su servicio esta a punto de caducar';
                $bodyN = 'La solicitud esta por caducar por falta de inactividad';
            } else {
                $preSolQ->update([
                    'status' => PreSolicitudStatus::EXPIRADA
                ]);

                //Desbloqueamos grúa en caso de existir
                for ($i = 0; $i < count($preSol); $i++) {
                    if (isset($preSol[$i]->operator_id)) {
                        $res = FleetVehicle::blockUnblockFleetVehicle($preSol[$i]->operator_id, true);
                        if ($res->ok === false) {
                            array_push($errors, $res->errors);
                        }
                    }
                }
            }


            // Notificamos al usuario
            if ($notify === true) {

                for ($i = 0; $i < count($preSol); $i ++) {
                    $sendPush = PushNotificationsHelper::pushToUsers(
                        $preSol[$i]->partner_id, 1, 'cms_pre_solicitudes',
                        $preSol[$i]->id, NotificationPriority::URGENT,
                        $titleN,
                        $bodyN,
                        'partners',
                        'cms_pre_solicitudes', $preSol[$i]->id
                    );
                    if ($sendPush->ok !== true) {
                        array_push($errors, $sendPush->errors);
                    }
                }
            }
            if (count($errors) > 0) {
                Log::debug(json_encode($errors));
            }
            return (object) [
                'ok' => true,
                'data' => $preSol
            ];
        } catch (\Exception $e) {
            Log::debug(json_encode($e));
            AppFailedJobs::create($jobname, 'update to expired', $e->getMessage());
            return (object) [
                'ok' => false,
                'errors' => ['Error during updating PreSolicitudes']
            ];
        }

    }
}
