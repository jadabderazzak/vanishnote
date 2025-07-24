<?php

namespace App\Controller;

use App\Service\HtmlSanitizer;
use App\Entity\SubscriptionPlan;
use App\Form\SubscriptionPlanType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\SubscriptionPlanRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller to manage subscription plans.
 *
 * Provides CRUD operations for SubscriptionPlan entities:
 *  - List all plans
 *  - Create a new plan
 *  - Update existing plans
 *  - View details of a plan
 *  - Deactivate and reactivate plans
 */
final class SubscriptionPlanController extends AbstractController
{
    /**
     * Translator service for translating messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {}

    /**
     * Lists all subscription plans.
     *
     * @param SubscriptionPlanRepository $repoSubscription Repository to fetch plans.
     * @return Response Rendered HTML response with list of plans.
     */
    #[Route('/subscription/plan', name: 'app_subscription_plan')]
    public function index(SubscriptionPlanRepository $repoSubscription): Response
    {
        $plans = $repoSubscription->findBy([]);
        return $this->render('subscription_plan/index.html.twig', [
            'plans' => $plans,
        ]);
    }

    /**
     * Adds a new subscription plan.
     *
     * Handles form submission, sanitizes inputs,
     * checks for duplicate names (case-insensitive),
     * persists the new plan if valid.
     *
     * @param HtmlSanitizer $sanitizer Service to sanitize inputs.
     * @param Request $request HTTP request object.
     * @param EntityManagerInterface $manager Doctrine entity manager.
     * @param SubscriptionPlanRepository $repoSubscription Repository to check for duplicates.
     * @return Response Rendered form or redirect after success.
     */
    #[Route('/subscription/plan/add', name: 'app_subscription_plan_add')]
    public function add(HtmlSanitizer $sanitizer, Request $request, EntityManagerInterface $manager, SubscriptionPlanRepository $repoSubscription): Response
    {
        $plan = new SubscriptionPlan();
        $plan->setIsActive(true);
        $form = $this->createForm(SubscriptionPlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plan = $form->getData();

            $plan->setName($sanitizer->purify($plan->getName()));
            $plan->setDescription($sanitizer->purify($plan->getDescription()));

            $existingPlan = $repoSubscription->findOneByNameCaseInsensitive($plan->getName());
            if ($existingPlan !== null) {
                $this->addFlash('error', $this->translator->trans('A subscription plan with this name already exists.'));
                return $this->render('subscription_plan/add.html.twig', [
                    'form' => $form->createView(),
                    'update' => false
                ]);
            }

            $manager->persist($plan);
            $manager->flush();

            $this->addFlash('success', $this->translator->trans('Subscription plan created successfully.'));

            return $this->redirectToRoute('app_subscription_plan');
        }

        return $this->render('subscription_plan/add.html.twig', [
            'form' => $form->createView(),
            'update' => false
        ]);
    }

    /**
     * Updates an existing subscription plan identified by slug.
     *
     * Loads the plan by slug, handles the update form,
     * sanitizes inputs and persists changes.
     * Redirects to listing if plan not found.
     *
     * @param HtmlSanitizer $sanitizer Service to sanitize inputs.
     * @param Request $request HTTP request.
     * @param EntityManagerInterface $manager Doctrine entity manager.
     * @param SubscriptionPlanRepository $repoSubscription Repository to find plan by slug.
     * @return Response Rendered form or redirect after success/error.
     */
    #[Route('/subscription/plan/update/{slug}', name: 'app_subscription_plan_update')]
    public function update(HtmlSanitizer $sanitizer, Request $request, EntityManagerInterface $manager, SubscriptionPlanRepository $repoSubscription): Response
    {
        $plan = $repoSubscription->findOneBy(['slug' => $request->get('slug')]);

        if (!$plan) {
            $this->addFlash("danger", $this->translator->trans("Subscription plan not found!"));
            return $this->redirectToRoute("app_subscription_plan");
        }

        $form = $this->createForm(SubscriptionPlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plan = $form->getData();
            $plan->setName($sanitizer->purify($plan->getName()));
            $plan->setDescription($sanitizer->purify($plan->getDescription()));

            $manager->flush();

            $this->addFlash('success', 'Subscription plan updated successfully.');

            return $this->redirectToRoute('app_subscription_plan');
        }

        return $this->render('subscription_plan/add.html.twig', [
            'form' => $form->createView(),
            'update' => true
        ]);
    }

    /**
     * Displays details of a subscription plan by slug.
     *
     * @param Request $request HTTP request.
     * @param SubscriptionPlanRepository $repoSubscription Repository to find plan.
     * @return Response Rendered details view or redirect if not found.
     */
    #[Route('/subscription/plan/view/{slug}', name: 'app_subscription_plan_view')]
    public function view(Request $request, SubscriptionPlanRepository $repoSubscription): Response
    {
        $plan = $repoSubscription->findOneBy(['slug' => $request->get('slug')]);

        if (!$plan) {
            $this->addFlash("danger", $this->translator->trans("Subscription plan not found!"));
            return $this->redirectToRoute("app_subscription_plan");
        }

        return $this->render('subscription_plan/view.html.twig', [
            'plan' => $plan,
        ]);
    }

    /**
     * Deactivates a subscription plan by slug.
     *
     * Marks the plan as inactive and persists the change.
     * Redirects to the plan detail view.
     *
     * @param Request $request HTTP request.
     * @param SubscriptionPlanRepository $repoSubscription Repository to find plan.
     * @param EntityManagerInterface $manager Doctrine entity manager.
     * @return Response Redirect to plan view or listing if not found.
     */
    #[Route('/subscription/plan/desactivate/{slug}', name: 'app_subscription_plan_desactivate')]
    public function desactivate(Request $request, SubscriptionPlanRepository $repoSubscription, EntityManagerInterface $manager): Response
    {
        $plan = $repoSubscription->findOneBy(['slug' => $request->get('slug')]);

        if (!$plan) {
            $this->addFlash("danger", $this->translator->trans("Subscription plan not found!"));
            return $this->redirectToRoute("app_subscription_plan");
        }

        $plan->setIsActive(false);
        $manager->flush();

        $this->addFlash("success", $this->translator->trans("Subscription plan successfully deactivated"));

        return $this->redirectToRoute("app_subscription_plan_view", [
            'slug' => $plan->getSlug()
        ]);
    }

    /**
     * Reactivates a previously deactivated subscription plan by slug.
     *
     * Marks the plan as active and persists the change.
     * Redirects to the plan detail view.
     *
     * @param Request $request HTTP request.
     * @param SubscriptionPlanRepository $repoSubscription Repository to find plan.
     * @param EntityManagerInterface $manager Doctrine entity manager.
     * @return Response Redirect to plan view or listing if not found.
     */
    #[Route('/subscription/plan/reactivate/{slug}', name: 'app_subscription_plan_reactivate')]
    public function reactivate(Request $request, SubscriptionPlanRepository $repoSubscription, EntityManagerInterface $manager): Response
    {
        $plan = $repoSubscription->findOneBy(['slug' => $request->get('slug')]);

        if (!$plan) {
            $this->addFlash("danger", $this->translator->trans("Subscription plan not found!"));
            return $this->redirectToRoute("app_subscription_plan");
        }

        $plan->setIsActive(true);
        $manager->flush();

        $this->addFlash("success", $this->translator->trans("Subscription plan successfully reactivated"));

        return $this->redirectToRoute("app_subscription_plan_view", [
            'slug' => $plan->getSlug()
        ]);
    }
}
