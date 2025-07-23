<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    /**
     * This method handles the homepage access.
     *
     * - If the user is logged in and has the ROLE_ADMIN, they are redirected to the admin dashboard.
     * - If the user has the ROLE_USER, they are redirected to the client dashboard.
     * - If the user is not authenticated, the default homepage is rendered.
     *
     * @param Security $security Used to retrieve the currently authenticated user.
     * @return Response The response object, either a redirection or the rendered homepage.
     */
    public function index(): Response
    {
        // Get the currently logged-in user
        $user = $this->getUser();

        if ($user) {
            $roles = $user->getRoles();

            // Redirect admin users to the admin dashboard
            if (in_array('ROLE_ADMIN', $roles, true)) {
                return $this->redirectToRoute('app_admin');
            }

            // Redirect regular users to the client dashboard
            if (in_array('ROLE_USER', $roles, true)) {
                return $this->redirectToRoute('app_clients');
            }
        }

        // Default behavior: show the public homepage
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
    
    #[Route('/change/locales/{locale}', name: 'change_locales')]
    public function change_locales($locale, Request $request): Response
    {
        
        $session = $request->getSession();
        $session->set('_locale', $locale);

        $request->setLocale($session->get('_locale'));
        

        

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));   
    }
}
