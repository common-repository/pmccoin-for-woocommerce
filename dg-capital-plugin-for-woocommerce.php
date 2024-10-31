<?php

/**
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @see               https://github.com/PMCGold
 * @since             2.0.0
 *
 * @package           Dg_Capital_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       PMC Gold for Woocommerce
 * Plugin URI:        https://github.com/PMCGold/pmc-plugin
 * Description:       Take PMC Gold as payment for woocommerce
 * Version:           2.7.10
 * Author:            PM Capital
 * Author URI:        https://pmccoingroup.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pmccoin-for-woocommerce
 * Domain Path:       /languages
 * WC tested up to:   4.8.0
 * Requires PHP:      7.0
 */
if (!defined('WPINC')) {
    exit;
}

define('DG_CAPITAL_ENV_PATH', plugin_dir_path(__FILE__) . 'includes/plugin.env');
$env = parse_ini_file(DG_CAPITAL_ENV_PATH, true);

define('DG_CAPITAL_PLUGIN_VERSION', current(get_file_data(__FILE__, array('version'))));
define('DG_CAPITAL_PLUGIN_MODE', 'redirect');
define('DG_CAPITAL_SHORT_NAME', $env['gateway']['SHORT_NAME']);
define('DG_CAPITAL_API_VERSION', $env['env']['API_VERSION']);
define('DG_CAPITAL_API_REQUEST_INTERVAL', $env['env']['API_REQUEST_INTERVAL']);
define('DG_CAPITAL_DEBUG_MODE', $env['env']['DEBUG_MODE']);
define('DG_CAPITAL_URL_PRODUCTION', $env['env']['URL_PRODUCTION']);
define('DG_CAPITAL_URL_SANDBOX', $env['env']['URL_SANDBOX']);
define('DG_CAPITAL_GA_TAG_ID', $env['env']['GA_TAG_ID'] ?? NULL);
define('DG_CAPITAL_ENABLE_LOGS', $env['env']['ENABLE_LOGS'] ?? false);

if (DG_CAPITAL_DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-dg-capital-plugin-activator.php
 */
function activate_dg_capital_plugin()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-dg-capital-plugin-activator.php';
    Dg_Capital_Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-dg-capital-plugin-deactivator.php
 */
function deactivate_dg_capital_plugin()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-dg-capital-plugin-deactivator.php';
    Dg_Capital_Plugin_Deactivator::deactivate();
}

/**
 * Add Settings Link to plugin
 *
 * Since the environment variables are loaded, will be added a settings link
 *
 * @since    2.0.0
 */
function add_dg_capital_plugin_settings_link($links)
{
    $id = get_option('dg_capital_plugin_id');
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $id) . '">' . 'Settings' . '</a>',
        '<a href="https://wordpress.org/plugins/". $id ."-for-woocommerce/" target="blank">Docs</a>',
    );

    return array_merge($plugin_links, $links);
}

register_activation_hook(__FILE__, 'activate_dg_capital_plugin');
register_deactivation_hook(__FILE__, 'deactivate_dg_capital_plugin');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_dg_capital_plugin_settings_link');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-dg-capital-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    2.0.0
 */
function run_dg_capital_plugin()
{
    $plugin = new DG_Capital_Plugin();
    $plugin->run();
}

run_dg_capital_plugin();
