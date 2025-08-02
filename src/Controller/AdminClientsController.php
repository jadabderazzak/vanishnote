<?php

namespace App\Controller;

use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Repository\PaymentRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SubscriptionsRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[IsGranted("ROLE_ADMIN")]
final class AdminClientsController extends AbstractController
{
    /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}

   /**
     * Displays a paginated list of all clients in the admin panel.
     *
     * @param ClientRepository $repoClient Repository used to fetch all client records.
     * @param PaginatorInterface $paginator Paginator service used to paginate the list of clients.
     * @param Request $request HTTP request containing the current page number.
     *
     * @return Response The rendered HTML response displaying the list of clients.
     */
    #[Route('/admin/clients', name: 'app_admin_clients')]
    public function index(ClientRepository $repoClient, PaginatorInterface $paginator, Request $request): Response
    {
        $clients = $repoClient->findAllWithLastActiveSubscription();
 
        // $clients = $repoClient->findBy([], ['id' => 'DESC']);
        $paginatedClients = $paginator->paginate($clients, $request->query->getInt('page', 1), 15); 

        return $this->render('admin_clients/index.html.twig', [
            'clients' => $paginatedClients,
            'type' => "All"
        ]);
    }

    /**
     * Displays a paginated list of all clients in the admin panel.
     *
     * @param ClientRepository $repoClient Repository used to fetch all client records.
     * @param PaginatorInterface $paginator Paginator service used to paginate the list of clients.
     * @param Request $request HTTP request containing the current page number.
     *
     * @return Response The rendered HTML response displaying the list of clients.
     */
    #[Route('/admin/clients/companies', name: 'app_admin_clients_companies')]
    public function companies(ClientRepository $repoClient, PaginatorInterface $paginator, Request $request): Response
    {
        $clients = $repoClient->findAllWithLastActiveSubscriptionByType(true);
        
        $paginatedClients = $paginator->paginate($clients, $request->query->getInt('page', 1), 15); 

        return $this->render('admin_clients/index.html.twig', [
            'clients' => $paginatedClients,
            'type' => "Companies"
        ]);
    }

     /**
     * Displays a paginated list of all clients in the admin panel.
     *
     * @param ClientRepository $repoClient Repository used to fetch all client records.
     * @param PaginatorInterface $paginator Paginator service used to paginate the list of clients.
     * @param Request $request HTTP request containing the current page number.
     *
     * @return Response The rendered HTML response displaying the list of clients.
     */
    #[Route('/admin/clients/individuals', name: 'app_admin_clients_individuals')]
    public function individuals(ClientRepository $repoClient, PaginatorInterface $paginator, Request $request): Response
    {
        $clients = $repoClient->findAllWithLastActiveSubscriptionByType(false);
 
        $paginatedClients = $paginator->paginate($clients, $request->query->getInt('page', 1), 15); 

        return $this->render('admin_clients/index.html.twig', [
            'clients' => $paginatedClients,
            'type' => "Individuals"
        ]);
    }

    #[Route('/admin/clients/view/{slug}', name: 'app_admin_clients_view')]
    public function view(ClientRepository $repoClient,  Request $request, CurrencyRepository $repoCurrency, PaymentRepository $repoPayment, SubscriptionsRepository $repoSubscription, NotesRepository $repoNotes): Response
    {
        $client = $repoClient->findOneBy([
            'slug' => $request->get('slug')
        ]);
        $user =  $client->getUser();
        $subscriptions = $repoSubscription->findBy([
            'user' => $user
        ]);
        $notesCount = $repoNotes->count([
            'user' => $user
        ]);
        $currency = $repoCurrency->findOneBy([
            'isPrimary' => true
        ]);
        $amountPayed = $repoPayment->getTotalAmountPaidByUser($user);
        return $this->render('admin_clients/view.html.twig', [
            'client' => $client,
            'subscriptions' => $subscriptions,
            'notesCount' => $notesCount,
            'amountPayed' => $amountPayed,
            'currency' => $currency->getCode()

        ]);
    }

    /**
     * Blocks access for a client by setting its associated user's access to false.
     * 
     * If the client is not found by slug, adds an error flash message and redirects.
     *
     * @param ClientRepository        $repoClient The repository to fetch clients.
     * @param Request                 $request    The current HTTP request.
     * @param EntityManagerInterface  $manager    The Doctrine entity manager.
     * 
     * @return Response The redirect response back to the referer or default route.
     */
    #[Route('/admin/client/access/block/{slug}', name: 'app_admin_client_access_block')]
    public function client_block_access(ClientRepository $repoClient, Request $request, EntityManagerInterface $manager): Response
    {
        
        $client = $repoClient->findOneBy([
            'slug' => $request->get('slug')
        ]);
        if (!$client) {
        $this->addFlash('error', $this->translator->trans('Client not found!'));
        $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin'));
        return $this->redirect($previousUrl);
    }

        /** @var User $user */
        $user =  $client->getUser();
        $user->setHasAccess(false);
        $manager->flush();

        $this->addFlash("success", $this->translator->trans("Access for the client has been restricted"));
       
        $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin'));
        return $this->redirect($previousUrl);
    }

    /**
 * Allows access for a client by setting its associated user's access to true.
 * 
 * If the client is not found by slug, adds an error flash message and redirects.
 *
 * @param ClientRepository        $repoClient The repository to fetch clients.
 * @param Request                 $request    The current HTTP request.
 * @param EntityManagerInterface  $manager    The Doctrine entity manager.
 * 
 * @return Response The redirect response back to the referer or default route.
 */
    #[Route('/admin/client/access/allow/{slug}', name: 'app_admin_client_access_allow')]
    public function client_allow_access(ClientRepository $repoClient, Request $request, EntityManagerInterface $manager): Response
    {
        $client = $repoClient->findOneBy([
            'slug' => $request->get('slug')
        ]);
        
        if (!$client) {
            $this->addFlash('error', $this->translator->trans('Client not found!'));
            $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin'));
            return $this->redirect($previousUrl);
        }

         /** @var User $user */
        $user =  $client->getUser();
        $user->setHasAccess(true);
        $manager->flush();

        $this->addFlash("success", $this->translator->trans("Access for the client has been granted"));
       
        $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin'));
        return $this->redirect($previousUrl);
    }
}
