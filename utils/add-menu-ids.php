<?php
/**
 * Title: Add menu ids
 * About: adds data attributes containing the ids
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add menu ID information to menu structure
add_action('wp', function () {
    add_filter('wp_nav_menu_args', function ($args) {
        // Store menu ID for later use
        if (isset($args['menu'])) {
            $menu = wp_get_nav_menu_object($args['menu']);
        } elseif (isset($args['theme_location'])) {
            $locations = get_nav_menu_locations();
            $menu_id = $locations[$args['theme_location']] ?? null;
            $menu = $menu_id ? wp_get_nav_menu_object($menu_id) : null;
        }

        if ($menu) {
            // Store menu ID globally for this menu render
            global $current_menu_id;
            $current_menu_id = $menu->term_id;

            // Add to menu container
            $args['container_class'] = ($args['container_class'] ?? '') . ' menu-id-' . $menu->term_id;

            // Modify the final output
            add_filter('wp_nav_menu', function ($nav_menu) use ($menu) {
                return str_replace(
                    '<ul',
                    '<ul data-menu-id="' . $menu->term_id . '" data-menu-slug="' . $menu->slug . '"',
                    $nav_menu
                );
            });
        }

        return $args;
    });

    // Add menu ID to individual items
    add_filter('nav_menu_css_class', function ($classes, $item, $args, $depth) {
        global $current_menu_id;
        if ($current_menu_id) {
            $classes[] = 'from-menu-' . $current_menu_id;
        }
        return $classes;
    }, 10, 4);
});
