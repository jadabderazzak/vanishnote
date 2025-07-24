<?php

namespace App\Controller;

use App\Repository\CurrencyRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionsRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_USER")]
final class ClientSubscriptionController extends AbstractController
{
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
        SubscriptionPlanRepository $repoSubPlans
    ): Response
    {
        $plan = $repoSubscription->findOneBy([
            'user' => $this->getUser(),
            'status' => true
        ]);
        
        $subscriptionPlans = $repoSubPlans->findBy([]);
       
        $currency = $repoCurrency->findOneBy([
            'isPrimary' => true
        ]);
        
        return $this->render('client_subscription/index.html.twig', [
            'currentPlan' => $plan,
            'subscriptionPlans' => $subscriptionPlans,
            'currency' => $currency
        ]);
    }
}
