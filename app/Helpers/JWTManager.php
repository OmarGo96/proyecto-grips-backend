<?php

namespace App\Helpers;

use Carbon\Carbon;
use \Firebase\JWT\JWT;

class JWTManager
{
    public static function createJwt($id, $aud, $exp = null) {
        $jwtSecret = config('app.jwt-key');
        $cryptSecret = config('app.crypt-key');
        $encrypt = new Encrypt($cryptSecret);

        $payload = array(
            'sub' => $encrypt->encrypt($id),
            'aud' => $encrypt->encrypt($aud),
            'iat' => Carbon::now()->unix(),
            'nbf' => Carbon::now()->unix(),
            'exp' => ($exp === null)  ? null : $exp
        );

        $jwt = JWT::encode($payload, $jwtSecret, 'HS256');
        if (isset($jwt)) {
            return $jwt;
        }
        return null;
    }

    public static function validateJWT($jwt) {
        $jwtSecret = config('app.jwt-key');
        $cryptSecret = config('app.crypt-key');
        $encrypt = new Encrypt($cryptSecret);


        try {
            $jwt = JWT::decode($jwt, $jwtSecret, array('HS256'));
        } catch(\UnexpectedValueException $e){
			return false;
		} catch(\DomainException $e){
			return false;
		}

        if (isset($jwt) && is_object($jwt) && isset($jwt->sub) && isset($jwt->aud)) {
            $jwt->sub = $encrypt->decrypt($jwt->sub);
            $jwt->aud = $encrypt->decrypt($jwt->aud);
            return $jwt;
        } else {
            return false;
        }
    }
}
