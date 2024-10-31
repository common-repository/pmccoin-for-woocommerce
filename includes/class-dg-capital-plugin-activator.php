<?php

/**
 * Fired during plugin activation
 *
 * @see       https://github.com/elitedevsquad
 * @since      2.0.0
 *
 * @package    Dg_Capital_Plugin
 * @subpackage Dg_Capital_Plugin/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      2.0.0
 *
 * @package    Dg_Capital_Plugin
 * @subpackage Dg_Capital_Plugin/includes
 *
 * @author     DevSquad <email@devsquad.com>
 */
class Dg_Capital_Plugin_Activator
{
    /**
     * Load env vars to wordpress.
     *
     * @since 2.0.0
     */
    public static function activate()
    {
        $env = parse_ini_file(DG_CAPITAL_ENV_PATH, true);

        foreach ($env['gateway'] as $key => $value) {
            update_option('dg_capital_plugin_' . $key, $value);
        }
    }
}
