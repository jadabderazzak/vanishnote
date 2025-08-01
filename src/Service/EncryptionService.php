<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service providing AES-256-CBC encryption and decryption.
 * 
 * Uses a fixed key and random initialization vector (IV) for encryption.
 * Encrypted data is base64-encoded with the IV prepended.
 */
class EncryptionService
{
    /**
     * @var string The secret encryption key (32 bytes for AES-256).
     */
    private readonly string $key;

    /**
     * Constructor.
     *
     * Initializes the encryption key.
     * Note: The key length must match the cipher requirements (32 bytes for AES-256).
     */
    public function __construct(ParameterBagInterface $params)
    {
        // The key must be the correct size (e.g., 32 bytes for AES-256)
        $this->key = $_ENV['APP_ENCRYPTION_KEY'] ?? '';
    }

    /**
     * Encrypts data using AES-256-CBC.
     *
     * Generates a random 16-byte initialization vector (IV) for each encryption.
     * The IV is prepended to the encrypted data and the result is base64-encoded.
     *
     * @param string $data The plaintext data to encrypt.
     * @return string The base64-encoded encrypted string with the IV prepended.
     */
    public function encrypt(string $data): string
    {
        $iv = random_bytes(16); // 16 bytes pour AES-256-CBC
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts data encrypted with this service.
     *
     * Expects base64-encoded string with 16-byte IV prepended.
     *
     * @param string $data The base64-encoded encrypted data with IV.
     * @return string The decrypted plaintext.
     */
    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, 0, $iv);
    }
}
