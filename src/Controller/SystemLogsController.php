<?php

namespace App\Controller;

use Psr\Log\LogLevel;
use App\Service\SystemLoggerService;
use App\Repository\SystemLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
final class SystemLogsController extends AbstractController
{
    /**
     * SystemLogsController constructor.
     *
     * @param TranslatorInterface   $translator Translator for user-facing messages.
     * @param SystemLoggerService   $logger     Service for logging critical system errors.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

   /**
     * Display paginated system logs for administrative review.
     *
     * Retrieves all system logs from the repository, paginates the results,
     * and renders the logs view. If an error occurs during retrieval or pagination,
     * logs the error and redirects to the dashboard with an error flash message.
     *
     * @param PaginatorInterface    $paginator       Service to paginate the logs.
     * @param Request               $request         HTTP request object (used to get current page number).
     * @param SystemLogRepository   $repoSystemLogs  Repository to fetch system logs.
     *
     * @return Response Rendered logs page or redirection response on error.
     */
    #[Route('/system/logs', name: 'app_system_logs')]
    public function index(PaginatorInterface $paginator, Request $request, SystemLogRepository $repoSystemLogs): Response
    {
        try {
            $allLogs = $repoSystemLogs->findAll();
             $logs = $paginator->paginate($allLogs, $request->query->getInt('page', 1), 15);
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf('[SystemLogsController::index()] Failed to retrieve system logs: %s', $e->getMessage())
            );
            $this->addFlash('danger', $this->translator->trans('Unable to load system logs at this time.'));
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('system_logs/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    /**
     * Mark a specific system log as handled.
     *
     * @param Request                $request        HTTP request containing the log ID.
     * @param SystemLogRepository    $repoSystemLogs Repository used to fetch the specific log.
     * @param EntityManagerInterface $manager        Doctrine entity manager for persisting changes.
     *
     * @return Response A redirect response with a success or error flash message.
     */
    #[Route('/system/logs/handle/{id}', name: 'app_admin_logs_handle')]
    public function handle(Request $request, SystemLogRepository $repoSystemLogs, EntityManagerInterface $manager): Response
    {
        $log = $repoSystemLogs->findOneBy([
            'id' => $request->get('id')
        ]);

        if (!$log) {
            $this->addFlash('danger', $this->translator->trans('The requested log entry was not found.'));
            return $this->redirectToRoute('app_system_logs');
        }

        try {
            $log->setIsHandled(true);
            $manager->flush();

            $this->addFlash('success', $this->translator->trans('The system log was successfully marked as handled.'));
        } catch (\Throwable $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf('[SystemLogsController::handle()] Failed to mark log #%d as handled: %s', $log->getId(), $e->getMessage())
            );

            $this->addFlash('danger', $this->translator->trans('An unexpected error occurred while updating the system log.'));
        }

        return $this->redirectToRoute('app_system_logs');
    }

    /**
     * Display the details of a specific system log entry.
     *
     * Retrieves the system log identified by the 'id' parameter from the request.
     * If the log entry is not found, adds an error flash message and redirects to the logs list.
     * Otherwise, renders the detail view with the retrieved log.
     *
     * @param Request                $request        The current HTTP request containing the log ID.
     * @param SystemLogRepository    $repoSystemLogs Repository for fetching system log entries.
     * @param EntityManagerInterface $manager        The entity manager (not used here but injected).
     *
     * @return Response The response rendering the log details or redirecting on error.
     */
     #[Route('/system/logs/details/{id}', name: 'app_admin_logs_details')]
    public function details(Request $request, SystemLogRepository $repoSystemLogs, EntityManagerInterface $manager): Response
    {

         $log = $repoSystemLogs->findOneBy([
            'id' => $request->get('id')
        ]);

        if (!$log) {
            $this->addFlash('danger', $this->translator->trans('The requested log entry was not found.'));
            return $this->redirectToRoute('app_system_logs');
        }


         return $this->render('system_logs/details.html.twig', [
            'log' => $log,
        ]);
    }
}
