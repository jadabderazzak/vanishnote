<?php

namespace App\Controller;

use App\Form\SupportType;
use App\Service\HtmlSanitizer;
use App\Message\supportMessage;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AdminEntrepriseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller responsible for handling client support form submissions.
 * Only authenticated users can access this page.
 */
#[IsGranted('ROLE_USER')]
final class SupportController extends AbstractController
{
    /**
     * @param TranslatorInterface $translator       Used to translate labels and flash messages.
     * @param EntityManagerInterface $manager        Doctrine entity manager to access AdminEntreprise.
     * @param MessageBusInterface $bus               Symfony Messenger bus to dispatch support messages.
     * @param AdminEntrepriseRepository $repoEntreprise 
     * @param ClientRepository $clientRepository
     * @param HtmlSanitizer $sanitizer
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $manager,
        private readonly MessageBusInterface $bus,
        private readonly AdminEntrepriseRepository $repoEntreprise,
        private readonly ClientRepository $clientRepository,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * Displays and handles the support contact form.
     *
     * @param Request $request HTTP request containing form data.
     * @return Response The rendered support page with form.
     */
    #[Route('/support', name: 'app_support')]
    public function index(Request $request): Response
    {
        // Fetch the company settings (you can make this dynamic later)
        $entreprise = $this->repoEntreprise->find(1);

          if (!$entreprise) {
                $this->addFlash('danger', $this->translator->trans('The company configuration could not be loaded. Please contact your administrator.'));

                return $this->redirectToRoute('app_dashboard'); // <-- adapte cette route selon ton app
            }

        // Collect available recipient emails
        $availableEmails = array_unique(array_filter([
            $entreprise->getSupportEmail(),
            $entreprise->getContactEmail(),
            
        ]));
        $client = $this->clientRepository->findOneBy(['user' => $this->getUser()]);
       
        if (!$client) {
            $this->addFlash('danger', $this->translator->trans('Your client account could not be found.'));
            return $this->redirectToRoute('app_home');
        }
         $defaultData = [
            'emailClient' => $client->getUser()?->getEmail() ?? '', // email of this USer
                
        ];
        // Build the support form
        $form = $this->createForm(SupportType::class, $defaultData, [
            'available_emails' => $availableEmails,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
           
            // Sanitize raw inputs before send Email (optional but recommended)
            $sanitizedTitle = $this->sanitizer->purify($data['title']);
            $sanitizedMessage = $this->sanitizer->purify($data['message']);
            $sanitizedEntrepriseClient = isset($data['entrepriseClient']) ? $this->sanitizer->purify($data['entrepriseClient']) : null;
            $sanitizedNameClient = $this->sanitizer->purify($data['nameClient']);
            // Create and dispatch the support message
            $message = new supportMessage(
                emailClient: $data['emailClient'],
                entrepriseClient: $sanitizedEntrepriseClient,
                email: $data['email'],
                message: $sanitizedMessage,
                nameClient: $sanitizedNameClient,
                title: $sanitizedTitle,
                entreprise: $entreprise
            );

            $this->bus->dispatch($message);

            $this->addFlash('success', $this->translator->trans('Your message has been sent successfully. Our support team will get back to you shortly.'));

            return $this->redirectToRoute('app_support');
        }

        return $this->render('support/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
