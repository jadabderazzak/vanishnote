<?php

namespace App\Controller;

use App\Form\ApiType;
use App\Entity\ApiCredential;
use App\Service\EncryptionService;
use App\Service\ApiTesterService;
use App\Service\SystemLoggerService;
use App\Enum\LogLevel;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ApiCredentialRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller responsible for managing API credentials (CRUD + validation).
 * Requires ROLE_ADMIN to access all actions.
 */
#[IsGranted("ROLE_ADMIN")]
final class ApiController extends AbstractController
{
    /**
     * Constructor.
     *
     * @param TranslatorInterface $translator Service for translating flash messages
     * @param SystemLoggerService $logger Service for recording logs in database
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

    /**
     * Displays all API credentials.
     *
     * @param ApiCredentialRepository $repoCredential
     * @return Response
     */
    #[Route('/api', name: 'app_api_credential')]
    public function index(ApiCredentialRepository $repoCredential): Response
    {
        try {
            $credentials = $repoCredential->findBy([]);
        } catch (\Throwable $e) {
            $this->logger->log(LogLevel::ERROR, 'Failed to fetch API credentials: ' . $e->getMessage());
            $this->addFlash('danger', $this->translator->trans('Unable to load API credentials.'));
            $credentials = [];
        }

        return $this->render('api/index.html.twig', [
            'credentials' => $credentials,
        ]);
    }

    /**
     * Handles creation of a new API credential with encryption.
     *
     * @param Request $request
     * @param EncryptionService $encryptionService
     * @param EntityManagerInterface $manager
     * @param ApiCredentialRepository $repoCredential
     * @return Response
     */
    #[Route('/api/add', name: 'app_api_credential_add')]
    public function add(Request $request, EncryptionService $encryptionService, EntityManagerInterface $manager, ApiCredentialRepository $repoCredential): Response
    {
        $apiCredential = new ApiCredential();
        $form = $this->createForm(ApiType::class, $apiCredential);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $apiCredential = $form->getData();
                $serviceName = strtolower($apiCredential->getService());

                if ($repoCredential->findOneBy(['service' => $serviceName])) {
                    $this->addFlash('warning', $this->translator->trans("This service already exists. Please modify the existing one."));
                    return $this->redirectToRoute('app_api_credential');
                }

                $secretKeyRaw = $form->get('secretKeyEncrypted')->getData();
                $publicKeyRaw = $form->get('publicKeyEncrypted')->getData();
                $webhookSecretRaw = $form->get('webhookSecretEncrypted')->getData();

                $apiCredential->setSecretKeyEncrypted($encryptionService->encrypt($secretKeyRaw));
                $apiCredential->setPublicKeyEncrypted(!empty($publicKeyRaw) ? $encryptionService->encrypt($publicKeyRaw) : '');
                $apiCredential->setWebhookSecretEncrypted(!empty($webhookSecretRaw) ? $encryptionService->encrypt($webhookSecretRaw) : '');

                $manager->persist($apiCredential);
                $manager->flush();

                $this->addFlash("success", $this->translator->trans("API key added successfully!"));
                return $this->redirectToRoute("app_api_credential");

            } catch (\Throwable $e) {
                $this->logger->log(LogLevel::ERROR, '[ApiController::add()] Error adding API credential: ' . $e->getMessage());
                $this->addFlash('danger', $this->translator->trans('An error occurred while adding the API key.'));
            }
        }

        return $this->render('api/add.html.twig', [
            'form' => $form->createView(),
            'update' => false,
        ]);
    }

    /**
     * Updates an existing API credential and re-encrypts keys.
     *
     * @param Request $request
     * @param EncryptionService $encryptionService
     * @param EntityManagerInterface $manager
     * @param ApiCredentialRepository $repoCredential
     * @return Response
     */
    #[Route('/api/update/{id}', name: 'app_api_credential_update')]
    public function update(Request $request, EncryptionService $encryptionService, EntityManagerInterface $manager, ApiCredentialRepository $repoCredential): Response
    {
        $id = $request->get('id');

        try {
            $apiCredential = $repoCredential->find($id);

            if (!$apiCredential) {
                $this->addFlash("danger", $this->translator->trans("No API credential found."));
                return $this->redirectToRoute("app_api_credential");
            }

            $form = $this->createForm(ApiType::class, $apiCredential);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $secretKeyRaw = $apiCredential->getSecretKeyEncrypted();
                $publicKeyRaw = $apiCredential->getPublicKeyEncrypted();
                $webhookSecretRaw = $apiCredential->getWebhookSecretEncrypted();

                $apiCredential->setSecretKeyEncrypted($encryptionService->encrypt($secretKeyRaw));
                $apiCredential->setPublicKeyEncrypted(!empty($publicKeyRaw) ? $encryptionService->encrypt($publicKeyRaw) : '');
                $apiCredential->setWebhookSecretEncrypted(!empty($webhookSecretRaw) ? $encryptionService->encrypt($webhookSecretRaw) : '');

                $manager->flush();

                $this->addFlash("success", $this->translator->trans("API key updated successfully!"));
                return $this->redirectToRoute("app_api_credential");
            }

        } catch (\Throwable $e) {
            $this->logger->log(LogLevel::ERROR, '[ApiController::update()] Error updating API credential ID '.$id.': ' . $e->getMessage());
            $this->addFlash("danger", $this->translator->trans("An error occurred while updating the API key."));
        }

        return $this->render('api/add.html.twig', [
            'form' => $form->createView(),
            'update' => true,
        ]);
    }

    /**
     * Tests the connection or validity of a given API credential.
     *
     * @param int $id ID of the API credential to test
     * @param ApiCredentialRepository $repo Repository to retrieve credentials
     * @param ApiTesterService $apiTester Service to validate credentials
     * @return Response
     */
    #[Route('/api/credential/test/{id}', name: 'app_api_credential_test')]
    public function test(int $id, ApiCredentialRepository $repo, ApiTesterService $apiTester): Response
    {
        try {
            $credential = $repo->find($id);

            if (!$credential) {
                $this->addFlash('danger', $this->translator->trans('API credential not found.'));
                return $this->redirectToRoute('app_api_credential');
            }

            $result = $apiTester->test($credential);

            if ($result) {
                $this->addFlash('success', $this->translator->trans('API key is valid.'));
            } else {
                $this->addFlash('warning', $this->translator->trans('API key is invalid or unreachable.'));
            }

        } catch (\Throwable $e) {
            $this->logger->log(LogLevel::ERROR, '[ApiController::test()] : Error testing API credential ID '.$id.': ' . $e->getMessage());
            $this->addFlash('danger', $this->translator->trans('Error testing API key: ') . $e->getMessage());
        }

        return $this->redirectToRoute('app_api_credential');
    }
}
