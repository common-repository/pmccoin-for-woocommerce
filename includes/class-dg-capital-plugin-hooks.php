<?php

/**
 * Register all actions and filters for the gateway
 *
 * @see       https://github.com/elitedevsquad
 * @since      2.0.0
 *
 * @package    Dg_Capital_Plugin
 * @subpackage Dg_Capital_Plugin/includes
 */

 /**
 * Register all actions and filters for the gateway.
 *
 * Enable and register actions and filters for dg_capital_gateway
 *
 * @package    Dg_Capital_Plugin
 * @subpackage Dg_Capital_Plugin/includes
 *
 * @author     DevSquad <http://github.com/elitedevsquad>
 */
class DG_Capital_Plugin_Hooks
{
    /**
     * The ID of this plugin.
     *
     * @since  2.0.0
     * @access private
     *
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since  2.0.0
     * @access private
     *
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * The id of this plugin.
     *
     * @since  2.0.0
     * @access private
     *
     * @var string $plugin_id The current id of this plugin.
     */
    private $plugin_id;

    /**
     * Initialize the class and set its properties.
     *
     * @since 2.0.0
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name     = $plugin_name;
        $this->version         = $version;
        $this->plugin_id       = get_option('dg_capital_plugin_id', null);
        $this->checkout_type   = 'spends';
    }

    /**
     * Load hooks and add wc-dg-capital-gateway to woocommerce.
     *
     * @since 2.0.0
     */
    public function load_dg_capital_gateway()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', function () {
                echo "<div class='error'><p>The WooCommerce plugin must be installed first.</p></div>";
            });

            return;
        }

        include_once 'class-wc-dg-capital-gateway.php';

        add_filter('woocommerce_payment_gateways', array($this, 'add_dg_capital_gateway'), 10, 2);
        add_filter('woocommerce_default_address_fields', array($this, 'dg_capital_billing_address_verification'));
        add_action('woocommerce_api_wc_' . $this->plugin_id . '_gateway', array($this, 'redirect_callback_handler'));
        add_action('wc_ajax_get_dg_capital_php_vars', array($this, 'get_dg_capital_php_vars'), 10, 2);
        // add_action('woocommerce_cart_calculate_fees', array($this, 'add_dg_capital_service_fee_rule'));
    
        add_action( 'init', array($this, 'create_new_order_status'));
        add_filter( 'wc_order_statuses', array($this, 'dg_add_new_wc_order_status'));
        add_action( 'manage_posts_extra_tablenav', array($this, 'dg_add_fetch_status_button'), 20, 1 );
        add_action( 'restrict_manage_posts', array($this, 'dg_update_pending_orders' ));
    }

    /**
     * Ajax Web hook to send php_vars to front end.
     *
     * @since 2.0.0
     */
    public function get_dg_capital_php_vars()
    {
        $dg_gateway = $this->get_dg_capital_gateway();

        if (!$dg_gateway) {
            exit;
        }

        if (0 == WC()->cart->total) {
            http_response_code(404);
        }

        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode($dg_gateway->load_dg_capital_php_vars());
        exit;
    }

    /**
     * Creating new order status 
     * "wc-dg-pending" for zel transactions
     * 
     * @since 2.6.8
     */
    function create_new_order_status() {
        register_post_status( 'wc-dg-pending', array(
            'label'                     => _x( 'Zelle Payment Pending', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Zelle Payment Pending <span class="count">(%s)</span>', 'Zelle Payment Pending<span class="count">(%s)</span>', 'woocommerce' )
        ) );
    }
    
    /**
     * Register in wc_order_statuses. 
     * 
     * @since 2.6.8
     */
    function dg_add_new_wc_order_status( $order_statuses ) {
        $order_statuses['wc-dg-pending'] = _x( 'Zelle Payment Pending', 'Order status', 'woocommerce' );
        return $order_statuses;
    }
    

    /**
     * Add Fetch Status button
     * 
     * @since 2.6.8
     */
    public function dg_add_fetch_status_button( $which ) {
        global $typenow;
    
        if ( 'shop_order' === $typenow && 'top' === $which ) {
            ?>
            <div class="alignleft actions custom">
                <button type="submit" name="update_status" style="height:32px;" class="button" value="yes"><?php
                    echo __( 'Fetch Status', 'woocommerce' ); ?></button>
            </div>
            <?php
        }
    }
    
    /**
     * Fetch and update status of Zelle Payment Pending transactions 
     * when the Fetch Status is clicked
     * 
     * 
     * @since 2.6.8
     */

    public function dg_update_pending_orders() {
        global $pagenow, $typenow;
        $dg_gateway   = $this->get_dg_capital_gateway();
    
        if ( 
            'shop_order' === $typenow && 
            'edit.php' === $pagenow && 
            isset($_GET['update_status']) && 
            $_GET['update_status'] === 'yes' 
        ) {
            $dg_gateway->dg_update_initiated_orders();
        }
    }

    /**
    * Woocomerce Api endpoint handler.
    *
    * redirect checkout and webhook notification.
    *
    * @since 2.0.0
    */
    public function redirect_callback_handler()
    {
        $dg_gateway   = $this->get_dg_capital_gateway();
        $order_number = filter_var($_GET['order_number'], FILTER_SANITIZE_STRING);

        if ($dg_gateway && $order_number != 'null') {
            $order = $dg_gateway->dg_get_order_by_order_number($order_number);

            if (0 !== filter_var($_GET['pmc_status'], FILTER_VALIDATE_INT) || !$order) {
                $message = null;

                if (30 == filter_var($_GET['pmc_status'], FILTER_VALIDATE_INT)) {
                    wc_add_notice('Thanks for purchase ' . get_option('dg_capital_plugin_method_title') . '! You can continue with your checkout.', 'success');
                    $order->update_status('cancelled', 'customer gave up on the purchase');
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
                $this->redirect_checkout_with_cart($message);
            }

            $response = wp_remote_post($dg_gateway->api_url . $this->checkout_type, $dg_gateway->generate_header_request($order));

            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);

                if (empty($body['errors']) && 200 == wp_remote_retrieve_response_code($response) || 201 == wp_remote_retrieve_response_code($response)) {
                    if (isset($body['status']) && 0 === filter_var($body['status'], FILTER_VALIDATE_INT)) {
                        if (!$order->get_date_paid()) {
                            $dg_gateway->complete_order($order, $_GET['is_zel']);
                        }

                        $return_url = $dg_gateway->get_return_url($order);
                        $return_url .= '&type='.$this->checkout_type;

                        header('Cache-Control: no-cache, must-revalidate');
                        header('Location:' . $return_url, 302);
                        exit;
                    } else {
                        $this->redirect_checkout_with_cart();
                    }
                }
            }
        }

        $this->redirect_checkout_with_cart('Ops, something went wrong, please contact the support.');
    }

    /**
     * Include WC_DG_Capital_Gateway class in woocommerce gateways array.
     *
     * @since 2.0.0
     *
     * @param array $gateways woocommerce gateways class array.
     */
    public function add_dg_capital_gateway($gateways)
    {
        array_push($gateways, 'WC_DG_Capital_Gateway');

        return $gateways;
    }

    /**
     * Add payment fees to cart.
     *
     * @since 2.0.0
     */
    // public function add_dg_capital_service_fee_rule()
    // {
    //     if (is_admin() && !defined('DOING_AJAX') || !is_checkout()) {
    //         return;
    //     }

    //     if (WC()->session->get('chosen_payment_method') == $this->plugin_id) {
    //         $dg_gateway = $this->get_dg_capital_gateway();

    //         if ($dg_gateway && !empty($dg_gateway->settings['plugin_payment_mode']) && 'direct' == $dg_gateway->settings['plugin_payment_mode']) {
    //             if (WC()->customer->get_billing_country() != 'US' && '0' != get_option($this->plugin_id . '_international_fee')) {
    //                 WC()->cart->add_fee(get_option('dg_capital_plugin_method_title') . ' International Fee', floatval(get_option($this->plugin_id . '_international_fee')), false);
    //             }

    //             if ('0' != get_option($this->plugin_id . '_service_fee') || '0' != get_option($this->plugin_id . '_percent_fee')) {
    //                 $service_fee = floatval(get_option($this->plugin_id . '_service_fee'));
    //                 $percent_fee = floatval(get_option($this->plugin_id . '_percent_fee'));
    //                 $cart_totals = WC()->cart->get_totals();

    //                 $cart_value = floatval($cart_totals['subtotal']) + floatval($cart_totals['shipping_total']) + $cart_totals['subtotal_tax'] + $cart_totals['shipping_tax'] - $cart_totals['discount_total'] - $cart_totals['discount_tax'] ;

    //                 $total_fee = ($cart_value * $percent_fee / 100) + $service_fee;

    //                 WC()->cart->add_fee(get_option('dg_capital_plugin_method_title') . ' Service Fee', $total_fee, false);
    //             }
    //         }
    //     }
    // }

    /**
     * Insert field postcode billing address if doesn't exists.
     *
     * @since 2.0.0
     *
     * @param array $fields checkout cart billing fields.
     */
    public function dg_capital_billing_address_verification($fields)
    {
        $address_fields['state']['required'] = true;

        if (empty($fields['postcode'])) {
            $fields['postcode'] = array(
                'label'    => 'Postcode / ZIP',
                'required' => true,
                'class'    => array(
                    'form-row-first',
                ),
                'autocomplete' => 'postal-code',
                'priority'     => 10,
            );
        }

        return $fields;
    }

    /**
     * Return the values of dg_capital gateway.
     *
     * @since  2.0.5
     *
     * @return object dg_capital gateway values.
     */
    private function get_dg_capital_gateway()
    {
        $gateways = new WC_Payment_Gateways();

        return empty($gateways->payment_gateways()[$this->plugin_id]) ? null : $gateways->payment_gateways()[$this->plugin_id];
    }

    /**
     * Redirect to checkout without clean the cart when payment was failed or get some error.
     *
     * @since 2.2.7
     *
     * @return void
     */
    private function redirect_checkout_with_cart($message = 'Sorry, your payment was not processed! Please try again.')
    {
        wc_add_notice($message, 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
}
