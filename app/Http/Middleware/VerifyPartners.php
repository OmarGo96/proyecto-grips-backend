<?php

namespace App\Http\Middleware;

use App\Enums\JsonResponse;
use Closure;
use Illuminate\Http\Request;

class VerifyPartners
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
        if (!$request->audience) {
            return response()->json([
                'ok' => false,
                'errors' => ['Acceso denegado, no se pudo determinar la audiencia']
            ], JsonResponse::UNAUTHORIZED);
        }
        if ($request->audience === 1) {
            return $next($request);
        } else {
            return response()->json([
                'ok' => false,
                'errors' => ['Acceso denegado, Endpoint Incorrecto']
            ], JsonResponse::UNAUTHORIZED);
        }
    }
}
