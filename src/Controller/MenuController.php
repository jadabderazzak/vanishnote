<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller responsible for rendering menus (public and authenticated).
 *
 * Contains actions for displaying the public menu (accessible to all)
 * and the authenticated menu (visible to logged-in users).
 */
final class MenuController extends AbstractController
{
    /**
     * Displays the main public menu for all users (unauthenticated).
     *
     * @return Response
     */
    #[Route('/menu', name: 'app_menu')]
    /**
     * Displays the main public menu.
     *
     * Typically shown on the homepage or before user authentication.
     *
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('menu/index.html.twig');
    }

    
    #[Route('/menu_authenticated', name: 'app_menu_authenticated')]
    /**
     * Displays the menu intended for authenticated users.
     *
     * Shows the admin menu if user has ROLE_ADMIN,
     * otherwise shows the normal user menu.
     *
     * @return Response
     */
    public function authenticated(): Response
    {
        $user = $this->getUser();

        if ($user && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            // Render the admin menu view for admins
            return $this->render('menu/menu_admin.html.twig');
        }

        // Render the standard menu view for non-admins
        return $this->render('menu/menu_auth.html.twig');
}
}
