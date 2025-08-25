# Web3D Utils Plugin

A simple utility manager that automatically runs enabled mini-plugins on every WordPress page load.

## How It Works

1. **Scans** the `utils/` folder for PHP files
2. **Reads** Title and About from file comments  
3. **Shows** settings page with on/off checkboxes
4. **Automatically runs** enabled utilities on every page load

## Plugin Structure

```
/web3d-utils/
├── web3d-utils.php          # Main plugin file
├── templates/
│   └── settings-page.php    # Simple settings form
└── utils/                   # Mini-plugins go here
    ├── admin-bar-enhancer.php
    └── login-security.php
```

## Creating Mini-Plugins

Add PHP files to `utils/` folder with this format:

```php
<?php
/**
 * Title: Your Mini-Plugin Name
 * About: What it does
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

try {
    // Your WordPress hooks and functionality here
    add_action('init', 'your_function');
    
} catch (Exception $e) {
    error_log('Your mini-plugin failed: ' . $e->getMessage());
}

function your_function() {
    // Your code here
}
```

## Key Features

- **Always Running**: Enabled utilities run on every page load
- **Simple On/Off**: Just checkboxes in settings  
- **Auto-Discovery**: Scans utils folder automatically
- **Error Safe**: Try/catch blocks prevent crashes
- **WordPress Native**: Uses standard WP hooks and functions

## Example Mini-Plugins Included

### Admin Bar Enhancer
- Adds custom menu to WordPress admin bar
- Shows current time and utility links

### Login Security  
- Hides WordPress version
- Generic login error messages
- Adds delay to failed logins

## Installation

1. Upload to `/wp-content/plugins/web3d-utils/`
2. Activate plugin
3. Go to Settings > Utility Options
4. Check utilities you want to run
5. Save settings

That's it! Enabled utilities now run automatically.