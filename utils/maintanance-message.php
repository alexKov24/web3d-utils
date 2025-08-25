<?php
/**
 * Title: Show maintenance messags
 * About: Show maintenance message for all but logged in users
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function show_development_message()
{
    // Check if user is not an admin
    if (!current_user_can('manage_options') && !is_admin()) {
        // Proper HTTP status for development
        header('HTTP/1.1 503 Service Unavailable');
        // Set retry-after header (in seconds)
        header('Retry-After: 3600');
        // Display development message
        echo '<html><head><title>Under Development</title></head>';
        echo '<body style="text-align: center; padding-top: 100px;">';
        echo '<h1>Site Under Development</h1>';
        echo '<p>We\'re currently working on improvements to bring you a better experience.</p>';
        echo '<p>Please check back soon!</p>';
        echo '</body></html>';
        exit;
    }
}
add_action('wp', 'show_development_message');
