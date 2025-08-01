<?php

namespace App\Controller;

use DateTime;
use App\Entity\User;
use App\Entity\Client;
use Stripe\Subscription;
use App\Form\RegisterType;
use App\Entity\Subscriptions;
use App\Service\HtmlSanitizer;
use Symfony\Component\Form\FormError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\SubscriptionPlanRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
     public function __construct(private readonly TranslatorInterface $translator)
    {}
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    
     #[Route(path: '/register', name: 'app_register', methods: ['GET', 'POST'])]
      public function register(HtmlSanitizer $sanitizer,Request $request, EntityManagerInterface $manager, SubscriptionPlanRepository $repoSubscription): Response
    {
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);

       
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $termsAccepted = $request->get('terms');
            if (!$termsAccepted) {
                $this->addFlash("danger", $this->translator->trans('You must accept the terms and conditions.'));
                return $this->redirectToRoute("app_register");
            }
            $password = $form->get('password')->getData();
            $confirmPassword = $form->get('confirm_password')->getData();
        
        if ($password !== $confirmPassword) {
                $form->get('confirm_password')->addError(new FormError($this->translator->trans('Passwords do not match')));
            } else {

            
                // Sanitize raw input to prevent XSS attacks
                // Even though Symfony escapes output by default in Twig, we sanitize user input before persisting it
                // to ensure no malicious HTML, scripts, or unwanted tags are stored in the database.
                // This adds an additional layer of security and protects other parts of the system (e.g., APIs, exports, or email rendering).
                $nameRaw = $request->get('register')['name'] ?? '';
                $emailRaw = $request->get('register')['email'] ?? '';

                $cleanName = $sanitizer->purify($nameRaw);
                $cleanEmail = $sanitizer->purify($emailRaw);

                $user = $form->getData();
                $user->setName($cleanName);
                $user->setIp($request->getClientIp());
                $user->setHasAccess(true);
                $user->setCreatedAt(new DateTime());
                $user->setEmail($cleanEmail);

                try {
                    // Persist and save the new user to the database
                    // After sanitizing and validating the form, we persist the user entity and flush to commit the changes.
                    // A success flash message is then shown to inform the user of successful registration.

                    $manager->persist($user);
                    $manager->flush();

                    // Create a new Client entity linked to the newly created User.
                    $client = new Client();

                    // Set the Client's name using a sanitized variable.
                    $client->setName($cleanName)
                        // Default the client type as an individual (not a company).
                        ->setIsCompany(false)
                        // Initialize the count of notes created by this client to zero.
                        ->setNumberNotesCreated(0)
                        // Link this Client to the associated User entity.
                        ->setUser($user);

              
                    // This stores the client data and the relationship to the User.
                    $manager->persist($client);
                    /************************* Adding Free subscription  */
                    $subscriptionPlan = $repoSubscription->findOneBy([
                        'id' => 1
                    ]);
                    $subscription = new Subscriptions();
                    $subscription->setUser($user)
                                ->setStatus(true)
                                ->setStartedAt(new DateTime())
                                ->setNotesCreated(0)
                                ->setCreatedAt(new DateTime())
                                ->setSubscriptionPlan($subscriptionPlan);
                    // Persist and save the Client subscription to the database.
                    $manager->persist($subscription);
   
                    // Persist and save the Client entity  to the database
                    $manager->persist($client);
                    $manager->flush();    


                    $this->addFlash("success", $this->translator->trans("Your account has been successfully created. You can now log in."));
                    return $this->redirectToRoute("app_login");
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash("danger", $this->translator->trans("This email address is already in use."));
                }

                
            }
        }
        return $this->render('security/register.html.twig',[
            'registrationForm'=> $form->createView()
        ]);
    }
}
