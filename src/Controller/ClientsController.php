<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\LogLevel;
use App\Form\ClientsType;
use App\Repository\LogsRepository;
use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Service\SystemLoggerService;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SubscriptionsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_USER")]
final class ClientsController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

    /**
     * Displays the dashboard for authenticated client user.
     *
     * @param NotesRepository $repoNotes
     * @param LogsRepository $repoLogs
     * @param PaymentRepository $repoPayment
     * @return Response
     */
    #[Route('/clients/dashboard', name: 'app_clients_dashboard')]
    public function index(
        NotesRepository $repoNotes,
        LogsRepository $repoLogs,
        PaymentRepository $repoPayment
    ): Response {

        /** @var User $user */
        $user = $this->getUser();

        try {
            $growthRate = $repoNotes->getMonthlyGrowthRate($user);
            $logs = $repoLogs->findLastFiveUniqueNotesByUser($user);
            $month = (new \DateTime())->format('m');
            $year = (new \DateTime())->format('Y');
            $currentMonthNoteCount = $repoNotes->countUserNotesByMonthAndYear($user, $year, $month);
            $burnedNotesCurrentMonth = $repoNotes->countBurnedNotesThisMonthByUser($user);
            $totalnotes = $repoNotes->countTotalNotesByUser($user);
            $totalBurned = $repoNotes->countTotalBurnedNotesByUser($user);
            $last5NotesNotBurned = $repoNotes->findLast5NotBurned($user);
            $payments = $repoPayment->findLastFivePaymentsByUser($user);
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf('[ClientsController::index()] Failed to load dashboard data for user #%d: %s', $user->getId(), $e->getMessage())
            );
            $this->addFlash('danger', $this->translator->trans('An error occurred while loading dashboard.'));
            return $this->redirectToRoute('app_logout');
        }

        return $this->render('clients/index.html.twig', [
            'growthRate' => $growthRate,
            'logs' => $logs,
            'currentMonthNoteCount' => $currentMonthNoteCount,
            'burnedNotesCurrentMonth' => $burnedNotesCurrentMonth,
            'totalnotes' => $totalnotes,
            'totalBurned' => $totalBurned,
            'last5NotesNotBurned' => $last5NotesNotBurned,
            'payments' => $payments,
        ]);
    }

    /**
     * Returns JSON stats about notes per month for charts.
     *
     * @param NotesRepository $notesRepository
     * @return JsonResponse
     */
    #[Route('/dashboard/statistics', name: 'dashboard_notes_stats')]
    public function notesStatistics(NotesRepository $notesRepository): JsonResponse
    {
        /** @var User $user */
        $user =$this->getUser();
        try {
            $monthlyStats = $notesRepository->getMonthlyStats($this->getUser());
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf('[ClientsController::notesStatistics()] Failed to fetch stats for user #%d: %s', $user->getId(), $e->getMessage())
            );
            return new JsonResponse(['error' => 'Failed to fetch statistics.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $chartData = [
            'labels' => array_column($monthlyStats, 'month'),
            'datasets' => [
                [
                    'label' => 'Notes Created',
                    'data' => array_column($monthlyStats, 'created'),
                    'backgroundColor' => 'rgba(58, 138, 192, 0.1)',
                    'borderColor' => 'rgba(58, 138, 192, 1)',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Notes Burned',
                    'data' => array_column($monthlyStats, 'burned'),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'borderWidth' => 2
                ]
            ]
        ];

        return new JsonResponse($chartData);
    }

    /**
     * Displays profile data for the logged-in client.
     *
     * @param ClientRepository $repoClient
     * @param NotesRepository $repoNotes
     * @param SubscriptionsRepository $repoSub
     * @return Response
     */
    #[Route('/clients/profile', name: 'app_clients_profile')]
    public function profile(
        ClientRepository $repoClient,
        NotesRepository $repoNotes,
        SubscriptionsRepository $repoSub
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $client = $repoClient->findOneBy(['user' => $user]);
            $notesCreated = $repoNotes->count(['user' => $user]);
            $accountType = $repoSub->findOneBy(['user' => $user, 'status' => true]);
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf('[ClientsController::profile()] Failed to load profile for user #%d: %s', $user->getId(), $e->getMessage())
            );
            $this->addFlash('danger', $this->translator->trans('An error occurred while loading your profile.'));
            return $this->redirectToRoute('app_clients_dashboard');
        }

        return $this->render('clients/profile.html.twig', [
            'client' => $client,
            'notesCreated' => $notesCreated,
            'accountType' => $accountType?->getSubscriptionPlan()?->getName(),
        ]);
    }

    /**
     * Updates a client profile based on slug.
     *
     * @param Request $request
     * @param EntityManagerInterface $manager
     * @param ClientRepository $repoClient
     * @return Response
     */
    #[Route('/clients/profile/update/{slug}', name: 'app_clients_profile_update')]
    public function profile_update(
        Request $request,
        EntityManagerInterface $manager,
        ClientRepository $repoClient
    ): Response {
        $slug = $request->get('slug');

        try {
            $client = $repoClient->findOneBy(['slug' => $slug]);
            if (!$client) {
                $this->logger->log(LogLevel::WARNING, sprintf('[ClientsController::profile_update()] No client found with slug "%s"', $slug));
                $this->addFlash("danger", $this->translator->trans("Client not found!"));
                return $this->redirectToRoute("app_clients_dashboard");
            }

            $form = $this->createForm(ClientsType::class, $client);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager->flush();
                $this->addFlash('success', $this->translator->trans('Profile updated successfully.'));
                return $this->redirectToRoute('app_clients_profile');
            }

        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf('[ClientsController::profile_update()] Failed to update profile for client slug "%s": %s', $slug, $e->getMessage())
            );
            $this->addFlash('danger', $this->translator->trans('An error occurred while updating your profile.'));
            return $this->redirectToRoute('app_clients_dashboard');
        }

        return $this->render('clients/update.html.twig', [
            'form' => $form->createView(),
            'update' => true
        ]);
    }
}
