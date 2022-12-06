<?php

namespace App\Helpers;

class Encrypt
{
    private $algorithm = 'AES-128-CBC'; // Algoritmo de operación AES
    private $key; // variable para almacenar la llave
    private $iv; // variable para el vector de incialización


   public function __construct($secret)
   {
       // Verificamos que exista la clave secreta y que sea de tipo string
       if (isset($secret) && !is_string($secret)) {
           throw new InvalidArgumentException(
               'Cryptr: it is mandatory to attach a value that works as a key or password to encrypt'
           );
       }

       $this->key = base64_decode($secret); // asigamos clave para encriptar y desencriptar
       $this->iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->algorithm)); // asignamos el tamaño del vector dependiendo del algoritmo
   }

     // Método para encriptar
     public function encrypt($data) {
        // Generar texto cifrado actual a partir de datos utilizando la clave y iv. Devolviendolo en formato binario.
        $ciphertext_raw = openssl_encrypt($data, $this->algorithm, $this->key, OPENSSL_RAW_DATA, $this->iv);
        // retornamos en base64
        return base64_encode($this->iv.$ciphertext_raw);
    }

    public function decrypt($data) {
        // decodificamos datos recibidos en base64
        $string = base64_decode($data);

        // obtenemos el tamaño del vector
        $sizeText =  strlen($this->iv);

        // desencriptamos texto y guardamos en variable
        $decryptText = openssl_decrypt($string, $this->algorithm, $this->key, OPENSSL_RAW_DATA, $this->iv);
        // retornamos texto desencriptado ignorando los caracteres que le corresponden al tamaño del vector IV
        return substr($decryptText, $sizeText);
    }

    /**
     * @description This function creates a passlib-compatible pbkdf2 hash result. Parameters are:
     * @param algo        - one of the algorithms supported by the php `hash_pbkdf2()` function
     * @param password    - the password to hash, `hash_pbkdf2()` format
     * @param salt        - a random string in ascii format
     * @param iterations  - the number of iterations to use
     */
    public static function create_passlib_pbkdf2($algo, $password, $salt, $iterations) {
        $hash = hash_pbkdf2($algo, $password, base64_decode(str_replace(".", "+", $salt)), $iterations, 64, true);
        return sprintf("\$pbkdf2-%s\$%d\$%s\$%s", $algo, $iterations, $salt, str_replace("+", ".", rtrim(base64_encode($hash), '=')));
    }

    /**
     * @description This function verifies a python passlib-format pbkdf2 hash against a password, returning true if they match
     * @important only ascii format password are supported.
     * @param password      - the string password
     * @param passlib_hash  - the hashed password in pbkdf2
     * @return bool
     */
    public static function verify_passlib_pbkdf2($password, $passlib_hash)
    {
        if (empty($password) || empty($passlib_hash)) return false;

        $parts = explode('$', $passlib_hash);
        if (!array_key_exists(4, $parts)) return false;

        /**
         * Results in:
         * (
             [0] =>
             [1] => pbkdf2-sha512
             [2] => 20000
             [3] => AGzdiek7yUzJ9iorZD6dBPdy
             [4] => 0298be2be9f2a84d2fcc56d8c88419f0819c3501e5434175cad3d8c44087866e7a42a3bd170a035108e18b1e296bb44f0a188f7862b3c005c5971b7b49df22ce
          *)
         */
        $t = explode('-', $parts[1]);
        if (!array_key_exists(1, $t)) return false;

        $algo = $t[1];
        $iterations = (int) $parts[2];
        $salt = $parts[3];
        $orghash = $parts[4];
        //dd($t);

        $hash = self::create_passlib_pbkdf2($algo, $password, $salt, $iterations);
        return $passlib_hash === $hash;
    }
}
