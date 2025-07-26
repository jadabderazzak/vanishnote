<?php

namespace App\Controller;

use Exception;
use App\Entity\Payment;
use App\Repository\ClientRepository;
use App\Service\StripePaymentService;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ApiCredentialRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionsRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\SubscriptionPlanRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_USER")]
final class ClientSubscriptionController extends AbstractController
{
    /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}
    /**
     * Display the current authenticated user's active subscription along with all available subscription plans and the primary currency.
     *
     * This action fetches:
     * - The user's active subscription plan (where 'status' is true)
     * - All subscription plans available in the system
     * - The primary currency marked as 'isPrimary'
     *
     * It then renders the 'client_subscription/index.html.twig' template
     * passing these data as template variables.
     *
     * Route: /client/subscription
     * Method: GET
     *
     * @param SubscriptionsRepository $repoSubscription Repository for fetching subscriptions
     * @param CurrencyRepository $repoCurrency Repository for fetching currencies
     * @param SubscriptionPlanRepository $repoSubPlans Repository for fetching subscription plans
     *
     * @return Response The HTTP response with rendered template
     */
    #[Route('/client/subscription', name: 'app_client_subscription')]
    public function index(
        SubscriptionsRepository $repoSubscription, 
        CurrencyRepository $repoCurrency, 
        SubscriptionPlanRepository $repoSubPlans,
        
    ): Response
    {
        $plan = $repoSubscription->findOneBy([
            'user' => $this->getUser(),
            'status' => true
        ]);
        
        $subscriptionPlans = $repoSubPlans->findBy([
            'isActive' => true
        ]);

      
        $currency = $repoCurrency->findOneBy([
            'isPrimary' => true
        ]);
        
        return $this->render('client_subscription/index.html.twig', [
            'currentPlan' => $plan,
            'subscriptionPlans' => $subscriptionPlans,
            'currency' => $currency
        ]);
    }
    
    

    /**
     * Display a summary of the selected subscription plan before payment.
     *
     * This action is triggered when the user selects a plan to subscribe to.
     * It shows:
     * - Plan name, price, and description
     * - Primary currency
     * - A form to select the duration in months (1–12)
     *
     * Route: /client/subscription/recap/{slug}
     * Methods: GET, POST
     *
     * @param string $slug Slug of the selected subscription plan
     * @param Request $request The HTTP request object
     * @param SubscriptionPlanRepository $repoSubPlans Repository to fetch subscription plans
     * @param CurrencyRepository $repoCurrency Repository to fetch primary currency
     *
     * @return Response The HTTP response with the rendered summary view
     */
    #[Route('/client/subscription/recap/{slug}', name: 'app_client_subscription_recap', methods: ['GET', 'POST'])]
    public function recap(
        
        Request $request,
        SubscriptionPlanRepository $repoSubPlans,
        CurrencyRepository $repoCurrency,
        ClientRepository $repoClient,
    ): Response {
        $plan = $repoSubPlans->findOneBy(['slug' => $request->get('slug')]);

        if (!$plan || !$plan->isActive()) {
            $this->addFlash('warning', $this->translator->trans('The selected subscription plan is not available.'));
            return $this->redirectToRoute('app_client_subscription');
        }

        $currency = $repoCurrency->findOneBy(['isPrimary' => true]);
        $user = $this->getUser();
        $client = $repoClient->findOneBy(['user' => $user]);
       
         // Check if the plan is the enterprise plan (id = 3)
        if ($plan->getId() === 3) {
            // If client data is incomplete or not marked as company, block the subscription
            if (!$client || !$client->isCompany() || empty($client->getCompany()) || empty($client->getCompanyAdress()) || empty($client->getVatNumber())) {
                $this->addFlash('warning', $this->translator->trans('To subscribe to this enterprise plan, please complete your company information in your profile.'));
                return $this->redirectToRoute('app_clients_profile');
            }
        }

        return $this->render('client_subscription/recap.html.twig', [
            'plan' => $plan,
            'currency' => $currency,
            'client' => $client
        ]);
    }


    /**
     * Creates a Stripe Checkout session for a subscription plan purchase.
     * 
     * @param string $slug The slug identifying the subscription plan.
     * @param Request $request The HTTP request containing user input.
     * @param SubscriptionPlanRepository $planRepository Repository to fetch subscription plans.
     * @param ClientRepository $clientRepository Repository to fetch client data.
     * @param ApiCredentialRepository $apiCredentialRepository Repository to fetch API credentials.
     * @param StripePaymentService $stripePaymentService Service to create Stripe checkout sessions.
     * @param EntityManagerInterface $manager Doctrine entity manager to persist data.
     * @param CurrencyRepository $repoCurrency Repository to fetch currency information.
     * 
     * @return Response Redirects user to Stripe checkout or back with error messages.
     */
    #[Route('/checkout/{slug}', name: 'stripe_checkout')]
    public function checkout(
        string $slug,
        Request $request,
        SubscriptionPlanRepository $planRepository,
        ClientRepository $clientRepository,
        ApiCredentialRepository $apiCredentialRepository,
        StripePaymentService $stripePaymentService,
        EntityManagerInterface $manager,
        CurrencyRepository $repoCurrency
    ): Response {
        // Get currently logged-in user
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Find client entity linked to user
        $client = $clientRepository->findOneBy(['user' => $user]);
        if (!$client) {
            $this->addFlash('danger', $this->translator->trans('Client not found. Please log in.'));
            return $this->redirectToRoute('app_login');
        }

        // Find subscription plan by slug
        $plan = $planRepository->findOneBy(['slug' => $slug]);
        if (!$plan) {
            $this->addFlash('danger', $this->translator->trans('Subscription plan not found.'));
            return $this->redirectToRoute('app_client_subscription');
        }

        // Retrieve active Stripe API credentials
        $credential = $apiCredentialRepository->findOneBy(['service' => 'stripe', 'isActive' => true]);
        if (!$credential) {
            $this->addFlash('danger', $this->translator->trans('API key is missing. Please contact support.'));
            return $this->redirectToRoute('app_client_subscription');
        }

        // Get subscription duration in months from form (default 1)
        $months = (int) $request->request->get('months', 1);
        if ($months <= 0) {
            $this->addFlash('danger', $this->translator->trans('Invalid subscription duration.'));
            return $this->redirectToRoute('app_client_subscription_recap', ['slug' => $slug]);
        }

        try {
            // Fetch primary currency code
            $currency = $repoCurrency->findOneBy(['isPrimary' => true]);

            // Create new Payment entity and populate fields
            $payment = new Payment();
            $payment->setUser($user);
            $payment->setSubscriptionPlan($plan);
            $payment->setStatus('pending');
            $payment->setAmount($plan->getPrice() * $months);
            $payment->setCurrency($currency->getCode());
            $payment->setMonths($months);
            $payment->setCreatedAt(new \DateTime());
            $payment->setUpdatedAt(new \DateTime());

            // Persist payment to database to generate an ID
            $manager->persist($payment);
            $manager->flush();

            // Create Stripe checkout session with payment details
            $checkoutSession = $stripePaymentService->createCheckoutSession(
                $credential,
                $client,
                $plan,
                $months,
                $payment->getId(),
                $payment->getSlug()
            );

            // Update payment with Stripe session ID
            $payment->setStripeSessionId($checkoutSession->id);
            $manager->flush();

            // Redirect user to Stripe checkout URL
            return $this->redirect($checkoutSession->url);
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->translator->trans('An error occurred while creating the payment. Please try again later.'));
            return $this->redirectToRoute('app_client_subscription_recap', ['slug' => $slug]);
        }
    }

        /**
         * Handles successful payment completion.
         * 
         * Adds a success flash message and redirects to subscription page.
         * 
         * @return Response Redirect response.
         */
        #[Route('/payment/success', name: 'payment_success')]
        public function paymentSuccess(): Response
        {
            $this->addFlash('success', 'Payment successful! Thank you for your purchase.');
            return $this->redirectToRoute('app_client_subscription');
        }

        /**
         * Handles payment cancellation by user.
         * 
         * Adds a warning flash message and redirects to subscription page.
         * 
         * @return Response Redirect response.
         */
        #[Route('/payment/cancel', name: 'payment_cancel')]
        public function paymentCancel(): Response
        {
            $this->addFlash('warning', 'Payment cancelled. You can retry your purchase.');
            return $this->redirectToRoute('app_client_subscription');
        }

        /**
     * Display a thank you page that polls payment status.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
        #[Route('/payment/thank-you/{slug}', name: 'payment_thank_you')]
        public function thankYou(Request $request, PaymentRepository $repoPayment): Response
        {
            $payment = $repoPayment->findOneBy([
                'slug' => $request->get('slug')
            ]);

            if (!$payment) {
                throw $this->createNotFoundException('Payment not found');
            }

            return $this->render('stripe_webhook/index.html.twig', [
                'slug' => $payment->getSlug(),
                'paymentStatus' => $payment->getStatus(),
            ]);
        }
        /**
         * API endpoint to check payment status.
         *
         * @param int $paymentId
         * @param EntityManagerInterface $entityManager
         * @return Response JSON with payment status
         */
        #[Route('/payment/status/{slug}', name: 'payment_status', methods: ['GET'])]
        public function paymentStatus(Request $request, PaymentRepository $repoPayment): Response
        {
            $payment = $repoPayment->findOneBy([
                'slug' => $request->get('slug')
            ]);

            if (!$payment) {
                throw $this->createNotFoundException('Payment not found');
            }


            if (!$payment) {
                return $this->json(['error' => 'Payment not found'], 404);
            }

            return $this->json([
                'status' => $payment->getStatus(),
            ]);
        }
    


}
