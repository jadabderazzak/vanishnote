<?php

namespace App\Controller;

use App\Enum\LogLevel;
use App\Repository\ClientRepository;
use App\Service\SystemLoggerService;
use App\Service\SecureEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SecureFilesRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
final class AdminSecureFileController extends AbstractController
{
    //$encryptionKeyAad Additional Authenticated Data (AAD) key used in encryption/decryption.
    private readonly string $encryptionKeyAad;
    /**
     * Constructor.
     *
     * @param TranslatorInterface $translator Translator service for translating user-facing messages.
     * @param EntityManagerInterface $manager Doctrine entity manager for database operations.
     * @param Filesystem $filesystem Filesystem service for file handling operations.
     * @param SecureFilesRepository $repoFiles Repository for managing Secure Files.
     * @param SystemLoggerService $logger System logging service
     * @param PaginatorInterface $paginator Pagination service for listing results.
     * @param SecureEncryptionService $encryptionService Service handling encryption and decryption.
     * 
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $manager,
        private readonly Filesystem $filesystem,
        private readonly PaginatorInterface $paginator,
        private readonly SecureFilesRepository $repoFiles,
        private readonly SecureEncryptionService $encryptionService,
        private readonly SystemLoggerService $logger
        
    ) {
        // The key must be the correct size (e.g., 32 bytes for AES-256)
        $this->encryptionKeyAad = $_ENV['APP_ENCRYPTION_KEY_AAD'] ?? '';

        }
    #[Route('/admin/secure/files/lists', name: 'app_admin_secure_file')]
    public function index(ClientRepository $repoClient,Request $request): Response
    {
        $files = $this->repoFiles->findBy([]);
        $filesOwners = [];

        foreach ($files as $file) {
            

            try {
              
                $owner = $repoClient->findOneBy([
                    'user' => $file->getUser()
                ]);

                $filesOwners[] = [
                    'id' => $file->getId(),
                    'slug' => $file->getSlug(),
                    'filename' => $file->getFilename(),
                    'mimeType' => $file->getMimeType(),
                    'owner' => $owner->getName(),
                    'client_slug' => $owner->getSlug(),
                    'size' => $file->getSize(),
                    'uploadedAt' => $file->getUploadedAt(),
                  
                 
                ];
            } catch (\Exception $e) {
                // Skip notes that cannot be decrypted
                continue;
            }
        }
        $paginatedFiles = $this->paginator->paginate($filesOwners, $request->query->getInt('page', 1), 15); 
        return $this->render('admin_secure_file/index.html.twig', [
            'files' => $paginatedFiles,
        ]);
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
    #[Route('/admin/secure/files/delete/{slug}', name: 'app_admin_secure_file_delete')]
    public function delete(Request $request): Response
    {
        $file = $this->repoFiles->findOneBy([
            'slug' => $request->get('slug')
        ]);
      if (!$file) {
        $this->addFlash('danger', $this->translator->trans('The requested file could not be found.'));
        return $this->redirectToRoute('app_admin_secure_file');
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
                   '[AdminSecureFileController::delete()] Failed to delete file "%s": %s',
                $file->getFilepath(),
                $e->getMessage()
                )
            );

        $this->addFlash('danger', $this->translator->trans('An error occurred while deleting the file. Please contact support.'));
    }    
      
        return $this->redirectToRoute('app_admin_secure_file');
    }

    /**
     * Decrypt an uploaded encrypted file and return the decrypted content without storing it.
     *
     * @param Request $request
     * @return Response
     *
     * @throws \Exception on decryption failure
     */
    #[Route('/admin/secure/files/public-decrypt/{slug}', name: 'app_admin_secure_file_download', methods: ['GET', 'POST'])]
    public function publicDecrypt(Request $request): Response
    {
         $file = $this->repoFiles->findOneBy([
            'slug' => $request->get('slug')
        ]);
        if (!$file) {
        $this->addFlash('danger', $this->translator->trans('The requested file could not be found.'));
        return $this->redirectToRoute('app_admin_secure_file');
        }
            try {
                $attachmentDirectory = $this->getParameter('secure_files_attachments_directory');
                $filePath = $attachmentDirectory . '/' . $file->getFilepath();
            
                // Read encrypted content
                $encryptedContent = file_get_contents($filePath);

                
               $aad = $this->encryptionKeyAad;    

                // Decrypt the file content
                $decryptedContent = $this->encryptionService->decrypt($encryptedContent, $aad);

                // Prepare response headers
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
                $originalFilename = $file->getFilename();
                $cleanFilename = preg_replace('/\.enc$/i', '', $originalFilename);

               
            } catch (\Exception $e) {
               
                 $this->logger->log(
                LogLevel::ERROR,
                sprintf(
                   '[AdminSecureFileController::publicDecrypt()] Failed to decrypt file "%s": %s',
                $file->getFilename(),
                $e->getMessage()
                )
            );
                $this->addFlash('danger', $this->translator->trans('Failed to decrypt the uploaded file.'));
                return $this->redirectToRoute('app_admin_secure_file');
            }
            
             return new Response($decryptedContent, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $cleanFilename . '"',
            ]);

    }
}
