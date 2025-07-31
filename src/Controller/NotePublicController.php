<?php

namespace App\Controller;

use DateTime;
use Exception;
use DateTimeZone;
use App\Entity\Notes;
use DateTimeInterface;
use App\Entity\LogsIps;
use App\Message\sendReadNote;
use App\Repository\LogsRepository;
use App\Repository\NotesRepository;
use App\Repository\ClientRepository;
use App\Service\SecureEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AttachementsRepository;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\AdminEntrepriseRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class NotePublicController extends AbstractController
{
   /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator,
    private readonly EntityManagerInterface $manager,
    private readonly Filesystem $filesystem,
    private readonly AttachementsRepository $repoAttachment,
    private readonly LoggerInterface $logger)
    {}
    #[Route('/note/public', name: 'app_note_public')]
    public function index(): Response
    {
        return $this->render('note_public/index.html.twig', [
            'controller_name' => 'NotePublicController',
        ]);
    }


     /**
 * Displays a decrypted note and its associated attachments for the authenticated user.
 *
 * This route retrieves a note using its slug, ensures it belongs to the current user,
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
#[Route('/public/note/view/{slug}', name: 'app_note_public_view')]
public function view(
    NotesRepository $repoNotes,
    SecureEncryptionService $encryptionService,
    Request $request,
    AttachementsRepository $repoAttachment,
    LogsRepository $repoLog,
    RequestStack $requestStack,
    EntityManagerInterface $manager,
    MessageBusInterface $messageBus,
    ClientRepository $repoClient,
    AdminEntrepriseRepository $repoEntreprise
): Response {
    
    $session = $request->getSession();
    $slug = $request->get('slug');

    $note = $repoNotes->findOneBy(['slug' => $request->get('slug')]);

    if (!$note) {
        $this->addFlash('danger', $this->translator->trans('Note not found or access denied.'));
        return $this->redirectToRoute('app_notes');
    }

    $aad = $note->getEncryptionMetadata();
    $passwordEncrypted = $note->getPassword();
    $passwordRequired = !empty($passwordEncrypted);
    $unlockedNotes = $session->get('unlocked_notes', []);

    $failedAttempts = $session->get('failed_password_attempts', []);

    // ⚠️ Block if more than 3 attempts are exceeded
    if (isset($failedAttempts[$slug]) && $failedAttempts[$slug] >= 3) {
        $this->addFlash('danger', 'This note is locked after 3 attempts. Please try again later.');
        return $this->render('note_public/password_prompt.html.twig', [
            'noteSlug' => $slug,
            'locked' => true,
        ]);
    }
    // 🔐 If the note is protected and not yet unlocked
    if ($passwordRequired && !in_array($slug, $unlockedNotes)) {
        if ($request->isMethod('POST')) {
            $submittedPassword = trim($request->request->get('note_password'));

            try {
                $decryptedPassword = $encryptionService->decrypt($passwordEncrypted, $aad);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'The note is inaccessible. Please try again later.');
                return $this->redirectToRoute('app_home');
            }

            if (hash_equals($decryptedPassword, $submittedPassword)) {
                unset($failedAttempts[$slug]);
                $session->set('failed_password_attempts', $failedAttempts);
                $unlockedNotes[] = $slug;
                $session->set('unlocked_notes', $unlockedNotes);
                return $this->redirectToRoute('app_note_public_view', ['slug' => $slug]);
            } else {
                $failedAttempts[$slug] = ($failedAttempts[$slug] ?? 0) + 1;
                $session->set('failed_password_attempts', $failedAttempts);
                $this->addFlash('danger', 'Incorrect password.');
            }
        }

        return $this->render('note_public/password_prompt.html.twig', [
            'noteSlug' => $slug,
        ]);
    }


    /**
         * Check and handle if the note should be marked as burned.
         * 
         * - If "Burn After Reading" is enabled and the note hasn't been read yet, mark as burned.
         * - If there is an expiration date and it has passed, mark as burned.
         * - If burned, add a flash message and redirect the user.
         */
        if ($note->isBurnAfterReading()) {
            if ($note->getReadAt() instanceof DateTimeInterface) {
                $note->setBurned(true);
                $this->burnNote($note,$repoLog);
            }
        }
    
        if ($note->getExpirationDate() instanceof DateTimeInterface) {
            $now = new \DateTime();
       
              // Convertir en GMT
            $now->setTimezone(new DateTimeZone('GMT'));
         
            if ($note->getExpirationDate() < $now) {
                $note->setBurned(true);
                $this->burnNote($note,$repoLog);
                
            }
        }

        if ($note->isBurned()) {
            $this->addFlash('danger', $this->translator->trans("This note doesn't exist."));
            return $this->redirectToRoute('app_home');
        }

        $now = new \DateTime();
        $now->setTimezone(new DateTimeZone('GMT'));
        $note->setReadAt($now);
       
        /****************************** Logs traitement */
            $log = $repoLog->findOneBy([
                'note' => $note
            ]);
            
            // Retrieve the current HTTP request from the request stack
            $requestInfo = $requestStack->getCurrentRequest();
            $ipAAdress = $requestInfo?->getClientIp();
            $userAgent = $requestInfo?->headers->get('User-Agent');
            $logIps = new LogsIps();
            $logIps->setLog($log);
            $logIps->setIpAdress($ipAAdress);
            $logIps->setUserAgent($userAgent);
            
            $manager->persist($logIps);
            $manager->flush();


    try {
        $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
        $decryptedContent = trim($encryptionService->decrypt($note->getContent(), $aad));
    } catch (\Exception $e) {
        $this->addFlash('danger', 'Unable to decrypt note.');
        $this->logger->error('Unable to decrypt note: ' . $e->getMessage());
        return $this->redirectToRoute('app_home');
    }

                /******************* Update logs with note title */
                $truncatedTitle = mb_substr($decryptedTitle, 0, 49);
                $log->setNoteTitle($truncatedTitle);
                $manager->flush();

            try {
                $user = $note->getUser();
                /** @var string $email */
                $email = $user->getEmail();

                // ************* Get Client name
                /** @var Client|null $clientInfos */
                $clientInfos = $repoClient->findOneBy([
                    'user' => $user
                ]);

                // ********************* Get infos adminEntreprise
               
                $adminEntreprise = $repoEntreprise->findOneBy([
                    'id' => 1
                ]);

                // Création et dispatch du message
                $message = new sendReadNote(
                    $email,
                    $ipAAdress,
                    $userAgent,
                    $clientInfos ? $clientInfos->getName() : 'Unknown Client',
                    $decryptedTitle,
                    $adminEntreprise
                );

                $messageBus->dispatch($message);

            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Email sending failed: ' . $e->getMessage());
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

   
    $response = $this->render('note_public/index.html.twig', [
        'note' => [
            'title' => $decryptedTitle,
            'slug' => $note->getSlug(),
            'content' => $decryptedContent,
            'createdAt' => $note->getCreatedAt(),
            'updatedAt' => $note->getUpdatedAt(),
            'characterCount' => $note->getCharacterCount(),
            'expirationDate' => $note->getExpirationDate(),
            'detetionDate' => $note->getDeletedAt(),
            'readAt' => $note->getReadAt(),
            'burning' => $note->isBurnAfterReading() ? "True" :  "False",
            'password' => (trim($note->getPassword()) !== "" && $note->getPassword() !== null),
            'minutes' => $note->getMinutes()
        ],
        'attachments' => $decryptedAttachments,
    ]);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    return $response;
}



/**
     * Download and decrypt a secure attachment file.
     *
     * This route allows users to securely download an encrypted file attachment by its slug.
     * If the attachment is not found or the file is missing, the user is redirected with a flash error.
     *
     * 
     * @param SecureEncryptionService $encryptionService The service handling decryption
     * @param Request $request The current HTTP request
     * @param AttachementsRepository $repoAttachment The repository to fetch attachment data
     * @return Response The decrypted file response or a redirection with error flash message
     */
    #[Route('/public/download/attachment/{slug}', name: 'app_attachment_public_download')]
    public function download(
        
        SecureEncryptionService $encryptionService,
        Request $request,
        AttachementsRepository $repoAttachment
    ): Response {
        $attachment = $repoAttachment->findOneBy(['slug' => $request->get('slug')]);
       
        if (!$attachment) {
            $this->addFlash('danger', $this->translator->trans('The requested file could not be found.'));
            return $this->redirectToRoute('app_notes');
        }
        $filesystem = new Filesystem();
        $filepath = $this->getParameter('kernel.project_dir') . '/public/template/notes/' . $attachment->getFilepath();
     
        if (!$filesystem->exists($filepath)) {
                $this->addFlash('danger', $this->translator->trans('The file has been deleted or is no longer accessible.'));
                return $this->redirectToRoute('app_notes');
            }

        try {
              $aad = $attachment->getNote()->getEncryptionMetadata();
            $encrypted = file_get_contents($filepath);
            $decrypted = $encryptionService->decrypt($encrypted, $aad);

            return new Response($decrypted, 200, [
                'Content-Type' => $attachment->getMimeType(),
                'Content-Disposition' => 'attachment; filename="' . $attachment->getFilename() . '"',
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->translator->trans('Failed to decrypt the file. Please contact support.'));
            return $this->redirectToRoute('app_home');
        }
    }

    /**
     * Clears the unlocked notes and failed password attempts from the user's session.
     *
     * This action is designed to be called when the user explicitly wants to "log out" or
     * clear their session related to secure notes, preventing unauthorized access if the
     * browser is left open or shared with others.
     *
     * The method removes session keys:
     *  - 'unlocked_notes' (array of slugs of notes that have been unlocked)
     *  - 'failed_password_attempts' (array tracking failed password entries per note)
     *
     * After clearing the session data, the user is redirected to the notes listing page
     * with a flash message confirming the session clearance.
     *
     * @param Request $request The current HTTP request, used to access the session.
     *
     * @return Response Redirects the user to the notes overview route 'app_notes'.
     */
    #[Route('/note/logout', name: 'app_note_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
       
        // Remove unlocked notes and failed attempts to force re-authentication
        $session->remove('unlocked_notes');
        $session->remove('failed_password_attempts');

        // Add a flash message to inform the user
        $this->addFlash('success', 'Session cleared. You will need to re-enter passwords.');

        // Redirect to notes list page
        return $this->redirectToRoute('app_home');
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
 * Burns (permanently masks and deletes) a note and its attachments.
 *
 * This endpoint sets the note as burned, erases its content and title, updates
 * the deletion timestamp, deletes all its attachments, and marks its associated
 * log as deleted. It only works for the note owner.
 *
 * The response is returned in JSON format.
 *
 * @param NotesRepository $repoNotes Repository used to fetch the note by slug and user
 * @param Request $request HTTP request, containing the note slug in the route
 * @param LogsRepository $repoLog Repository used to fetch and update the related log
 *
 * @return JsonResponse JSON status and message
 */
#[Route('/public/note/burn/{slug}', name: 'app_note_public_burn', methods: ['POST'])]
public function burn(
    NotesRepository $repoNotes,
    Request $request,
    LogsRepository $repoLog,
): JsonResponse {
    $user = $this->getUser();
    $slug = $request->get('slug');

    $note = $repoNotes->findOneBy(['slug' => $slug]);

    if (!$note) {
        return new JsonResponse([
            'status' => 'error',
            'message' => $this->translator->trans('Note not found or access denied.')
        ], JsonResponse::HTTP_FORBIDDEN);
    }

    // Mask content and title, mark as burned and deleted
    $note->setBurned(true);
    $note->setContent('**********');
    $note->setTitle('**********');
    $note->setDeletedAt(new DateTime());

    // Update log if it exists
    $log = $repoLog->findOneBy(['note' => $note]);

    if ($log) {
        $log->setDeletedAt(new DateTime());
        $log->setAdditionnalData(
            $this->translator->trans("Permanently removed by the note’s creator")
        );
    }

    // Delete attached files and database records
    $this->deleteAllAttachmentsForNote($note);

    // Persist changes
    $this->manager->flush();

    return new JsonResponse([
        'status' => 'ok',
        'message' => $this->translator->trans('Note burned successfully.')
    ]);
}


    private function burnNote(Notes $note, LogsRepository $repoLog): void
    {
        $note->setBurned(true);
        $note->setContent("**********");
        $note->setTitle("**********");
        $note->setDeletedAt(new \DateTime());

        $log = $repoLog->findOneBy([
            'note' => $note
        ]);

        if ($log) {
            $log->setDeletedAt(new \DateTime());
            $log->setAdditionnalData($this->translator->trans("Permanently removed by Burning action"));
        }

        $this->deleteAllAttachmentsForNote($note);
    }

}
