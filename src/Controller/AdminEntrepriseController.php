<?php

namespace App\Controller;

use App\Entity\AdminEntreprise;
use App\Form\EntrepriseFormType;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
final class AdminEntrepriseController extends AbstractController
{
    /**
     * @param TranslatorInterface $translator Translator service for flash messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}
    #[Route('/admin/entreprise', name: 'app_admin_entreprise')]
    public function index(): Response
    {
        return $this->render('admin_entreprise/index.html.twig', [
            'controller_name' => 'AdminEntrepriseController',
        ]);
    }

   /**
     * @Route("/admin/entreprise/configuration", name="app_admin_entreprise_configuration")
     * Show and handle entreprise config form
     */
    #[Route('/admin/entreprise/configuration', name: 'app_admin_entreprise_configuration')]
    public function configuration(Request $request, EntityManagerInterface $em, CurrencyRepository $repoCurrency): Response
    {
        // Récupérer la config existante ou en créer une nouvelle
        $adminEntreprise = $em->getRepository(AdminEntreprise::class)->find(1) ?? new AdminEntreprise();
        $currency = $repoCurrency->findOneBy([
            'isPrimary' => true
        ]);
       /** @var User $user */
        $user = $this->getUser();
        if($currency){
            $adminEntreprise->setDefaultCurrency($currency->getSymbol());
        }
        $adminEntreprise->setCompanyEmail($user->getEmail());
        $form = $this->createForm(EntrepriseFormType::class, $adminEntreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $extentions = ['jpeg', 'jpg', 'png', 'JPEG', 'JPG', 'PNG','svg','SVG'];
            /** @var UploadedFile|null $logoFile */
            $logo = $form->get('logoPath')->getData();
            if ($logo) {
                $uploads_directory = $this->getParameter(
                    'logos_directory'
                );
                // Extension verification
                $extension = $logo->guessExtension();
                if (!in_array($extension, $extentions)) {
                    $this->addFlash('danger', $this->translator->trans('Invalid logo format. Only JPG and PNG files are allowed.'));
                    return $this->redirectToRoute('app_admin_entreprise_configuration');
                }

                // Size verification
                if (filesize($logo) > 5000000) {
                    $this->addFlash('danger', $this->translator->trans('Logo is too large. Maximum allowed size is 5MB.'));
                     return $this->redirectToRoute('app_admin_entreprise_configuration');
                }
                    if ($adminEntreprise->getLogoPath()) {
                        $fs = new Filesystem();
                        $fs->remove(
                            $uploads_directory . '/' . $adminEntreprise->getLogoPath()
                        );
                    }
                
                    if (
                        filesize($logo) < 50000000 &&
                        in_array($logo->guessExtension(), $extentions)
                    ) {

                        $filename ='logo.'. $logo->guessExtension();
                        $logo->move($uploads_directory, $filename); 
                        $adminEntreprise->setLogoPath($filename);
                     }

            }
            $em->persist($adminEntreprise);
            $em->flush();

            $this->addFlash('success', $this->translator->trans('Settings saved successfully.'));

            return $this->redirectToRoute('app_admin_entreprise_configuration');
        }

        return $this->render('admin_entreprise/index.html.twig', [
            'form' => $form->createView(),
            'adminEntreprise'=> $adminEntreprise
        ]);
    }
}
