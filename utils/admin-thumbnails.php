<?php
/**
 * Title: Add admin thumbnails
 * About: adds thumbnails on posts archive views if possible
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Admin_Thumbnails_Column
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Post type columns
        $post_types = $this->get_post_types_with_thumbnails();
        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", array($this, 'add_thumbnail_column'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'display_thumbnail_column'), 10, 2);
        }

        // Taxonomy columns - now works with all taxonomies that have thumbnails
        $taxonomies = $this->get_taxonomies_with_thumbnails();
        foreach ($taxonomies as $taxonomy) {
            add_filter("manage_edit-{$taxonomy}_columns", array($this, 'add_taxonomy_thumbnail_column'));
            add_filter("manage_{$taxonomy}_custom_column", array($this, 'display_taxonomy_thumbnail_column'), 10, 3);
        }

        // Add CSS and JS
        add_action('admin_head', array($this, 'add_admin_styles'));
        add_action('admin_footer', array($this, 'add_admin_scripts'));

        // AJAX handlers for updating thumbnails
        add_action('wp_ajax_update_post_thumbnail', array($this, 'ajax_update_post_thumbnail'));
        add_action('wp_ajax_update_term_thumbnail', array($this, 'ajax_update_term_thumbnail'));
    }

    /**
     * Get post types that support thumbnails
     * 
     * @return array Post types with thumbnail support
     */
    private function get_post_types_with_thumbnails()
    {
        $post_types = get_post_types(array('public' => true));

        foreach ($post_types as $key => $post_type) {
            if (!post_type_supports($post_type, 'thumbnail')) {
                unset($post_types[$key]);
            }
        }

        return apply_filters('admin_thumbnails_column_post_types', $post_types);
    }

    /**
     * Get all taxonomies that have thumbnail support
     * Checks for ACF fields, term meta, or custom thumbnail implementations
     * 
     * @return array Taxonomies with thumbnail support
     */
    private function get_taxonomies_with_thumbnails()
    {
        $all_taxonomies = get_taxonomies(array('public' => true));
        $supported_taxonomies = array();

        foreach ($all_taxonomies as $taxonomy) {
            if ($this->taxonomy_has_thumbnail_support($taxonomy)) {
                $supported_taxonomies[] = $taxonomy;
            }
        }

        return apply_filters('admin_thumbnails_column_taxonomies', $supported_taxonomies);
    }

    /**
     * Check if a taxonomy has thumbnail support
     * 
     * @param string $taxonomy Taxonomy name
     * @return bool True if taxonomy supports thumbnails
     */
    private function taxonomy_has_thumbnail_support($taxonomy)
    {
        // Get sample terms to check for thumbnail fields
        $sample_terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => 5
        ));

        if (empty($sample_terms) || is_wp_error($sample_terms)) {
            // If no terms exist, check common field patterns
            return $this->has_common_thumbnail_fields($taxonomy);
        }

        // Check each sample term for thumbnail fields
        foreach ($sample_terms as $term) {
            if ($this->term_has_thumbnail_field($term)) {
                return true;
            }
        }

        // Check for common field patterns even if no terms have thumbnails yet
        return $this->has_common_thumbnail_fields($taxonomy);
    }

    /**
     * Check if a term has a thumbnail field
     * 
     * @param WP_Term $term Term object
     * @return bool True if term has thumbnail field
     */
    private function term_has_thumbnail_field($term)
    {
        $term_id = $term->term_id;
        $taxonomy = $term->taxonomy;

        // Check for ACF fields
        if (function_exists('get_field_objects')) {
            $fields = get_field_objects($taxonomy . '_' . $term_id);
            if ($fields) {
                foreach ($fields as $field) {
                    if ($this->is_image_field($field)) {
                        return true;
                    }
                }
            }
        }

        // Check for term meta thumbnail
        $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        if ($thumbnail_id) {
            return true;
        }

        // Check for other common meta keys
        $common_keys = array('thumbnail', 'image', 'featured_image', 'term_image');
        foreach ($common_keys as $key) {
            if (get_term_meta($term_id, $key, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for common thumbnail field patterns
     * 
     * @param string $taxonomy Taxonomy name
     * @return bool True if common patterns found
     */
    private function has_common_thumbnail_fields($taxonomy)
    {
        // Check if ACF field groups exist for this taxonomy
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups();
            foreach ($field_groups as $group) {
                if (isset($group['location'])) {
                    foreach ($group['location'] as $location_group) {
                        foreach ($location_group as $location) {
                            if ($location['param'] === 'taxonomy' && $location['value'] === $taxonomy) {
                                // Get fields in this group
                                $fields = acf_get_fields($group);
                                if ($fields) {
                                    foreach ($fields as $field) {
                                        if ($this->is_image_field($field)) {
                                            return true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a field is an image field
     * 
     * @param array $field Field array
     * @return bool True if field is image type
     */
    private function is_image_field($field)
    {
        if (!is_array($field) || !isset($field['type'])) {
            return false;
        }

        $image_types = array('image', 'gallery', 'file');
        $image_names = array('thumbnail', 'image', 'featured_image', 'term_image', 'category_image');

        return in_array($field['type'], $image_types) ||
            in_array($field['name'], $image_names) ||
            strpos($field['name'], 'image') !== false ||
            strpos($field['name'], 'thumbnail') !== false;
    }

    /**
     * Get the best thumbnail field name for a taxonomy
     * 
     * @param string $taxonomy Taxonomy name
     * @return string Field name to use
     */
    private function get_thumbnail_field_name($taxonomy)
    {
        // Check for ACF fields first
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups();
            foreach ($field_groups as $group) {
                if (isset($group['location'])) {
                    foreach ($group['location'] as $location_group) {
                        foreach ($location_group as $location) {
                            if ($location['param'] === 'taxonomy' && $location['value'] === $taxonomy) {
                                $fields = acf_get_fields($group);
                                if ($fields) {
                                    foreach ($fields as $field) {
                                        if ($this->is_image_field($field)) {
                                            return $field['name'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Fallback to common patterns
        $common_names = array(
            $taxonomy . '_image',
            $taxonomy . '_thumbnail',
            'thumbnail',
            'image',
            'featured_image'
        );

        return apply_filters('admin_thumbnails_column_field_name', $common_names[0], $taxonomy);
    }

    /**
     * Get thumbnail ID for a term
     * 
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name
     * @return int|null Thumbnail ID or null if not found
     */
    private function get_term_thumbnail_id($term_id, $taxonomy)
    {
        $field_name = $this->get_thumbnail_field_name($taxonomy);
        $thumbnail_id = null;

        // Try ACF first
        if (function_exists('get_field')) {
            $image = get_field($field_name, $taxonomy . '_' . $term_id);

            if ($image) {
                if (is_array($image) && isset($image['ID'])) {
                    $thumbnail_id = $image['ID'];
                } elseif (is_numeric($image)) {
                    $thumbnail_id = $image;
                }
            }
        }

        // Fallback to term meta
        if (!$thumbnail_id) {
            $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        }

        // Try other common meta keys
        if (!$thumbnail_id) {
            $common_keys = array('thumbnail', 'image', 'featured_image', $field_name);
            foreach ($common_keys as $key) {
                $meta_value = get_term_meta($term_id, $key, true);
                if ($meta_value) {
                    if (is_numeric($meta_value)) {
                        $thumbnail_id = $meta_value;
                        break;
                    } elseif (is_array($meta_value) && isset($meta_value['ID'])) {
                        $thumbnail_id = $meta_value['ID'];
                        break;
                    }
                }
            }
        }

        return $thumbnail_id;
    }

    /**
     * Update term thumbnail
     * 
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name
     * @param int $thumbnail_id Thumbnail ID (0 to remove)
     * @return bool Success status
     */
    private function update_term_thumbnail($term_id, $taxonomy, $thumbnail_id)
    {
        $field_name = $this->get_thumbnail_field_name($taxonomy);

        // Try ACF first
        if (function_exists('update_field')) {
            $acf_updated = update_field($field_name, $thumbnail_id ?: '', $taxonomy . '_' . $term_id);
            if ($acf_updated) {
                return true;
            }
        }

        // Fallback to term meta
        if ($thumbnail_id) {
            return update_term_meta($term_id, 'thumbnail_id', $thumbnail_id);
        } else {
            return delete_term_meta($term_id, 'thumbnail_id');
        }
    }

    /**
     * Add thumbnail column to post tables
     */
    public function add_thumbnail_column($columns)
    {
        $new_columns = array();

        foreach ($columns as $key => $title) {
            if ($key == 'cb') {
                $new_columns[$key] = $title;
                $new_columns['thumbnail'] = __('Thumbnail', 'admin-thumbnails-column');
            } else {
                $new_columns[$key] = $title;
            }
        }

        return $new_columns;
    }

    /**
     * Display thumbnail in post column
     */
    public function display_thumbnail_column($column_name, $post_id)
    {
        if ($column_name == 'thumbnail') {
            $thumbnail_id = get_post_thumbnail_id($post_id);

            echo '<div class="thumbnail-container" data-post-id="' . esc_attr($post_id) . '">';

            if ($thumbnail_id) {
                echo '<div class="thumbnail-preview">';
                echo get_the_post_thumbnail($post_id, array(50, 50));
                echo '<div class="thumbnail-actions">';
                echo '<button type="button" class="change-thumbnail" title="' . esc_attr__('Change image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-edit"></span></button>';
                echo '<button type="button" class="remove-thumbnail" title="' . esc_attr__('Remove image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-no"></span></button>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<button type="button" class="upload-thumbnail" title="' . esc_attr__('Set featured image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-plus"></span></button>';
            }

            echo '</div>';
        }
    }

    /**
     * Add thumbnail column to taxonomy tables
     */
    public function add_taxonomy_thumbnail_column($columns)
    {
        $new_columns = array();

        foreach ($columns as $key => $title) {
            if ($key == 'cb') {
                $new_columns[$key] = $title;
                $new_columns['thumbnail'] = __('Thumbnail', 'admin-thumbnails-column');
            } else {
                $new_columns[$key] = $title;
            }
        }

        return $new_columns;
    }

    /**
     * Display thumbnail in taxonomy column
     */
    public function display_taxonomy_thumbnail_column($content, $column_name, $term_id)
    {
        if ($column_name == 'thumbnail') {
            $term = get_term($term_id);
            $taxonomy = $term->taxonomy;
            $field_name = $this->get_thumbnail_field_name($taxonomy);
            $thumbnail_id = $this->get_term_thumbnail_id($term_id, $taxonomy);

            $output = '<div class="thumbnail-container" data-term-id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" data-field-name="' . esc_attr($field_name) . '">';

            if ($thumbnail_id) {
                $output .= '<div class="thumbnail-preview">';
                $output .= wp_get_attachment_image($thumbnail_id, array(50, 50));
                $output .= '<div class="thumbnail-actions">';
                $output .= '<button type="button" class="change-term-thumbnail" title="' . esc_attr__('Change image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-edit"></span></button>';
                $output .= '<button type="button" class="remove-term-thumbnail" title="' . esc_attr__('Remove image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-no"></span></button>';
                $output .= '</div>';
                $output .= '</div>';
            } else {
                $output .= '<button type="button" class="upload-term-thumbnail" title="' . esc_attr__('Set image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-plus"></span></button>';
            }

            $output .= '</div>';

            return $output;
        }

        return $content;
    }

    /**
     * AJAX handler for updating post thumbnails
     */
    public function ajax_update_post_thumbnail()
    {
        check_ajax_referer('admin_thumbnails_column', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $thumbnail_id = isset($_POST['thumbnail_id']) ? intval($_POST['thumbnail_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        if ($action === 'remove') {
            delete_post_thumbnail($post_id);
            wp_send_json_success(array(
                'message' => 'Thumbnail removed',
                'html' => '<button type="button" class="upload-thumbnail" title="' . esc_attr__('Set featured image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-plus"></span></button>'
            ));
        } else {
            if ($thumbnail_id) {
                set_post_thumbnail($post_id, $thumbnail_id);

                $html = '<div class="thumbnail-preview">';
                $html .= get_the_post_thumbnail($post_id, array(50, 50));
                $html .= '<div class="thumbnail-actions">';
                $html .= '<button type="button" class="change-thumbnail" title="' . esc_attr__('Change image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-edit"></span></button>';
                $html .= '<button type="button" class="remove-thumbnail" title="' . esc_attr__('Remove image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-no"></span></button>';
                $html .= '</div>';
                $html .= '</div>';

                wp_send_json_success(array(
                    'message' => 'Thumbnail updated',
                    'html' => $html
                ));
            } else {
                wp_send_json_error('Invalid thumbnail ID');
            }
        }
    }

    /**
     * AJAX handler for updating term thumbnails
     */
    public function ajax_update_term_thumbnail()
    {
        check_ajax_referer('admin_thumbnails_column', 'nonce');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Permission denied');
        }

        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
        $thumbnail_id = isset($_POST['thumbnail_id']) ? intval($_POST['thumbnail_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

        if (!$term_id || !$taxonomy) {
            wp_send_json_error('Invalid term ID or taxonomy');
        }

        if ($action === 'remove') {
            $this->update_term_thumbnail($term_id, $taxonomy, 0);

            wp_send_json_success(array(
                'message' => 'Thumbnail removed',
                'html' => '<button type="button" class="upload-term-thumbnail" title="' . esc_attr__('Set image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-plus"></span></button>'
            ));
        } else {
            $this->update_term_thumbnail($term_id, $taxonomy, $thumbnail_id);

            $html = '<div class="thumbnail-preview">';
            $html .= wp_get_attachment_image($thumbnail_id, array(50, 50));
            $html .= '<div class="thumbnail-actions">';
            $html .= '<button type="button" class="change-term-thumbnail" title="' . esc_attr__('Change image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-edit"></span></button>';
            $html .= '<button type="button" class="remove-term-thumbnail" title="' . esc_attr__('Remove image', 'admin-thumbnails-column') . '"><span class="dashicons dashicons-no"></span></button>';
            $html .= '</div>';
            $html .= '</div>';

            wp_send_json_success(array(
                'message' => 'Thumbnail updated',
                'html' => $html
            ));
        }
    }

    /**
     * Add CSS for the thumbnail columns
     */
    public function add_admin_styles()
    {
        ?>
        <style>
            .column-thumbnail {
                width: 70px;
                text-align: center;
            }

            .column-thumbnail img {
                border-radius: 3px;
                max-width: 50px;
                max-height: 50px;
                object-fit: cover;
            }

            .thumbnail-container {
                position: relative;
                display: inline-block;
            }

            .thumbnail-preview {
                position: relative;
                display: inline-block;
            }

            .thumbnail-actions {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                opacity: 0;
                transition: opacity 0.2s;
                border-radius: 3px;
            }

            .thumbnail-preview:hover .thumbnail-actions {
                opacity: 1;
            }

            .thumbnail-actions button {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 2px;
                margin: 0 2px;
            }

            .thumbnail-actions button:hover {
                color: #00a0d2;
            }

            .upload-thumbnail,
            .upload-term-thumbnail {
                width: 50px;
                height: 50px;
                border: 1px dashed #b4b9be;
                background: none;
                cursor: pointer;
                border-radius: 3px;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .upload-thumbnail:hover,
            .upload-term-thumbnail:hover {
                border-color: #0073aa;
                color: #0073aa;
            }

            .upload-thumbnail .dashicons,
            .upload-term-thumbnail .dashicons {
                color: #ddd;
                font-size: 20px;
            }

            .upload-thumbnail:hover .dashicons,
            .upload-term-thumbnail:hover .dashicons {
                color: #0073aa;
            }
        </style>
        <?php
    }

    /**
     * Add JavaScript for the thumbnail columns
     */
    public function add_admin_scripts()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var file_frame;
                var wp_media_post_id = 0;
                var term_id = 0;
                var taxonomy = '';
                var field_name = '';
                var container;

                var nonce = '<?php echo wp_create_nonce('admin_thumbnails_column'); ?>';

                // Handle the upload button for posts
                $(document).on('click', '.upload-thumbnail, .change-thumbnail', function (e) {
                    e.preventDefault();

                    container = $(this).closest('.thumbnail-container');
                    wp_media_post_id = container.data('post-id');

                    if (file_frame) {
                        file_frame.uploader.uploader.param('post_id', wp_media_post_id);
                        file_frame.open();
                        return;
                    }

                    file_frame = wp.media({
                        title: 'Select or Upload an Image',
                        button: {
                            text: 'Use this image'
                        },
                        multiple: false
                    });

                    file_frame.on('select', function () {
                        var attachment = file_frame.state().get('selection').first().toJSON();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'update_post_thumbnail',
                                post_id: wp_media_post_id,
                                thumbnail_id: attachment.id,
                                action_type: 'set',
                                nonce: nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    container.html(response.data.html);
                                } else {
                                    alert('Error updating thumbnail');
                                }
                            }
                        });
                    });

                    file_frame.open();
                });

                // Handle remove button for posts
                $(document).on('click', '.remove-thumbnail', function (e) {
                    e.preventDefault();

                    if (!confirm('Are you sure you want to remove this image?')) {
                        return;
                    }

                    container = $(this).closest('.thumbnail-container');
                    wp_media_post_id = container.data('post-id');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'update_post_thumbnail',
                            post_id: wp_media_post_id,
                            action_type: 'remove',
                            nonce: nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                container.html(response.data.html);
                            } else {
                                alert('Error removing thumbnail');
                            }
                        }
                    });
                });

                // Handle the upload button for terms
                $(document).on('click', '.upload-term-thumbnail, .change-term-thumbnail', function (e) {
                    e.preventDefault();

                    container = $(this).closest('.thumbnail-container');
                    term_id = container.data('term-id');
                    taxonomy = container.data('taxonomy');
                    field_name = container.data('field-name');

                    if (file_frame) {
                        file_frame.open();
                        return;
                    }

                    file_frame = wp.media({
                        title: 'Select or Upload an Image',
                        button: {
                            text: 'Use this image'
                        },
                        multiple: false
                    });

                    file_frame.on('select', function () {
                        var attachment = file_frame.state().get('selection').first().toJSON();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'update_term_thumbnail',
                                term_id: term_id,
                                taxonomy: taxonomy,
                                field_name: field_name,
                                thumbnail_id: attachment.id,
                                action_type: 'set',
                                nonce: nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    container.html(response.data.html);
                                } else {
                                    alert('Error updating thumbnail');
                                }
                            }
                        });
                    });

                    file_frame.open();
                });

                // Handle remove button for terms
                $(document).on('click', '.remove-term-thumbnail', function (e) {
                    e.preventDefault();

                    if (!confirm('Are you sure you want to remove this image?')) {
                        return;
                    }

                    container = $(this).closest('.thumbnail-container');
                    term_id = container.data('term-id');
                    taxonomy = container.data('taxonomy');
                    field_name = container.data('field-name');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'update_term_thumbnail',
                            term_id: term_id,
                            taxonomy: taxonomy,
                            field_name: field_name,
                            action_type: 'remove',
                            nonce: nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                container.html(response.data.html);
                            } else {
                                alert('Error removing thumbnail');
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }
}

// Initialize the plugin
new Admin_Thumbnails_Column();