<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientsType;
use App\Service\HtmlSanitizer;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_USER")]
final class ClientsController extends AbstractController
{
    /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}

    /**
     * Displays the clients dashboard page.
     *
     * @return Response Rendered dashboard view.
     */
    #[Route('/clients/dashboard', name: 'app_clients_dashboard')]
    public function index(): Response
    {
        return $this->render('clients/index.html.twig', [
            'controller_name' => 'ClientsController',
        ]);
    }

    /**
     * Displays the profile page of the currently logged-in client.
     *
     * @param ClientRepository $repoClient The repository to fetch Client data.
     * @return Response Rendered profile view with the client data.
     */
    #[Route('/clients/profile', name: 'app_clients_profile')]
    public function profile(ClientRepository $repoClient): Response
    {
        $client = $repoClient->findOneBy([
            'user' => $this->getUser()
        ]);

        return $this->render('clients/profile.html.twig', [
            'client' => $client,
        ]);
    }

    /**
     * Handles the update of a client's profile identified by the slug.
     * 
     * - Retrieves the client entity by its slug.
     * - Displays and processes a form for editing client data.
     * - Validates the submitted data.
     * - Persists changes to the database.
     * - Adds flash messages to inform the user of success or failure.
     * - Redirects appropriately after a successful update.
     * 
     * Note: Input sanitization is expected to be handled globally (e.g., via a form event subscriber).
     *
     * @param Request $request The current HTTP request.
     * @param EntityManagerInterface $manager Doctrine entity manager used for database operations.
     * @param ClientRepository $repoClient Repository service to find the Client entity by slug.
     * 
     * @return Response Returns the form view if not submitted or invalid, or redirects after successful update.
     */
    #[Route('/clients/profile/update/{slug}', name: 'app_clients_profile_update')]
    public function profile_update(
        Request $request,
        EntityManagerInterface $manager,
        ClientRepository $repoClient
    ): Response
    {
        $client = $repoClient->findOneBy([
            'slug' => $request->get('slug')
        ]);

        if (!$client) {
            $this->addFlash("danger", $this->translator->trans("Client not found!"));
            return $this->redirectToRoute("app_clients_dashboard");
        }

        $form = $this->createForm(ClientsType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get updated client data from form
            $client = $form->getData();
            $manager->flush();

            $this->addFlash('success', $this->translator->trans('Profile updated successfully.'));
            return $this->redirectToRoute('app_clients_profile');
        }


        return $this->render('clients/update.html.twig', [
            'form' => $form->createView(),
            'update' => true
        ]);
    }
}
