<?php
/**
 * Title: Template Usage
 * About: Disaplays a table of templates and pages using them
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TemplateUsageDisplay {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    /**
     * Add the admin menu item
     */
    public function add_admin_menu() {
        add_options_page(
            'Template Usage', // Page title
            'Template Usage', // Menu title
            'manage_options', // Capability
            'template-usage', // Menu slug
            array($this, 'admin_page') // Callback function
        );
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ('settings_page_template-usage' !== $hook) {
            return;
        }
        
        // Add custom CSS
        wp_add_inline_style('wp-admin', '
            .template-usage-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .template-usage-table th,
            .template-usage-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
                vertical-align: top;
            }
            .template-usage-table th {
                background-color: #f1f1f1;
                font-weight: bold;
            }
            .template-usage-table tr:hover {
                background-color: #f9f9f9;
            }
            .page-list {
                margin: 0;
                padding: 0;
            }
            .page-list li {
                margin-bottom: 5px;
            }
            .page-list a {
                text-decoration: none;
            }
            .page-list a:hover {
                text-decoration: underline;
            }
            .no-pages {
                font-style: italic;
                color: #666;
            }
            .template-count {
                background: #0073aa;
                color: white;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 12px;
                margin-left: 5px;
            }
        ');
    }
    
    /**
     * Get all page templates
     */
    private function get_page_templates() {
        $templates = wp_get_theme()->get_page_templates();
        $templates['default'] = 'Default Template';
        return $templates;
    }
    
    /**
     * Get pages using a specific template
     */
    private function get_pages_by_template($template_slug) {
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array()
        );
        
        if ($template_slug === 'default') {
            // Pages with no template or default template
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_page_template',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_page_template',
                    'value' => 'default',
                    'compare' => '='
                ),
                array(
                    'key' => '_wp_page_template',
                    'value' => '',
                    'compare' => '='
                )
            );
        } else {
            // Pages with specific template
            $args['meta_query'] = array(
                array(
                    'key' => '_wp_page_template',
                    'value' => $template_slug,
                    'compare' => '='
                )
            );
        }
        
        return get_posts($args);
    }
    
    /**
     * Display the admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>This page shows which pages are using which templates in your WordPress site.</p>
            
            <?php
            $templates = $this->get_page_templates();
            $template_data = array();
            
            // Collect data for all templates
            foreach ($templates as $template_slug => $template_name) {
                $pages = $this->get_pages_by_template($template_slug);
                $template_data[] = array(
                    'slug' => $template_slug,
                    'name' => $template_name,
                    'pages' => $pages,
                    'count' => count($pages)
                );
            }
            
            // Sort by template name
            usort($template_data, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            ?>
            
            <table class="template-usage-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Template Name</th>
                        <th style="width: 70%;">Pages Using This Template</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($template_data as $template): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($template['name']); ?></strong>
                                <span class="template-count"><?php echo $template['count']; ?></span>
                                <br>
                                <code style="font-size: 11px; color: #666;">
                                    <?php echo esc_html($template['slug']); ?>
                                </code>
                            </td>
                            <td>
                                <?php if (!empty($template['pages'])): ?>
                                    <ul class="page-list">
                                        <?php foreach ($template['pages'] as $page): ?>
                                            <li>
                                                <a href="<?php echo get_edit_post_link($page->ID); ?>" 
                                                   target="_blank">
                                                    <?php echo esc_html($page->post_title); ?>
                                                </a>
                                                <a href="<?php echo get_permalink($page->ID); ?>" 
                                                   target="_blank" 
                                                   style="margin-left: 10px; font-size: 12px;">
                                                    (View)
                                                </a>
                                                <br>
                                                <small style="color: #666;">
                                                    ID: <?php echo $page->ID; ?> | 
                                                    Status: <?php echo ucfirst($page->post_status); ?> |
                                                    Modified: <?php echo get_the_modified_date('Y-m-d H:i', $page->ID); ?>
                                                </small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="no-pages">No pages using this template</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h3>Summary</h3>
                <p>
                    <strong>Total Templates:</strong> <?php echo count($template_data); ?><br>
                    <strong>Total Pages:</strong> <?php echo array_sum(array_column($template_data, 'count')); ?>
                </p>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h4>How to Use This Information</h4>
                <ul>
                    <li><strong>Template Optimization:</strong> Identify which templates are most used to prioritize optimization efforts.</li>
                    <li><strong>Template Cleanup:</strong> Find unused templates that can be safely removed.</li>
                    <li><strong>Content Management:</strong> Quickly locate pages using specific templates for bulk updates.</li>
                    <li><strong>Debugging:</strong> Identify pages that might be using incorrect templates.</li>
                </ul>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new TemplateUsageDisplay();

?>