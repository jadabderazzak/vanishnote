<?php

namespace App\Controller;

use App\Form\ClientsType;
use App\Repository\LogsRepository;
use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionsRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}

    /**
     * Displays the clients dashboard page.
     *
     * This action renders the dashboard view for the currently authenticated user (client),
     * and includes the monthly growth rate of notes created over the last two months.
     *
     * The growth rate is calculated using the NotesRepository::getMonthlyGrowthRate()
     * method and is passed to the Twig template for display.
     *
     * @param NotesRepository $notesRepository The repository used to fetch user note statistics.
     *
     * @return Response The rendered dashboard view with optional growth rate data.
     */
    #[Route('/clients/dashboard', name: 'app_clients_dashboard')]
    public function index(NotesRepository $repoNotes, LogsRepository $repoLogs,PaymentRepository $repoPayment): Response
    {
        $user = $this->getUser();
        $growthRate = $repoNotes->getMonthlyGrowthRate($user);
        $logs = $repoLogs->findLastFiveUniqueNotesByUser($user);
        $month = (new \DateTime())->format('m');
        $year = (new \DateTime())->format('Y');
        $currentMonthNoteCount = $repoNotes->countUserNotesByMonthAndYear($user, $year,$month);
        $burnedNotesCurrentMonth = $repoNotes->countBurnedNotesThisMonthByUser($user);
        $totalnotes = $repoNotes->countTotalNotesByUser($user);
        $totalBurned = $repoNotes->countTotalBurnedNotesByUser($user);
        $last5NotesNotBurned =  $repoNotes->findLast5NotBurned($user);
        $payments = $repoPayment->findBy([
            'user' => $this->getUser(),
            'status' => "succeeded"
        ]);
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

#[Route('/dashboard/statistics', name: 'dashboard_notes_stats')]
public function notesStatistics(NotesRepository $notesRepository): JsonResponse
{
    // Get raw statistics data from repository
    $monthlyStats = $notesRepository->getMonthlyStats($this->getUser());

    // Transform data for Chart.js
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
     * Displays the profile page of the currently logged-in client.
     *
     * @param ClientRepository $repoClient The repository to fetch Client data.
     * @return Response Rendered profile view with the client data.
     */
    #[Route('/clients/profile', name: 'app_clients_profile')]
    public function profile(ClientRepository $repoClient,NotesRepository $repoNotes,SubscriptionsRepository $repoSub): Response
    {
        $client = $repoClient->findOneBy([
            'user' => $this->getUser()
        ]);
        $notesCreated = $repoNotes->count([
            'user' => $this->getUser()
        ]);

        $accountType = $repoSub->findOneBy([
            'user' => $this->getUser(),
            'status' => true
        ]);
    
        return $this->render('clients/profile.html.twig', [
            'client' => $client,
            'notesCreated' => $notesCreated,
            'accountType' => $accountType->getSubscriptionPlan()->getName()
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
