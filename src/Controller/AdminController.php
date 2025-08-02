<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Repository\PaymentRepository;
use App\Repository\CurrencyRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
final class AdminController extends AbstractController
{
     /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}
    #[Route('/admin', name: 'app_admin')]
    public function index(
        PaymentRepository $repoPayment, 
        ClientRepository $repoClient, 
        CurrencyRepository $repoCurrency,
        UserRepository $repoUser
        ): Response
    {
        /******************************** Return Last 5 payment with owners  */
        $Alllast5payments = $repoPayment->findLastFivePayments();
        $last5payments = [];
        foreach ($Alllast5payments as $payment) {
         
            try {
                $user = $payment->getUSer();
                $client = $repoClient->findOneBy([
                    'user' => $user
                ]);
                $last5payments[] = [
                    'id' => $payment->getId(),
                    'slug' => $payment->getSlug(),
                    'amount' => $payment->getAmount(),
                    'createdAt' => $payment->getCreatedAt(),
                    'clientName' => $client->getName(),
                    'currency' => $payment->getCurrency(),
                    'client_slug' => $client->getSlug(),
                  
                    
                ];
            } catch (\Exception $e) {
               
                continue;
            }
        }
        /*************** Get Currency  */
         $currency = $repoCurrency->findOneBy([
            'isPrimary' => true
        ]);

        /********************* Stats  */
        // Payment Stats
        $weeklyPaymentStats = $repoPayment->getWeeklyAmountAndPercentageGain();
        $monthlyPaymentStats = $repoPayment->getMonthlyAmountAndPercentageGain();
        // Users Stats 
        $weeklyUserInscription = $repoUser->getWeeklyUserRegistrationsAndPercentageGain();
        $monthlyUserInscription = $repoUser->getMonthlyUserRegistrationsAndPercentageGain();
        //  5 last Clients
        $last5Clients = $repoClient->findLatestClients(5);
        // Total Revenue 
        $totalRevenue = $repoPayment->getTotalRevenue();
        return $this->render('admin/index.html.twig', [
            'payments' => $last5payments,
            'weeklyPaymentStats' => $weeklyPaymentStats,
            'currency' => $currency->getSymbol(),
            'monthlyPaymentStats' => $monthlyPaymentStats,
            'weeklyUserInscription' => $weeklyUserInscription,
            'monthlyUserInscription' => $monthlyUserInscription,
            'last5Clients' => $last5Clients,
            'totalRevenue' => $totalRevenue,
         
        ]);
    }
    

  #[Route('/admin/dashboard/statistics', name: 'admin_dashboard_statistics', methods: ['GET'])]
public function statistics(Request $request, PaymentRepository $paymentRepository): JsonResponse
{
    $year = (int) $request->get('year', date('Y'));

    // Récupère les paiements mensuels sous la forme ['January' => 0, 'February' => 10.5, ...]
    $monthlyPayments = $paymentRepository->getMonthlyPaymentsByYear($year);

    // Traduit les mois (avec le traducteur injecté dans ta classe, par ex via constructeur)
    $months = [
        $this->translator->trans('January'),
        $this->translator->trans('February'),
        $this->translator->trans('March'),
        $this->translator->trans('April'),
        $this->translator->trans('May'),
        $this->translator->trans('June'),
        $this->translator->trans('July'),
        $this->translator->trans('August'),
        $this->translator->trans('September'),
        $this->translator->trans('October'),
        $this->translator->trans('November'),
        $this->translator->trans('December'),
    ];

    // IMPORTANT : Les clés de $monthlyPayments sont en anglais (ex: "January").
    // On doit reconstituer un tableau de valeurs dans l'ordre des mois (index 0..11)
    $paymentsOrdered = [];
    $monthKeys = array_keys($monthlyPayments); // ex ['January', 'February', ...]
    $monthlyPaymentsLower = array_change_key_case($monthlyPayments, CASE_LOWER);

    foreach (['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'] as $monthLower) {
        // On récupère la valeur correspondant au mois (0 si absent)
        $paymentsOrdered[] = $monthlyPaymentsLower[$monthLower] ?? 0;
    }

    $response = [
        'labels' => $months,       // Mois traduits, dans l'ordre Jan, Feb, ...
        'datasets' => [
            [
                'label' => 'Payments',
                'data' => $paymentsOrdered,
            ],
            [
                // Deuxième dataset vide pour le JS (ou supprimer dans JS si pas utile)
                'label' => 'Dataset 2',
                'data' => array_fill(0, 12, 0),
            ],
        ],
    ];

    return $this->json($response);
}

        
}
