<?php
/**
 * Settings page template for Web3D Utils
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

try {
    $utilities = web3d_utils_scan_utilities();
    $enabled = web3d_utils_get_enabled_utilities();
} catch (Exception $e) {
    $utilities = array();
    $enabled = array();
    echo '<div class="notice notice-error"><p>Error loading utilities: ' . esc_html($e->getMessage()) . '</p></div>';
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <p>Enable or disable utilities that run automatically on every page load.</p>
    
    <?php if (empty($utilities)): ?>
        <div class="notice notice-info">
            <p>No utilities found in the utils folder. Add PHP files to <code><?php echo esc_html(WEB3D_UTILS_UTILS_DIR); ?></code></p>
        </div>
    <?php else: ?>
        
        <form method="post" action="options.php">
            <?php settings_fields('web3d_utils_settings'); ?>
            
            <div style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
                
                <?php foreach ($utilities as $utility): ?>
                    <div style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px; margin-bottom: 10px;">
                        <label style="display: flex; align-items: flex-start; justify-content: space-between; cursor: pointer;">
                            <div style="flex: 1; margin-right: 15px;">
                                <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">
                                    <?php echo esc_html($utility['title']); ?>
                                </h3>
                                <p style="margin: 0; color: #646970; font-size: 14px;">
                                    <?php echo esc_html($utility['about']); ?>
                                </p>
                            </div>
                            <input 
                                type="checkbox" 
                                name="web3d_utils_enabled_plugins[]" 
                                value="<?php echo esc_attr($utility['filename']); ?>"
                                <?php checked(in_array($utility['filename'], $enabled)); ?>
                                style="width: 18px; height: 18px; margin-top: 2px;"
                            >
                        </label>
                    </div>
                <?php endforeach; ?>
                
            </div>
            
            <?php submit_button('Save Settings'); ?>
            
        </form>
        
        <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
            <h4 style="margin-top: 0;">How it works:</h4>
            <ul>
                <li>Enabled utilities run automatically on every page load</li>
                <li>They register WordPress hooks and add functionality</li>
                <li>No need to manually execute - they're always working when enabled</li>
                <li>Changes take effect immediately after saving</li>
            </ul>
        </div>
        
    <?php endif; ?>
</div>