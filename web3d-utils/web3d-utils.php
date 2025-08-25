<?php
/**
 * Plugin Name: Web3D Utils
 * Description: A utility manager that runs enabled mini-plugins automatically
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: web3d-utils
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WEB3D_UTILS_VERSION', '1.0.0');
define('WEB3D_UTILS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEB3D_UTILS_UTILS_DIR', WEB3D_UTILS_PLUGIN_DIR . 'utils/');

/**
 * Initialize the plugin and run enabled utilities
 */
function web3d_utils_init() {
    try {
        // Add admin menu
        add_action('admin_menu', 'web3d_utils_add_admin_menu');
        
        // Register settings
        add_action('admin_init', 'web3d_utils_register_settings');
        
        // Run enabled utilities on every page load
        web3d_utils_run_enabled_utilities();
        
    } catch (Exception $e) {
        error_log('Web3D Utils initialization failed: ' . $e->getMessage());
    }
}
add_action('init', 'web3d_utils_init');

/**
 * Run all enabled utilities
 */
function web3d_utils_run_enabled_utilities() {
    try {
        $utilities = web3d_utils_scan_utilities();
        $enabled = web3d_utils_get_enabled_utilities();
        
        foreach ($utilities as $utility) {
            try {
                if (in_array($utility['filename'], $enabled)) {
                    include_once $utility['filepath'];
                }
            } catch (Exception $e) {
                error_log('Failed to run utility ' . $utility['filename'] . ': ' . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        error_log('Failed to run utilities: ' . $e->getMessage());
    }
}

/**
 * Add admin menu page
 */
function web3d_utils_add_admin_menu() {
    try {
        add_options_page(
            'Utility Options',
            'Utility Options', 
            'manage_options',
            'web3d-utils',
            'web3d_utils_settings_page'
        );
    } catch (Exception $e) {
        error_log('Failed to add admin menu: ' . $e->getMessage());
    }
}

/**
 * Register plugin settings
 */
function web3d_utils_register_settings() {
    try {
        register_setting('web3d_utils_settings', 'web3d_utils_enabled_plugins');
    } catch (Exception $e) {
        error_log('Failed to register settings: ' . $e->getMessage());
    }
}

/**
 * Scan utils directory for PHP files and extract metadata
 * @return array Array of discovered utilities
 */
function web3d_utils_scan_utilities() {
    $utilities = array();
    
    try {
        if (!is_dir(WEB3D_UTILS_UTILS_DIR)) {
            return $utilities;
        }
        
        $files = glob(WEB3D_UTILS_UTILS_DIR . '*.php');
        
        foreach ($files as $file) {
            try {
                $metadata = web3d_utils_extract_metadata($file);
                if ($metadata) {
                    $utilities[] = array(
                        'filename' => basename($file, '.php'),
                        'title' => $metadata['title'],
                        'about' => $metadata['about'],
                        'filepath' => $file
                    );
                }
            } catch (Exception $e) {
                error_log('Failed to process utility file ' . $file . ': ' . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        error_log('Failed to scan utilities: ' . $e->getMessage());
    }
    
    return $utilities;
}

/**
 * Extract Title and About from PHP file comment
 * @param string $filepath Path to PHP file
 * @return array|null Metadata array or null if not found
 */
function web3d_utils_extract_metadata($filepath) {
    try {
        if (!file_exists($filepath)) {
            throw new Exception('File does not exist');
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new Exception('Cannot read file');
        }
        
        // Match /** comment block at start of file (after <?php)
        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            $comment = $matches[1];
            
            // Extract Title and About
            $title = null;
            $about = null;
            
            if (preg_match('/Title:\s*(.+)/i', $comment, $titleMatch)) {
                $title = trim($titleMatch[1]);
            }
            
            if (preg_match('/About:\s*(.+)/i', $comment, $aboutMatch)) {
                $about = trim($aboutMatch[1]);
            }
            
            if ($title && $about) {
                return array(
                    'title' => $title,
                    'about' => $about
                );
            }
        }
        
    } catch (Exception $e) {
        error_log('Failed to extract metadata from ' . $filepath . ': ' . $e->getMessage());
    }
    
    return null;
}

/**
 * Get enabled utilities from settings
 * @return array Array of enabled utility filenames
 */
function web3d_utils_get_enabled_utilities() {
    try {
        $enabled = get_option('web3d_utils_enabled_plugins', array());
        return is_array($enabled) ? $enabled : array();
    } catch (Exception $e) {
        error_log('Failed to get enabled utilities: ' . $e->getMessage());
        return array();
    }
}

/**
 * Display the settings page
 */
function web3d_utils_settings_page() {
    try {
        include WEB3D_UTILS_PLUGIN_DIR . 'templates/settings-page.php';
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>Failed to load settings page: ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

/**
 * Plugin activation hook
 */
function web3d_utils_activate() {
    try {
        // Create utils directory if it doesn't exist
        if (!is_dir(WEB3D_UTILS_UTILS_DIR)) {
            wp_mkdir_p(WEB3D_UTILS_UTILS_DIR);
        }
        
        // Set default options
        add_option('web3d_utils_enabled_plugins', array());
        
    } catch (Exception $e) {
        error_log('Plugin activation failed: ' . $e->getMessage());
    }
}
register_activation_hook(__FILE__, 'web3d_utils_activate');

/**
 * Plugin deactivation hook
 */
function web3d_utils_deactivate() {
    try {
        // Clean up if needed
        delete_option('web3d_utils_enabled_plugins');
        
    } catch (Exception $e) {
        error_log('Plugin deactivation failed: ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, 'web3d_utils_deactivate');