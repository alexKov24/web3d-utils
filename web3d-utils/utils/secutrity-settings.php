<?php
/**
 * Title: Security Settings for functions.php
 * About: adds the following: XMLRPC disable, xframe options, remove wp version from the_generator, limit login attempts to 3, removes harmful rest end points cleaning feed
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

try {
    // Disable XML-RPC: XML-RPC is a protocol that allows external applications to access and modify your WordPress site.
    add_filter('xmlrpc_enabled', '__return_false');
    
    // Add security headers
    add_filter('wp_headers', 'web3d_security_headers');
    
    // Remove WordPress version from generator
    add_filter('the_generator', 'web3d_remove_version');
    
    // Add login attempt limiting
    add_action('wp_login_failed', 'web3d_handle_failed_login');
    add_action('wp_authenticate', 'web3d_check_login_attempts');
    
    // Remove harmful REST endpoints
    add_filter('rest_endpoints', 'web3d_disable_rest_endpoints');
    
    // Disable RSS/Atom feeds
    web3d_disable_feeds();
    
} catch (Exception $e) {
    error_log('Security Settings utility failed: ' . $e->getMessage());
}

/**
 * Add security headers
 */
function web3d_security_headers($headers) {
    try {
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-XSS-Protection'] = '1; mode=block';
        return $headers;
    } catch (Exception $e) {
        error_log('Security headers function failed: ' . $e->getMessage());
        return $headers;
    }
}

/**
 * Remove WordPress version from generator
 */
function web3d_remove_version() {
    try {
        return '';
    } catch (Exception $e) {
        error_log('Remove version function failed: ' . $e->getMessage());
        return '';
    }
}

/**
 * Handle failed login attempts
 */
function web3d_handle_failed_login($username) {
    try {
        $ip = web3d_get_user_ip();
        $key = 'login_attempts_' . md5($ip);
        $attempts = get_transient($key) ?: 0;
        
        // Increment attempts and set expiry for 15 minutes
        set_transient($key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
        
        // Log the attempt
        error_log("Failed login attempt #{$attempts} from IP: {$ip} for username: {$username}");
        
    } catch (Exception $e) {
        error_log('Handle failed login function failed: ' . $e->getMessage());
    }
}

/**
 * Check login attempts before authentication
 */
function web3d_check_login_attempts($user) {
    try {
        $ip = web3d_get_user_ip();
        $key = 'login_attempts_' . md5($ip);
        $attempts = get_transient($key) ?: 0;
        
        if ($attempts >= 5) {
            $remaining_time = get_option('_transient_timeout_' . $key) - time();
            $minutes = ceil($remaining_time / 60);
            
            wp_die(
                "Too many failed login attempts from your IP address. Please try again in {$minutes} minutes.",
                'Login Blocked',
                array('response' => 429)
            );
        }
        
        return $user;
        
    } catch (Exception $e) {
        error_log('Check login attempts function failed: ' . $e->getMessage());
        return $user;
    }
}

/**
 * Get user IP address safely
 */
function web3d_get_user_ip() {
    try {
        // Check for various headers that might contain the real IP
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (forwarded headers)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Basic IP validation
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
    } catch (Exception $e) {
        error_log('Get user IP function failed: ' . $e->getMessage());
        return '0.0.0.0';
    }
}

/**
 * Disable harmful REST endpoints
 */
function web3d_disable_rest_endpoints($endpoints) {
    try {
        // Users related
        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        
        // Posts & Pages (only if not needed for frontend)
        unset($endpoints['/wp/v2/posts']);
        unset($endpoints['/wp/v2/pages']);
        
        // Comments
        unset($endpoints['/wp/v2/comments']);
        
        // Media
        unset($endpoints['/wp/v2/media']);
        
        // Taxonomies
        unset($endpoints['/wp/v2/categories']);
        unset($endpoints['/wp/v2/tags']);
        
        // Block types and patterns
        unset($endpoints['/wp/v2/block-types']);
        unset($endpoints['/wp/v2/block-patterns']);
        
        // Settings
        unset($endpoints['/wp/v2/settings']);
        
        return $endpoints;
        
    } catch (Exception $e) {
        error_log('Disable REST endpoints function failed: ' . $e->getMessage());
        return $endpoints;
    }
}

/**
 * Disable RSS/Atom feeds
 */
function web3d_disable_feeds() {
    try {
        add_action('do_feed', 'web3d_disable_feed_redirect', 1);
        add_action('do_feed_rdf', 'web3d_disable_feed_redirect', 1);
        add_action('do_feed_rss', 'web3d_disable_feed_redirect', 1);
        add_action('do_feed_rss2', 'web3d_disable_feed_redirect', 1);
        add_action('do_feed_atom', 'web3d_disable_feed_redirect', 1);
        add_action('do_feed_rss2_comments', 'web3d_disable_feed_redirect', 1);
        add_action('do_feed_atom_comments', 'web3d_disable_feed_redirect', 1);
        
        // Remove feed links from head
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'feed_links', 2);
        
    } catch (Exception $e) {
        error_log('Disable feeds function failed: ' . $e->getMessage());
    }
}

/**
 * Redirect feed requests to homepage
 */
function web3d_disable_feed_redirect() {
    try {
        wp_redirect(home_url('/'), 301);
        exit;
    } catch (Exception $e) {
        error_log('Feed redirect function failed: ' . $e->getMessage());
        wp_die(__('No feed available, please visit the <a href="'.esc_url(home_url('/')).'">homepage</a>!'));
    }
}