<?php

namespace App\Http\Middleware;

use App\Enums\JsonResponse;
use App\Helpers\JWTManager;
use App\Models\Operadores;
use App\Models\User;
use App\Models\UserAdmin;
use Closure;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;

class VerifyJWT
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        // Validamos que existe el header Authorization
        if (!$request->header('Authorization')) {
            return response()->json([
                'ok' => false,
                'errors' => ['Acceso denegado, no se pudo obtener el JWT']
            ], JsonResponse::UNAUTHORIZED);
        }

        // validamos jwt
        $tokenData = $request->header('Authorization');

        $jwtVal = JWTManager::validateJWT($tokenData);

        if ($jwtVal === false) {
            return response()->json([
                'ok' => false,
                'errors' => ['Acceso denegado, error al verificar JWT']
            ], JsonResponse::UNAUTHORIZED);
        }
        // revisamos aud
        if (!isset($jwtVal->aud)) {
            return response()->json([
                'ok' => false,
                'errors' => ['Acceso denegado, Endpoint Incorrecto']
            ], JsonResponse::UNAUTHORIZED);
        }
        $audience = null;
        switch($jwtVal->aud) {
            case 'partners':
                $userData = User::where('id', '=', $jwtVal->sub)->where('active', '=', true)->first();
                $audience = 1;
            break;
            case 'operators':
                $userData = Operadores::where('id', '=', $jwtVal->sub)->where('active', '=', true)->first();
                $userData->load('res_users');
                if (!isset($userData->res_users)) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['Acceso denegado, el operador no esta ligado a un usuario']
                    ], JsonResponse::UNAUTHORIZED);
                }
                $audience = 2;
            break;
            case 'admin':
                $userData = UserAdmin::where('id', '=', $jwtVal->sub)->where('active', '=', true)->first();
                $audience = 3;
            break;
            default:
                return response()->json([
                    'ok' => false,
                    'errors' => ['Acceso denegado, no se pudo determinar la audiencia']
                ], JsonResponse::UNAUTHORIZED);
            break;
        }

        if(!$userData) {
            return response()->json([
                'ok' => false,
                'errors' => ['Acceso denegado, usuario no encontrado']
            ], JsonResponse::UNAUTHORIZED);
        }

        $request->user = $userData;
        $request->audience = $audience;

        return $next($request);
    }
}
