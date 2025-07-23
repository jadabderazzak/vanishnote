// Import necessary libraries and styles
import './bootstrap.js';               // Bootstrap JS setup
import 'flowbite';                    // Flowbite UI components
import 'toastr/build/toastr.min.css'; // Toastr notification styles
import toastr from 'toastr';           // Toastr library import

// Make toastr globally accessible
window.toastr = toastr;

// Toastr configuration options
toastr.options = {
    "showDuration": 0,            // Duration of show animation in ms (0 = instant)
    "hideDuration": 0,            // Duration of hide animation in ms (0 = instant)
    "timeOut": 5000,              // Time in ms before toast disappears automatically
    "extendedTimeOut": 1000,      // Time in ms toast remains visible after hover
    "positionClass": "toast-top-right", // Position of the toast notifications
    "closeButton": true,          // Show close button on toasts
    "progressBar": true,          // Show progress bar indicating timeout
    "preventDuplicates": true,    // Prevent duplicate toasts
    "showEasing": "swing",        // Easing for show animation
    "hideEasing": "linear",       // Easing for hide animation
    "showMethod": "fadeIn",       // Animation method for showing toast
    "hideMethod": "fadeOut"       // Animation method for hiding toast
};

/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Import your main CSS file (this will be bundled into app.css)
import './styles/app.css';
