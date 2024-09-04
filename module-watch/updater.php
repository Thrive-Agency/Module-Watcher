<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Thrive_Updater Class
 *
 * This class handles updating the plugin from a remote server.
 * It checks for plugin activation, updates, and other relevant actions.
 * Based on misha-update-checker https://github.com/rudrastyh/misha-update-checker/
 * 
 * @since 1.9.0
 * @package watch-modules
 */

if( !class_exists('Thrive_Updater') ) {

    // Ensure constants are defined before using them
    if( defined('WATCH_MODULES_VERSION') && defined('UPDATE_SERVER_URL') ) {
        do_action('qm/debug', WATCH_MODULES_VERSION);
        do_action('qm/debug', UPDATE_SERVER_URL);
    } else {
        do_action('qm/debug', 'WATCH_MODULES_VERSION or UPDATE_SERVER_URL not defined');
    }

    class Thrive_Updater {
        // Initialize properties
        public $plugin_slug;
        public $version;
        public $cache_key;
        public $cache_allowed;

        public function __construct() {

            $this->plugin_slug = 'module-watch';
            $this->version = defined('WATCH_MODULES_VERSION') ? WATCH_MODULES_VERSION : '1.0.0'; // Default version
            $this->cache_key = 'watch_modules_update';
            $this->cache_allowed = true; // Set to true to allow caching

            add_filter( 'plugins_api', array( $this, 'info'), 20, 3);
            add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
            add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
        }

        // Retrieve update information about the plugin from the remote server. 
        public function request() {

            $remote = get_transient( $this->cache_key );

            if( false === $remote || ! $this->cache_allowed ) {

                $remote = wp_remote_get(
                    UPDATE_SERVER_URL . 'info.json',
                    array(
                        'timeout' => 10,
                        'headers' => array(
                            'Accept' => 'application/json'
                        )
                    )
                );

                // Log the remote request attempt
                do_action('qm/debug', 'Fetched remote value: ' . print_r($remote, true));

                if(
                    is_wp_error( $remote )
                    || 200 !== wp_remote_retrieve_response_code( $remote )
                    || empty( wp_remote_retrieve_body( $remote ) )
                ) {
                    do_action('qm/debug', 'Error fetching remote or empty body.');
                    return false;
                }

                set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );
            }

            // Decode JSON and log the decoded value
            $remote = json_decode( wp_remote_retrieve_body( $remote ) );
            do_action('qm/debug', 'Decoded remote value: ' . print_r($remote, true));

            return $remote;
        }

        function info( $res, $action, $args ) {

            // Debug the action and args
            do_action('qm/debug', 'Action: ' . $action);
            do_action('qm/debug', 'Args: ' . print_r($args, true));

            // Do nothing if you're not getting plugin information right now
            if( 'plugin_information' !== $action ) {
                do_action('qm/debug', 'Not getting plugin information');
                return $res;
            }

            // Do nothing if it is not our plugin
            if( $this->plugin_slug !== $args->slug ) {
                do_action('qm/debug', 'Not the plugin');
                return $res;
            }

            // Get updates
            $remote = $this->request();

            if( ! $remote ) {
                do_action('qm/debug', 'No remote data retrieved');
                return $res;
            }

            // Populate an object with the JSON data returned from the update server
            $res = new stdClass();
            do_action('qm/debug', 'Remote Slug: ' . $remote->slug);

            $res->name = $remote->name;
            $res->slug = $remote->slug;
            $res->version = $remote->version;
            $res->tested = $remote->tested;
            $res->requires = $remote->requires;
            $res->author = $remote->author;
            $res->author_profile = $remote->author_profile;
            $res->download_link = $remote->download_url;
            $res->trunk = $remote->download_url;
            $res->requires_php = $remote->requires_php;
            $res->last_updated = $remote->last_updated;

            $res->sections = array(
                'description' => $remote->sections->description,
                'installation' => $remote->sections->installation,
                'changelog' => $remote->sections->changelog
            );

            if( ! empty( $remote->banners ) ) {
                $res->banners = array(
                    'low' => $remote->banners->low,
                    'high' => $remote->banners->high
                );
            }

            do_action('qm/debug', 'Response: ' . print_r($res, true));

            return $res;
        }

        public function update( $transient ) {

            if ( empty($transient->checked ) ) {
                do_action('qm/debug', 'No checked transient');
                return $transient;
            }

            $remote = $this->request();

            if(
                $remote
                && version_compare( $this->version, $remote->version, '<' )
                && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' )
                && version_compare( $remote->requires_php, PHP_VERSION, '<' )
            ) {
                $res = new stdClass();
                $res->slug = $this->plugin_slug;
                $res->plugin = plugin_basename( __FILE__ ); // Main plugin file
                $res->new_version = $remote->version;
                $res->tested = $remote->tested;
                $res->package = $remote->download_url;

                $transient->response[ $res->plugin ] = $res;
                do_action('qm/debug', 'Update response added: ' . print_r($res, true));
            }

            return $transient;
        }

        public function purge( $upgrader, $options ) {

            if (
                $this->cache_allowed
                && 'update' === $options['action']
                && 'plugin' === $options['type']
            ) {
                // Clear the cache when a new plugin version is installed
                delete_transient( $this->cache_key );
                do_action('qm/debug', 'Cache cleared after update.');
            }

        }
    }

    new Thrive_Updater();
}