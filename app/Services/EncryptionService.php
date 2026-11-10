<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;

class EncryptionService
{
    /**
     * Encrypt a string securely.
     *
     * @param  string  $value
     * @return string
     */
    public static function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a string securely.
     *
     * @param  string  $encryptedValue
     * @return string|null
     */
    public static function decrypt(string $encryptedValue): ?string
    {
        try {
            return Crypt::decryptString($encryptedValue);
        } catch (DecryptException $e) {
            // Log the error and return null if decryption fails
            Log::error('Decryption failed: '.$e->getMessage());
            return null;
        }
    }


    public static function do_encrypt($str)
    {
        $enc_key = env('APP_KEY');
        $key64 = base64_encode(substr(hash('sha256', $enc_key, true), 0, 32));
        $iv64 = "AAECAwQFBgcICQoLDA0ODw==";

        $key = base64_decode($key64, true);
        $iv = base64_decode($iv64, true);

        $encrypted = openssl_encrypt($str, 'aes-256-cbc', $key, 0, $iv);
        $encrypted = strtr($encrypted, '+/=', '-_~');

        return $encrypted;
    }

    public static function do_decrypt($str)
    {
        $enc_key = env('APP_KEY');
        $str = strtr($str, '-_~', '+/=');
        $key64 = base64_encode(substr(hash('sha256', $enc_key, true), 0, 32));
        $iv64 = "AAECAwQFBgcICQoLDA0ODw==";

        $key = base64_decode($key64, true);
        $iv = base64_decode($iv64, true);

        $decrypted = openssl_decrypt($str, 'aes-256-cbc', $key, 0, $iv);

        return $decrypted;
    }
}
