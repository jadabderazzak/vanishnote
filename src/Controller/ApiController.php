<?php

namespace App\Controller;

use App\Form\ApiType;
use App\Entity\ApiCredential;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ApiCredentialRepository;
use App\Service\ApiTesterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller managing API credentials CRUD operations and testing.
 * 
 * Requires ROLE_ADMIN to access all routes.
 */
#[IsGranted("ROLE_ADMIN")]
final class ApiController extends AbstractController
{
    /**
     * @param TranslatorInterface $translator Translator service for flash messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}

    /**
     * Lists all API credentials.
     *
     * @param ApiCredentialRepository $repoCredential Repository for API credentials.
     * @return Response The rendered credentials list view.
     */
    #[Route('/api', name: 'app_api_credential')]
    public function index(ApiCredentialRepository $repoCredential): Response
    {
        $credentials = $repoCredential->findBy([]);
        return $this->render('api/index.html.twig', [
            'credentials' => $credentials,
        ]);
    }

    /**
     * Handles adding a new API credential.
     * 
     * Encrypts secret and public keys before persisting.
     *
     * @param Request $request The HTTP request.
     * @param EncryptionService $encryptionService Service to encrypt API keys.
     * @param EntityManagerInterface $manager Doctrine entity manager.
     * @param ApiCredentialRepository $repoCredential Repository for API credentials.
     * 
     * @return Response The rendered form or redirect after success.
     */
    #[Route('/api/add', name: 'app_api_credential_add')]
    public function add(Request $request, EncryptionService $encryptionService, EntityManagerInterface $manager, ApiCredentialRepository $repoCredential): Response
    {
        $apiCredential = new ApiCredential();
        $form = $this->createForm(ApiType::class, $apiCredential);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $apiCredential = $form->getData();
            // Check if the service already exists
            $serviceName = strtolower($apiCredential->getService());
            $existingCredential = $repoCredential->findOneBy(['service' => $serviceName]);

            if ($existingCredential) {
                $this->addFlash('warning', $this->translator->trans("This service already exists. Please modify the existing one."));
                return $this->redirectToRoute('app_api_credential');
            }

            // Get raw (unencrypted) keys from the form fields
            $secretKeyRaw = $form->get('secretKeyEncrypted')->getData();
            $publicKeyRaw = $form->get('publicKeyEncrypted')->getData();

            // Encrypt keys if present
            $secretKeyEncrypted = $encryptionService->encrypt($secretKeyRaw);
            $publicKeyEncrypted = !empty($publicKeyRaw) ? $encryptionService->encrypt($publicKeyRaw) : "";

            $apiCredential->setSecretKeyEncrypted($secretKeyEncrypted);
            $apiCredential->setPublicKeyEncrypted($publicKeyEncrypted);

            $manager->persist($apiCredential);
            $manager->flush();

            $this->addFlash("success", $this->translator->trans("Api key added successfuly!"));
            return $this->redirectToRoute("app_api_credential");
        }

        return $this->render('api/add.html.twig', [
            'form' => $form->createView(),
            'update' => false,
        ]);
    }

    /**
     * Handles updating an existing API credential.
     * 
     * Encrypts keys before saving.
     *
     * @param Request $request The HTTP request.
     * @param EncryptionService $encryptionService Service to encrypt API keys.
     * @param EntityManagerInterface $manager Doctrine entity manager.
     * @param ApiCredentialRepository $repoCredential Repository for API credentials.
     * 
     * @return Response The rendered form or redirect after update.
     */
    #[Route('/api/update/{id}', name: 'app_api_credential_update')]
    public function update(Request $request, EncryptionService $encryptionService, EntityManagerInterface $manager, ApiCredentialRepository $repoCredential): Response
    {
        $apiCredential = $repoCredential->findOneBy(['id' => $request->get('id')]);

        if (!$apiCredential) {
            $this->addFlash("danger", $this->translator->trans("No Api found."));
            return $this->redirectToRoute("app_api_credential");
        }

        $form = $this->createForm(ApiType::class, $apiCredential);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $apiCredential = $form->getData();

            $secretKeyRaw = $apiCredential->getSecretKeyEncrypted();
            $publicKeyRaw = $apiCredential->getPublicKeyEncrypted();

            $secretKeyEncrypted = $encryptionService->encrypt($secretKeyRaw);
            $publicKeyEncrypted = !empty($publicKeyRaw) ? $encryptionService->encrypt($publicKeyRaw) : "";

            $apiCredential->setSecretKeyEncrypted($secretKeyEncrypted);
            $apiCredential->setPublicKeyEncrypted($publicKeyEncrypted);

            $manager->flush();

            $this->addFlash("success", $this->translator->trans("Api key added successfuly!"));

            return $this->redirectToRoute("app_api_credential");
        }

        return $this->render('api/add.html.twig', [
            'form' => $form->createView(),
            'update' => true,
        ]);
    }

    /**
     * Tests the validity of an API credential by ID.
     *
     * Calls the ApiTesterService to validate the API keys.
     *
     * @param int $id The API credential ID.
     * @param ApiCredentialRepository $repo Repository to fetch the credential.
     * @param ApiTesterService $apiTester Service that tests the API key validity.
     * 
     * @return Response Redirects back to the credentials list with flash message.
     */
    #[Route('/api/credential/test/{id}', name: 'app_api_credential_test')]
    public function test(int $id, ApiCredentialRepository $repo, ApiTesterService $apiTester): Response
    {
        $credential = $repo->find($id);

        if (!$credential) {
            $this->addFlash('danger', $this->translator->trans('API credential not found.'));
            return $this->redirectToRoute('app_api_credential');
        }

        try {
            $result = $apiTester->test($credential);

            if ($result) {
                $this->addFlash('success', $this->translator->trans('API key is valid.'));
            } else {
                $this->addFlash('warning', $this->translator->trans('API key is invalid or unreachable.'));
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->translator->trans('Error testing API: ') . $e->getMessage());
        }

        return $this->redirectToRoute('app_api_credential'); 
    }
}
