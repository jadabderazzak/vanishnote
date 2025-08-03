<?php

namespace App\Controller;

use App\Enum\LogLevel;
use App\Repository\UserRepository;
use App\Repository\ClientRepository;
use App\Repository\PaymentRepository;
use App\Repository\CurrencyRepository;
use App\Repository\SystemLogRepository;
use App\Service\SystemLoggerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Admin dashboard controller handling administrative functions and statistics display.
 */
#[IsGranted("ROLE_ADMIN")]
final class AdminController extends AbstractController
{
    /**
     * @param TranslatorInterface $translator Interface translation service
     * @param SystemLoggerService $logger System logging service
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

    /**
     * Displays the admin dashboard with recent payments and statistics.
     * 
     * @param PaymentRepository $repoPayment Payment data repository
     * @param ClientRepository $repoClient Client data repository
     * @param CurrencyRepository $repoCurrency Currency data repository
     * @param UserRepository $repoUser User data repository
     * 
     * @return Response Rendered dashboard view
     */
    #[Route('/admin', name: 'app_admin')]
    public function index(
        PaymentRepository $repoPayment,
        ClientRepository $repoClient,
        CurrencyRepository $repoCurrency,
        UserRepository $repoUser,
        SystemLogRepository $repoSystemLog
    ): Response {
        $defaultCurrencySymbol = '';
        $last5payments = [];
        
        try {
            // Process last 5 payments
            foreach ($repoPayment->findLastFivePayments() as $payment) {
                try {
                    $client = $repoClient->findOneBy(['user' => $payment->getUser()]);
                    
                    $last5payments[] = [
                        'id' => $payment->getId(),
                        'slug' => $payment->getSlug(),
                        'amount' => $payment->getAmount(),
                        'createdAt' => $payment->getCreatedAt(),
                        'clientName' => $client?->getName() ?? 'Unknown Client',
                        'currency' => $payment->getCurrency(),
                        'client_slug' => $client?->getSlug() ?? '#',
                    ];
                } catch (\Exception $e) {
                    $this->logger->log(LogLevel::WARNING, 'Payment processing skipped: ' . $e->getMessage());
                }
            }

            // Get primary currency symbol
            $currency = $repoCurrency->findOneBy(['isPrimary' => true]);
            $defaultCurrencySymbol = $currency?->getSymbol() ?? '';

        } catch (\Exception $e) {
            $this->logger->log(LogLevel::ERROR, 'Dashboard initialization failed: ' . $e->getMessage());
            $this->addFlash('error', 'Could not load all dashboard data. Showing partial results.');
        }

        // Get statistics (with individual error handling)
        try {
            $weeklyPaymentStats = $repoPayment->getWeeklyAmountAndPercentageGain();
        } catch (\Exception $e) {
            $weeklyPaymentStats = null;
            $this->logger->log(LogLevel::ERROR, 'Weekly stats unavailable: ' . $e->getMessage());
        }

        try {
            $monthlyPaymentStats = $repoPayment->getMonthlyAmountAndPercentageGain();
        } catch (\Exception $e) {
            $monthlyPaymentStats = null;
            $this->logger->log(LogLevel::ERROR, 'Monthly stats unavailable: ' . $e->getMessage());
        }

        try {
            $last5Clients = $repoClient->findLatestClients(5);
        } catch (\Exception $e) {
            $last5Clients = [];
            $this->logger->log(LogLevel::ERROR, 'Client list unavailable: ' . $e->getMessage());
        }

         try {
            $systemLogs = $repoSystemLog->findFiveMostRecentLogs();
         } catch (\Exception $e) {
            $systemLogs = [];
            $this->logger->log(LogLevel::ERROR, 'System Logs list unavailable: ' . $e->getMessage());
        }

        return $this->render('admin/index.html.twig', [
            'payments' => $last5payments,
            'weeklyPaymentStats' => $weeklyPaymentStats ?? null,
            'currency' => $defaultCurrencySymbol,
            'monthlyPaymentStats' => $monthlyPaymentStats ?? null,
            'weeklyUserInscription' => $this->safeGetUserStats($repoUser, 'weekly'),
            'monthlyUserInscription' => $this->safeGetUserStats($repoUser, 'monthly'),
            'last5Clients' => $last5Clients,
            'totalRevenue' => $this->safeGetTotalRevenue($repoPayment),
            'systemLogs' => $systemLogs
        ]);
    }

    /**
     * Safely retrieves user registration statistics.
     */
    private function safeGetUserStats(UserRepository $repo, string $period): ?array 
    {
        try {
            return match($period) {
                'weekly' => $repo->getWeeklyUserRegistrationsAndPercentageGain(),
                'monthly' => $repo->getMonthlyUserRegistrationsAndPercentageGain(),
                default => null
            };
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::ERROR, "User {$period} stats failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Safely retrieves total revenue.
     */
    private function safeGetTotalRevenue(PaymentRepository $repo): float 
    {
        try {
            return $repo->getTotalRevenue() ?? 0.0;
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::ERROR, 'Revenue calculation failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
 * Provides JSON data for dashboard statistics charts.
 * 
 * @param Request $request Incoming HTTP request
 * @param PaymentRepository $paymentRepository Payment data repository
 * 
 * @return JsonResponse Chart data in JSON format
 */
#[Route('/admin/dashboard/statistics', name: 'admin_dashboard_statistics', methods: ['GET'])]
public function statistics(Request $request, PaymentRepository $paymentRepository): JsonResponse
{
    try {
        $year = (int) $request->get('year', date('Y'));
        $monthlyPayments = $paymentRepository->getMonthlyPaymentsByYear($year);

        // English month names as keys (from database)
        $monthKeys = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        // Translated month names for display
        $translatedMonths = array_map(
            fn($month) => $this->translator->trans($month),
            $monthKeys
        );

        // Map payments to correct order (0..11)
        $paymentsOrdered = array_map(
            fn($month) => $monthlyPayments[$month] ?? 0,
            $monthKeys
        );

        return $this->json([
            'labels' => $translatedMonths,
            'datasets' => [[
                'label' => $this->translator->trans('Payments'),
                'data' => $paymentsOrdered
            ]]
        ]);

    } catch (\Exception $e) {
        $this->logger->log(LogLevel::ERROR, 'Statistics API failed: ' . $e->getMessage());
        return $this->json([
            'error' => $this->translator->trans('Payment statistics are currently unavailable')
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
}