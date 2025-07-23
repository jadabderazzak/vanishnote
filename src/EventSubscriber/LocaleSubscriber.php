<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * LocaleSubscriber sets the request locale based on the `_locale` query parameter,
 * the session, or falls back to a default locale.
 *
 * This allows the application to handle multilingual content by switching languages
 * dynamically based on the user's preference or session.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @var string The default locale to use when none is set
     */
    private $defaultLocale;

    /**
     * Constructor.
     *
     * @param string $defaultLocale The default locale (e.g., 'fr')
     */
    public function __construct(string $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * This method is called during the kernel.request event.
     *
     * It checks if the request has a previous session and attempts to set
     * the locale from the `_locale` query parameter, session, or default value.
     *
     * @param RequestEvent $event The request event
     */
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        // Get the locale from the URL query, then from session, or fallback to default
        $locale = $request->query->get('_locale')
            ?? $request->getSession()->get('_locale')
            ?? $this->defaultLocale;

        $request->setLocale($locale);
    }

    /**
     * Returns the list of events this subscriber listens to.
     *
     * @return array The array of event names and corresponding methods
     */
    public static function getSubscribedEvents()
    {
        return [
            // Listen to kernel.request with a high priority
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
