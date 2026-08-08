<?php

namespace NCB\Component\Gda\Site\Helper;

\defined('_JEXEC') or die;

use RuntimeException;

final class CryptoHelper
{
    private const CIPHER = 'aes-256-gcm';

    private function __construct()
    {
    }

    /**
     * Encrypts a value with the application secret key.
     *
     * Storage format: base64(json({iv,tag,value})).
     */
    public static function encrypt(string $plainText, ?string $secretKey = null): string
    {
        $secretKey = $secretKey ?? self::getSecretKey();
        $key = hash('sha256', $secretKey, true);

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if ($ivLength === false || $ivLength <= 0) {
            throw new RuntimeException('Invalid IV length for cipher: ' . self::CIPHER);
        }

        $iv = random_bytes($ivLength);
        $tag = '';

        $cipherText = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipherText === false) {
            throw new RuntimeException('Unable to encrypt value.');
        }

        $payload = [
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'value' => base64_encode($cipherText),
        ];

        return base64_encode((string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypts a value encrypted by self::encrypt().
     */
    public static function decrypt(string $cipherPayload, ?string $secretKey = null): string
    {
        $secretKey = $secretKey ?? self::getSecretKey();
        $key = hash('sha256', $secretKey, true);

        $decodedPayload = base64_decode($cipherPayload, true);

        if ($decodedPayload === false) {
            throw new RuntimeException('Invalid encrypted payload format.');
        }

        $data = json_decode($decodedPayload, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || empty($data['iv']) || empty($data['tag']) || empty($data['value'])) {
            throw new RuntimeException('Encrypted payload is missing required fields.');
        }

        $iv = base64_decode((string) $data['iv'], true);
        $tag = base64_decode((string) $data['tag'], true);
        $value = base64_decode((string) $data['value'], true);

        if ($iv === false || $tag === false || $value === false) {
            throw new RuntimeException('Encrypted payload contains invalid base64 values.');
        }

        $plainText = openssl_decrypt($value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plainText === false) {
            throw new RuntimeException('Unable to decrypt value.');
        }

        return $plainText;
    }

    /**
     * Reads encryption key from environment or .env file.
     */
    public static function getSecretKey(): string
    {
        // Try environment variable first
        $secret = getenv('GDA_SECRET_KEY');
        if (is_string($secret) && trim($secret) !== '') {
            return trim($secret);
        }

        // Fallback: read from .env file
        $envFile = JPATH_ROOT . '/.env';
        if (is_file($envFile) && is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) {
                        continue; // Skip comments
                    }
                    if (strpos($line, 'GDA_SECRET_KEY=') === 0) {
                        $secret = substr($line, strlen('GDA_SECRET_KEY='));
                        if (trim($secret) !== '') {
                            return trim($secret);
                        }
                    }
                }
            }
        }

        throw new RuntimeException('Missing GDA_SECRET_KEY in environment variable or .env file.');
    }
}
