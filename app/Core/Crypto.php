<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cifra segredos guardados no banco (MFA, credenciais de gateway) com AES-256-GCM e a APP_KEY.
 * OpenSSL está presente em praticamente toda hospedagem PHP (libsodium nem sempre vem ativa).
 * Formato: base64("v1" . iv[12] . tag[16] . cifra).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $raw = (string) Env::get('APP_KEY', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = (string) base64_decode(substr($raw, 7), true);
        }
        if (strlen($raw) < 32) {
            throw new \RuntimeException('APP_KEY ausente ou curta. Gere com: php bin/generate-key.php');
        }
        return hash('sha256', 'motostock-enc|' . $raw, true);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'motostock', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Falha ao cifrar.');
        }
        return base64_encode('v1' . $iv . $tag . $cipher);
    }

    public static function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $bin = base64_decode($encoded, true);
        if ($bin === false || strlen($bin) < 31 || !str_starts_with($bin, 'v1')) {
            return null;
        }
        $plain = openssl_decrypt(substr($bin, 30), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($bin, 2, 12), substr($bin, 14, 16), 'motostock');
        return $plain === false ? null : $plain;
    }

    /** Segredo derivado para assinaturas internas (ex.: gateway simulado). */
    public static function derive(string $context): string
    {
        return hash_hmac('sha256', $context, self::key());
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
