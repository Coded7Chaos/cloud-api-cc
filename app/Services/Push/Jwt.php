<?php

namespace App\Services\Push;

use RuntimeException;

/**
 * Firma de JWT con ext-openssl, sin librerías de terceros.
 *
 * Es lo único de criptografía que necesitan los dos canales nativos:
 * APNs pide un token ES256 firmado con la clave .p8 de Apple, y FCM pide un
 * assertion RS256 firmado con la service account de Google para canjearlo por
 * un access token OAuth2.
 */
final class Jwt
{
    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $claims
     * @param  string  $privateKey  Clave en PEM.
     */
    public static function sign(array $header, array $claims, string $privateKey, string $algorithm): string
    {
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new RuntimeException('La clave privada de push no es un PEM válido.');
        }

        $input = self::encode($header).'.'.self::encode($claims);
        $signature = '';

        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el JWT de push.');
        }

        // ES256 en JWS usa la firma "cruda" R||S de 64 bytes, pero openssl_sign
        // devuelve ECDSA en DER. RS256 sí sale ya en el formato final.
        if ($algorithm === 'ES256') {
            $signature = self::derToRaw($signature);
        }

        return $input.'.'.self::base64url($signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        return self::base64url((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * DER (SEQUENCE de dos INTEGER) -> R||S de 64 bytes.
     *
     * Cada INTEGER de DER viene con signo, así que puede traer un 0x00 delante
     * para no leerse como negativo, o medir menos de 32 bytes si sobran ceros
     * a la izquierda. Se normalizan los dos a 32 bytes exactos.
     */
    private static function derToRaw(string $der): string
    {
        $offset = 0;

        if (($der[$offset++] ?? '') !== "\x30") {
            throw new RuntimeException('Firma ECDSA con formato DER inesperado.');
        }

        $length = ord($der[$offset++]);

        if ($length & 0x80) {
            $offset += $length & 0x7F;
        }

        $parts = [];

        for ($i = 0; $i < 2; $i++) {
            if (($der[$offset++] ?? '') !== "\x02") {
                throw new RuntimeException('Firma ECDSA con formato DER inesperado.');
            }

            $size = ord($der[$offset++]);
            $value = ltrim(substr($der, $offset, $size), "\x00");
            $offset += $size;

            $parts[] = str_pad($value, 32, "\x00", STR_PAD_LEFT);
        }

        return $parts[0].$parts[1];
    }
}
