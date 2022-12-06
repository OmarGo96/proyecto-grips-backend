<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Helpers\Encrypt;
use Illuminate\Http\Request;
use App\Helpers\JWTManager;
use App\Helpers\Odoo;
use App\Helpers\PaymentGatewayHelper;
use App\Mail\UserRecoveyPsw;
use App\Mail\ActivactionCode;
use App\Models\AppUserDevices;
use App\Models\Operadores;
use App\Models\User;
use App\Models\UserAdmin;
use App\Models\UserTracking;
use App\Models\UserVerificationToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    // Método para autentificar al usuario a traves de las credenciales proporcionadas
    public function login(Request $request)
    {
        $validPrefixes = ['api/admin', 'api/operators', 'api/partners'];


        // Validamos datos recibidos por el request
        $validateData = User::validateBeforeLogin($request->all());

        if ($validateData !== true) {
            return response()->json(['ok' => false, 'error' => $validateData], JsonResponse::BAD_REQUEST);
        }

        // Forzamos a utilizar la guardia api
        Auth::shouldUse('api');
        $passCheck = false;
        $audience = 'partners';
        $jwtExp = null;

        $password = trim($request->password);

        // Verificamos si es usuario admin por el prefijo
        $routePrefix = (object) $request->route()->action;
        if (in_array($routePrefix->prefix, $validPrefixes) === false) {
            return response()->json(['ok' => false, 'error' => ['Endpoint inváido']], JsonResponse::UNAUTHORIZED);
        }

        if ($routePrefix->prefix === 'api/admin') {
            $username = trim($request->username);
            $user = UserAdmin::where('login', 'ilike', $username)->first();
        } else if ($routePrefix->prefix === 'api/operators') {
            $username = trim($request->username);
            $user = UserAdmin::where('login', 'ilike', $username)->first();
            try {
                $user->load('driver');
                if (!isset($user->driver)) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['El usuario no esta ligado a un operador']
                    ], JsonResponse::UNAUTHORIZED);
                }
            } catch(\Exception $e) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['El usuario no esta ligado a un operador']
                ], JsonResponse::UNAUTHORIZED);
            }

        } else {
            $username = trim($request->username);
            $user = User::where('email', 'ilike', $username)->first();
        }

        if (is_null($user)) {
            return response()->json(['ok' => false, 'errors' => ['Usuario o contraseña inválidos']], JsonResponse::BAD_REQUEST);
        }

        if ($routePrefix->prefix === 'api/admin') {
            $passCheck = Encrypt::verify_passlib_pbkdf2($password, $user->password);
            $audience = 'admin';
            $jwtExp = time() + (7 * 24 * 60 * 60);
        } else if ($routePrefix->prefix === 'api/operators') {
            $passCheck = Encrypt::verify_passlib_pbkdf2($password, $user->password);
            $audience = 'operators';
            $jwtExp = time() + (7 * 24 * 60 * 60);
        } else {
            // revisar si el usuario esta activo
            if ($user->active === false) {
                // generamos token de verificación y enviamos correo
                $tokenData = $this->generateRecoveryToken($user->id, 2, 1);
                if ($tokenData->ok !== true) {
                    return response()->json($tokenData, JsonResponse::BAD_REQUEST);
                }
                try {
                    Mail::to($user->email)->send(new ActivactionCode($tokenData->token, Carbon::now()));
                } catch(\Exception $e) {
                    Log::debug($e);
                    return response()->json(['ok' => false, 'errors' => ['No fue posible enviar el correo de activación']], JsonResponse::BAD_REQUEST);
                }
                return response()->json([
                    'ok' => true,
                    'validateCode' => true
                ], JsonResponse::OK);
            }
            $passCheck = Hash::check($password, $user->password);
        }

        if ($passCheck) {
            $id = $user->id;
            if ($audience === 'operators') {
                $id = $user->driver->id;
            }
            $jwt = JWTManager::createJwt($id, $audience, $jwtExp);
            if ($jwt === false) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['Error durante generación de sesión']
                ], JsonResponse::BAD_REQUEST);
            }

            if ($request->has('fcm_token') && ($audience === 'partners' || $audience === 'operators')) {
                if ($audience === 'partners') {
                    $source = 1;
                }
                if ($audience === 'operators') {
                    $source = 2;
                }
                $fcmTKN = AppUserDevices::where('foreign_id', '=', $user->id)->where('audience', '=', $source)
                ->where('fcm_token', '=', $request->fcm_token)
                ->first();
                // verificamos que no este registrado el fcm_token al usuario
                if (is_null($fcmTKN) === true) {

                    $userDevices = new AppUserDevices();
                    $userDevices->foreign_id = $id;
                    $userDevices->fcm_token = $request->fcm_token;
                    $userDevices->date_reg = Carbon::now();
                    $userDevices->audience = $source;

                    $saveEvent = $userDevices->save();

                    if ($saveEvent === false) {
                        return response()->json([
                            'ok' => false,
                            'errors' => ['Algo salio mal, no se pudo registrar procesar parte de su solicitud']
                        ], JsonResponse::BAD_REQUEST);
                    }
                }
            }
            $message = 'Bienvenido nuevamente ';
            $message .= ($audience ===  'admin' || $audience ===  'operators') ? $user->login :  $user->name;

            return response()->json(
                [
                    'ok' => true,
                    'token' => $jwt,
                    'message' => $message
                ],
                JsonResponse::OK
            );
        }

        return response()->json(
            [
                'ok' => false,
                'errors' => ['Usuario o contraseña inválidos']
            ],
            JsonResponse::BAD_REQUEST
        );
    }

    // Método para registar un partner nuevo
    public function signup(Request $request)
    {
        // validamos
        $validateData = User::validateBeforeRegister($request->all());


        if ($validateData !== true) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData
            ], JsonResponse::BAD_REQUEST);
        }

        $user = new User();
        $user->name = $request->name;
        $user->display_name = $request->name;
        $user->vat = (isset($request->vat)) ? $request->vat : null;
        $user->email = $request->email;
        $user->mobile = (isset($request->mobile)) ? $request->mobile : null;
        $user->password = Hash::make(trim($request->password));
        $user->customer = true;
        $user->asignar_vehiculo = true;
        $user->cms_agencia = true;
        $user->active = false;
        $user->is_company = true;

        if ($user->save()) {
            // generamos token de verificación y enviamos por correo
            $tokenData = $this->generateRecoveryToken($user->id, 2, 1);
            if ($tokenData->ok !== true) {
                return response()->json($tokenData, JsonResponse::BAD_REQUEST);
            }
            try {
                Mail::to($user->email)->send(new ActivactionCode($tokenData->token, Carbon::now()));
            } catch(\Exception $e) {
                Log::debug($e);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Registro exitoso, felicidades'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal al guardar su información, por favor intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    public function activateUserByCode(Request $request) {
        $validateData = Validator::make($request->all(), [
            'verifyEmail' => 'required|email',
            'recoveryToken' => 'required',
            'recoveryToken.token' => 'required'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $tokenData = $this->checkRecoveryToken($request->recoveryToken['token']);

        if ($tokenData->ok !== true) {
            return response()->json($tokenData, JsonResponse::BAD_REQUEST);
        }

        $userId = $tokenData->data->foreign_id;
        $audience = 'partners';

        if ($tokenData->data->audience === 1) {
            $audience = 'partners';
            $userData = User::where('id', '=', $userId)
            ->where('email', '=', $request->verifyEmail)
            ->where('active', '=', false)
            ->first();
        }

        if ($tokenData->data->audience === 2) {
            $audience = 'operators';
            $userData = Operadores::where('id', '=', $userId)
            ->where('active', '=', false)
            ->first();
        }

        if (!$userData) {
            return response()->json([
                'ok' => false,
                'errors' => ['Usuario no encontrado']
            ], JsonResponse::BAD_REQUEST);
        }

        if ($tokenData->data->type !== 2) {
            return response()->json([
                'ok' => false,
                'errors' => ['Este token es inválido']
            ], JsonResponse::BAD_REQUEST);
        }

        $userData->active = true;
        if ($userData->save()) {
            $jwtExp = time() + (7 * 24 * 60 * 60);
            $jwt = JWTManager::createJwt($userData->id, $audience, $jwtExp);

            // Invalidamos token si existen
            $this->invalidateTokens($userData->id, $tokenData->data->type, $tokenData->data->audience);
            return response()->json([
                'ok' => true,
                'token' => $jwt,
                'message' => 'Se ha activado correctamente su cuenta, ya puede iniciar sesión'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Hubo un error, no se pudo activar su cuenta, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    // Método para modificar perfil
    public function updateProfile(Request $request)
    {
        // validamos
        $validateData = User::validateBeforeUpdate($request->all());


        if ($validateData !== true) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData
            ], JsonResponse::BAD_REQUEST);
        }

        $user = $request->user;
        if (!$user) {
            return response()->json([
                'ok' => false,
                'errors' => ['Usuario no encontrado']
            ], JsonResponse::BAD_REQUEST);
        }

        $user->name = $request->name;
        $user->display_name = $request->name;
        $user->vat = (isset($request->vat)) ? $request->vat : null;
        $user->mobile = (isset($request->mobile)) ? $request->mobile : null;
        $user->street = (isset($request->street)) ? $request->street : null;
        $user->street2 = (isset($request->street2)) ? $request->street2 : null;
        $user->zip = (isset($request->zip)) ? $request->zip : null;
        $user->city = (isset($request->city)) ? $request->city : null;
        $user->state_id = (isset($request->state_id)) ? $request->state_id : null;
        $user->country_id = (isset($request->country_id)) ? $request->country_id : null;
        $user->customer = true;
        $user->asignar_vehiculo = true;
        $user->cms_agencia = true;
        $user->active = true;

        if ($user->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Datos actualizados correctamente'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal al guardar su información, por favor intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    // Método para modificar username o email
    public function changeUsernameOrEmail(Request $request)
    {
        $user = $request->user;
        $errors = [];
        if ($request->has('email')) {
            $checkEmail = User::where('email', '=', $request->email)
                ->where('id', '!=', $user->id)
                ->first();
            if (is_null($checkEmail)) {
                $user->email = $request->email;
            } else {
                array_push($errors, 'Este correo ya esta registrado en nuestro sistema, intente con uno nuevo o inicie sesión con la cuenta correspondiete');
            }
        }

        if ($request->has('username')) {
            $username = Str::upper($request->username);
            $checkUsername = User::where('username_app', '=', $username)
                ->where('id', '!=', $user->id)
                ->first();
            if (is_null($checkUsername)) {
                $user->username_app = $username;
            } else {
                array_push($errors, 'Este nombre de usuario ya esta registrado en nuestro sistema, intente con uno nuevo o inicie sesión con la cuenta correspondiete');
            }
        }


        if (count($errors) > 0) {
            return response()->json([
                'ok' => false,
                'errors' => $errors
            ], JsonResponse::BAD_REQUEST);
        }

        if ($user->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Información actualizada correctamente'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal, intenta nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }


    }

    // función para cambiar contraseña de sesión
    public function changePwd(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }
        if ($request->audience === 1) {
            $userId = $request->user->id;

            $userData = User::where('id', '=', $userId)
            ->where('active', '=', true)
            ->first();
        } else if ($request->audience === 2) {
            $userId = $request->user->id;

            $userData = Operadores::where('id', '=', $userId)
            ->where('active', '=', true)
            ->first();
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Usuario no encontrado (AUD)']
            ], JsonResponse::BAD_REQUEST);
        }

        if (!$userData) {
            return response()->json([
                'ok' => false,
                'errors' => ['Usuario no encontrado']
            ], JsonResponse::BAD_REQUEST);
        }

        if (Hash::check($request->old_password, $userData->password)) {
            $userData->password = Hash::make($request->new_password);
            if ($userData->save()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Su contraseña fue cambiada correctamente'
                ], JsonResponse::OK);
            }
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['La contraseña anterior no concide con la registrada, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    // función para obtener listado del perfil
    public function getProfileData(Request $request) {
        $user = $request->user;
        if (!$user) {
            return response()->json([
                'ok' => false,
                'errors' => ['No es posible obtener información de su perfil por el momento, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
        if ($request->audience === 1) {
            $userData = User::select('id', 'username_app as username', 'email', 'name', 'vat', 'mobile', 'street', 'street2', 'zip', 'city', 'state_id', 'country_id')
            ->where('id', '=', $user->id)
            ->where('active', '=', true)
            ->first();
        } else if ($request->audience === 2) {
            $userData = Operadores::select('id', 'empleado_id', 'login', 'company_id', 'disponible_op', 'bloqueado_x_op')
            ->where('id', '=', $user->id)
            ->where('active', '=', true)
            ->first();
            if (!$userData) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['No es posible obtener información de su perfil por el momento, intente nuevamente']
                ], JsonResponse::BAD_REQUEST);
            }
            $odooC = new Odoo();
            $odooRes = $odooC->getEmployeeData($userData->empleado_id);

            if ($odooRes->ok === true) {
                $userData->employeeData = $odooRes->data;
            }
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['No fue posible determinar la audiencia, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }

        if (!$userData) {
            return response()->json([
                'ok' => false,
                'errors' => ['No es posible obtener información de su perfil por el momento, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }

        return response()->json([
            'ok' => true,
            'profile' => $userData
        ], JsonResponse::OK);
    }

    public function testJWT(Request $request)
    {
        $authUser = new \stdClass();
        $authUser->id = 1;
        $check = JWTManager::createJwt($authUser, 'api');

        if ($request->jwt) {
            dd(JWTManager::validateJWT($request->jwt));
        } else {
            dd($check);
        }
    }

    public function generateRecoveryPswToken(Request $request) {
        $validateData = Validator::make($request->all(), [
            'email' => 'required',
            'audience' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        if ($request->audience === 1) {
            $user = User::where('email', '=', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'errors' =>['No se encontro este correo registrado, intente con otro']
                ], JsonResponse::BAD_REQUEST);
            }
            $foreingId = $user->id;
        }

        if ($request->audience === 2) {
            $user = Operadores::whereHas('employee', function(Builder $query) use ($request) {
                return $query->where('work_email', '=', $request['email']);
            })->first();

            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'errors' =>['No se encontro este correo registrado, intente con otro']
                ], JsonResponse::BAD_REQUEST);
            }
            $foreingId = $user->id;
        }

        $tokenData = $this->generateRecoveryToken($foreingId, 1, $request->audience);

        if ($tokenData->ok !== true) {
            return response()->json(
                $tokenData
            ,JsonResponse::BAD_REQUEST);
        }
        $token = $tokenData->token;
        try {
            Mail::to($request->email)->send(new UserRecoveyPsw($token, Carbon::now()));
            return response()->json(['ok' => true, 'message' => 'Se ha enviado su token de recuperación de contraseña'], JsonResponse::OK);
        } catch(\Exception $e) {

            Log::debug($e);
            return response()->json([
                'ok' => false,
                'errors' => ['Hubo un error al generar su token de recuperación, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    // función para cambiar contraseña de sesión por generación de token
    public function changePwdByToken(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'verifyEmail' => 'required|email',
            'new_password' => 'required|string',
            'recoveryToken' => 'required',
            'recoveryToken.token' => 'required'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }
        $tokenData = $this->checkRecoveryToken($request->recoveryToken['token']);

        if ($tokenData->ok !== true) {
            return response()->json($tokenData, JsonResponse::BAD_REQUEST);
        }

        $userId = $tokenData->data->foreign_id;

        if ($tokenData->data->audience === 1) {
            $userData = User::where('id', '=', $userId)
            ->where('email', '=', $request->verifyEmail)
            ->where('active', '=', true)
            ->first();
        }

        if ($tokenData->data->audience === 2) {
            $userData = Operadores::where('id', '=', $userId)
            ->where('active', '=', true)
            ->first();
        }

        if (!$userData) {
            return response()->json([
                'ok' => false,
                'errors' => ['Usuario no encontrado']
            ], JsonResponse::BAD_REQUEST);
        }

        if ($tokenData->data->type !== 1) {
            return response()->json([
                'ok' => false,
                'errors' => ['Este token es inválido']
            ], JsonResponse::BAD_REQUEST);
        }

        $userData->password = Hash::make(trim($request->new_password));

        if ($userData->save()) {
            // Invalidamos token si existen
            $this->invalidateTokens($userId, $tokenData->data->type, $tokenData->data->audience);

            return response()->json([
                'ok' => true,
                'message' => 'Su contraseña fue cambiada correctamente'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Hubo un error al momento de cambiar su contraseña, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    public function trackingUser(Request $request) {
        $user = $request->user;

        $audience = $request->audience;
        $validateData = Validator::make($request->all(), [
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric'
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
        $userTracking = UserTracking::where('foreign_id', $id)->where('audience', $audience)->first();

        if (!$userTracking) {
            $userTracking = new UserTracking();
        }

        $userTracking->audience = $audience;
        $userTracking->foreign_id = $id;
        $userTracking->lat = $request->lat;
        $userTracking->lon = $request->lon;
        $userTracking->available = $request->available;

        if ($request->has('available')) {

            $userTracking->active = true;
        }

        if ($userTracking->save()) {
            return response()->json([
                'ok' => true,
                'message' => 'Coords, registradas correctamente'
            ], JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal, intente nuevamente']
            ], JsonResponse::BAD_REQUEST);
        }


    }

    public function isOnService(Request $request) {
        $user = $request->user;
        $audience = $request->audience;

        if ($audience === 1) {
            $id = $user->id;
        } else if ($audience === 2) {
            $id = $user->id;
        }
        $userTracking = UserTracking::where('foreign_id', $id)->where('audience', $audience)->first();

        if (!$userTracking) {
            return response()->json([
                'ok' => false,
                'errors' => ['Habilite su disponibilidad para continuar']
            ], JsonResponse::BAD_REQUEST);
        }

        return response()->json([
            'ok' => true,
            'data' => $userTracking
        ], JsonResponse::OK);

    }

    public function reviewToken(Request $request) {
        $validateData = Validator::make($request->all(), [
            'token' => 'required'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $validateToken = $this->checkRecoveryToken($request->token);

        if ($validateToken->ok !== true) {
            return response()->json(
                $validateToken
            , JsonResponse::BAD_REQUEST);
        }

        return response()->json([
            'ok' => true,
            'data' => $validateToken->data
        ], JsonResponse::OK);
    }

    public function registerClientOpenPay(Request $request) {
        $user = $request->user;

        if ($user->openpay_customer_id && $user->openpay_customer_id != '') {
            return response()->json([
                'ok' => false,
                'errors' => ['Este cliente ya fue registrado con el motor de pago']
            ], JsonResponse::BAD_REQUEST);
        }

        $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'lastname' => 'nullable|string|max:100',
            'telephone' => 'required|string|max:15',
            'email' => 'required|string|max:150|email',
            'address' => 'nullable',
            'address.state' => 'nullable|string|max:100',
            'address.city' => 'nullable|string|max:100',
            'address.street' => 'nullable|string|max:100',
            'address.zip' => 'nullable|string|max:5',
            'address.neighborhood' => 'nullable|string|max:100',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validate->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $res = PaymentGatewayHelper::createClient(
            $request->name,
            $request->lastname,
            $request->telephone,
            $request->email,
            isset($request->address) ? $request->address : null
        );

        if ($res->ok === false) {
            return response()->json($res, JsonResponse::BAD_REQUEST);
        }

        $user->openpay_customer_id = $res->customer_id;

        if ($user->save()) {
            return response()->json($res, JsonResponse::OK);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Algo salio mal al registrar el cliente con el motor de pago']
            ], JsonResponse::BAD_REQUEST);
        }
    }

    public function getCardsClientOpenPay(Request $request) {
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
                    'errors' => $createClient->errors
                ], JsonResponse::BAD_REQUEST);
            }

            $user->openpay_customer_id = $createClient->customer_id;
            $user->save();
        }

        $res = PaymentGatewayHelper::getClientCards(
            $user->openpay_customer_id
        );

        if ($res->ok === false) {
            return response()->json($res, JsonResponse::BAD_REQUEST);
        }

        //Quitamos info duplicada
        $collection = collect($res->cards);

        $uniques = $collection->unique(function ($item) {
            return
                    $item->card_number
                    .$item->type
                    .$item->brand
                    .$item->bank_name;
        });

        return response()->json([
            'ok' => true,
            'cards' =>  $uniques->values()->all()
        ], JsonResponse::OK);
    }

    public function deleteCardsClientOpenPay(Request $request) {
        $validate = Validator::make($request->all(), [
            'cardToken' => 'required|string'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validate->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

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
                    'errors' => $createClient->errors
                ], JsonResponse::BAD_REQUEST);
            }

            $user->openpay_customer_id = $createClient->customer_id;
            $user->save();
        }

        $res = PaymentGatewayHelper::deleteClientCard(
            $request->cardToken
        );

        if ($res->ok === false) {
            return response()->json($res, JsonResponse::BAD_REQUEST);
        }

        return response()->json($res, JsonResponse::OK);
    }

    public function saveClientCardOpenPay(Request $request) {
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
                    'errors' => $createClient->errors
                ], JsonResponse::BAD_REQUEST);
            }

            $user->openpay_customer_id = $createClient->customer_id;
            $user->save();
        }
        $validate = Validator::make($request->all(), [
            'card_number' => 'required|string|max:16',
            'holder_name' => 'required|string|max:200',
            'year' => 'required|string|max:2',
            'month' => 'required|string|max:2',
            'cvv2' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validate->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $res = PaymentGatewayHelper::saveClientCard(
            $user->openpay_customer_id,
            $request->card_number,
            $request->holder_name,
            $request->year,
            $request->month,
            $request->cvv2
        );

        if ($res->ok === false) {
            return response()->json($res, JsonResponse::BAD_REQUEST);
        }

        return response()->json($res, JsonResponse::OK);
    }

    public function getOpenPayCustomerId(Request $request) {
        $user = $request->user;

        return response()->json([
            'ok' => true,
            'data' => $user->openpay_customer_id ? $user->openpay_customer_id : null
        ], JsonResponse::OK);
    }



    private function checkRecoveryToken($token) {
        $validate = UserVerificationToken::select('token', 'type', 'foreign_id', 'audience')->where('token', '=', $token)->where('active', '=', true)->orderBy('id', 'DESC')->first();

        if (!$validate) {
            return (object) ['ok' => false, 'errors' => ['Este código ya expiró o no es válido. Intente nuevamente o genere uno nuevo']];
        }

        return (object) ['ok' => true, 'data' => $validate];
    }

    private function generateRecoveryToken($user_id, $type, $audience) {
        $token = Str::upper(Str::random(4));

        DB::beginTransaction();
        try {
            // Invalidamos token si existen
           $this->invalidateTokens($user_id, $type, $audience);

            // Creamos nuevo token
            $utoken = new UserVerificationToken();
            $utoken->foreign_id = $user_id;
            $utoken->audience = $audience;
            $utoken->token = $token;
            $utoken->date_reg = Carbon::now();
            $utoken->active = true;
            $utoken->type = $type;
            $utoken->save();

            DB::commit();

            return (object) ['ok' => true, 'token' => $token];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::debug($e);
            return (object) ['ok' => false, 'errors' => ['No fue posible generar el token de verificación']];
        }
    }

    private function invalidateTokens($user_id, $type, $audience) {
         UserVerificationToken::query()
         ->where('foreign_id', '=', $user_id)
         ->where('audience', '=', $audience)
         ->where('type', '=', $type)
         ->delete();
    }

}
