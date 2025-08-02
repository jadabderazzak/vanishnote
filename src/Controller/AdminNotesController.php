<?php

namespace App\Controller;

use DateTime;
use App\Entity\Notes;
use App\Repository\LogsRepository;
use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Service\SecureEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AttachementsRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[IsGranted("ROLE_ADMIN")]
final class AdminNotesController extends AbstractController
{
   /**
     * Constructor.
     *
     * @param TranslatorInterface $translator Translator service for translating user-facing messages.
     * @param EntityManagerInterface $manager Doctrine entity manager for database operations.
     * @param Filesystem $filesystem Filesystem service for file handling operations.
     * @param AttachementsRepository $repoAttachment Repository for managing attachments.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $manager,
        private readonly Filesystem $filesystem,
        private readonly AttachementsRepository $repoAttachment
    ) {}
    #[Route('/admin/notes', name: 'app_admin_notes')]
    public function index(PaginatorInterface $paginator, ClientRepository $repoClient, SecureEncryptionService $encryptionService, Request $request, NotesRepository $repoNotes): Response
    {
        $notes = $repoNotes->findNotBurnedNotes();
         $decryptedNotes = [];

        foreach ($notes as $note) {
            $aad = $note->getEncryptionMetadata();

            try {
                $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
                $decryptedContent = $encryptionService->decrypt($note->getContent(), $aad);
                $owner = $repoClient->findOneBy([
                    'user' => $note->getUser()
                ]);

                $decryptedNotes[] = [
                    'id' => $note->getId(),
                    'slug' => $note->getSlug(),
                    'title' => $decryptedTitle,
                    'content' => $decryptedContent,
                    'owner' => $owner->getName(),
                    'client_slug' => $owner->getSlug(),
                    'createdAt' => $note->getCreatedAt(),
                    'updatedAt' => $note->getUpdatedAt(),
                    'characterCount' => $note->getCharacterCount(),
                    'expirationDate' => $note->getExpirationDate(),
                    'readAt' => $note->getReadAt(),
                ];
            } catch (\Exception $e) {
                // Skip notes that cannot be decrypted
                continue;
            }
        }

 
        $paginatedNotes = $paginator->paginate($decryptedNotes, $request->query->getInt('page', 1), 1); 

        return $this->render('admin_notes/index.html.twig', [
            'notes' => $paginatedNotes,
        ]);
    }

    /**
 * Displays a decrypted note and its associated attachments .
 *
 * This route retrieves a note using its slug,
 * decrypts its content using the `SecureEncryptionService`, and renders it along with
 * any related attachments. If the note is not found or cannot be decrypted, the user
 * is redirected back to the notes list with a flash message.
 *
 * Attachments are listed even if individual attachment decryption fails — the note itself
 * is still shown as long as its core content can be decrypted.
 *
 * @param NotesRepository $repoNotes Repository to fetch the encrypted note by slug and user.
 * @param SecureEncryptionService $encryptionService Service to decrypt the note's title and content.
 * @param Request $request The current HTTP request, used to extract the slug.
 * @param AttachementsRepository $repoAttachment Repository used to retrieve attachments related to the note.
 *
 * @return Response Renders the decrypted note and its attachments in the `view.html.twig` template.
 */
#[Route('/admin/notes/view/{slug}', name: 'app_admin_notes_view')]
public function view(
    NotesRepository $repoNotes,
    SecureEncryptionService $encryptionService,
    Request $request,
    AttachementsRepository $repoAttachment,
    ClientRepository $repoClient
): Response {
    $user = $this->getUser();

    $note = $repoNotes->findOneBy(['slug' => $request->get('slug')]);

    if (!$note) {
        $this->addFlash('danger', $this->translator->trans('Note not found or access denied.'));
        return $this->redirectToRoute('app_admin_notes');
    }

    $aad = $note->getEncryptionMetadata();

    try {
        $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
        $decryptedContent = $encryptionService->decrypt($note->getContent(), $aad);
    } catch (\Exception $e) {
        $this->addFlash('danger', 'Unable to decrypt note.');
        return $this->redirectToRoute('app_admin_notes');
    }

    // Decrypt attachments metadata (not contents)
    $decryptedAttachments = [];
    $attachments = $repoAttachment->findBy(['note' => $note]);

    foreach ($attachments as $attachment) {
        try {
            $decryptedAttachments[] = [
                'filename' => $attachment->getFilename(),
                'mimeType' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
                'slug' => $attachment->getSlug(),
                'uploadedAt' => $attachment->getUploadedAt(),
            ];
        } catch (\Exception $e) {
            // Ignore individual attachment errors and continue
            continue;
        }
    }

    /************************ CInfigure GMT for dates */
    $createdAt = $note->getCreatedAt();
    $createdAt->setTimezone(new \DateTimeZone('GMT'));
    $updatedAt = $note->getUpdatedAt();
    $updatedAt->setTimezone(new \DateTimeZone('GMT'));
    $expiredAt = $note->getExpirationDate() ? $note->getExpirationDate() : null;
    if($note->getExpirationDate()){
        $expiredAt->setTimezone(new \DateTimeZone('GMT'));
    }
    $deletedAt = $note->getDeletedAt() ? $note->getDeletedAt() : null;
    if($note->getDeletedAt()){
        $deletedAt->setTimezone(new \DateTimeZone('GMT'));
    }
    $readAt = $note->getReadAt() ? $note->getReadAt() : null;
    if($note->getReadAt()){
        $readAt->setTimezone(new \DateTimeZone('GMT'));
    }
    $owner = $repoClient->findOneBy([
        'user' => $note->getUser()
    ]);
    return $this->render('admin_notes/view.html.twig', [
        'note' => [
            'title' => $decryptedTitle,
            'slug' => $note->getSlug(),
            'content' => $decryptedContent,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'client_slug' => $owner->getSlug(),
            'characterCount' => $note->getCharacterCount(),
            'expirationDate' => $expiredAt,
            'detetionDate' => $deletedAt,
            'readAt' => $readAt,
            'burning' => $note->isBurnAfterReading() ? "True" :  "False",
            'password' => (trim($note->getPassword()) !== "" && $note->getPassword() !== null)
        ],
        'attachments' => $decryptedAttachments,
    ]);
}



 /**
 * Deletes all attachment files and database records linked to the given note.
 *
 * @param Notes|null $note
 * @param AttachementsRepository $repoAttachment
 * @param Filesystem $filesystem
 * @param EntityManagerInterface $manager
 *
 * @return void
 *
 * @throws \Exception If a file cannot be deleted or database operation fails.
 */
private function deleteAllAttachmentsForNote(?Notes $note): void
{
    if ($note === null) {
        // Note does not exist, do nothing
        return;
    }

    $attachments = $this->repoAttachment->findBy(['note' => $note]);

    $attachmentDirectory = $this->getParameter('notes_attachments_directory');

    foreach ($attachments as $attachment) {
        $filePath = $attachmentDirectory . '/' . $attachment->getFilepath();

        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }

        $this->manager->remove($attachment);
    }

    $this->manager->flush();
}

/**
 * Permanently "burns" a note by masking its content and title, setting a deletion timestamp,
 * updating the related log, and deleting all its attachments.
 *
 *
 * @Route("/admin/notes/burn/{slug}", name="app_admin_notes_burn")
 *
 * @param NotesRepository $repoNotes Repository used to retrieve the note by slug and user.
 * @param Request $request Symfony HTTP request object to access route parameters.
 * @param LogsRepository $repoLog Repository used to retrieve and update the log related to the note.
 *
 * @return Response Redirects to the notes listing page after processing.
 */

    #[Route('/admin/notes/burn/{slug}', name: 'app_admin_notes_burn')]
    public function admin_burn(
        NotesRepository $repoNotes,
        Request $request,
        LogsRepository $repoLog,
        SecureEncryptionService $encryptionService,
        EntityManagerInterface $manager
    ): Response {
       

        $note = $repoNotes->findOneBy(['slug' => $request->get('slug')]);

        if (!$note) {
            $this->addFlash('danger', $this->translator->trans('Note not found or access denied.'));
            return $this->redirectToRoute('app_notes');
        }
         $aad = $note->getEncryptionMetadata();

        try {
            $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
           

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Unable to decrypt note.');
            return $this->redirectToRoute('app_admin_notes');
        }
            
        $note->setBurned(true);
        $note->setContent("**********");
        $note->setTitle("**********");
        $now = new DateTime();
        $now->setTimezone(new \DateTimeZone('GMT'));
        $note->setDeletedAt($now);
        
        $log = $repoLog->findOneBy([
            'note' => $note
        ]);
        $log->setNoteTitle($decryptedTitle);
        $log->setDeletedAt($now);
        $log->setAdditionnalData($this->translator->trans("Permanently removed by the administrator"));
        $manager->flush();
        /***************** delete Attachments */

        $this->deleteAllAttachmentsForNote($note);

       
        $this->addFlash("success", $this->translator->trans("The note has been permanently destroyed"));
       
       
        return $this->redirectToRoute("app_admin_notes");
        }

    /**
     * Displays paginated logs.
     *
     * Fetches all log entries and paginates them.
     * The results are rendered using the 'notes/burned.html.twig' template.
     *
     * Pagination is handled via KnpPaginatorBundle, showing 15 logs per page.
     *
     * @Route('/admin/notes/logs', name: 'app_notes_logs')
     *
     * @param LogsRepository $repoLog Repository to retrieve log entries.
     * @param Request $request HTTP request to get the current page.
     * @param PaginatorInterface $paginator Service used to paginate results.
     *
     * @return Response Rendered HTML response containing paginated logs.
     */
    #[Route('/admin/notes/logs', name: 'app_admin_notes_logs')]
    public function logs(LogsRepository $repoLog, Request $request, PaginatorInterface $paginator): Response
    {
        $logs = $repoLog->getAllLogs();

        $paginatedLogs = $paginator->paginate(
            $logs,
            $request->query->getInt('page', 1), // Current page number from query string
            15 // Items per page
        );

        return $this->render('admin_notes/burned.html.twig', [
            'logs' => $paginatedLogs
        ]);
    }
}
