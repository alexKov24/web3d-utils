<?php
/**
 * Title: WordPress Template Directives System
 * About: Simple directive processing for WordPress templates
 */

/**
 * Register a new template directive
 * @param string $name Directive name (without @)
 * @param callable $handler Function to process the directive
 * @throws InvalidArgumentException If name is invalid
 */
function add_template_directive($name, callable $handler)
{
    if (!is_string($name) || empty($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
        throw new InvalidArgumentException("Invalid directive name: {$name}");
    }

    $directives = get_template_directives();
    $directives[$name] = $handler;
    wp_cache_set('template_directives', $directives, 'template_system');
}

/**
 * Get registered template directives
 * @return array Registered directives
 */
function get_template_directives()
{
    $directives = wp_cache_get('template_directives', 'template_system');
    return is_array($directives) ? $directives : [];
}

/**
 * Check if content contains any directives
 * @param string $content Content to check
 * @return bool True if directives found
 */
function has_template_directives($content)
{
    $directives = get_template_directives();
    if (empty($directives)) {
        return false;
    }

    foreach (array_keys($directives) as $name) {
        if (strpos($content, "@{$name}") !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Process single directive in content
 * @param string $name Directive name
 * @param callable $handler Directive handler
 * @param string $content Content to process
 * @return string Processed content
 */
function process_single_directive($name, callable $handler, $content)
{
    $escaped_name = preg_quote($name, '/');
    $pattern = "/@{$escaped_name}(?:\(([^)]*)\))?\s*(.*?)\s*@\/{$escaped_name}/s";

    return preg_replace_callback($pattern, function ($matches) use ($handler, $name) {
        try {
            return call_user_func($handler, $matches);
        } catch (Exception $e) {
            error_log("Template directive '{$name}' failed: " . $e->getMessage());
            return isset($matches[2]) ? $matches[2] : '';
        }
    }, $content);
}

/**
 * Process template content with registered directives
 * @param string $content Template content
 * @return string Processed content
 */
function process_template_directives($content)
{
    if (empty($content) || !has_template_directives($content)) {
        return $content;
    }

    $directives = get_template_directives();

    foreach ($directives as $name => $handler) {
        $content = process_single_directive($name, $handler, $content);
    }

    return $content;
}

/**
 * Safely get directive parameter
 * @param array $matches Regex matches
 * @param string $default Default value
 * @return string Sanitized parameter
 */
function get_directive_parameter($matches, $default = '')
{
    if (!isset($matches[1]) || empty($matches[1])) {
        return $default;
    }

    return sanitize_text_field(trim($matches[1], '"\''));
}

/**
 * Initialize built-in directives
 */
function init_builtin_directives()
{
    // Admin only content
    add_template_directive('admin', function ($matches) {
        return current_user_can('manage_options') ? $matches[2] : '';
    });

    // Logged in users only
    add_template_directive('user', function ($matches) {
        return is_user_logged_in() ? $matches[2] : '';
    });

    // Guest (not logged in) only  
    add_template_directive('guest', function ($matches) {
        return !is_user_logged_in() ? $matches[2] : '';
    });

    // Role-based content
    add_template_directive('role', function ($matches) {
        $role = get_directive_parameter($matches);
        if (empty($role)) {
            return '';
        }

        return current_user_can($role) ? $matches[2] : '';
    });

    // Capability-based content
    add_template_directive('can', function ($matches) {
        $capability = get_directive_parameter($matches);
        if (empty($capability)) {
            return '';
        }

        return current_user_can($capability) ? $matches[2] : '';
    });

    // Allow developers to add custom directives
    do_action('register_template_directives');
}

/**
 * Process template file content
 * @param string $template Template file path
 * @return string Same template path
 */
function process_template_file($template)
{
    if (is_admin() || empty($template) || !file_exists($template)) {
        return $template;
    }

    // Start output buffering for this template
    ob_start('process_template_directives');

    return $template;
}

/**
 * Process the final HTML output
 * @param string $content Complete page content
 * @return string Processed content
 */
function process_final_output($content)
{
    return process_template_directives($content);
}

/**
 * Setup WordPress hooks
 */
function setup_directive_hooks()
{
    // Initialize directives once
    init_builtin_directives();

    // Hook into template loading
    add_filter('template_include', 'process_template_file');

    // Process final output before sending to browser
    add_action('shutdown', function () {
        if (!is_admin() && ob_get_level() > 0) {
            $content = ob_get_clean();
            echo process_final_output($content);
        }
    });
}

// Initialize the system
setup_directive_hooks();