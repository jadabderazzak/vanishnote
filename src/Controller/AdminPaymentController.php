<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\ClientRepository;
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
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}
    #[Route('/admin/payment', name: 'app_admin_payment')]
    public function index(PaginatorInterface $paginator, ClientRepository $repoClient,Request $request,PaymentRepository $repoPayment): Response
    {
        $Allpayments = $repoPayment->findAll();
        $payments = [];
        foreach ($Allpayments as $payment) {
         
            try {
                $user = $payment->getUSer();
                $client = $repoClient->findOneBy([
                    'user' => $user
                ]);
                $payments[] = [
                    'id' => $payment->getId(),
                    'slug' => $payment->getSlug(),
                    'amount' => $payment->getAmount(),
                    'status' => $payment->getStatus(),
                    'createdAt' => $payment->getCreatedAt(),
                    'subscriptionPlan' => $payment->getSubscriptionPlan()->getName(),
                    'clientName' => $client->getName(),
                    'currency' => $payment->getCurrency(),
                    'client_slug' => $client->getSlug()
                    
                ];
            } catch (\Exception $e) {
                // Skip notes that cannot be decrypted
                continue;
            }
        }
        $paginatedpayment = $paginator->paginate($payments, $request->query->getInt('page', 1), 5); 
        return $this->render('admin_payment/index.html.twig', [
            'payments' => $paginatedpayment,
        ]);
    }

    /**
         * Generates and displays a PDF invoice for a given payment.
         *
         * This action:
         * - Verifies the payment belongs to the authenticated user
         * - Retrieves the client information
         * - Renders a Twig HTML template into PDF via Dompdf
         * - Streams the PDF directly in the browser
         *
         * Route: /payment/invoice/{slug}
         * Method: GET
         *
         * @param string $slug Unique slug for the payment
         * @param PaymentRepository $paymentRepo Repository to fetch payment
         * @param ClientRepository $clientRepo Repository to fetch client data
         *
         * @return Response The rendered PDF invoice or a redirection with flash
         */
        #[Route('/admin/payment/invoice/{slug}', name: 'admin_payment_invoice')]
        public function invoicePdf(
            string $slug,
            PaymentRepository $paymentRepo,
            ClientRepository $clientRepo,
            AdminEntrepriseRepository $repoEntreprise
        ): Response {
            

            $payment = $paymentRepo->findOneBy(['slug' => $slug]);
            if (!$payment) {
                $this->addFlash('danger', 'Payment not found.');
                return $this->redirectToRoute('app_admin_payment');
            }
            $user = $payment->getUser();
            $client = $clientRepo->findOneBy(['user' => $user]);
            if (!$client) {
                $this->addFlash('danger', 'Client information is missing. Please update your profile.');
                return $this->redirectToRoute('app_admin_payment');
            }

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->setIsRemoteEnabled(true);
            $dompdf = new Dompdf($options);
              $entrepriseInfo = $repoEntreprise->findOneBy([
            'id' => 1
            ]);
          
            $html = $this->renderView('stripe_webhook/payment_invoice.html.twig', [
                'payment' => $payment,
                'client' => $client,
                'entreprise' => $entrepriseInfo
             
            ]);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="invoice-'.$payment->getSlug().'.pdf"',
            ]);
        }
}
