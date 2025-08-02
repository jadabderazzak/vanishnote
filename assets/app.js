// Import necessary libraries and styles
import './bootstrap.js';               // Bootstrap JS setup
import 'flowbite';                    // Flowbite UI components
import 'toastr/build/toastr.min.css'; // Toastr notification styles
import toastr from 'toastr';           // Toastr library import
import flatpickr from "flatpickr"; // flatpickr styles
import "flatpickr/dist/flatpickr.min.css"; // flatpickr library import
import { French } from "flatpickr/dist/l10n/fr.js";
import { German } from "flatpickr/dist/l10n/de.js";
import { Spanish } from "flatpickr/dist/l10n/es.js";
import './js/note';
import './js/chart';
import './js/admin_chart';
// Make toastr globally accessible
window.toastr = toastr;

// Toastr configuration options
toastr.options = {
    "showDuration": 0,            // Duration of show animation in ms (0 = instant)
    "hideDuration": 0,            // Duration of hide animation in ms (0 = instant)
    "timeOut": 5000,              // Time in ms before toast disappears automatically
    "extendedTimeOut": 1000,      // Time in ms toast remains visible after hover
    "positionClass": "toast-bottom-right", // Position of the toast notifications
    "closeButton": true,          // Show close button on toasts
    "progressBar": true,          // Show progress bar indicating timeout
    "preventDuplicates": true,    // Prevent duplicate toasts
    "showEasing": "swing",        // Easing for show animation
    "hideEasing": "linear",       // Easing for hide animation
    "showMethod": "fadeIn",       // Animation method for showing toast
    "hideMethod": "fadeOut"       // Animation method for hiding toast
};

// Map of supported locales for Flatpickr
// Flatpickr uses English ('en') as the default locale, so we use `null` in that case.
const localeMap = {
  fr: French,
  de: German,
  es: Spanish,
  en: null,
};

document.addEventListener('DOMContentLoaded', () => {
  // Select all inputs with the 'datetime-picker' class
  document.querySelectorAll('.datetime-picker').forEach(input => {
    // Get the desired locale from the input's data attribute (set in Twig with data-locale="{{ app.request.locale }}")
    const localeCode = input.dataset.locale || 'en';
    
    // Match the locale code with the Flatpickr locale object
    const locale = localeMap[localeCode] || null;

    // Initialize Flatpickr with basic options
    flatpickr(input, {
      enableTime: true,              // Enables time selection (Y-m-d H:i)
      dateFormat: "Y-m-d H:i",       // Format: 2025-07-28 14:00
      minDate: "today",              // Prevent selection of past dates
      locale: locale                 // Apply translated UI labels if needed
    });
  });
});

/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Import your main CSS file (this will be bundled into app.css)
import './styles/app.css';
