<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use App\Entity\Client;
use App\Enum\LogLevel;
use App\Entity\Payment;
use App\Entity\AdminEntreprise;
use App\Service\SystemLoggerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * PDF generation service for creating invoice documents.
 * 
 * Handles the creation of PDF invoices with proper styling and error handling.
 */
class PdfGeneratorService
{
    /**
     * @param TranslatorInterface $translator For translating text in PDFs
     * @param SystemLoggerService $logger For logging generation errors
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger,
        private readonly Environment $renderer
    ) {}

    /**
     * Generates a PDF invoice response for the given payment.
     *
     * @param Payment $payment The payment entity to invoice
     * @param Client $client The client receiving the invoice
     * @param AdminEntreprise|null $entreprise The business entity info
     * @param string $templatePath Path to the Twig template
     * 
     * @return Response HTTP response containing the PDF
     * 
     * @throws \RuntimeException When PDF generation fails
     */
    public function generateInvoice(
        Payment $payment,
        Client $client,
        ?AdminEntreprise $entreprise,
        string $templatePath = 'stripe_webhook/payment_invoice.html.twig'
    ): Response {
        try {
            // Configure DomPDF
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->setIsRemoteEnabled(true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);

            // Render HTML content
            $html = $this->renderer->render($templatePath, [
                'payment' => $payment,
                'client' => $client,
                'entreprise' => $entreprise,
            ]);

            // Generate PDF
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Create response
            return new Response(
                $dompdf->output(),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="invoice-'.$payment->getSlug().'.pdf"',
                ]
            );

        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                'PDF generation failed for payment '.$payment->getId().': '.$e->getMessage()
            );
            
            throw new \RuntimeException('Failed to generate PDF invoice', 0, $e);
        }
    }
}