<?php


namespace App\Service;

class EncryptionService
{
    private string $key;

    public function __construct()
    {
        // La clé doit être de la bonne taille (ex: 32 bytes pour AES-256)
        $this->key = "K8d9jN2fR5mX7vTb4QwZyH3pLsEjUaF0";
    }

    public function encrypt(string $data): string
    {
        $iv = random_bytes(16); // 16 bytes pour AES-256-CBC
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, 0, $iv);
    }
}
