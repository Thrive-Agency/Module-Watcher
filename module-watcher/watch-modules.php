<?php
/*
Plugin Name: Watch Modules
Description: Module Watcher
Version: 1.1 
Author: Thrive Agency
Author URI: https://thriveagency.com
GitHub Plugin URI: https://github.com/Thrive-Agency/Module-Watcher
*/ 

// Register activation hook
register_activation_hook(__FILE__, 'plugin_checker_on_activation'); 

// Function to hide the plugin from the plugins page
function hide_plugin($plugins) {
    // Specify the plugin file you want to hide (relative to the wp-content/plugins/ directory)
    $plugin_basename = plugin_basename(__FILE__);

    // Remove your plugin from the list of active plugins
    if (isset($plugins[$plugin_basename])) {
        unset($plugins[$plugin_basename]);
    }

    return $plugins;
}
add_filter('all_plugins', 'hide_plugin');

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


// Function to return the list of plugins to check
function plugin_checker_get_plugins_to_check() {
    return array(
        'wp-file-manager',
        'fusion-optimizer-pro',
        'HTML Page Sitemap',
        'IM8-Exclude-Pages',
        'exclude-pages-from-navigation',
        'easy-logo-slider',
        'SubHeading',
        'WP-SecurityPrime',
        'PHP Code Widget',
        'PHP text widget',
        'file_folder_manager',
    );
}


// Function to check for specified plugins
function plugin_checker_check_plugins() {
    $plugins_to_check = plugin_checker_get_plugins_to_check();

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

// Function to check if a plugin folder is present
function is_plugin_installed($plugin_slug) {
    $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

    // Check if the directory exists for the plugin
    return is_dir($plugin_dir);
}

// Function to send email notification
function plugin_checker_send_email_notification($plugin_slugs) {
    $to = 'greg.white@thriveagency.com'; // Change to your email address
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

// Function to display an admin notice if vulnerable plugins are found
function plugin_checker_display_admin_notice() {
    $plugins_to_check = plugin_checker_get_plugins_to_check(); // Get the plugin list from the central function

    $installed_plugins = array();

    foreach ($plugins_to_check as $plugin_slug) {
        if (is_plugin_installed($plugin_slug)) {
            $installed_plugins[] = $plugin_slug;
        }
    }

    if (!empty($installed_plugins)) {
        // Generate the warning message
        $message = '<b>Warning: You have potentially vulnerable, abandoned, or compromised plugins installed:</b><br/><ul>';
        foreach ($installed_plugins as $plugin_slug) {
            $message .= '<li>' . ucfirst(str_replace('-', ' ', $plugin_slug)) . '</li>';
        }
        $message .= '</ul>';
        $message .= 'It is recommended that you remove or replace these plugins. If you are seeing this notice, please <a href="' . admin_url('plugins.php') . '">visit the Plugins page</a> to manage them, or <a href="mailto:support@thriveagency.com">contact Support</a>.';

        echo '<div class="error notice-warning is-dismissible"><p>' . $message . '</p></div>';
    }
}

// Hook the function into the admin_notices action
add_action('admin_notices', 'plugin_checker_display_admin_notice'); 


// Update Functions:
class MyPluginUpdater {
    private $api_url = 'https://api.github.com/repos/Thrive-Agency/Module-Watcher/releases/latest';
    private $plugin_slug = 'module-watcher';  // Should match the folder name inside the ZIP
    private $plugin_file;

    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        error_log("Plugin Updater Initialized");

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $response = wp_remote_get($this->api_url, [
            'headers' => [
                'User-Agent'    => 'WordPress Plugin Updater'
            ]
        ]);
 
        if (is_wp_error($response)) {
            error_log("GitHub API Response Error: " . $response->get_error_message());
            return $transient;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            error_log("GitHub API Response Status Code: " . $status_code);
            return $transient;
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        if (!isset($data->tag_name) || !isset($data->zipball_url)) {
            error_log("Error decoding GitHub API response: " . wp_remote_retrieve_body($response));
            return $transient;
        } 

        $latest_version = $data->tag_name;
        $plugin_data = get_plugin_data($this->plugin_file);
        $current_version = $plugin_data['Version'];

        error_log("Current Version: " . $current_version);
        error_log("Latest Version: " . $latest_version);

        if (version_compare($current_version, $latest_version, '<')) {
            $plugin_update = new stdClass();
            $plugin_update->slug = $this->plugin_slug;
            $plugin_update->new_version = $latest_version;
            $plugin_update->url = $data->html_url;
            //Hardcode test: $plugin_update->package = 'https://github.com/Thrive-Agency/Module-Watcher/releases/download/2.6/module-watcher.zip';

            $plugin_update->package = $data->assets[0]->browser_download_url;

            error_log("Update Available: " . print_r($plugin_update, true));

            $transient->response[$this->plugin_slug . '/' . basename($this->plugin_file)] = $plugin_update;
        }

        return $transient;
    }

    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return false;
        }

        $response = wp_remote_get($this->api_url, [
            'headers' => [
                'User-Agent'    => 'WordPress Plugin Updater'
            ]
        ]); 

        if (is_wp_error($response)) {
            error_log("GitHub API Response Error: " . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            error_log("GitHub API Response Status Code: " . $status_code);
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        $res = new stdClass();
        $res->name = $data->name;
        $res->slug = $this->plugin_slug;
        $res->version = $data->tag_name;
        $res->author = '<a href="https://github.com/Thrive-Agency">Thrive Support</a>';
        $res->homepage = $data->html_url;
        $res->requires = '5.0';
        $res->tested = '7.8';
        $res->download_link = $data->zipball_url;
        $res->sections = [
            'description' => $data->body,
            'changelog' => $data->body
        ];

        return $res;
    }
}

new MyPluginUpdater(__FILE__);
?>