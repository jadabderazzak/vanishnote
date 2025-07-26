<?php

// src/Service/StripePaymentService.php

namespace App\Service;

use Stripe\Stripe;
use App\Entity\Client;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use App\Entity\ApiCredential;
use App\Entity\SubscriptionPlan;
use App\Repository\CurrencyRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service responsible for handling Stripe payment operations,
 * specifically creating Stripe Checkout sessions.
 */
class StripePaymentService
{
    public function __construct(
        private EncryptionService $encryptionService,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
        private readonly CurrencyRepository $currencyRepository,
    ) {}

    /**
     * Creates a Stripe Checkout session for a subscription payment.
     *
     * This method decrypts the Stripe secret key, sets the Stripe API key,
     * prepares the session data including product info, amount, and metadata,
     * and generates success/cancel URLs.
     *
     * @param ApiCredential    $credential The API credential containing encrypted Stripe secret key.
     * @param Client           $client     The client initiating the purchase.
     * @param SubscriptionPlan $plan       The subscription plan being purchased.
     * @param int              $months     Number of months for the subscription.
     * @param int              $paymentId  Internal payment entity ID to track the payment.
     *
     * @return Session The created Stripe Checkout session object.
     *
     * @throws \RuntimeException If the secret key is missing or invalid.
     * @throws \Exception        If Stripe session creation fails.
     */
    public function createCheckoutSession(
        ApiCredential $credential,
        Client $client,
        SubscriptionPlan $plan,
        int $months,
        int $paymentId,
        string $slug
    ): Session {
        // Decrypt the encrypted Stripe secret key
        $secretKey = $this->encryptionService->decrypt($credential->getSecretKeyEncrypted());

        // Retrieve primary currency (e.g. USD, EUR) for the payment
        $primaryCurrency = $this->currencyRepository->findOneBy(['isPrimary' => true]);

        // Validate the Stripe secret key format
        if (empty($secretKey) || !str_starts_with($secretKey, 'sk_')) {
            throw new \RuntimeException('Invalid or missing Stripe secret key.');
        }

        // Configure Stripe PHP SDK with the secret key
        Stripe::setApiKey($secretKey);

        // Calculate total amount in the smallest currency unit (cents)
        $amount = $plan->getPrice() * $months;

        try {
            // Create a Stripe Checkout session with payment details
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $primaryCurrency->getCode(),
                        'unit_amount' => (int)($amount * 100), // convert to cents
                        'product_data' => [
                            'name' => $plan->getName(),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $client->getUser()->getEmail(),

                // URLs to redirect after success or cancellation
                'success_url' => $this->urlGenerator->generate('payment_thank_you', [
                    'slug' => $slug,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->urlGenerator->generate('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),

                // Metadata to track internal references in Stripe dashboard and webhooks
                'payment_intent_data' => [
                    'metadata' => [
                        'internal_payment_id' => $paymentId,
                        'client_id' => $client->getId(),
                        'plan_id' => $plan->getId(),
                        'months' => $months,
                    ],
                ],

                // Expand payment_intent in response for more details if needed
                'expand' => ['payment_intent'],
            ]);

            return $session;
        } catch (\Exception $e) {
            // Log error and rethrow to be handled upstream
            $this->logger->error('Stripe checkout session creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
