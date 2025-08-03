<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Enum\LogLevel;
use App\Repository\ClientRepository;
use App\Service\PdfGeneratorService;
use App\Service\SystemLoggerService;
use App\Repository\PaymentRepository;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\AdminEntrepriseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
final class AdminPaymentController extends AbstractController
{
    /**
     * Translator service for translating user-facing messages.
     * Logger service to log errors or warnings.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

   /**
 * Displays a paginated list of payments with related client information.
 *
 * This method:
 * - Retrieves all payments from the repository.
 * - Preloads clients related to the payment users in bulk to reduce database queries.
 * - Iterates through each payment to prepare an array of payment details including client data.
 * - Handles any exceptions by logging errors and skipping problematic entries.
 * - Paginates the final payments data array.
 * - Renders the 'admin_payment/index.html.twig' template with the paginated payments.
 *
 * @param PaginatorInterface $paginator Service to paginate the payments list
 * @param ClientRepository $repoClient Repository to fetch client entities
 * @param Request $request HTTP request containing query parameters for pagination
 * @param PaymentRepository $repoPayment Repository to fetch payment entities
 *
 * @return Response HTTP response rendering the payments list page
 */
#[Route('/admin/payment', name: 'app_admin_payment')]
public function index(
    PaginatorInterface $paginator,
    ClientRepository $repoClient,
    Request $request,
    PaymentRepository $repoPayment
): Response {
    $paymentsData = [];

    try {
        // Retrieve all payments from database
        $allPayments = $repoPayment->findAll();

        // Preload all clients related to users of these payments to avoid N+1 queries
        $clientsByUserId = $this->preloadClientsByUsers($repoClient, $allPayments);

        foreach ($allPayments as $payment) {
            try {
                // Get the user associated with the payment
                $user = $payment->getUser();
                if (!$user) {
                    // Log a warning if payment has no user, then skip
                    $this->logger->log(LogLevel::WARNING, 'Payment ID ' . $payment->getId() . ' has no associated user.');
                    continue;
                }

                // Find the client linked to the user from preloaded clients
                $client = $clientsByUserId[$user->getId()] ?? null;
                if (!$client) {
                    // Log a warning if client not found, then skip
                    $this->logger->log(LogLevel::WARNING, 'Client not found for user ID ' . $user->getId());
                    continue;
                }

                // Build the payment data array with relevant fields and client info
                $paymentsData[] = [
                    'id' => $payment->getId(),
                    'slug' => $payment->getSlug(),
                    'amount' => $payment->getAmount(),
                    'status' => $payment->getStatus(),
                    'createdAt' => $payment->getCreatedAt(),
                    'subscriptionPlan' => $payment->getSubscriptionPlan()->getName(),
                    'clientName' => $client->getName(),
                    'currency' => $payment->getCurrency(),
                    'client_slug' => $client->getSlug(),
                    'stripePaymentIntentId' => $payment->getStripePaymentIntentId(),
                    'stripeSessionId' => $payment->getStripeSessionId(),
                ];
            } catch (\Throwable $e) {
                // Log any error during processing a single payment, skip to next
                $this->logger->log(
                    LogLevel::ERROR,
                    'Error processing payment ID ' . $payment->getId() . ': ' . $e->getMessage()
                );
                continue;
            }
        }
    } catch (\Throwable $e) {
        // Log error when fetching all payments and show user-friendly flash message
        $this->logger->log(LogLevel::ERROR, 'Error fetching payments: ' . $e->getMessage());
        $this->addFlash('danger', $this->translator->trans('An error occurred while loading payments.'));
        $paymentsData = [];
    }

    // Paginate the payments data array, 5 items per page, current page from query
    $paginatedPayments = $paginator->paginate($paymentsData, $request->query->getInt('page', 1), 15);

    // Render the template passing the paginated payments list
    return $this->render('admin_payment/index.html.twig', [
        'payments' => $paginatedPayments,
    ]);
}


    /**
 * Generates and returns a PDF invoice for the specified payment.
 * 
 * This endpoint:
 * - Validates the slug format using route requirements (lowercase alphanumeric with hyphens)
 * - Retrieves the payment by its unique slug
 * - Verifies associated client exists
 * - Fetches company information
 * - Delegates PDF generation to the PdfGeneratorService
 * - Handles all possible error cases gracefully
 *
 * @param string $slug Payment identifier slug (format: a-z0-9\-)
 * @param PaymentRepository $paymentRepo Repository for payment entities
 * @param ClientRepository $clientRepo Repository for client entities
 * @param AdminEntrepriseRepository $entrepriseRepo Repository for company info
 * @param PdfGeneratorService $pdfGenerator Service for PDF generation
 * 
 * @return Response Returns either:
 *               - PDF file response on success
 *               - Redirect to payment list with error flash on failure
 * 
 * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException If slug format is invalid (handled by route requirements)
 */
#[Route(
    '/admin/payment/invoice/{slug}', 
    name: 'admin_payment_invoice', 
    requirements: ['slug' => '[a-z0-9\-]+']
)]
public function invoicePdf(
    Request $request,
    PaymentRepository $paymentRepo,
    ClientRepository $clientRepo,
    AdminEntrepriseRepository $entrepriseRepo,
    PdfGeneratorService $pdfGenerator
): Response {
    try {
        $slug =$request->get('slug');
        // 1. Retrieve payment by slug
        $payment = $paymentRepo->findOneBy(['slug' => $slug]);
        if (!$payment) {
            $this->addFlash('danger', $this->translator->trans('Payment not found'));
            return $this->redirectToRoute('app_admin_payment');
        }

        // 2. Verify client exists
        $client = $clientRepo->findOneBy(['user' => $payment->getUser()]);
        if (!$client) {
            $this->addFlash('danger', $this->translator->trans('Client information missing'));
            return $this->redirectToRoute('app_admin_payment');
        }

        // 3. Get company information (assuming ID 1 is the main company)
        $entrepriseInfo = $entrepriseRepo->findOneBy(['id' => 1]);

        // 4. Generate and return PDF
        return $pdfGenerator->generateInvoice($payment, $client, $entrepriseInfo);

    } catch (\Throwable $e) {
        // Log technical error for administrators
        $this->logger->log(
            LogLevel::ERROR, 
            sprintf('Invoice generation failed for slug "%s": %s', $slug, $e->getMessage())
        );
        
        // Show user-friendly error message
        $this->addFlash('danger', $this->translator->trans('Invoice generation failed'));
        
        return $this->redirectToRoute('app_admin_payment');
    }
}

      /**
     * Preloads clients related to users of given payments to reduce database queries.
     *
     * @param ClientRepository $repoClient
     * @param array $payments Array of Payment entities.
     *
     * @return array<int, \App\Entity\Client> Clients indexed by user ID.
     */
    private function preloadClientsByUsers(ClientRepository $repoClient, array $payments): array
    {
        $userIds = [];

        foreach ($payments as $payment) {
            $user = $payment->getUser();
            if ($user) {
                $userIds[] = $user->getId();
            }
        }

        $userIds = array_unique($userIds);

        $clients = $repoClient->findBy(['user' => $userIds]);

        $clientsByUserId = [];
        foreach ($clients as $client) {
            $clientsByUserId[$client->getUser()->getId()] = $client;
        }

        return $clientsByUserId;
    }
}
