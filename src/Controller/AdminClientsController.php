<?php

namespace App\Controller;

use App\Enum\LogLevel;
use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Service\SystemLoggerService;
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
     * Constructor.
     *
     * @param TranslatorInterface $translator Service for translating user-facing messages.
     * @param SystemLoggerService $logger Service for logging system-level events and errors.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

    /**
     * Displays a paginated list of all clients in the admin panel.
     *
     * @param ClientRepository $repoClient Repository to fetch clients.
     * @param PaginatorInterface $paginator Paginator for paginating results.
     * @param Request $request HTTP request to get current page number.
     *
     * @return Response Rendered HTML response with paginated clients.
     */
    #[Route('/admin/clients', name: 'app_admin_clients')]
    public function index(ClientRepository $repoClient, PaginatorInterface $paginator, Request $request): Response
    {
        $clients = $repoClient->findAllWithLastActiveSubscription();

        $paginatedClients = $paginator->paginate($clients, $request->query->getInt('page', 1), 15);

        return $this->render('admin_clients/index.html.twig', [
            'clients' => $paginatedClients,
            'type' => 'All',
        ]);
    }

    /**
     * Displays paginated list of company clients.
     *
     * @param ClientRepository $repoClient
     * @param PaginatorInterface $paginator
     * @param Request $request
     *
     * @return Response
     */
    #[Route('/admin/clients/companies', name: 'app_admin_clients_companies')]
    public function companies(ClientRepository $repoClient, PaginatorInterface $paginator, Request $request): Response
    {
        $clients = $repoClient->findAllWithLastActiveSubscriptionByType(true);

        $paginatedClients = $paginator->paginate($clients, $request->query->getInt('page', 1), 15);

        return $this->render('admin_clients/index.html.twig', [
            'clients' => $paginatedClients,
            'type' => 'Companies',
        ]);
    }

    /**
     * Displays paginated list of individual clients.
     *
     * @param ClientRepository $repoClient
     * @param PaginatorInterface $paginator
     * @param Request $request
     *
     * @return Response
     */
    #[Route('/admin/clients/individuals', name: 'app_admin_clients_individuals')]
    public function individuals(ClientRepository $repoClient, PaginatorInterface $paginator, Request $request): Response
    {
        $clients = $repoClient->findAllWithLastActiveSubscriptionByType(false);

        $paginatedClients = $paginator->paginate($clients, $request->query->getInt('page', 1), 15);

        return $this->render('admin_clients/index.html.twig', [
            'clients' => $paginatedClients,
            'type' => 'Individuals',
        ]);
    }

    /**
     * Displays detailed information about a client.
     *
     * @param ClientRepository $repoClient
     * @param Request $request
     * @param CurrencyRepository $repoCurrency
     * @param PaymentRepository $repoPayment
     * @param SubscriptionsRepository $repoSubscription
     * @param NotesRepository $repoNotes
     *
     * @return Response
     */
    #[Route('/admin/clients/view/{slug}', name: 'app_admin_clients_view', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function view(
        ClientRepository $repoClient,
        Request $request,
        CurrencyRepository $repoCurrency,
        PaymentRepository $repoPayment,
        SubscriptionsRepository $repoSubscription,
        NotesRepository $repoNotes
    ): Response {
        $slug = $request->get('slug');
        $client = $repoClient->findOneBy(['slug' => $slug]);

        if (!$client) {
            $this->addFlash('error', $this->translator->trans('Client not found.'));
            $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin_clients'));
            return $this->redirect($previousUrl);
        }

        $user = $client->getUser();

        $subscriptions = $repoSubscription->findBy(['user' => $user]);
        $notesCount = $repoNotes->count(['user' => $user]);
        $currency = $repoCurrency->findOneBy(['isPrimary' => true]);
        $amountPaid = $repoPayment->getTotalAmountPaidByUser($user);
        $allPayments = $repoPayment->findBy(['user' => $user]);

        // Preload clients linked to payment users to reduce DB queries
        $clientsByUserId = $this->preloadClientsByUsers($repoClient, $allPayments);

        $payments = [];

        foreach ($allPayments as $payment) {
            try {
                $paymentUser = $payment->getUser();

                if (!$paymentUser) {
                    $this->logger->log(
                        LogLevel::ERROR,
                        sprintf('[AdminClientsController::view()] Payment #%d has no associated user.', $payment->getId())
                    );
                    continue;
                }

                $paymentClient = $clientsByUserId[$paymentUser->getId()] ?? null;

                $payments[] = [
                    'id' => $payment->getId(),
                    'slug' => $payment->getSlug(),
                    'amount' => $payment->getAmount(),
                    'status' => $payment->getStatus(),
                    'createdAt' => $payment->getCreatedAt(),
                    'subscriptionPlan' => $payment->getSubscriptionPlan()?->getName(),
                    'clientName' => $paymentClient?->getName(),
                    'currency' => $payment->getCurrency(),
                    'client_slug' => $paymentClient?->getSlug(),
                    'stripePaymentIntentId' => $payment->getStripePaymentIntentId(),
                    'stripeSessionId' => $payment->getStripeSessionId(),
                ];
            } catch (\Throwable $e) {
                $this->logger->log(
                    LogLevel::ERROR,
                    sprintf('[AdminClientsController::view()] Failed to map payment #%d. Error: %s', $payment->getId(), $e->getMessage())
                );
            }
        }

        return $this->render('admin_clients/view.html.twig', [
            'client' => $client,
            'subscriptions' => $subscriptions,
            'notesCount' => $notesCount,
            'amountPaid' => $amountPaid,
            'currency' => $currency ? $currency->getCode() : null,
            'payments' => $payments,
        ]);
    }

    /**
     * Blocks client access by disabling the associated user's access.
     *
     * @param ClientRepository $repoClient
     * @param Request $request
     * @param EntityManagerInterface $manager
     *
     * @return Response
     */
    #[Route('/admin/client/access/block/{slug}', name: 'app_admin_client_access_block')]
    public function client_block_access(ClientRepository $repoClient, Request $request, EntityManagerInterface $manager): Response
    {
        $slug = $request->get('slug');
        $client = $repoClient->findOneBy(['slug' => $slug]);

        if (!$client) {
            $this->addFlash('error', $this->translator->trans('Client not found!'));
            $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin_clients'));
            return $this->redirect($previousUrl);
        }

        try {
            $user = $client->getUser();
            $user->setHasAccess(false);
            $manager->flush();

            $this->addFlash('success', $this->translator->trans('Access for the client has been restricted'));
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::CRITICAL,
                sprintf('[%s::%s()] Failed to block access for client "%s". Error: %s', __CLASS__, __FUNCTION__, $client->getSlug(), $e->getMessage())
            );

            $this->addFlash('error', $this->translator->trans('Could not block access for this client.'));
        }

        $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin_clients'));
        return $this->redirect($previousUrl);
    }

    /**
     * Allows client access by enabling the associated user's access.
     *
     * @param ClientRepository $repoClient
     * @param Request $request
     * @param EntityManagerInterface $manager
     *
     * @return Response
     */
    #[Route('/admin/client/access/allow/{slug}', name: 'app_admin_client_access_allow')]
    public function client_allow_access(ClientRepository $repoClient, Request $request, EntityManagerInterface $manager): Response
    {
        $slug = $request->get('slug');
        $client = $repoClient->findOneBy(['slug' => $slug]);

        if (!$client) {
            $this->addFlash('error', $this->translator->trans('Client not found!'));
            $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin_clients'));
            return $this->redirect($previousUrl);
        }

        try {
            $user = $client->getUser();
            $user->setHasAccess(true);
            $manager->flush();

            $this->addFlash('success', $this->translator->trans('Access for the client has been granted'));
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::CRITICAL,
                sprintf('[%s::%s()] Failed to allow access for client "%s". Error: %s', __CLASS__, __FUNCTION__, $client->getSlug(), $e->getMessage())
            );

            $this->addFlash('error', $this->translator->trans('Could not allow access for this client.'));
        }

        $previousUrl = $request->headers->get('referer', $this->generateUrl('app_admin_clients'));
        return $this->redirect($previousUrl);
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
