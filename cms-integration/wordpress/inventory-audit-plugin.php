<?php
/**
 * Plugin Name: Inventory Audit Panel
 * Plugin URI: https://github.com/yourusername/inventory-sync-module
 * Description: Critical Inventory Synchronization Module - WordPress Plugin Integration
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: inventory-audit
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('INVENTORY_AUDIT_VERSION', '1.0.0');
define('INVENTORY_AUDIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INVENTORY_AUDIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INVENTORY_AUDIT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Inventory Audit Plugin Class
 */
class InventoryAuditPlugin
{
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings = array();

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
        add_action('wp_ajax_inventory_audit_proxy', array($this, 'ajaxApiProxy'));

        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('InventoryAuditPlugin', 'uninstall'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Load text domain for translations
        load_plugin_textdomain('inventory-audit', false, dirname(INVENTORY_AUDIT_PLUGIN_BASENAME) . '/languages');

        // Initialize settings
        $this->initSettings();

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . INVENTORY_AUDIT_PLUGIN_BASENAME, array($this, 'addSettingsLink'));
    }

    /**
     * Initialize plugin settings
     */
    private function initSettings()
    {
        $this->settings = get_option('inventory_audit_settings', array(
            'api_base_url' => 'http://localhost:8000/api',
            'items_per_page' => 50,
            'auto_refresh' => true,
            'refresh_interval' => 300000 // 5 minutes
        ));
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu()
    {
        // Main menu page
        add_menu_page(
            __('Inventory Audit', 'inventory-audit'),
            __('Inventory Audit', 'inventory-audit'),
            'manage_options',
            'inventory-audit',
            array($this, 'renderAuditPage'),
            'dashicons-clipboard',
            30
        );

        // Settings submenu
        add_submenu_page(
            'inventory-audit',
            __('Settings', 'inventory-audit'),
            __('Settings', 'inventory-audit'),
            'manage_options',
            'inventory-audit-settings',
            array($this, 'renderSettingsPage')
        );

        // Statistics submenu
        add_submenu_page(
            'inventory-audit',
            __('Statistics', 'inventory-audit'),
            __('Statistics', 'inventory-audit'),
            'manage_options',
            'inventory-audit-stats',
            array($this, 'renderStatsPage')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, 'inventory-audit') === false) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'inventory-audit-admin',
            INVENTORY_AUDIT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            INVENTORY_AUDIT_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'inventory-audit-config',
            INVENTORY_AUDIT_PLUGIN_URL . 'assets/js/config.js',
            array('jquery'),
            INVENTORY_AUDIT_VERSION,
            true
        );

        wp_enqueue_script(
            'inventory-audit-api',
            INVENTORY_AUDIT_PLUGIN_URL . 'assets/js/api.js',
            array('jquery', 'inventory-audit-config'),
            INVENTORY_AUDIT_VERSION,
            true
        );

        wp_enqueue_script(
            'inventory-audit-admin',
            INVENTORY_AUDIT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'inventory-audit-api'),
            INVENTORY_AUDIT_VERSION,
            true
        );

        // Localize script with WordPress-specific data
        wp_localize_script('inventory-audit-admin', 'inventoryAuditWP', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('inventory_audit_nonce'),
            'apiBaseUrl' => $this->settings['api_base_url'],
            'itemsPerPage' => $this->settings['items_per_page'],
            'autoRefresh' => $this->settings['auto_refresh'],
            'refreshInterval' => $this->settings['refresh_interval'],
            'strings' => array(
                'loading' => __('Loading...', 'inventory-audit'),
                'error' => __('Error', 'inventory-audit'),
                'success' => __('Success', 'inventory-audit'),
                'noData' => __('No data found', 'inventory-audit'),
                'confirmDelete' => __('Are you sure you want to delete this item?', 'inventory-audit')
            )
        ));
    }

    /**
     * AJAX API proxy for secure requests
     */
    public function ajaxApiProxy()
    {
        // Check nonce for security
        check_ajax_referer('inventory_audit_nonce', 'nonce');

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'inventory-audit'));
        }

        $endpoint = sanitize_text_field($_POST['endpoint']);
        $method = sanitize_text_field($_POST['method']);
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        // Make API request
        $response = $this->makeApiRequest($endpoint, $method, $data);

        wp_send_json($response);
    }

    /**
     * Make API request to backend
     */
    private function makeApiRequest($endpoint, $method = 'GET', $data = array())
    {
        $url = trailingslashit($this->settings['api_base_url']) . ltrim($endpoint, '/');

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest'
            )
        );

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return $decoded ? $decoded : array(
            'success' => false,
            'message' => __('Invalid API response', 'inventory-audit')
        );
    }

    /**
     * Render main audit page
     */
    public function renderAuditPage()
    {
        include INVENTORY_AUDIT_PLUGIN_DIR . 'templates/audit-page.php';
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage()
    {
        // Handle form submission
        if (isset($_POST['submit'])) {
            check_admin_referer('inventory_audit_settings');

            $this->settings = array(
                'api_base_url' => sanitize_url($_POST['api_base_url']),
                'items_per_page' => intval($_POST['items_per_page']),
                'auto_refresh' => isset($_POST['auto_refresh']),
                'refresh_interval' => intval($_POST['refresh_interval'])
            );

            update_option('inventory_audit_settings', $this->settings);

            add_settings_error(
                'inventory_audit_settings',
                'settings_saved',
                __('Settings saved successfully!', 'inventory-audit'),
                'success'
            );
        }

        include INVENTORY_AUDIT_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * Render statistics page
     */
    public function renderStatsPage()
    {
        include INVENTORY_AUDIT_PLUGIN_DIR . 'templates/stats-page.php';
    }

    /**
     * Add settings link to plugins page
     */
    public function addSettingsLink($links)
    {
        $settingsLink = '<a href="' . admin_url('admin.php?page=inventory-audit-settings') . '">' . __('Settings', 'inventory-audit') . '</a>';
        array_unshift($links, $settingsLink);
        return $links;
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create default settings
        add_option('inventory_audit_settings', array(
            'api_base_url' => 'http://localhost:8000/api',
            'items_per_page' => 50,
            'auto_refresh' => true,
            'refresh_interval' => 300000
        ));

        // Set activation flag
        add_option('inventory_audit_activated', true);

        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clear any scheduled events
        wp_clear_scheduled_hook('inventory_audit_cleanup');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall()
    {
        // Remove settings
        delete_option('inventory_audit_settings');
        delete_option('inventory_audit_activated');

        // Remove any custom database tables if created
        // global $wpdb;
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inventory_audit_cache");
    }

    /**
     * Get plugin settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Update plugin settings
     */
    public function updateSettings($newSettings)
    {
        $this->settings = array_merge($this->settings, $newSettings);
        update_option('inventory_audit_settings', $this->settings);
    }
}

// Initialize the plugin
InventoryAuditPlugin::getInstance();
