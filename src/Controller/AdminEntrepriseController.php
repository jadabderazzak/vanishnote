<?php

namespace App\Controller;

use App\Entity\AdminEntreprise;
use App\Form\EntrepriseFormType;
use App\Repository\CurrencyRepository;
use App\Service\SystemLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller to manage entreprise configuration for admins.
 */
#[IsGranted("ROLE_ADMIN")]
final class AdminEntrepriseController extends AbstractController
{
    /**
     * AdminEntrepriseController constructor.
     *
     * @param TranslatorInterface    $translator Translator service for flash messages.
     * @param SystemLoggerService    $logger     Logger service to record system events/errors.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

    /**
     * Displays the entreprise admin home page.
     *
     * @return Response
     */
    #[Route('/admin/entreprise', name: 'app_admin_entreprise')]
    public function index(): Response
    {
        return $this->render('admin_entreprise/index.html.twig', [
            'controller_name' => 'AdminEntrepriseController',
        ]);
    }

    /**
     * Show and handle the entreprise configuration form.
     *
     * @param Request                $request       HTTP request instance.
     * @param EntityManagerInterface $em            Doctrine entity manager.
     * @param CurrencyRepository     $repoCurrency  Currency repository to fetch currency data.
     *
     * @return Response
     */
    #[Route('/admin/entreprise/configuration', name: 'app_admin_entreprise_configuration')]
    public function configuration(Request $request, EntityManagerInterface $em, CurrencyRepository $repoCurrency): Response
    {
        // Retrieve existing config or create a new one.
        $adminEntreprise = $em->getRepository(AdminEntreprise::class)->find(1) ?? new AdminEntreprise();

        $currency = $repoCurrency->findOneBy(['isPrimary' => true]);

        /** @var User $user */
        $user = $this->getUser();

        if ($currency) {
            $adminEntreprise->setDefaultCurrency($currency->getSymbol());
        }

        $adminEntreprise->setCompanyEmail($user->getEmail());

        $form = $this->createForm(EntrepriseFormType::class, $adminEntreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $allowedExtensions = ['jpeg', 'jpg', 'png'];

            /** @var UploadedFile|null $logo */
            $logo = $form->get('logoPath')->getData();

            if ($logo) {
                $uploadsDirectory = $this->getParameter('logos_directory');

                $extension = strtolower($logo->guessExtension());

                // Validate extension
                if (!in_array($extension, $allowedExtensions, true)) {
                    $this->addFlash('danger', $this->translator->trans('Invalid logo format. Only JPG and PNG files are allowed.'));
                    return $this->redirectToRoute('app_admin_entreprise_configuration');
                }

                // Validate size (max 5MB)
                if ($logo->getSize() > 5_000_000) {
                    $this->addFlash('danger', $this->translator->trans('Logo is too large. Maximum allowed size is 5MB.'));
                    return $this->redirectToRoute('app_admin_entreprise_configuration');
                }

                // Remove old logo if exists
                if ($adminEntreprise->getLogoPath()) {
                    $fs = new Filesystem();
                    try {
                        $fs->remove($uploadsDirectory . '/' . $adminEntreprise->getLogoPath());
                    } catch (\Throwable $e) {
                        $this->logger->log(
                            \App\Enum\LogLevel::ERROR,
                            sprintf('Failed to remove old logo: %s', $e->getMessage())
                        );
                    }
                }

                // Move and set new logo
                $filename = 'logo.' . $extension;
                $logo->move($uploadsDirectory, $filename);
                $adminEntreprise->setLogoPath($filename);
            }

            $em->persist($adminEntreprise);
            $em->flush();

            $this->addFlash('success', $this->translator->trans('Settings saved successfully.'));

            return $this->redirectToRoute('app_admin_entreprise_configuration');
        }

        return $this->render('admin_entreprise/index.html.twig', [
            'form' => $form->createView(),
            'adminEntreprise' => $adminEntreprise,
        ]);
    }
}
