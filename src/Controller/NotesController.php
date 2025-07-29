<?php

namespace App\Controller;

use DateTime;
use App\Entity\Logs;
use App\Entity\Notes;
use App\Form\NotesFormType;
use App\Entity\Attachements;
use App\Entity\Subscriptions;
use App\Service\HtmlSanitizer;
use Symfony\Component\Uid\Uuid;
use App\Repository\NotesRepository;
use App\Service\SecureEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AttachementsRepository;
use App\Repository\LogsRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[IsGranted("ROLE_USER")]
final class NotesController extends AbstractController
{
    /**
     * Translator service for translating user-facing messages.
     */
    public function __construct(private readonly TranslatorInterface $translator,
      private readonly EntityManagerInterface $manager,
       private readonly Filesystem $filesystem,
       private readonly AttachementsRepository $repoAttachment)
    {}



    /**
     * Displays a list of decrypted notes for the currently authenticated user.
     *
     * This route fetches all notes belonging to the current user, attempts to decrypt
     * their title and content using the provided encryption metadata, and passes the
     * successfully decrypted notes to the Twig template for rendering.
     *
     * Notes that cannot be decrypted (due to exceptions such as tampering or corruption)
     * are silently skipped.
     *
     * @param NotesRepository $repoNotes The repository used to fetch notes from the database.
     * @param SecureEncryptionService $encryptionService The service responsible for decrypting note data.
     *
     * @return Response The rendered HTML response containing the list of decrypted notes.
     */
    #[Route('/notes', name: 'app_notes')]
    public function index(NotesRepository $repoNotes, SecureEncryptionService $encryptionService): Response
    {
        $notes = $repoNotes->findBy([
            'user' => $this->getUser()
        ]);
      
        $decryptedNotes = [];

        foreach ($notes as $note) {
            $aad = $note->getEncryptionMetadata();

            try {
                $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
                $decryptedContent = $encryptionService->decrypt($note->getContent(), $aad);

                $decryptedNotes[] = [
                    'id' => $note->getId(),
                    'slug' => $note->getSlug(),
                    'title' => $decryptedTitle,
                    'content' => $decryptedContent,
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
 
        return $this->render('notes/index.html.twig', [
            'notes' => $decryptedNotes
        ]);
    }


   /**
 * Displays the list of logs related to the current user's notes.
 *
 * Retrieves all log entries associated with the authenticated user and
 * renders them in the 'notes/burned.html.twig' template.
 *
 * @Route("/notes/logs", name="app_notes_logs")
 *
 * @param LogsRepository $repoLog Repository to fetch log entries.
 *
 * @return Response Rendered view displaying the user's note logs.
 */

    #[Route('/notes/logs', name: 'app_notes_logs')]
    public function logs(LogsRepository $repoLog): Response
    {
        $logs = $repoLog->findBy([
            'user' => $this->getUser(),
           
        ]);
      
 
        return $this->render('notes/burned.html.twig', [
            'logs' => $logs
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
#[Route('/notes/view/{slug}', name: 'app_notes_view')]
public function view(
    NotesRepository $repoNotes,
    SecureEncryptionService $encryptionService,
    Request $request,
    AttachementsRepository $repoAttachment
): Response {
    $user = $this->getUser();

    $note = $repoNotes->findOneBy(['slug' => $request->get('slug'), 'user' => $user]);

    if (!$note) {
        $this->addFlash('danger', $this->translator->trans('Note not found or access denied.'));
        return $this->redirectToRoute('app_notes');
    }

    $aad = $note->getEncryptionMetadata();

    try {
        $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
        $decryptedContent = $encryptionService->decrypt($note->getContent(), $aad);
    } catch (\Exception $e) {
        $this->addFlash('danger', 'Unable to decrypt note.');
        return $this->redirectToRoute('app_notes');
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

    // Optionally mark the note as read (currently commented out)
    // if (!$note->getReadAt()) {
    //     $note->setReadAt(new \DateTime());
    //     $entityManager->flush();
    // }

    return $this->render('notes/view.html.twig', [
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
            'password' => (trim($note->getPassword()) !== "" && $note->getPassword() !== null)
        ],
        'attachments' => $decryptedAttachments,
    ]);
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
    #[Route('/download/attachment/{slug}', name: 'app_attachment_download')]
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
            return $this->redirectToRoute('app_notes');
        }
    }





#[Route('/notes/new', name: 'app_notes_new')]
/**
 * Handle creation of a new note, including encryption of title and content,
 * and secure upload of attachments if supported.
 *
 * This method:
 * - Checks authenticated user and active subscription with valid plan.
 * - Encrypts note title and content using SecureEncryptionService.
 * - Uses a generated UUID slug as Additional Authenticated Data (AAD) for encryption.
 * - Stores encrypted title and content in the entity.
 * - Stores the UUID slug in encryptionMetadata field.
 * - Sets character count, timestamps, user, and subscription plan.
 * - Handles encrypted file uploads for attachments if 'attachment_support' feature enabled.
 *
 * @param Request $request
 * @param EntityManagerInterface $entityManager
 * @param HtmlSanitizer $sanitizer
 * @param SecureEncryptionService $encryptionService AES-256-GCM encryption service
 * @return Response
 */
public function new(
    Request $request,
    EntityManagerInterface $entityManager,
    HtmlSanitizer $sanitizer,
    SecureEncryptionService $encryptionService,
    RequestStack $requestStack
): Response {
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('danger', $this->translator->trans('You must be logged in to create a note.'));
        return $this->redirectToRoute('app_login');
    }

    $subscription = $entityManager->getRepository(Subscriptions::class)
        ->findOneBy(['user' => $user, 'status' => true]);

    if (!$subscription) {
        $this->addFlash('danger', $this->translator->trans('Your subscription is not active. Please renew to create notes.'));
        return $this->redirectToRoute('app_client_subscription');
    }

    $plan = $subscription->getSubscriptionPlan();
    if (!$plan) {
        $this->addFlash('danger', $this->translator->trans('Subscription plan not found. Contact support.'));
        return $this->redirectToRoute('app_menu_authenticated');
    }

    $features = $plan->getFeatures() ?? [];

    $note = new Notes();
    $note->setUser($this->getUser());
    $note->setCreatedAt(new DateTime());
    $note->setUpdatedat(new DateTime());
    $note->setCharacterCount(0);
    $note->setPlanType($plan);
    $note->setEncryptionMetadata("Meta");
   
    $form = $this->createForm(NotesFormType::class, $note, [
        'planPrivileges' => $features,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $note = $form->getData();
        // Create logs 
       // Retrieve the current HTTP request from the request stack
        $requestInfo = $requestStack->getCurrentRequest();
        // Create a new Logs entity instance to store user access data
        $log = new Logs();
        $log->setUser($user);
        /**
            * Set the IP address of the client making the request.
            * This uses Symfony's getClientIp(), which supports reverse proxies
            * if properly configured in the framework settings.
            */
        $log->setIpAdress($requestInfo?->getClientIp());
        /**
        * Set the User-Agent string, which identifies the client's browser,
        * operating system, or device. This information is extracted from
        * the HTTP headers of the current request.
        */
        $log->setUserAgent($requestInfo?->headers->get('User-Agent'));

        // Sanitize raw inputs before encryption (optional but recommended)
        $sanitizedTitle = $sanitizer->purify($note->getTitle());
        $sanitizedContent = $sanitizer->purify($note->getContent());

        // Generate UUID slug to use as AAD for encryption (do NOT overwrite note's slug)
        $aad = Uuid::v4()->toRfc4122();

        // Concatenate title and content or build JSON for encryption (not stored, only encrypted separately)
        // Here we encrypt separately, so encryptionMetadata stores only the AAD slug
        try {
            // Encrypt title and content using SecureEncryptionService with AAD slug
            $encryptedTitle = $encryptionService->encrypt($sanitizedTitle, $aad);
            $encryptedContent = $encryptionService->encrypt($sanitizedContent, $aad);
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->translator->trans('Encryption failed. Please try again.'));
            return $this->redirectToRoute('notes_new');
        }
        $countCaracter = mb_strlen($sanitizedContent);
        if ($countCaracter > 50000) {
            $this->addFlash('danger', $this->translator->trans('This note exceeds the maximum allowed length of 50000 characters.'));
            return $this->redirectToRoute('app_notes_new');
        }
        // Set encrypted data and metadata in note entity
        $note->setTitle($encryptedTitle);
        $note->setContent($encryptedContent);
        $note->setEncryptionMetadata($aad);

        // Set other required fields
        $note->setCharacterCount(mb_strlen($sanitizedContent));
        

        // Retrieve the plain password value from the form
        $plainPassword = $note->getPassword();
        if ($plainPassword !== null && trim($plainPassword) !== '') {
        $encryptedPassword = $encryptionService->encrypt($plainPassword, $aad);
        $note->setPassword($encryptedPassword);
        }

        $entityManager->persist($note);
       
        
      
        // Handle attachments if supported and files uploaded
        if (in_array('attachment_support', $features, true)) {
                $uploadedFiles = $request->files->get('notes_form')['attachements'] ?? [];

                // Validation parameters
                $maxFiles = 5;
                $maxTotalSize = 10 * 1024 * 1024; // 10 MB in bytes
                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain',
                    // Add other allowed MIME types here if needed
                ];

                // Check number of uploaded files
                if (count($uploadedFiles) > $maxFiles) {
                    $this->addFlash('danger', $this->translator->trans('You can upload up to 5 files only.'));
                    return $this->redirectToRoute("app_notes_new");
                } else {
                    // Calculate total size of uploaded files
                    $totalSize = 0;
                    foreach ($uploadedFiles as $file) {
                        if ($file) {
                            $totalSize += $file->getSize();

                            // Check MIME type of each file
                            if (!in_array($file->getClientMimeType(), $allowedMimeTypes, true)) {
                                $this->addFlash('danger', $this->translator->trans('Please upload only valid file types (images, PDF, DOCX, text).'));
                                return $this->redirectToRoute("app_notes_new");    
                            }
                        }
                    }

                    // Check total file size limit
                    if ($totalSize > $maxTotalSize) {
                        $this->addFlash('danger', $this->translator->trans('Total file size must not exceed 10 MB.'));
                        return $this->redirectToRoute("app_notes_new");
                    } else {
                        // All validations passed - process each uploaded file
                        foreach ($uploadedFiles as $uploadedFile) {
                            if ($uploadedFile) {
                                try {
                                        // Read file content for encryption
                                        $fileContent = file_get_contents($uploadedFile->getPathname());

                                        // Encrypt file content with the same AAD slug
                                        $encryptedContent = $encryptionService->encrypt($fileContent, $aad);

                                        // Generate unique filename (keep original extension)
                                        $extension = $uploadedFile->guessExtension() ?: 'bin';
                                        $newFilename = uniqid('note_attach_') . '.' . $extension;

                                        // Save encrypted content as a file in safe directory
                                        $filePath = $this->getParameter('notes_attachments_directory') . '/' . $newFilename;
                                        file_put_contents($filePath, $encryptedContent);

                                        // Create Attachment entity
                                        $attachment = new Attachements();
                                        $attachment->setNote($note);
                                        $attachment->setFilename($newFilename);
                                        $attachment->setFilepath($newFilename); 
                                        $attachment->setMimeType($uploadedFile->getClientMimeType());
                                        $attachment->setSize($uploadedFile->getSize());
                                        $attachment->setUploadedAt(new \DateTime());

                                        $entityManager->persist($attachment);
                                    } catch (\Exception $e) {
                                    $this->addFlash('danger', $this->translator->trans('Failed to upload and encrypt attachment.'));
                                    return $this->redirectToRoute("app_notes_new");
                                        }
                                    }
                                }
                            
                            }
                        }
                    }
                    $entityManager->flush();
                    /************** Persist and flush Logs *////////////
                    $log->setNote($note);
                    $entityManager->persist($log);
                    $entityManager->flush();
                    $this->addFlash('success', $this->translator->trans('The note has been successfully created.'));
                    return $this->redirectToRoute("app_notes_new");
            }

            return $this->render('notes/add.html.twig', [
                'form' => $form->createView(),
                'plan' => $plan,
                'features' => $features,
                'numberOfNotesAllowed' => $plan->getNumberNotes(),
            ]);
        }


#[Route('/notes/update/{slug}', name: 'app_notes_update')]
public function update(
 
    Request $request,
    EntityManagerInterface $entityManager,
    HtmlSanitizer $sanitizer,
    SecureEncryptionService $encryptionService,
    NotesRepository $repoNote,
    AttachementsRepository $repoAttachment,
    RequestStack $requestStack,
    LogsRepository $repoLog
): Response {
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('danger', $this->translator->trans('You must be logged in to edit a note.'));
        return $this->redirectToRoute('app_login');
    }
    $slug = $request->get('slug');
    $note = $repoNote->findOneBy(['slug' => $slug, 'user' => $user]);
    if (!$note) {
        $this->addFlash('danger', $this->translator->trans('Note not found or access denied.'));
        return $this->redirectToRoute('app_notes');
    }

   if ($note->getReadAt() !== null) {
        $this->addFlash('danger', $this->translator->trans('This note has already been read and cannot be modified.'));
        return $this->redirectToRoute('app_notes');
    }
    $subscription = $entityManager->getRepository(Subscriptions::class)
        ->findOneBy(['user' => $user, 'status' => true]);

    if (!$subscription || !$subscription->getSubscriptionPlan()) {
        $this->addFlash('danger', $this->translator->trans('No active subscription.'));
        return $this->redirectToRoute('app_client_subscription');
    }

    $plan = $subscription->getSubscriptionPlan();
    $features = $plan->getFeatures() ?? [];

    $aad = $note->getEncryptionMetadata(); // Reuse the same AAD
    try {
        // Decrypt original title and content for pre-filling the form
        $decryptedTitle = $encryptionService->decrypt($note->getTitle(), $aad);
        $decryptedContent = $encryptionService->decrypt($note->getContent(), $aad);

        // Temporarily set decrypted data for editing
        $note->setTitle($decryptedTitle);
        $note->setContent($decryptedContent);
    } catch (\Exception $e) {
        $this->addFlash('danger', $this->translator->trans('Decryption error. Cannot edit this note.'));
        return $this->redirectToRoute('app_notes');
    }



    $decryptedAttachments = [];
    $attachments = $repoAttachment->findBy([
        'note' => $note
    ]);
  
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
            // Ne bloque pas la note entière si un fichier échoue
            continue;
        }
    }

   
    $form = $this->createForm(NotesFormType::class, $note, [
        'planPrivileges' => $features,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        try {
            // Sanitize inputs
            $sanitizedTitle = $sanitizer->purify($note->getTitle());
            $sanitizedContent = $sanitizer->purify($note->getContent());

            // Encrypt again with same AAD
            $encryptedTitle = $encryptionService->encrypt($sanitizedTitle, $aad);
            $encryptedContent = $encryptionService->encrypt($sanitizedContent, $aad);
            $countCaracter = mb_strlen($sanitizedContent);
              if ($countCaracter > 50000) {
                $this->addFlash('danger', $this->translator->trans('This note exceeds the maximum allowed length of 50000 characters.'));
                 return $this->redirectToRoute('app_notes_update', ['slug' => $slug]);
            }
            $note->setTitle($encryptedTitle);
            $note->setContent($encryptedContent);
            $note->setCharacterCount($countCaracter);
            $note->setUpdatedAt(new \DateTime());
             // Retrieve the plain password value from the form
                $plainPassword = $note->getPassword();
                if ($plainPassword !== null && trim($plainPassword) !== '') {
                $encryptedPassword = $encryptionService->encrypt($plainPassword, $aad);
                $note->setPassword($encryptedPassword);
                }
            $countAttachments = count($note->getAttachements());
            // Handle updated attachments
            if (in_array('attachment_support', $features, true)) {
                $uploadedFiles = $request->files->get('notes_form')['attachements'] ?? [];

                if (count($uploadedFiles) > 0) {
                    $maxFiles = 5 - $countAttachments;
                    $maxTotalSize = 10 * 1024 * 1024; // 10 MB
                    $allowedMimeTypes = [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain',
                    ];

                    if (count($uploadedFiles) > $maxFiles) {
                        $this->addFlash('danger', $this->translator->trans('You can upload up to '.$maxFiles.' files only.'));
                        return $this->redirectToRoute('app_notes_update', ['slug' => $slug]);
                    }

                    $totalSize = 0;
                    foreach ($uploadedFiles as $file) {
                        if ($file) {
                            $totalSize += $file->getSize();
                            if (!in_array($file->getClientMimeType(), $allowedMimeTypes, true)) {
                                $this->addFlash('danger', $this->translator->trans('Invalid file type.'));
                                return $this->redirectToRoute('app_notes_update', ['slug' => $slug]);
                            }
                        }
                    }

                    if ($totalSize > $maxTotalSize) {
                        $this->addFlash('danger', $this->translator->trans('Total file size must not exceed 10 MB.'));
                        return $this->redirectToRoute('app_notes_update', ['slug' => $slug]);
                    }

                    // Save encrypted attachments
                    foreach ($uploadedFiles as $uploadedFile) {
                        if ($uploadedFile) {
                            try {
                                $fileContent = file_get_contents($uploadedFile->getPathname());
                                $encryptedFile = $encryptionService->encrypt($fileContent, $aad);
                                $extension = $uploadedFile->guessExtension() ?: 'bin';
                                $newFilename = uniqid('note_attach_') . '.' . $extension;
                                $filePath = $this->getParameter('notes_attachments_directory') . '/' . $newFilename;

                                file_put_contents($filePath, $encryptedFile);

                                $attachment = new Attachements();
                                $attachment->setNote($note);
                                $attachment->setFilename($newFilename);
                                $attachment->setFilepath($newFilename);
                                $attachment->setMimeType($uploadedFile->getClientMimeType());
                                $attachment->setSize($uploadedFile->getSize());
                                $attachment->setUploadedAt(new \DateTime());

                                $entityManager->persist($attachment);
                            } catch (\Exception $e) {
                                $this->addFlash('danger', $this->translator->trans('Failed to encrypt and upload attachment.'));
                                return $this->redirectToRoute('app_notes_update', ['slug' => $slug]);
                            }
                        }
                    }
                }
            }
             // Create logs 
        // Retrieve the current HTTP request from the request stack
            $requestInfo = $requestStack->getCurrentRequest();
            // Create a new Logs entity instance to store user access data
            $log = $repoLog->findOneBy([
            'note' => $note
            ]);
            if($log)
            {            
            /**
                * Set the IP address of the client making the request.
                * This uses Symfony's getClientIp(), which supports reverse proxies
                * if properly configured in the framework settings.
                */
            $log->setIpAdress($requestInfo?->getClientIp());
            /**
            * Set the User-Agent string, which identifies the client's browser,
            * operating system, or device. This information is extracted from
            * the HTTP headers of the current request.
            */
            $log->setUserAgent($requestInfo?->headers->get('User-Agent'));
            }
            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('Note updated successfully.'));
             return $this->redirectToRoute('app_notes');
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->translator->trans('Update failed. Please try again.'));
            return $this->redirectToRoute('app_notes_update', ['slug' => $slug]);
        }
    }

    return $this->render('notes/update.html.twig', [
        'form' => $form->createView(),
        'plan' => $plan,
        'features' => $features,
        'noteSlug' => $slug,
        'attachments' => $decryptedAttachments
    ]);
}



#[Route('/notes/attachement/remove/{slug}', name: 'app_attachment_remove')]
   /**
 * Removes an attachment file and its database record via AJAX.
 *
 * @Route("/attachments/remove/{slug}", name="app_attachment_remove", methods={"POST"})
 *
 * @param Request $request
 * @param EntityManagerInterface $entityManager
 * @param Filesystem $filesystem
 * @param AttachementsRepository $attachmentsRepository
 *
 * @return JsonResponse
 */
public function removeAttachment(
    Request $request,
    EntityManagerInterface $entityManager,
    Filesystem $filesystem,
    AttachementsRepository $attachmentsRepository
): JsonResponse {
    $slug = $request->get('slug');

    $attachment = $attachmentsRepository->findOneBy(['slug' => $slug]);

    if (!$attachment) {
        return $this->json([
            'status' => 'error',
            'message' => $this->translator->trans('Attachment not found.'),
        ], 404);
    }

    $user = $this->getUser();
    if (!$user || $attachment->getNote()->getUser() !== $user) {
        return $this->json([
            'status' => 'error',
            'message' => $this->translator->trans('Access denied.'),
        ], 403);
    }

    $attachmentDirectory = $this->getParameter('notes_attachments_directory');
    $filePath = $attachmentDirectory . '/' . $attachment->getFilepath();

    try {
        if ($filesystem->exists($filePath)) {
            $filesystem->remove($filePath);
        }

        $entityManager->remove($attachment);
        $entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => $this->translator->trans('Attachment removed successfully.'),
            'noteSlug' => $attachment->getNote()->getSlug(),
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'status' => 'error',
            'message' => $this->translator->trans('Error removing attachment.'),
        ], 500);
    }
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
 * Access is restricted to the user who created the note.
 * If the note is not found or does not belong to the current user, the operation is aborted
 * and the user is redirected with a flash error message.
 *
 * @Route("/notes/burn/{slug}", name="app_notes_burn")
 *
 * @param NotesRepository $repoNotes Repository used to retrieve the note by slug and user.
 * @param Request $request Symfony HTTP request object to access route parameters.
 * @param LogsRepository $repoLog Repository used to retrieve and update the log related to the note.
 *
 * @return Response Redirects to the notes listing page after processing.
 */

    #[Route('/notes/burn/{slug}', name: 'app_notes_burn')]
    public function burn(
        NotesRepository $repoNotes,
        Request $request,
        LogsRepository $repoLog
    ): Response {
        $user = $this->getUser();

        $note = $repoNotes->findOneBy(['slug' => $request->get('slug'), 'user' => $user]);

        if (!$note) {
            $this->addFlash('danger', $this->translator->trans('Note not found or access denied.'));
            return $this->redirectToRoute('app_notes');
        }
        
        $note->setBurned(true);
        $note->setContent("**********");
        $note->setTitle("**********");
        $note->setDeletedAt(new DateTime());
        
        $log = $repoLog->findOneBy([
            'note' => $note
        ]);
     
        $log->setDeletedAt(new DateTime());
        $log->setAdditionnalData($this->translator->trans("Permanently removed by the note’s creator"));

        /***************** delete Attachments */

        $this->deleteAllAttachmentsForNote($note);

        return $this->redirectToRoute('app_notes');
        }

}
