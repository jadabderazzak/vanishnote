<?php

namespace App\Controller;

use App\Enum\LogLevel;
use App\Entity\SecureFiles;
use App\Form\SecureFilesType;
use App\Form\DecryptFilesType;
use App\Service\HtmlSanitizer;
use App\Service\SystemLoggerService;
use App\Service\SecureEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SecureFilesRepository;
use App\Repository\SubscriptionsRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SecureFilesController extends AbstractController
{
    /**
     * Constructor.
     *
     * @param TranslatorInterface $translator Translator service for translating user-facing messages.
     * @param EntityManagerInterface $manager Doctrine entity manager for database operations.
     * @param Filesystem $filesystem Service for filesystem operations.
     * @param SecureFilesRepository $repoFiles Repository for managing secure files.
     * @param PaginatorInterface $paginator Pagination service for listing results.
     * @param SystemLoggerService $logger System logging service.
     * @param SecureEncryptionService $encryptionService Service handling encryption and decryption.
     * @param HtmlSanitizer $sanitizer HTML sanitizer service.
     * @param string $encryptionKeyAad Additional Authenticated Data (AAD) key used in encryption/decryption.
     * 
     * NOTE: Changing this key will make all previously encrypted documents undecryptable.
     */
    private readonly string $encryptionKeyAad;
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $manager,
        private readonly Filesystem $filesystem,
        private readonly SecureFilesRepository $repoFiles,
        private readonly PaginatorInterface $paginator,
        private readonly SystemLoggerService $logger,
        private readonly SecureEncryptionService $encryptionService,
        private readonly HtmlSanitizer $sanitizer,

    ) {
         // The key must be the correct size (e.g., 32 bytes for AES-256)
        $this->encryptionKeyAad = $_ENV['APP_ENCRYPTION_KEY_AAD'] ?? '';

        }

/**
 * Display a paginated list of secure files for the current user.
 *
 * This route fetches all secure files associated with the logged-in user,
 * paginates the results (15 files per page), and renders them in the
 * 'secure_files/index.html.twig' template.
 *
 * @Route("/secure/files/lists", name="app_secure_files_lists")
 *
 * @param Request $request The current HTTP request, used to get the page number.
 * @return Response Renders the paginated list of user files.
 */
    #[Route('/secure/files/lists', name: 'app_secure_files_lists')]
    public function index(Request $request): Response
    {
        $files = $this->repoFiles->findBy([
            'user' => $this->getUser()
        ]);

        $paginatedFiles = $this->paginator->paginate($files, $request->query->getInt('page', 1), 15); 
        return $this->render('secure_files/index.html.twig', [
            'files' => $paginatedFiles,
        ]);
    }
    /**
     * Handle the upload, encryption, and storage of a new secure file.
     *
     * This route presents a form to upload a file, validates the uploaded file's
     * MIME type and size, then encrypts the file content using the encryption service
     * and saves it securely on the server. It also creates a SecureFiles entity
     * associated with the current user and persists it to the database.
     *
     * Validation errors or failures during upload/encryption result in user-friendly
     * flash messages and redirection back to the upload form.
     *
     * @Route("/secure/files/add", name="app_secure_files_add")
     *
     * @param Request $request The current HTTP request containing the form data and file upload.
     * @return Response Renders the upload form or redirects after processing.
     */
    #[Route('/secure/files/add', name: 'app_secure_files_add')]
    public function add(Request $request): Response
    {
        $form = $this->createForm(SecureFilesType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $sanitizedTitle = $this->sanitizer->purify($data["filename"]);

           // Retrieve the uploaded file from the request
            $uploadedFile = $request->files->get('secure_files')['attachement'] ?? null;
          
            $aad = $this->encryptionKeyAad;    
            // Validation settings
            $maxFileSize = 10 * 1024 * 1024; // 10 MB
            $allowedMimeTypes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                // Add other allowed MIME types here
            ];

            if ($uploadedFile) {
                // Check if MIME type is allowed
                if (!in_array($uploadedFile->getClientMimeType(), $allowedMimeTypes, true)) {
                    $this->addFlash('danger', $this->translator->trans('Please upload only valid file types (images, PDF, DOCX, text).'));
                    return $this->redirectToRoute("app_secure_form_add");
                }

                // Check file size limit
                if ($uploadedFile->getSize() > $maxFileSize) {
                    $this->addFlash('danger', $this->translator->trans('File size must not exceed 10 MB.'));
                    return $this->redirectToRoute("app_secure_form_add");
                }

                try {
                    // Read the file content
                    $fileContent = file_get_contents($uploadedFile->getPathname());

                    // Encrypt the file content (using your encryption service and AAD)
                    $encryptedContent = $this->encryptionService->encrypt($fileContent, $aad);

                    // Generate a unique filename with original extension
                    $extension = $uploadedFile->guessExtension() ?: 'bin';
                    $filepath = uniqid('note_attach_') . '.' . $extension;

                    // Save encrypted content to a secure directory
                    $filePath = $this->getParameter('secure_files_attachments_directory') . '/' . $filepath;
                    file_put_contents($filePath, $encryptedContent);

                    // Create the Attachment entity and set its properties
                    $attachment = new SecureFiles();
                    $attachment->setFilename($sanitizedTitle);
                    $attachment->setFilepath($filepath);
                    $attachment->setUser($this->getUser());
                    $attachment->setAad($aad);
                    $attachment->setMimeType($uploadedFile->getClientMimeType());
                    $attachment->setSize($uploadedFile->getSize());
                    $attachment->setUploadedAt(new \DateTime());

                    // Persist the entity
                    $this->manager->persist($attachment);
                    // Don't forget to flush after all operations are done
                    $this->addFlash('success', $this->translator->trans('The file was added successfully'));
                    $this->manager->flush();

                    } catch (\Exception $e) {
                        $this->addFlash('danger', $this->translator->trans('Failed to upload and encrypt attachment.'));
                        $this->logger->log(
                            LogLevel::ERROR,
                            sprintf(
                                '[SecureFilesController::add()]: Failed to upload and encrypt attachment. "%s"',
                                $e->getMessage()
                            )
                        );
                        return $this->redirectToRoute("app_secure_form_add");
                    }
                } else {
                    // Handle case when no file is uploaded
                    $this->addFlash('danger', $this->translator->trans('No file was uploaded.'));
                    return $this->redirectToRoute("app_secure_form_add");
                }

        }
        return $this->render('secure_files/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }



    /**
 * Download an encrypted file.
 *
 * This route handles the downloading of an encrypted file by its slug.
 * It verifies that the file record exists and the physical file is accessible on the server.
 * If the file is available, it returns the encrypted file as a downloadable response
 * with the correct content type and disposition headers.
 *
 * If the file is missing or inaccessible, the user is redirected to the secure files list
 * with an appropriate error message.
 *
 * @Route("/secure/files/encrypted/download", name="app_encrypted_file_download")
 *
 * @param Request $request The current HTTP request containing the file slug.
 * @return Response Returns a binary file response with the encrypted file content,
 *                  or redirects to the files list on failure.
 */
    #[Route('/secure/files/encrypted/download', name: 'app_encrypted_file_download')]
    public function encrypted_download(Request $request): Response
    {
        // Retrieve the file entity using the slug parameter
        $attachment = $this->repoFiles->findOneBy(['slug' => $request->get('slug')]);

        // If the file doesn't exist in the database, redirect with error message
        if (!$attachment) {
            $this->addFlash('danger', $this->translator->trans('The requested file could not be found.'));
            return $this->redirectToRoute('app_secure_files_lists');
        }

        // Construct the absolute path to the file on the server
        $filesystem = new Filesystem();
        $filepath = $this->getParameter('kernel.project_dir') . '/public/template/secureFiles/' . $attachment->getFilepath();

        // Check if the physical file exists
        if (!$filesystem->exists($filepath)) {
            $this->addFlash('danger', $this->translator->trans('The file has been deleted or is no longer accessible.'));
            return $this->redirectToRoute('app_secure_files_lists');
        }

        // Return the raw encrypted file for download
        $response = new BinaryFileResponse($filepath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getFilepath()
        );
        $response->headers->set('Content-Type', $attachment->getMimeType());

        return $response;
    }

    /**
     * Download and decrypt a secure file.
     *
     * This route handles the downloading of an encrypted file by its slug.
     * It first checks if the requested file exists and is accessible.
     * If the file is found, it attempts to decrypt the content using the
     * encryption service and returns the decrypted file as a response
     * with appropriate headers for downloading.
     *
     * In case the file is missing, deleted, or the decryption fails,
     * the user is redirected back to the secure files list with an error message.
     *
     * @Route("/secure/files/decrypted/download", name="app_decrypted_file_download")
     *
     * @param Request $request The current HTTP request containing the file slug.
     * @return Response Returns the decrypted file content as a downloadable response,
     *                  or redirects to the files list on failure.
     */
    #[Route('/secure/files/decrypted/download', name: 'app_decrypted_file_download')]
    public function decrypted_download(Request $request): Response
    {
        $attachment = $this->repoFiles->findOneBy(['slug' => $request->get('slug')]);
       
        if (!$attachment) {
            $this->addFlash('danger', $this->translator->trans('The requested file could not be found.'));
            return $this->redirectToRoute('app_secure_files_lists');
        }
        $filesystem = new Filesystem();
        $filepath = $this->getParameter('kernel.project_dir') . '/public/template/secureFiles/' . $attachment->getFilepath();
     
        if (!$filesystem->exists($filepath)) {
                $this->addFlash('danger', $this->translator->trans('The file has been deleted or is no longer accessible.'));
                return $this->redirectToRoute('app_secure_files_lists');
            }

        try {
            $aad = $attachment->getAad();
            $encrypted = file_get_contents($filepath);
            $decrypted = $this->encryptionService->decrypt($encrypted, $aad);

            return new Response($decrypted, 200, [
                'Content-Type' => $attachment->getMimeType(),
                'Content-Disposition' => 'attachment; filename="' . $attachment->getFilepath() . '"',
            ]);
        } catch (\Exception $e) {
             $this->logger->log(
                LogLevel::ERROR,
                sprintf(
                    '[SecureFilesController::decrypt_download()]: Failed to decrypt the file. Please contact support. "%s": %s',
                    $attachment->getFilename(),
                    $e->getMessage()
                )
            );
            $this->addFlash('danger', $this->translator->trans('Failed to decrypt the file. Please contact support.'));
            return $this->redirectToRoute('app_secure_files_lists');
        }
        return $this->redirectToRoute('app_secure_files_lists');
    }

    /**
     * Delete a secure file by its slug.
     *
     * This method removes the physical file from the filesystem,
     * deletes its database record, and shows a success or error message.
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/secure/files/delete/{slug}', name: 'app_secure_file_delete')]
    public function delete(Request $request): Response
    {
        $file = $this->repoFiles->findOneBy([
            'slug' => $request->get('slug')
        ]);
      if (!$file) {
        $this->addFlash('danger', $this->translator->trans('The requested file could not be found.'));
        return $this->redirectToRoute('app_secure_files_lists');
        }

        try {
            $attachmentDirectory = $this->getParameter('secure_files_attachments_directory');
            $filePath = $attachmentDirectory . '/' . $file->getFilepath();

            if ($this->filesystem->exists($filePath)) {
                $this->filesystem->remove($filePath);
            }

            $this->manager->remove($file);
            $this->manager->flush();
            $this->addFlash('success', $this->translator->trans('The file was deleted successfully'));
        } catch (\Throwable $e) {
             $this->logger->log(
                LogLevel::ERROR,
                sprintf(
                   '[SecureFilesController::delete()] Failed to delete file "%s": %s',
                $file->getFilepath(),
                $e->getMessage()
                )
            );

        $this->addFlash('danger', $this->translator->trans('An error occurred while deleting the file. Please contact support.'));
    }    
      
        return $this->redirectToRoute('app_secure_files_lists');
    }




    /**
     * Decrypt an uploaded encrypted file and return the decrypted content without storing it.
     *
     * @param Request $request
     * @return Response
     *
     * @throws \Exception on decryption failure
     */
    #[Route('/secure/files/public-decrypt', name: 'app_secure_files_public_decrypt', methods: ['GET', 'POST'])]
    public function publicDecrypt(Request $request, SubscriptionsRepository $repoSubscription): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        /**** Check if a premium member */
        $subscription = $repoSubscription->findOneBy([
            'user' => $user,

        ]);

        if($subscription->getSubscriptionPlan()->getId() === 1)
        {
            $this->addFlash('danger', $this->translator->trans('This service is for premium members.'));
            return $this->redirectToRoute('app_secure_files_lists');    
        }

        // Create the form and handle request
        $form = $this->createForm(DecryptFilesType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get('attachement')->getData();

            if (!$uploadedFile) {
                $this->addFlash('danger', $this->translator->trans('No file was uploaded.'));
                return $this->redirectToRoute('app_secure_files_public_decrypt');
            }

            try {
                // Read encrypted content
                $encryptedContent = file_get_contents($uploadedFile->getPathname());

      
               $aad = $this->encryptionKeyAad;    

                // Decrypt the file content
                $decryptedContent = $this->encryptionService->decrypt($encryptedContent, $aad);

                // Prepare response headers
                $mimeType = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
                $originalFilename = $uploadedFile->getClientOriginalName();
                $cleanFilename = preg_replace('/\.enc$/i', '', $originalFilename);

                return new Response($decryptedContent, 200, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'attachment; filename="' . $cleanFilename . '"',
                ]);
            } catch (\Exception $e) {
               
                 $this->logger->log(
                LogLevel::ERROR,
                sprintf(
                   '[SecureFilesController::publicDecrypt()] Failed to delete file "%s": %s',
                $uploadedFile->getClientOriginalName(),
                $e->getMessage()
                )
            );
                $this->addFlash('danger', $this->translator->trans('Failed to decrypt the uploaded file.'));
                return $this->redirectToRoute('app_secure_files_public_decrypt');
            }
        }

        // Render form if GET or not submitted/valid
        return $this->render('secure_files/decrypt.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    
}
