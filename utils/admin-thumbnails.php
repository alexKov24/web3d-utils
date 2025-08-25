<?php
/**
 * Title: Add admin thumbnails
 * About: adds thumbnails on posts archive views for post types with thumbnail support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize the thumbnail columns functionality
 */
function admin_thumbnails_init()
{
    // Only add hooks if we're in admin
    if (!is_admin()) {
        return;
    }

    add_action('admin_init', 'admin_thumbnails_setup');
}

/**
 * Setup thumbnail columns for supported post types
 */
function admin_thumbnails_setup()
{
    $post_types = admin_thumbnails_get_supported_post_types();

    if (empty($post_types)) {
        return;
    }

    // Add hooks for each supported post type
    foreach ($post_types as $post_type) {
        add_filter("manage_{$post_type}_posts_columns", 'admin_thumbnails_add_column');
        add_action("manage_{$post_type}_posts_custom_column", 'admin_thumbnails_display_column', 10, 2);
    }

    // Add CSS and JS
    add_action('admin_head', 'admin_thumbnails_add_styles');
    add_action('admin_footer', 'admin_thumbnails_add_scripts');

    // AJAX handlers
    add_action('wp_ajax_update_post_thumbnail', 'admin_thumbnails_ajax_update');
}

/**
 * Get post types that support thumbnails
 * 
 * @return array Post types with thumbnail support
 */
function admin_thumbnails_get_supported_post_types()
{
    $post_types = get_post_types(array('public' => true));
    $supported = array();

    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'thumbnail')) {
            $supported[] = $post_type;
        }
    }

    return apply_filters('admin_thumbnails_supported_post_types', $supported);
}

/**
 * Add thumbnail column to post tables
 * 
 * @param array $columns Existing columns
 * @return array Modified columns
 */
function admin_thumbnails_add_column($columns)
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
 * 
 * @param string $column_name Column name
 * @param int $post_id Post ID
 */
function admin_thumbnails_display_column($column_name, $post_id)
{
    if ($column_name !== 'thumbnail') {
        return;
    }

    $thumbnail_id = get_post_thumbnail_id($post_id);

    echo '<div class="thumbnail-container" data-post-id="' . esc_attr($post_id) . '">';

    if ($thumbnail_id) {
        echo '<div class="thumbnail-preview">';
        echo get_the_post_thumbnail($post_id, array(50, 50));
        echo '<div class="thumbnail-actions">';
        echo '<button type="button" class="change-thumbnail" title="' . esc_attr__('Change image', 'admin-thumbnails-column') . '">';
        echo '<span class="dashicons dashicons-edit"></span>';
        echo '</button>';
        echo '<button type="button" class="remove-thumbnail" title="' . esc_attr__('Remove image', 'admin-thumbnails-column') . '">';
        echo '<span class="dashicons dashicons-no"></span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<button type="button" class="upload-thumbnail" title="' . esc_attr__('Set featured image', 'admin-thumbnails-column') . '">';
        echo '<span class="dashicons dashicons-plus"></span>';
        echo '</button>';
    }

    echo '</div>';
}

/**
 * AJAX handler for updating post thumbnails
 */
function admin_thumbnails_ajax_update()
{
    // Verify nonce
    check_ajax_referer('admin_thumbnails_column', 'nonce');

    // Check permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    // Get POST data
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $thumbnail_id = isset($_POST['thumbnail_id']) ? intval($_POST['thumbnail_id']) : 0;
    $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    // Verify user can edit this specific post
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permission denied for this post');
    }

    if ($action === 'remove') {
        delete_post_thumbnail($post_id);

        $html = '<button type="button" class="upload-thumbnail" title="' . esc_attr__('Set featured image', 'admin-thumbnails-column') . '">';
        $html .= '<span class="dashicons dashicons-plus"></span>';
        $html .= '</button>';

        wp_send_json_success(array(
            'message' => 'Thumbnail removed',
            'html' => $html
        ));
    } else {
        if (!$thumbnail_id) {
            wp_send_json_error('Invalid thumbnail ID');
        }

        // Verify the attachment exists
        if (!wp_attachment_is_image($thumbnail_id)) {
            wp_send_json_error('Invalid image attachment');
        }

        set_post_thumbnail($post_id, $thumbnail_id);

        $html = '<div class="thumbnail-preview">';
        $html .= get_the_post_thumbnail($post_id, array(50, 50));
        $html .= '<div class="thumbnail-actions">';
        $html .= '<button type="button" class="change-thumbnail" title="' . esc_attr__('Change image', 'admin-thumbnails-column') . '">';
        $html .= '<span class="dashicons dashicons-edit"></span>';
        $html .= '</button>';
        $html .= '<button type="button" class="remove-thumbnail" title="' . esc_attr__('Remove image', 'admin-thumbnails-column') . '">';
        $html .= '<span class="dashicons dashicons-no"></span>';
        $html .= '</button>';
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
function admin_thumbnails_add_styles()
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

        .upload-thumbnail {
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

        .upload-thumbnail:hover {
            border-color: #0073aa;
            color: #0073aa;
        }

        .upload-thumbnail .dashicons {
            color: #ddd;
            font-size: 20px;
        }

        .upload-thumbnail:hover .dashicons {
            color: #0073aa;
        }
    </style>
    <?php
}

/**
 * Add JavaScript for the thumbnail columns
 */
function admin_thumbnails_add_scripts()
{
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            var file_frame;
            var wp_media_post_id = 0;
            var container;
            var nonce = '<?php echo wp_create_nonce('admin_thumbnails_column'); ?>';

            // Handle upload/change thumbnail buttons
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
                                alert('Error updating thumbnail: ' + response.data);
                            }
                        },
                        error: function () {
                            alert('Error updating thumbnail');
                        }
                    });
                });

                file_frame.open();
            });

            // Handle remove thumbnail button
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
                            alert('Error removing thumbnail: ' + response.data);
                        }
                    },
                    error: function () {
                        alert('Error removing thumbnail');
                    }
                });
            });
        });
    </script>
    <?php
}

// Initialize the functionality
admin_thumbnails_init();