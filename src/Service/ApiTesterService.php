<?php
// src/Service/ApiTesterService.php

namespace App\Service;

use Exception;
use Stripe\Plan;
use Stripe\Stripe;
use Stripe\Product;
use Psr\Log\LoggerInterface;
use App\Entity\ApiCredential;
use App\Service\EncryptionService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service responsible for testing the validity of API credentials
 * for supported services such as Stripe (and potentially PayPal).
 *
 * This service attempts to verify if the provided API key or credentials
 * are valid by performing a lightweight API call.
 */
class ApiTesterService
{
    public function __construct(
        private EncryptionService     $encryptionService,
        private LoggerInterface       $logger,
        private HttpClientInterface   $httpClient  // fallback HTTP client, e.g., for PayPal
    ) {}

    /**
     * Tests the validity of API credentials for the given service.
     *
     * Currently supports:
     *  - Stripe
     * 
     * @param ApiCredential $credential The API credential entity containing encrypted keys and service type.
     * 
     * @return bool True if the credentials are valid and the service responds correctly; false otherwise.
     * 
     * @throws Exception If the service type is unsupported.
     */
    public function test(ApiCredential $credential): bool
    {
        $service = strtolower($credential->getService());

        if ($service === 'stripe') {
            return $this->testStripe($credential);
        }

        $this->logger->warning("Unsupported service: $service");
        throw new Exception("Unsupported service: $service");
    }

    /**
     * Tests the validity of a Stripe API key by attempting
     * to retrieve a list of products with a minimal query.
     *
     * @param ApiCredential $credential The Stripe API credential entity.
     * 
     * @return bool True if the API key is valid; false otherwise.
     */
    private function testStripe(ApiCredential $credential): bool
    {
        $secretKeyEncrypted = $credential->getSecretKeyEncrypted();
        if (!$secretKeyEncrypted) {
            $this->logger->error("Empty Stripe secret key for credential ID {$credential->getId()}");
            return false;
        }

        $secretKey = $this->encryptionService->decrypt($secretKeyEncrypted);

        if (empty($secretKey) || !str_starts_with($secretKey, 'sk_')) {
            return false;
        }

        Stripe::setApiKey($secretKey);

        try {
            $products = Product::all(['limit' => 1]);
            return isset($products->data);
        } catch (Exception $e) {
            $this->logger->error("Stripe API test error: " . $e->getMessage());
            return false;
        }
    }
}
