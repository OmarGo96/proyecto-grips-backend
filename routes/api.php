<?php

use App\Enums\NotificationPriority;
use App\Enums\PreSolicitudStatus;
use App\Helpers\JobsHelper;
use App\Helpers\PushNotificationsHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//region RUTAS GLOBALES
Route::post('activate-usr-token', 'SessionController@activateUserByCode');
Route::post('recovery-psw', 'SessionController@generateRecoveryPswToken');
Route::post('review-recovery-token', 'SessionController@reviewToken');
Route::post('change-pwd-token', 'SessionController@changePwdByToken');
//endregion

 //region RUTAS GLOBALES DE CONFIGURACIÓN
 Route::middleware(['verify.jwt'])->group(function() {
    Route::get('config/params', 'ConfigParamsController@getConfigParams');
    Route::get('config/available/fleets', 'ConfigParamsController@checkAvailabeFleets');
    Route::get('config/validate-questions', 'ConfigParamsController@getValidateQuestions');

    //region rutas para push notifications
    Route::post('push-notifications/register', 'PushNotificationController@registerTokenDevice');
    Route::post('push-notifications/remove-token', 'PushNotificationController@removeLogoutFCMToken');

    Route::post('notifications/list', 'PushNotificationController@getNotifications');
    Route::post('notifications/mark-read', 'PushNotificationController@markReadNotification');
    Route::post('notifications/delete', 'PushNotificationController@deleteNotification');
    Route::get('notifications/total-unread', 'PushNotificationController@getTotalUnRead');
    //endregion

     //region rutas para catálogos
     Route::post('catalog/countries', 'CatalogController@listCountries');
     Route::post('catalog/states', 'CatalogController@listStates');
     Route::get('catalog/vehiculos/marcas', 'CatalogController@listMarcasVehiculo');
     Route::post('catalog/vehiculos/clases', 'CatalogController@listClasesVehiculos');
     Route::get('catalog/vehiculos/tipos', 'CatalogController@listTipoVehiculos');
     Route::get('catalog/vehiculos/colores', 'CatalogController@listColorVehiculo');
     Route::get('catalog/tipo-pagos', 'CatalogController@listTiposPagos');
     Route::get('catalog/tipo-servicios', 'CatalogController@listTipoServicios');
     //endregion

     //region rutas operadores disponibles + costo
     Route::post('maps/available-operators', 'GeoMapsController@GetFleetOperators');
     //endregion

 });
 //endregion

//region RUTAS PARA PARTNERS (CLIENTES)


Route::prefix('partners')->group(function() {
    Route::post('login', 'SessionController@login');
    Route::post('register', 'SessionController@signup');

    Route::middleware(['verify.jwt', 'verify.partner'])->group(function() {

        //region rutas sesión
        Route::put('profile', 'SessionController@updateProfile');
        Route::put('profile/change-usr-email', 'SessionController@changeUsernameOrEmail');
        Route::put('profile/change-pwd', 'SessionController@changePwd');
        Route::get('profile', 'SessionController@getProfileData');

        Route::post('profile/openpay/register-client', 'SessionController@registerClientOpenPay');
        Route::get('profile/openpay/get-customer-id', 'SessionController@getOpenPayCustomerId');
        Route::get('profile/openpay/get-cards', 'SessionController@getCardsClientOpenPay');
        Route::post('profile/openpay/save-client-card', 'SessionController@saveClientCardOpenPay');
        Route::post('profile/openpay/delete-client-card', 'SessionController@deleteCardsClientOpenPay');
        //endregion

        //region rutas vehículos
        Route::post('vehiculos/register', 'VehiculosController@saveUpdateVehiculo');
        Route::get('vehiculos/list', 'VehiculosController@listVehiculos');
        Route::post('vehiculos/show', 'VehiculosController@showVehiculo');
        Route::delete('vehiculo', 'VehiculosController@deleteVehiculo');
        //endregion

        //region rutas solicitudes
        Route::post('solicitudes/pre', 'PreSolicitudesController@newPreSolicitud');
        Route::get('pre-solicitud/{id}', 'PreSolicitudesController@getPreSolicitudData');
        Route::post('pre-solicitudes/files', 'PreSolicitudesController@getPreSolFiles');
        Route::post('pre-solicitudes/get', 'PreSolicitudesController@getPreSolictudByAudience');
        Route::get('pre-solicitudes/inprogress', 'PreSolicitudesController@inProgress');
        //Route::post('pre-solicitudes/accept', 'PreSolicitudesController@acceptPreSol');
        Route::post('pre-solicitudes/renew', 'PreSolicitudesController@rePublishPreSol');

        Route::post('solicitudes/register', 'SolicitudesController@register');
        Route::post('solicitudes/list', 'SolicitudesController@listServices');
        Route::post('solicitudes/call-me', 'SolicitudesController@progCall');
        Route::post('solicitudes/wt-attach', 'SolicitudesController@attachWTPayment');
        Route::post('solicitudes/get-ticket', 'SolicitudesController@getPaymentTicket');
        //endregion

        //region TRANSACTIONS ROUTES
        Route::post('transaction/pay-request', 'TransactionsController@RecibePaymentRequest');
        Route::post('transaction/review', 'TransactionsController@ReviewTransaction');
        //endregion
    });

});
//endregion


//region rutas para sisema admin (oodo)
Route::prefix('admin')->group(function () {
    Route::post('login', 'SessionController@login');

    Route::middleware(['verify.jwt', 'verify.admin'])->group(function() {
        Route::post('push-notifications/push-direct', 'PushNotificationController@pushDirectNotification');
    });
});
//endregion

//region RUTAS PARA OPERATORS
Route::prefix('operators')->group(function () {
    Route::post('login', 'SessionController@login');

    Route::middleware(['verify.jwt','verify.operator'])->group(function() {

        Route::get('profile', 'SessionController@getProfileData');
        Route::put('profile/change-pwd', 'SessionController@changePwd');

        Route::post('pre-solicitudes/new', 'PreSolicitudesController@getPreSolicitudGeo');
        Route::get('pre-solicitud/{id}', 'PreSolicitudesController@getPreSolicitudData');
        Route::post('pre-solicitudes/files', 'PreSolicitudesController@getPreSolFiles');
        Route::post('pre-solicitudes/attend', 'PreSolicitudesController@attendRequest');
        Route::get('pre-solicitudes/inprogress', 'PreSolicitudesController@inProgress');
        Route::post('pre-solicitudes/get', 'PreSolicitudesController@getPreSolictudByAudience');

        Route::post('solicitudes/changeStatus', 'SolicitudesController@changeSolicitudState');
        Route::post('solicitudes/savePartnerDocs', 'SolicitudesController@attachArrivedPhotos');
        Route::post('solicitutes/update-plate-serie', 'SolicitudesController@updateVehiclePlatesSerie');
        Route::post('solicitudes/can-sign', 'SolicitudesController@canCaptureSignature');
        Route::post('solicitudes/capture-signature-finish', 'SolicitudesController@captureSignatureAndFinish');
        Route::post('solicitudes/get-ticket', 'SolicitudesController@getPaymentTicket');
        Route::post('solicitudes/list', 'SolicitudesController@listServices');
        Route::post('solicitudes/getPartnerDocs', 'SolicitudesController@getArrivedPhotos');
        Route::post('solicitudes/unLinkDocs', 'SolicitudesController@unLinkArrivedPhoto');

        Route::post('user/tracking', 'SessionController@trackingUser');
        Route::get('user/on-service', 'SessionController@isOnService');
    });

});
//endregion
Route::get('dev/resetPreSol', 'PreSolicitudesController@resetPreSol');
Route::get('dev/resetPreSol/{id}', 'PreSolicitudesController@resetPreSol');

Route::post('test/notifications', function (Request $request) {
    $validator = Validator::make($request->all(), [
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

    if ($validator->fails()) {
        return response()->json([
            'ok' => false,
            'errors' => $validator->errors()->all()
        ], 400);
    }

    $validModels = ['cms_padsolicitudes'];
    $validModules = ['operador'];
    $validSection = ['servicios'];

    if (in_array($request->model, $validModels) === false) {
        return response()->json([
            'ok' => false,
            'errors' => [
                            'El modelo de datos es incorrecto.',
                            ['Modelos validos: ' => $validModels]
                        ]
        ], 400);
    }

    if (in_array($request->module, $validModules) === false) {
        return response()->json([
            'ok' => false,
            'errors' => [
                            'El modulo de datos es incorrecto.',
                            ['Modulos validos: ' => $validModules]
                        ]
        ], 400);
    }

    if (in_array($request->section, $validSection) === false) {
        return response()->json([
            'ok' => false,
            'errors' => [
                            'La sección de datos es incorrecto.',
                            ['Secciones validos: ' => $validSection]
                        ]
        ], 400);
    }

    // Enviamos notificación al operador
    $sendNotification = PushNotificationsHelper::pushToUsers(
        $request->foreign_id,
        $request->audience,
        $request->model,
        $request->model_id,
        NotificationPriority::URGENT,
        $request->title,
       $request->body,
        $request->module,
        $request->section,
        $request->idobject
    );
    return response()->json([
        'data' => $sendNotification
    ], 200);
});

Route::post('test', 'SolicitudesController@testing');
Route::post('test/mailPresol', 'SolicitudesController@testPreMailSol');
