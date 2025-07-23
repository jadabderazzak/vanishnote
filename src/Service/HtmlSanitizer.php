<?php

namespace App\Service;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * This service sanitizes and cleans HTML content using HTMLPurifier.
 *
 * It allows only a safe and limited set of HTML tags and attributes,
 * removing any potentially dangerous elements such as JavaScript or unknown tags.
 */
class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    /**
     * Initializes the HTMLPurifier with a custom configuration.
     *
     * Allowed tags include basic text formatting, lists, tables, images, and links.
     * Only a limited set of CSS properties are allowed for inline styling.
     * Empty HTML tags will be automatically removed.
     */
    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();

        // Allow basic HTML tags and attributes
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr',
            'b', 'strong', 'i', 'em', 'u', 's', 'sub', 'sup',
            'blockquote', 'code', 'pre', 'span[style]',
            'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'table', 'thead', 'tbody', 'tr', 'td', 'th',
            'a[href|target]', 'img[src|alt|title|width|height]',
            'div[style]'
        ]));

        // Allow a limited set of CSS inline properties
        $config->set('CSS.AllowedProperties', [
            'color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration',
            'font-size', 'width', 'height', 'border', 'margin', 'padding'
        ]);

        // Use CDATA for attribute names (for extra flexibility)
        $config->set('HTML.Attr.Name.UseCDATA', true);

        // Definition version (required when using custom configurations)
        $config->set('HTML.DefinitionID', 'custom-def');
        $config->set('HTML.DefinitionRev', 1);

        // Automatically remove empty tags
        $config->set('AutoFormat.RemoveEmpty', true);

        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Sanitizes raw HTML input and returns a clean, safe HTML string.
     *
     * @param string|null $dirtyHtml The potentially unsafe HTML content.
     * @return string Clean and secure HTML, safe to display.
     */
    public function purify(?string $dirtyHtml): string
    {
        if ($dirtyHtml === null) {
            return '';
        }

        return $this->purifier->purify($dirtyHtml);
    }
}
