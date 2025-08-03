<?php

namespace App\Controller;

use App\Enum\LogLevel;
use App\Entity\Currency;
use App\Form\CurrencyType;
use App\Service\HtmlSanitizer;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\SystemLoggerService;

/**
 * Controller managing Currency entities.
 * 
 * Provides actions for listing, adding, and updating currencies.
 * Utilizes HTML sanitization to clean user input and ensures only one currency is set as primary.
 */
#[IsGranted("ROLE_ADMIN")]
final class CurrencyController extends AbstractController
{
    /**
     * Translator service for translating user-facing messages.
     * Logger service to log errors or warnings.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SystemLoggerService $logger
    ) {}

    /**
     * Lists all currencies.
     * 
     * @param CurrencyRepository $repoCurrency Repository to fetch currencies from database.
     * 
     * @return Response Renders a template displaying all currencies.
     */
    #[Route('/currency', name: 'app_currency')]
    public function index(CurrencyRepository $repoCurrency): Response
    {
        // Retrieve all currencies without filtering
        $currencies = $repoCurrency->findBy([]);

        // Render the currency list view
        return $this->render('currency/index.html.twig', [
            'currencies' => $currencies,
        ]);
    }

    /**
 * Handles the creation of a new currency.
 * 
 * Sanitizes inputs, manages primary currency uniqueness, and persists the new currency.
 * 
 * @param HtmlSanitizer $sanitizer Service to sanitize HTML inputs.
 * @param Request $request HTTP request containing form data.
 * @param EntityManagerInterface $manager Entity manager to handle database operations.
 * @param CurrencyRepository $repoCurrency Repository to query existing currencies.
 * 
 * @return Response Renders the currency add form or redirects on success.
 */
#[Route('/currency/add', name: 'app_currency_add')]
public function add(HtmlSanitizer $sanitizer, Request $request, EntityManagerInterface $manager, CurrencyRepository $repoCurrency): Response
{
    $currency = new Currency();

    // Create the form bound to the new Currency entity
    $form = $this->createForm(CurrencyType::class, $currency);
    $form->handleRequest($request);

    // On form submission and validation
    if ($form->isSubmitted() && $form->isValid()) {
        try {
            $currency = $form->getData();

            // Sanitize all user inputs to prevent XSS and malicious HTML
            $currency->setCode($sanitizer->purify($currency->getCode()));
            $currency->setName($sanitizer->purify($currency->getName()));
            $currency->setSymbol($sanitizer->purify($currency->getSymbol()));

            // Ensure only one primary currency exists:
            // If this currency is marked as primary, unset primary on any other currency
            if ($currency->isPrimary()) {
                $otherCurrency = $repoCurrency->findOneBy(['isPrimary' => true]);

                if ($otherCurrency) {
                    $otherCurrency->setIsPrimary(false);
                }
            }

            $manager->persist($currency);
            $manager->flush();

            $this->addFlash("success", $this->translator->trans("The currency has been added successfully."));

            // Redirect to currency list after successful addition
            return $this->redirectToRoute("app_currency");
        } catch (\Exception $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf(
                    '[CurrencyController::add()] Failed to add new currency: %s',
                    $e->getMessage()
                )
            );
            
            $this->addFlash("danger", $this->translator->trans("An error occurred while adding the currency."));
        }
    }

    // Render the add currency form if not submitted or invalid
    return $this->render('currency/add.html.twig', [
        'form' => $form->createView(),
        'update' => false,
    ]);
}

/**
 * Handles updating an existing currency identified by its slug.
 * 
 * Fetches the currency, processes form submission, sanitizes inputs,
 * maintains primary currency uniqueness, and saves changes.
 * 
 * @param HtmlSanitizer $sanitizer Service to sanitize HTML inputs.
 * @param Request $request HTTP request containing form data and slug parameter.
 * @param EntityManagerInterface $manager Entity manager to handle database operations.
 * @param CurrencyRepository $repoCurrency Repository to fetch the currency.
 * 
 * @return Response Renders the currency edit form or redirects with error/success messages.
 */
#[Route('/currency/update/{slug}', name: 'app_currency_update')]
public function update(HtmlSanitizer $sanitizer, Request $request, EntityManagerInterface $manager, CurrencyRepository $repoCurrency): Response
{
    // Find currency by slug
    $currency = $repoCurrency->findOneBy(['slug' => $request->get('slug')]);

    if (!$currency) {
        // If currency does not exist, show error and redirect
        $this->addFlash("danger", $this->translator->trans("Currency not found."));
        return $this->redirectToRoute("app_currency");
    }

    // Create form bound to the found Currency entity
    $form = $this->createForm(CurrencyType::class, $currency);
    $form->handleRequest($request);

    // On form submission and validation
    if ($form->isSubmitted() && $form->isValid()) {
        try {
            $currency = $form->getData();

            // Sanitize inputs
            $currency->setCode($sanitizer->purify($currency->getCode()));
            $currency->setName($sanitizer->purify($currency->getName()));
            $currency->setSymbol($sanitizer->purify($currency->getSymbol()));

            // Maintain uniqueness of primary currency
            if ($currency->isPrimary()) {
                $otherCurrency = $repoCurrency->findOneBy(['isPrimary' => true]);

                if ($otherCurrency && $otherCurrency !== $currency) {
                    $otherCurrency->setIsPrimary(false);
                }
            }

            $manager->flush();

            $this->addFlash("success", $this->translator->trans("Currency updated successfully."));

            // Redirect to currency list after successful update
            return $this->redirectToRoute("app_currency");
        } catch (\Exception $e) {
            $this->logger->log(
                LogLevel::ERROR,
                sprintf(
                    '[CurrencyController::update()] Failed to update currency with slug %s: %s',
                    $request->get('slug'),
                    $e->getMessage()
                )
            );
            
            $this->addFlash("danger", $this->translator->trans("An error occurred while updating the currency."));
        }
    }

    // Render the edit currency form if not submitted or invalid
    return $this->render('currency/add.html.twig', [
        'form' => $form->createView(),
        'update' => true,
    ]);
}
}
