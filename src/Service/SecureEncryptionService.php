<?php

namespace App\Service;

use Exception;

/**
 * Service for encrypting and decrypting data using AES-256-GCM with Additional Authenticated Data (AAD).
 */
class SecureEncryptionService
{
    private string $key;

    /**
     * @param string $key 32-byte (256-bit) encryption key, must be kept secret.
     *
     * @throws Exception if key length is invalid.
     */
     public function __construct()
    {
        // if (mb_strlen($key, '8bit') !== 32) {
        //     throw new \InvalidArgumentException("Encryption key must be exactly 32 bytes.");
        // }
        $this->key = "1e35b13eb225cd21c85230785569b1dea5f8eec7c4e7d08e6022b45ff42d56ce";
    }

    /**
     * Encrypt plaintext data with AES-256-GCM including Additional Authenticated Data (AAD).
     *
     * Format of output (base64 encoded):
     *  [12 bytes IV][ciphertext][16 bytes auth tag]
     *
     * @param string $plaintext Data to encrypt
     * @param string $aad Additional Authenticated Data (e.g. note or attachment ID)
     * @return string Base64-encoded encrypted payload including IV and tag
     *
     * @throws Exception on encryption failure
     */
    public function encrypt(string $plaintext, string $aad): string
    {
        $ivLength = 12;
        $iv = random_bytes($ivLength);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            16
        );

        if ($ciphertext === false) {
            throw new Exception("Encryption failed.");
        }

        return base64_encode($iv . $ciphertext . $tag);
    }

    /**
     * Decrypt data previously encrypted by this service with AAD.
     *
     * @param string $encrypted Base64 encoded encrypted data
     * @param string $aad Additional Authenticated Data used during encryption
     * @return string Decrypted plaintext
     *
     * @throws Exception on decryption failure or data tampering
     */
    public function decrypt(string $encrypted, string $aad): string
    {
        $data = base64_decode($encrypted, true);

        if ($data === false) {
            throw new Exception("Data is not valid base64.");
        }

        $ivLength = 12;
        $tagLength = 16;

        if (mb_strlen($data, '8bit') < ($ivLength + $tagLength)) {
            throw new Exception("Encrypted data is too short.");
        }

        $iv = mb_substr($data, 0, $ivLength, '8bit');
        $tag = mb_substr($data, -$tagLength, null, '8bit');
        $ciphertext = mb_substr($data, $ivLength, mb_strlen($data, '8bit') - $ivLength - $tagLength, '8bit');

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new Exception("Decryption failed or data integrity check failed.");
        }

        return $plaintext;
    }
}
