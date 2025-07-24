<?php
// src/Form/EventSubscriber/FormSanitizeSubscriber.php
namespace App\Form\EventSubscriber;

use App\Service\HtmlSanitizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * FormSanitizeSubscriber is a global event subscriber that listens to all form submissions
 * and sanitizes all string input data to prevent XSS attacks by purifying the HTML content.
 * 
 * This subscriber hooks into the PRE_SUBMIT event of Symfony forms to sanitize the raw
 * submitted data before it is bound to the form model.
 * 
 * It recursively processes the submitted data array, purifying every string value it contains.
 * 
 * Usage:
 *  - Register this subscriber as a service with the "kernel.event_subscriber" tag.
 *  - It will automatically be applied to all forms in your Symfony application.
 * 
 * Benefits:
 *  - Centralizes XSS sanitization logic, avoiding the need to manually purify inputs in each controller.
 *  - Ensures consistent input sanitation across the app.
 * 
 * Important notes:
 *  - If a field contains only malicious HTML (e.g. <script> tags) and is completely stripped,
 *    it may become empty, so Symfony's validation constraints will catch it if required.
 *  - You can extend or modify the sanitizeArray method to exclude certain fields or handle special cases.
 */
class FormSanitizeSubscriber implements EventSubscriberInterface
{
    public function __construct(private HtmlSanitizer $sanitizer)
    {
        
    }

    /**
     * Returns the events this subscriber wants to listen to.
     * Here, it subscribes to the PRE_SUBMIT form event.
     *
     * @return array<string, string> Event name to method mapping.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
        ];
    }

   /**
     * Handles the form's PRE_SUBMIT event.
     * 
     * This method intercepts the raw data submitted by the form before it is bound to the form object.
     * It expects the data as an array, then recursively sanitizes all string values to prevent XSS attacks.
     * After sanitization, it replaces the original submitted data with the cleaned data.
     * 
     * If the submitted data is not an array (e.g., for simple fields), the method exits early.
     * 
     * @param FormEvent $event The form event containing the submitted data.
     * 
     * @return void
     */
    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();

        if (!is_array($data)) {
            return;
        }

        // Recursively sanitize all string fields in the submitted data
        $sanitizedData = $this->sanitizeArray($data);

        // Replace the original form data with the sanitized data
        $event->setData($sanitizedData);
    }

    /**
     * Recursively sanitizes all string values in the given array by purifying their HTML.
     *
     * @param array<mixed> $data The array of submitted data to sanitize.
     * @return array<mixed> The sanitized data array.
     */
    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Recursively sanitize nested arrays.
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Purify string values using the HtmlSanitizer service.
                $data[$key] = $this->sanitizer->purify($value);
            }
        }

        return $data;
    }
}
