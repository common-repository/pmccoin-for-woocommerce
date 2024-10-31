<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @see       https://github.com/elitedevsquad
 * @since      2.0.0
 *
 * @package    Dg_Capital_Plugin
 * @subpackage Dg_Capital_Plugin/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      2.0.0
 *
 * @package    Dg_Capital_Plugin
 * @subpackage Dg_Capital_Plugin/includes
 *
 * @author     DevSquad <email@devsquad.com>
 */
class DG_Capital_Plugin_i18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since 2.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'dg-capital-plugin',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
