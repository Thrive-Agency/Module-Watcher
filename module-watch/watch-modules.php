<?php
/*
Plugin Name: Watch Modules
Description: Module Watcher
Version: 1.9.0
Author: Thrive Agency
Author URI: https://thriveagency.com
GitHub Plugin URI: https://github.com/Thrive-Agency/Module-Watcher
GitHub Access Token: your-github-access-token
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Retrieve the plugin data from this file (plugin name, version, etc.) Then set a constant with the plugin version for use in the updater class
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
define('WATCH_MODULES_VERSION', $plugin_data['Version']);
// Define the update server URL
define('UPDATE_SERVER_URL', 'https://phpstack-1314194-4796733.cloudwaysapps.com/module-watch/');

// Include the updater class
require_once plugin_dir_path(__FILE__) . 'updater.php';


// Register activation hook
register_activation_hook(__FILE__, 'plugin_checker_on_activation');

// Function to run on plugin activation
function plugin_checker_on_activation() {
    plugin_checker_schedule_daily_cron(); // Schedule daily cron job on activation
    plugin_checker_check_plugins(); // Check plugins upon activation
}

// Schedule daily cron job for plugin check
function plugin_checker_schedule_daily_cron() {
    if (!wp_next_scheduled('plugin_checker_daily_event')) {
        wp_schedule_event(time(), 'daily', 'plugin_checker_daily_event');
    }
}

// Hook daily cron job function
add_action('plugin_checker_daily_event', 'plugin_checker_check_plugins');

// Function to check for specified plugins
function plugin_checker_check_plugins() {
    $plugins_to_check = array(
        'fusion-optimizer-pro',
        'HTML Page Sitemap',
        'IM8-Exclude-Pages',
        'exclude-pages-from-navigation',
        'easy-logo-slider',
        'SubHeading',
        'wp-file-manager'
        //'akismet',
        //'classic-widgets'
        // Add more plugin slugs as needed
    );

    $installed_plugins = array();

    foreach ($plugins_to_check as $plugin_slug) {
        if (is_plugin_installed($plugin_slug)) {
            $installed_plugins[] = $plugin_slug;
        }
    }

    if (!empty($installed_plugins)) {
        plugin_checker_send_email_notification($installed_plugins);
    }
}

// Function to check if a plugin is installed
function is_plugin_installed($plugin_slug) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    $plugin_path = $plugin_slug . '/' . $plugin_slug . '.php';
    return file_exists(WP_PLUGIN_DIR . '/' . $plugin_path);
}

// Function to send email notification
function plugin_checker_send_email_notification($plugin_slugs) {
    $to = 'support@thriveagency.com'; // Change to your email address
    $site_url = get_site_url(); // Get the site URL
    $subject = 'Nefarious Plugins Detected on '. $site_url;
    $message = 'The following nefarious plugins have been detected on the site:<br><br>';

    foreach ($plugin_slugs as $plugin_slug) {
        $message .= ucfirst(str_replace('-', ' ', $plugin_slug)) . '<br>';
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $mail_sent = wp_mail($to, $subject, $message, $headers);
    // Debug wp_mailing
    //if ($mail_sent) {
    //    error_log('Plugin checker email sent successfully.');
    //} else {
    //   error_log('Plugin checker email failed to send.');
    //}
}
?>
