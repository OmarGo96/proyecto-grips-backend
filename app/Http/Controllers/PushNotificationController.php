<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Helpers\PushNotificationsHelper;
use App\Models\AppNotifications;
use App\Models\AppUserDevices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class PushNotificationController extends Controller
{
    // Función para que el dispositivo del usuario registre el fcm token
    public function registerTokenDevice(Request $request)
    {
        // Verificamos que exista el modelo user en el request
        $user = $request->user;
        if (!$user) {
            return response()->json([
                'ok' => false,
                'errors' => ['No se determino el usuario autenticado']
            ], JsonResponse::UNAUTHORIZED);
        }

        // validamos que venga el fcm token en el request
        $validateData = Validator::make($request->all(), [
            'fcm_token' => 'required|string'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }


        if ($request->audience === 1) {
            $id = $user->id;
        } else if ($request->audience === 2) {
            $id = $user->id;
        }

        $fcmTKN = AppUserDevices::where('audience', '=', $request->audience)->where('foreign_id', '=', $id)
                  ->where('fcm_token', '=', $request->fcm_token)
                  ->first();
        // verificamos que no este registrado el fcm_token al usuario
        if (is_null($fcmTKN) === true) {

            $userDevices = new AppUserDevices();
            $userDevices->audience = $request->audience;
            $userDevices->foreign_id = $id;
            $userDevices->fcm_token = $request->fcm_token;
            $userDevices->date_reg = Carbon::now();

            if ($userDevices->save()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Token FCM registrado correctamente'
                ], JsonResponse::OK);
            } else {
                return response()->json([
                    'ok' => false,
                    'errors' => ['Algo salio mal, no se pudo registrar el token de notificación']
                ], JsonResponse::BAD_REQUEST);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Token previamente registrado'
        ], JsonResponse::OK);
    }

    /**
     *
     * función para enviar una push notificaion a un dispositivo en concreto dado el foreign_id y la audiencia
     */
    public function pushDirectNotification(Request $request)
    {
        // Validamos request data
        $validateData = Validator::make($request->all(), [
            'partner_id' => 'required|numeric',
            'audience' => 'nullable|numeric',
            'model' => 'required|string',
            'model_id' => 'required|numeric',
            'priority' => 'nullable|numeric',
            'title' => 'required|string|max:30',
            'body' => 'required|string|max:100',
            'module' => 'required|string',
            'section' => 'required|string',
            'idobject' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        // Enviamos notificación al cliente
        $audience = 1;
        if($request->has('audience')) {
            $audience = $request->audience;
        }
        $model = 'cms_padsolicitudes';
        if ($request->has('model')) {
            $model = $request->model;
        }
        $model_id = $request->model_id;
        $priority = NotificationPriority::URGENT;
        if ($request->has('priority')) {
            $priority = $request->priority;
        }
        $module = 'partners';
        if ($request->has('module')) {
            $module = $request->module;
        }
        $section = 'cms_padsolicitudes';
        if ($request->has('section')) {
            $section = $request->section;
        }
        $idobject = $request->idobject;

        $sendNotification = PushNotificationsHelper::pushToUsers(
            $request->partner_id, $audience, $model,
            $model_id, $priority,
            $request->title,
            $request->body,
            $module,
            $section, $idobject
        );
        // TODO: agregar un log de errores para reprocesar notificaciones
        if ($sendNotification->ok !== true) {
            Log::debug(["Error on send notification --->", json_encode($sendNotification)]);
            return response()->json([
                'ok' => false,
                'errors' => $sendNotification->errors,
            ], JsonResponse::BAD_REQUEST);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Notificación enviada correctamente',
        ], JsonResponse::OK);
    }

    public function removeLogoutFCMToken(Request $request) {
        $user = $request->user;
        $audience = $request->audience;

        if ($request->audience === 1) {
            $id = $user->id;
        } else if ($request->audience === 2) {
            $id = $user->id;
        }

        if($request->has('fcm_token')) {
            $userDevices = AppUserDevices::where('foreign_id', '=', $id)
                           ->where('audience', '=', $audience)
                           ->where('fcm_token', '=', $request->fcm_token)
                           ->first();

            if ($userDevices) {
                $userDevices->delete();
                return response()->json([
                    'ok' => true,
                    'message' => 'Se removio el FCM Token'
                ], JsonResponse::OK);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'No se removio ningun fcm token'
        ], JsonResponse::OK);
    }

    public function getNotifications(Request $request) {
        $validateData = Validator::make($request->all(), [
            'read' => 'nullable|boolean',
            'priority' => 'nullable|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $audience = $request->audience;
        $user = $request->user;

        if ($audience === 1) {
            $id = $user->id;
        } else if ($audience === 2) {
            $id = $user->id;
        }

        $notificationsQ = AppNotifications::select('id', 'title', 'body', 'date_reg', 'priority', 'model', 'model_id', 'exp_date', 'status')
                          ->where('audience', $audience)
                          ->where('foreign_id', $id)
                          ->where('active', 1);

        if ($request->has('read')) {
            $notificationsQ->where('status', NotificationStatus::READ);
        }

        if ($request->has('priority')) {
            $notificationsQ->where('priority', $request->priority);
        }
        $notificationsQ->with('preSol:id,status,partner_id,fecha_hora_extension');
        $notificationsQ->orderBy('id', 'DESC');
        $notifications = $notificationsQ->get();

        return response()->json([
            'ok' => true,
            'notifications' => $notifications
        ], JsonResponse::OK);
    }

    public function markReadNotification(Request $request) {
        $validateData = Validator::make($request->all(), [
            'id' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $audience = $request->audience;
        $user = $request->user;

        if ($audience === 1) {
            $id = $user->id;
        } else if ($audience === 2) {
            $id = $user->id;
        }

        $notification = AppNotifications::where('audience', $audience)->where('foreign_id', $id)->where('id', $request->id)->first();
        $notification->status = NotificationStatus::READ;


        if ($notification->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Notificación marcada como leida'
            ], JsonResponse::OK);
        }
    }

    public function deleteNotification (Request $request) {
        $user = $request->user;
        $audience = $request->audience;

        $validateData = Validator::make($request->all(), [
            'id' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        if ($audience === 1) {
            $id = $user->id;
        } else if ($audience === 2) {
            $id = $user->id;
        }

        $notification = AppNotifications::where('audience', $audience)->where('foreign_id', $id)->first();
        $notification->active = false;
        $notification->status = NotificationStatus::CANCEL;

        if ($notification->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Notificación elimindada'
            ], JsonResponse::OK);
        }
    }

    public function getTotalUnRead(Request $request) {
        $user = $request->user;
        $audience = $request->audience;

        if ($audience === 1) {
            $id = $user->id;
        } else if ($audience === 2) {
            $id = $user->id;
        }

        $totalNotifications = AppNotifications::where('audience', $audience)->where('foreign_id', $id)->where('status', NotificationStatus::EMITED)->where('active', true)->count();
        if ($totalNotifications > 99) {
            $totalNotifications = '99+';
        }
        return response()->json([
            'ok' => true,
            'total' => $totalNotifications
        ], JsonResponse::OK);
    }

}
