<?php

namespace App\Controller;

use Stripe\Webhook;
use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\ApiCredential;
use App\Entity\Subscriptions;
use App\Entity\SubscriptionPlan;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ApiCredentialRepository;
use App\Repository\PaymentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller to handle Stripe webhook events.
 * 
 * This controller verifies the webhook signature,
 * processes checkout session completions and payment failures,
 * and updates the local database accordingly.
 */
class StripeWebhookController extends AbstractController
{
    /**
     * Stripe webhook secret used to verify event signatures.
     * It is loaded from encrypted API credentials.
     */
    private string $endpointSecret = "";

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EncryptionService $encrypt,
        private ApiCredentialRepository $apiCredentialRepository,
        private PaymentRepository $repoPayment
    ) {
        // Constructor to inject dependencies
    }

    /**
     * Stripe webhook endpoint to receive event notifications.
     * 
     * @param Request $request HTTP request containing the webhook payload and headers
     * 
     * @return Response HTTP response indicating success or failure of processing
     */
    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        // Retrieve the Stripe API credentials for webhook secret
        $api = $this->apiCredentialRepository->findOneBy(['service' => 'stripe']);

        if (!$api) {
            return new Response('Stripe API credentials not found', Response::HTTP_BAD_REQUEST);
        }

        // Decrypt the webhook secret from the stored encrypted data
        $this->endpointSecret = $this->encrypt->decrypt($api->getWebhookSecretEncrypted());

        try {
            // Verify and construct the Stripe event from payload and signature
            $event = Webhook::constructEvent($payload, $sigHeader, $this->endpointSecret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            // Handle event types accordingly
            switch ($event->type) {
                case 'checkout.session.completed':
                    return $this->handleCheckoutSessionCompleted($event->data->object);

                case 'payment_intent.payment_failed':
                    return $this->handlePaymentIntentFailed($event->data->object);

                default:
                    // Other event types can be handled here or ignored
                    return new Response('Event type not handled', Response::HTTP_OK);
            }
        } catch (\Throwable $e) {
            // General catch for any processing errors
            return new Response('Webhook processing error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle the 'checkout.session.completed' event from Stripe.
     * 
     * This event indicates a successful checkout payment.
     * 
     * @param object $session Stripe checkout session object
     * 
     * @return Response HTTP response indicating the processing result
     */
    private function handleCheckoutSessionCompleted(object $session): Response
    {
        $stripeSessionId = $session->id;

        // Find payment entity by Stripe session ID
        $payment = $this->entityManager->getRepository(Payment::class)->findOneBy(['stripeSessionId' => $stripeSessionId]);

        if (!$payment) {
            // Payment not found, return 200 to prevent retries
            return new Response('Payment not found', Response::HTTP_OK);
        }

        if ($payment->getStatus() === 'succeeded') {
            // Payment already processed
            return new Response('Payment already processed', Response::HTTP_OK);
        }

        $user = $payment->getUser();
        $plan = $payment->getSubscriptionPlan();
        $months = $payment->getMonths();

        $subscription = $this->entityManager->getRepository(Subscriptions::class)->findOneBy(['user' => $user]);

        if (!$user || !$plan || !$subscription) {
            // Required data missing, return 200 to avoid webhook retry
            return new Response('Invalid payment data', Response::HTTP_OK);
        }

        // Begin a database transaction to ensure consistency
        $this->entityManager->beginTransaction();

        try {
            /**
             * Retrieves the latest succeeded invoice ID and increments it for the current payment.
             *
             * If a succeeded invoice already exists, the new invoice ID will be set as the last one + 1.
             */
            $lastId = $this->repoPayment->findLastSucceededInvoiceId();
            $payment->setInvoiceId($lastId + 1);
            // Update payment status to succeeded
            $payment->setStatus('succeeded');
            $payment->setUpdatedAt(new \DateTime());

            // Update or create subscription plan data
            $now = new \DateTime();
            $subscription->setSubscriptionPlan($plan);
            $subscription->setStartedAt($now);

            // Extend subscription end date by the number of months purchased
            if ($subscription->getEndsAt() && $subscription->getEndsAt() > $now) {
                $endsAt = (clone $subscription->getEndsAt())->modify("+$months months");
            } else {
                $endsAt = (clone $now)->modify("+$months months");
            }
            $subscription->setEndsAt($endsAt);

            // Persist all changes
            $this->entityManager->flush();
            $this->entityManager->commit();

            return new Response('Payment processed successfully', Response::HTTP_OK);
        } catch (\Throwable $e) {
            // Rollback transaction on error
            $this->entityManager->rollback();
            return new Response('Transaction failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle the 'payment_intent.payment_failed' event from Stripe.
     * 
     * This event indicates a failed payment attempt.
     * 
     * @param object $paymentIntent Stripe payment intent object
     * 
     * @return Response HTTP response indicating the processing result
     */
    private function handlePaymentIntentFailed(object $paymentIntent): Response
    {
        $paymentIntentId = $paymentIntent->id;
        $metadata = $paymentIntent->metadata ?? null;

        // Check metadata for internal payment ID reference
        if (!$metadata || !isset($metadata->internal_payment_id)) {
            // Missing internal payment id in metadata - ignore event gracefully
            return new Response('Missing internal payment ID in metadata', Response::HTTP_OK);
        }

        $paymentId = $metadata->internal_payment_id;

        // Retrieve the payment entity by internal payment ID
        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            // Payment record not found - ignore event to prevent retry
            return new Response('Payment not found', Response::HTTP_OK);
        }

        $this->entityManager->getConnection()->beginTransaction();

        try {
            // Update payment with Stripe payment intent ID and mark as failed
            $payment->setStripePaymentIntentId($paymentIntentId);
            $payment->setStatus('failed');
            $payment->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $this->entityManager->getConnection()->commit();

            return new Response('Payment failure processed', Response::HTTP_OK);
        } catch (\Throwable $e) {
            // Rollback on failure
            $this->entityManager->getConnection()->rollBack();
            return new Response('Failed to process payment failure', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
