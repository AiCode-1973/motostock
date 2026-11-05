<?php
declare(strict_types=1);

namespace App\Core;

/** Cifra segredos guardados no banco (MFA, credenciais de gateway) com a APP_KEY (libsodium). */
final class Crypto
{
    private static function key(): string
    {
        $raw = (string) Env::get('APP_KEY', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = (string) base64_decode(substr($raw, 7), true);
        }
        if (strlen($raw) < 32) {
            throw new \RuntimeException('APP_KEY ausente ou curta. Gere com: php bin/generate-key.php');
        }
        return sodium_crypto_generichash($raw, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $bin = base64_decode($encoded, true);
        if ($bin === false || strlen($bin) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
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
