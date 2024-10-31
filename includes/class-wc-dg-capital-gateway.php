<?php

/**
 * The Woocommerce gateway class.
 *
 * @see        https://github.com/PMCGold
 * @since      2.0.0
 *
 * @package    DG_Capital_Plugin
 * @subpackage DG_Capital_Plugin/includes
 */

/**
 * The Woocommerce gateway class
 *
 * Defines and overrides core functions
 *
 * @package    DG_Capital_Plugin
 * @subpackage DG_Capital_Plugin/includes
 *
 * @author     PM Capital <https://pmccoingroup.com>
 */
class WC_DG_Capital_Gateway extends WC_Payment_Gateway
{
    const HTTP_STATUS_OK      = 200;
    const HTTP_STATUS_CREATED = 201;

    /**
     * Initialize the class fields and hooks.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->id          = get_option('dg_capital_plugin_id');
        $this->on_settings = !empty($_GET['section']) && sanitize_text_field($_GET['section']) == $this->id;

        /** Work around for woocommerce bug duplicated request when save changes*/
        if (is_admin() && $this->on_settings && $_POST) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . get_option('dg_capital_plugin_id')));
        }

        $this->test_mode               = $this->get_option('test_mode', 'no');
        $this->app_url                 = $this->test_mode == 'yes' ? DG_CAPITAL_URL_SANDBOX : DG_CAPITAL_URL_PRODUCTION;
        $this->method_title            = get_option('dg_capital_plugin_method_title');
        $this->plugin_name             = $this->method_title . ' For Woocommerce';
        $this->teams_service_fee       = array();
        $this->teams_percent_fee       = array();
        $this->teams_plugin_mode       = array();
        $this->teams_international_fee = array();
        $this->api_key_right           = false;
        $this->gold_price              = 0;
        $this->order_button_text       = 'Place Order with ' . DG_CAPITAL_SHORT_NAME;
        $this->method_description      = 'Take ' . $this->method_title . ' payments';
        $this->api_url                 = $this->app_url . '/api/' . DG_CAPITAL_API_VERSION . '/';
        $plugin_icon_name              = $this->id == 'pmccoin' ? 'pmc_gold.svg' : 'flex.svg';
        $this->icon                    = plugins_url('/assets/images/' . $plugin_icon_name, __DIR__);
        $this->plugin_payment_mode     = !empty($this->get_option('plugin_payment_mode')) ? $this->get_option('plugin_payment_mode') : DG_CAPITAL_PLUGIN_MODE;
        $this->checkout_type           = 'spends';
        $pay_with = 'Credit/Debit card';
        if($this->get_option('ach') == 'yes' || !$this->get_option('ach')) {
            $pay_with .= ', ACH';
        }
        if($this->get_option('zelle') == 'yes' || !$this->get_option('zelle')) {
            $pay_with .= ', Zelle';
        }
        $pay_with = $pay_with.' & more';
        $this->title                   = 'Pay With '. DG_CAPITAL_SHORT_NAME .' ('. $pay_with. ')';
        $this->enabled                 = $this->get_option('enabled');        
        $this->init_settings();

        if ('redirect' == $this->plugin_payment_mode) {
            $this->has_fields = false;
        }

        if ($this->on_settings) {
            delete_transient('teams');
            delete_transient('wallets');
        }

        $this->init_form_fields();
        $this->get_token_quantity();

        add_action('wp_enqueue_scripts', array($this, 'add_checkout_scripts_and_styles'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'change_order_received_text'), 10, 2);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        $this->admin_settings_validations();

        if ($this->on_settings) {
            if ($this->errors) {
                $this->display_errors();
                return;
            }
        
            $this->notice('The plugin is configured correctly', 'notice-success');
            $this->enabled == 'no' ? $this->notice("The plugin is disabled! Enable to show $this->method_title in the checkout page", 'notice-info') : '';
            $this->test_mode == 'yes' ? $this->notice("The test mode is enabled", 'notice-warning') : '';
        }
    }

    /**
     * Return the gold price value
     *
     * @return float $this->gold_price
     */
    public function get_gold_price()
    {
        if (0 == $this->gold_price) {
            $this->get_token_quantity();
        }

        return $this->gold_price;
    }

    /**
     * Rewrite order receive page.
     *
     * @since 1.0.0
     */
    public function change_order_received_text($str, $order)
    {
        if ($order->get_payment_method() == $this->id) {
            $gold = $this->generate_token_quantity($order->get_total());
            $str .= sprintf("<div class=\"dg_capital-transaction-msg\">You purchased %s %s's".
                " for \$%s Your %s's were transferred to the <b>%s</b> in exchange for the ".
                "products and services listed below. <br/> Please check your email for a ".
                "confirmation of the purchase.</div>", $gold, $this->method_title,
                $order->get_total(), $this->method_title, get_bloginfo('name'));
        }

        return $str;
    }

    /**
     * Generate token quantity.
     *
     * @since  1.0.0
     *
     * @param float $total
     *
     * @return string
     */
    public function generate_token_quantity($total)
    {
        $token_quantity = '';

        if ($this->gold_price) {
            $token_quantity = ($total * 100) / $this->gold_price;
            $dot_position   = strpos($token_quantity, '.');
            $token_quantity = substr($token_quantity, 0, $dot_position + 10);
        }

        return $token_quantity;
    }

    /**
     * Get plugin icon
     *
     * @since  1.0.0
     *
     * @return string
     */
    public function get_icon()
    {
        $html = "";
        return apply_filters('woocommerce_gateway_icon', $html, $this->id);
    }

    /**
     * Get plugin description.
     *
     * @since  2.7.4
     *
     * @return string
     */
    public function get_description()
    {
        $description = '&nbsp;&nbsp;<a target="blank" href="' . esc_attr(get_option('dg_capital_plugin_what_is_url')) . '">'. DG_CAPITAL_SHORT_NAME .' terms and conditions</a>'.
        '<img style="float:left" src="' . esc_attr($this->icon) . '" alt="' . esc_attr__($this->method_title . ' icon', 'woocommerce') . '" width="80px" height="28px"/>';

        return apply_filters('woocommerce_gateway_description', $description, $this->id);
    }

    /**
     * Print messages for admin settings.
     *
     * @since  1.0.0
     *
     * @return void
     */
    private function admin_settings_validations()
    {
        $warnings = [];

        if (empty($this->get_option('title'))) {
            array_push($warnings, 'Title is required');
        }

        if (empty($this->get_option('api_key'))) {
            array_push($warnings, $this->method_title . ' API key is required');
        }

        if (empty($this->get_option('team_id')) && $this->api_key_right) {
            array_push($warnings, 'Select the Company and save changes');
        }

        if (empty($this->get_option('wallet_id')) && $this->api_key_right) {
            $warning = 'Select the Wallet and save changes';

            if (!get_transient('wallets')) {
                $warning = "This company doesn't have wallets";
            }

            array_push($warnings, $warning);
        }

        if ($this->on_settings && !wp_doing_cron() && !is_ssl() && $this->test_mode == 'no') {
            $ssl = false;
            if (empty($warnings)) {
                $ssl = $this->retry();
            }
            if (!$ssl) {
                array_push($warnings, 'SSL must be enabled');
            }
        }

        foreach ($warnings as $warning) {
            $this->add_error($warning);
        }

        if (!empty($warnings)) {
            $this->custom_logs('error caught at '. __METHOD__ . ' ' . __LINE__);
            $this->custom_logs($warnings);
            $this->custom_logs($this);
            $this->update_option('enabled', 'no');
            $this->notice('Plugin disabled until properly configured', 'notice-warning');
        }
    }

    /**
     * Set Up admin plugin fields and options
     *
     * @since  1.0.0
     *
     * @return array
     */
    public function init_form_fields()
    {
        $fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable plugin',
                'label'       => 'Enable to show this payment option on checkout',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ),

            'test_mode' => array(
                'title'       => 'Test Mode',
                'label'       => 'Enable to test on sandbox environment',
                'type'        => 'checkbox',
                'default'     => 'no',
            ),

            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout',
                'default'     => $this->method_title,
            ),

            'ach' => array(
                'title'       => 'Description',
                'label'       => 'ACH',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ),

            'zelle' => array(
                'title'       => '',
                'label'       => 'ZELLE',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ),

            'api_key' => array(
                'title'       => $this->method_title . ' API key',
                'type'        => 'password',
                'description' => "If you don't know how to generate the API token click <a href=\"" . DG_CAPITAL_URL_PRODUCTION . '/docs/v1/auth" target="blank">Here</a>',
            ),
        );

        $teams = array();

        if ($this->get_option('team_id') && get_transient('teams')) {
            $teams = $this->generateTeamOptions(get_transient('teams'), 'teams');
        } else {
            $teams = $this->get_teams_or_wallets('teams');
        }

        if ($this->api_key_right && !empty($teams)) {
            $fields['team_id'] = array(
                'title'       => 'Company',
                'type'        => 'select',
                'description' => 'Choose a company and save',
                'default'     => 'Select Company',
                'options'     => $teams,
            );

            if (!empty($_POST['woocommerce_' . $this->id . '_team_id'])
                && sanitize_text_field($_POST['woocommerce_' . $this->id . '_team_id'])
                != $this->get_option('team_id')) {
                $this->update_option('wallet_id', null);
            }
        }

        if ($this->api_key_right && $this->get_option('team_id')) {
            $wallets = array();

            if (get_transient('wallets')) {
                $wallets = $this->generateTeamOptions(get_transient('wallets'), 'wallets');
            } else {
                $wallets = $this->get_teams_or_wallets('wallets');
            }

            if (!empty($wallets)) {
                $fields['wallet_id'] = array(
                    'title'       => 'Wallet',
                    'type'        => 'select',
                    'description' => 'Choose a wallet and save',
                    'default'     => 'Select Wallet',
                    'options'     => $wallets,
                );
            }

            if (!empty($this->teams_international_fee)
                && array_key_exists($this->get_option('team_id'), $this->teams_international_fee)) {
                update_option($this->id . '_international_fee', $this->teams_international_fee[$this->get_option('team_id')]);
            }

            if (!empty($this->teams_service_fee)
                && !empty($this->teams_percent_fee)
                && array_key_exists($this->get_option('team_id'), $this->teams_service_fee)
                && array_key_exists($this->get_option('team_id'), $this->teams_percent_fee)) {
                update_option($this->id . '_service_fee', $this->teams_service_fee[$this->get_option('team_id')]);
                update_option($this->id . '_percent_fee', $this->teams_percent_fee[$this->get_option('team_id')]);
            }

            if (!empty($this->teams_plugin_mode)
                && array_key_exists($this->get_option('team_id'), $this->teams_plugin_mode)
                && 'both' != $this->teams_plugin_mode[$this->get_option('team_id')]) {
                $this->update_option('plugin_payment_mode', $this->teams_plugin_mode[$this->get_option('team_id')]);
            }
        }

        if ($this->api_key_right
            && $this->get_option('team_id')
            && array_key_exists($this->get_option('team_id'), $this->teams_plugin_mode)
            && 'both' == $this->teams_plugin_mode[$this->get_option('team_id')]) {
            $fields['plugin_payment_mode'] = array(
                'title'       => 'Plugin Payment Mode',
                'type'        => 'select',
                'description' => 'Choose the payment mode',
                'default'     => 'redirect',
                'options'     => array(
                    'direct'   => 'Direct',
                    'redirect' => 'Redirect',
                ),
            );
        }

        $this->form_fields = $fields;
        wp_enqueue_script('settings_script', plugins_url('assets/js/dg_settings.js', __DIR__), array('jquery'), null, true);
        $this->load_dg_settings_php_vars();
    }

    /**
     * Insert javascript script variables on front end.
     *
     * @since  2.7.6
     *
     * @return array
     */
    public function load_dg_settings_php_vars()
    {
        $settings_php_vars = ['id' => $this->id];
        wp_localize_script('settings_script', 'settings_php_vars', $settings_php_vars);
        return $settings_php_vars;
    }

    /**
     * Insert javascript script variables on front end.
     *
     * @since  2.0.0
     *
     * @return array
     */
    public function load_dg_capital_php_vars()
    {
        $php_vars = array(
            'id'                  => $this->id,
            'method_title'        => $this->method_title,
            'short_name'          => DG_CAPITAL_SHORT_NAME,
            'token_quantity'      => $this->generate_token_quantity(WC()->cart->total),
            'plugin_payment_mode' => $this->plugin_payment_mode,
            'vendor_name'         => get_bloginfo('name'),
            'total_amount'        => WC()->cart->total,
            'plugin_version'      => DG_CAPITAL_PLUGIN_VERSION,
            'api_version'         => DG_CAPITAL_API_VERSION,
            'plugin_icon'         => $this->icon,
            'images_dir'          => plugins_url('/assets/images/', __DIR__),
            'gold_price'          => $this->get_gold_price(),
            'app_url'             => parse_url($this->app_url)['host'],
            'app_url_raw'         => $this->app_url,
            'token'               => strtok($this->method_title," "),
            'ga_tag_id'           => DG_CAPITAL_GA_TAG_ID
        );

        wp_localize_script('scripts', 'php_vars', $php_vars);

        return $php_vars;
    }

    /**
     * register scripts and styles on checkout page.
     *
     * @since  1.0.0
     *
     * @return void
     */
    public function add_checkout_scripts_and_styles()
    {
        if (DG_CAPITAL_GA_TAG_ID) {
            wp_enqueue_script('ga', "https://www.googletagmanager.com/gtag/js?id=" . DG_CAPITAL_GA_TAG_ID, array('jquery'), null, true);
        }
        wp_enqueue_script('scripts', plugins_url('assets/js/dg_capital.js', __DIR__), array(DG_CAPITAL_GA_TAG_ID ? 'ga' : 'jquery'), null, true);
        wp_enqueue_script('swall', plugins_url('assets/js/swall2.js', __DIR__), array('scripts'), null, true);
        wp_enqueue_style('dg_capital-css', plugins_url('assets/css/dg_capital.css', __DIR__), null, 'all');
        wp_enqueue_style('swall', plugins_url('assets/css/swall2.css', __DIR__), null, 'all');
        $this->load_dg_capital_php_vars();
    }

    /**
     * Validate order fields.
     *
     * @since  1.0.0
     *
     * @return bool
     */
    public function validate_fields()
    {
        return true;
    }

    /**
     * Prepare data for send to DG_Capital_Plugin API
     *
     * @since  1.0.0
     *
     * @param object $order
     *
     * @return array
     */
    private function parseFields($order)
    {
        $order_id    = $this->dg_get_order_string_by_id($order->get_id());

        if ('redirect' == $this->plugin_payment_mode) {
            $data = array(
                'wallet_id'    => $this->get_option('wallet_id'),
                'order_id'     => $order_id,
                'return_url'   => get_site_url(null, '/wc-api/wc_' . $this->id . '_gateway', 'https'),
                'return_token' => $order->get_order_key(),
                'customer'     => array(
                    'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'email'   => $order->get_billing_email(),
                    'phone'   => $order->get_billing_phone(),
                    'address' => array(
                        'line1'   => $order->get_billing_address_1(),
                        'line2'   => $order->get_billing_address_2(),
                        'country' => $order->get_billing_country(),
                        'state'   => $order->get_billing_state(),
                        'city'    => $order->get_billing_city(),
                        'zipcode' => $order->get_billing_postcode(),
                    ),
                ),
            );

            $data['items'] = array();
            $taxes         = WC()->cart->get_tax_totals();
            $discounts     = WC()->cart->get_coupon_discount_totals();
            $data['total'] = (int) (WC()->cart->total * 100);

            foreach ($order->get_data()['line_items'] as $item_id => $item_data) {
                $unit_price = ($item_data['subtotal'] / intval($item_data['quantity']));
                array_push($data['items'], array(
                    'name'          => $item_data['name'],
                    'description'   => $item_data['name'],
                    'quantity'      => $item_data['quantity'],
                    'price'         => $this->formatValueToProcess($unit_price),
                    'precision'     => $this->getPrecision($unit_price),
                    'type'          => 'product',
                ));
            }

            if (!empty($discounts)) {
                foreach ($discounts as $discount_name => $discount_value) {
                    array_push($data['items'], array(
                        'name'          => (string) $discount_name,
                        'description'   => (string) $discount_name,
                        'quantity'      => 1,
                        'price'         => $this->formatValueToProcess($discount_value),
                        'precision'     => $this->getPrecision($discount_value),
                        'type'          => 'discount',
                    ));
                }
            }

            if ($order->get_data()['shipping_total'] > 0) {
                $shipping = $order->get_data()['shipping_total'];
                array_push($data['items'], array(
                    'name'          => 'Shipping',
                    'description'   => 'Shipping value',
                    'quantity'      => 1,
                    'price'         => $this->formatValueToProcess($shipping),
                    'precision'     => $this->getPrecision($shipping),
                    'type'          => 'tax',
                ));
            }

            if (!empty($taxes)) {
                foreach ($taxes as $tax_key => $tax_value) {
                    array_push($data['items'], array(
                        'name'          => (string) $tax_value->label,
                        'description'   => (string) $tax_value->label,
                        'quantity'      => 1,
                        'price'         => $this->formatValueToProcess($tax_value->amount),
                        'precision'     => $this->getPrecision($tax_value->amount),
                        'type'          => 'tax',
                    ));
                }
            }

            if (WC()->cart->get_fee_total() > 0) {
                $fee_total = WC()->cart->get_fee_total();
                array_push($data['items'], array(
                    'name'          => 'Fee',
                    'description'   => 'Fee total',
                    'quantity'      => 1,
                    'price'         => $this->formatValueToProcess($fee_total),
                    'precision'     => $this->getPrecision($fee_total),
                    'type'          => 'tax',
                ));
            }
        }

        return $data;
    }

    /**
     * get gold price.
     *
     * @since  1.0.0
     *
     * @return void
     */
    private function get_token_quantity()
    {
        if (get_transient('gold_price')) {
            $this->gold_price = get_transient('gold_price');

            return;
        }

        if (!$this->on_settings && !is_checkout()
            || empty($this->get_option('team_id'))
            || 'no' == $this->enabled && 'no' == $this->test_mode) {
            return;
        }

        $response = wp_remote_get($this->app_url . '/api/gold-price', $this->generate_header_request());

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if (empty($body['errors'])
                && self::HTTP_STATUS_OK == wp_remote_retrieve_response_code($response)
                || self::HTTP_STATUS_CREATED == wp_remote_retrieve_response_code($response)) {
                $this->gold_price = $body;
                set_transient('gold_price', $body, DG_CAPITAL_API_REQUEST_INTERVAL);
            }
        }
    }

    /**
     * Get Teams list or Wallets list
     *
     * @since  1.0.0
     *
     * @param string $endpoint
     *
     * @return array
     */
    private function get_teams_or_wallets($endpoint)
    {
        if (!$this->on_settings && !is_checkout()
            || !$this->get_option('api_key')
            || 'no' == $this->enabled && 'no' == $this->test_mode && is_checkout()) {
            return;
        }

        $response = wp_remote_get($this->api_url . $endpoint, $this->generate_header_request());

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if (empty($body['errors'])
                && self::HTTP_STATUS_OK == wp_remote_retrieve_response_code($response)
                || self::HTTP_STATUS_CREATED == wp_remote_retrieve_response_code($response)) {
                $items = array();

                if (!empty($body['data'])) {
                    $this->api_key_right = true;

                    if ('teams' == $endpoint) {
                        set_transient('teams', $body['data'], DG_CAPITAL_API_REQUEST_INTERVAL);
                    } else {
                        set_transient('wallets', $body['data'], DG_CAPITAL_API_REQUEST_INTERVAL);
                    }

                    $items = $this->generateTeamOptions($body['data'], $endpoint);
                }

                return $items;
            } else {
                $message = $body['message'] . " When try get $endpoint";

                $this->custom_logs('logged at '. __METHOD__ . ' ' . __LINE__ . ' before Unauthenticated.');
                $this->custom_logs($response);

                if ('Unauthenticated.' == $body['message']) {
                    $this->custom_logs('error caught at '. __METHOD__ . ' ' . __LINE__);

                    $message = 'API Key invalid. Please contact our support team.';
                    $this->update_option('enabled', 'no');
                    $this->update_option('team_id', null);
                }

                if (empty($body['message'])) {
                    $message = 'Something went wrong when try access the API';
                }

                $this->add_error($message);

                if (!empty($body['errors'])) {
                    foreach ($body['errors'] as $key => $error) {
                        $this->add_error($error);
                    }
                }
            }
        } else {
            $this->add_error('Error on try access the API');

            $this->custom_logs("error caught at get_teams_or_wallets");
            $this->custom_logs('error caught at '. __METHOD__ . ' ' . __LINE__);
            $this->custom_logs($response);
            
            if (!empty($response->errors)) {
                foreach ($response->errors as $key => $error) {
                    $this->add_error(current($error));
                }
            }
        }
    }

    /**
     * Process payment on DG_Capital_Plugin API
     *
     * @since  1.0.0
     *
     * @param int $order_id
     *
     * @return void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_currency() !== 'USD') {
            wc_add_notice(DG_CAPITAL_SHORT_NAME . ' only accepts USD at this time. If you want to use other currencies you might want to choose another payment option', 'error');
            return;
        }

        if ('redirect' == $this->plugin_payment_mode) {
            $response = wp_remote_post($this->api_url . $this->checkout_type, $this->generate_header_request($order));
            
            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);

                if (empty($body['errors'])
                    && self::HTTP_STATUS_OK == wp_remote_retrieve_response_code($response)
                    || self::HTTP_STATUS_CREATED == wp_remote_retrieve_response_code($response)) {
                    return array(
                        'result'   => 'success',
                        'redirect' => $body['url'],
                    );
                } else {
                    /**
                     * on 402 exception and checkout type is spend
                     * form custom error block and attaching it to wc error
                     */
                    if (wp_remote_retrieve_response_code($response) == 402) {
                        return array(
                            'result' => 'success',
                            'redirect' => $body['url'],
                        );
                    } else {
                        wc_add_notice($body['message'], 'error');

                        $this->custom_logs('error caught at '. __METHOD__ . ' ' . __LINE__);
                        $this->custom_logs($response);
                    }

                    foreach ($body['errors'] as $error) {
                        wc_add_notice(json_encode($error), 'error');
                    }

                    return;
                }
            } else {
                $this->custom_logs('error caught at '. __METHOD__ . ' ' . __LINE__);
                $this->custom_logs($response);

                wc_add_notice('Something went wrong.', 'error');

                return;
            }
        }
    }

    /**
     * Generate Header Request for API
     *
     * @since 2.2.7
     *
     * @param object $order
     *
     * @return array
     */
    public function generate_header_request($order = null, $ids = [])
    {
        $request = array(
            'sslverify' => (boolean) $this->test_mode == 'no',
            'timeout'   => 300,
            'headers'   => array(
                'Content-Type'            => 'application/json',
                'Accept'                  => 'application/json',
                'Authorization'           => 'Bearer ' . $this->get_option('api_key'),
                'page'                    => 1,
                'perPage'                 => 500,
                'plugin-mode'             => $this->plugin_payment_mode,
                'plugin-version'          => DG_CAPITAL_PLUGIN_VERSION,
                'plugin-request-interval' => DG_CAPITAL_API_REQUEST_INTERVAL,
             ),
        );

        if ($order) {
            $request['body'] = json_encode($this->parseFields($order));
        }
        if ($ids) {
            $request['body'] = ["orders" => $ids];
        }

        if ($this->get_option('team_id')) {
            $request['headers']['team-id'] = $this->get_option('team_id');
        }

        return $request;
    }

    /**
     * Complete the order
     *
     * @since 2.2.8
     *
     * @param object $order
     *
     * @return void
     */
    public function complete_order($order, $is_zel = false)
    {
        $status = $order->get_status();
        if ($is_zel && ($status=='pending' || $status=='cancelled')) {
            $order->update_status('wc-dg-pending', 'Payment initiated with zel.');
        } else {
            $order->update_status('processing', 'Payment authorized on credit card.');
            $order->payment_complete();
        }
        WC()->cart->empty_cart();
    }

    /**
     * Generate team data
     *
     * @since 2.5.0
     *
     * @param array $items Teams or Wallets data
     * @param string $itemType Can be 'teams' or 'wallets'
     *
     * @return array
     */
    private function generateTeamOptions($items, $itemType)
    {
        $returnData = array(0 => $itemType == 'teams' ? 'Select Company' : 'Select Wallet');

        foreach ($items as $item) {
            $returnData[$item['id']] = $item[$itemType == 'wallets' ? 'nickname' : 'name'];

            if ('teams' == $itemType) {
                $this->teams_international_fee[$item['id']] = $item['international_fee'];
                $this->teams_service_fee[$item['id']]       = $item['service_fee'];
                $this->teams_percent_fee[$item['id']]       = $item['percent_fee'];
                $this->teams_plugin_mode[$item['id']]       = !empty($item['settings']['plugin_payment_mode']) ? $item['settings']['plugin_payment_mode'] : DG_CAPITAL_PLUGIN_MODE;
            }
        }

        return $returnData;
    }

    /**
     * To detect and record precision counts of amounts
     * 
     * since 2.8.0
     * 
     * @param mixed $value
     *
     * @return int
     * 
     */
    private function getPrecision($value)
    {
        return strlen(substr(strrchr($value, "."), 1));
    }

    /**
     * Format value removing dots, comma and multiplying for 100.
     *
     * since 2.6.1
     *
     * @param mixed $value
     *
     * @return int
     */
    private function formatValueToProcess($value)
    {
        return (int) preg_replace('/[^0-9]/', '', $value * 100);
    }

    /**
     * Add flash message on admin plugin page
     *
     * @since 2.6.2
     *
     * @param string $message
     * @param string $type notice-error | notice-info | notice-warning | notice-success
     *
     * @return void
     */
    public function notice($message, $type = 'notice-error')
    {
        if ($this->on_settings) {
            echo "<div class='notice is-dismissible $type'><p>$message</p></div>";
        }
    }

    private function retry()
    {
        $domain = $_SERVER['SERVER_NAME'];
        $ssl_check = @fsockopen('ssl://'.$domain, 443, $errno, $errstr, 30);
        $enabled = (bool) $ssl_check;
        if ($ssl_check) fclose($ssl_check);
        if (!$enabled) {
            $this->custom_logs(__METHOD__.' '.__LINE__.' Site Domain: '.$domain.' Port: '.
                $_SERVER['SERVER_PORT'].' HTTP Referer: '.wp_get_raw_referer());
        }
        return $enabled;
    }

    /**
     * Get order string by the order id
     *
     * @since 2.6.8
     *
     * @param string $order_id
     *
     * @return String
     */
    private function dg_get_order_string_by_id($order_id)
    {
        return 'wc_order_' . crc32(get_bloginfo('name')) . '_' . $order_id;
    }

    /**
     * Get order object by the order number
     *
     * @since 2.6.8
     *
     * @param string $order_number
     *
     * @return Order
     */
    public function dg_get_order_by_order_number($order_number)
    {
        $order_number = explode('_', $order_number);
        $order_id     = end($order_number);
        return wc_get_order($order_id);
    }

     /**
     * Fetch and update Zelle Payment Pending orders
     *
     * @since 2.6.8
     *
     * @return void
     */
    public function dg_update_initiated_orders() {
        global $wpdb;
        $order_string = 'wc_order_' . crc32(get_bloginfo('name')) . '_';
        $initiated_orders = $wpdb->prepare(
            "SELECT CONCAT(%s,id) as orders
            FROM $wpdb->posts 
            WHERE post_type = 'shop_order' 
            AND (post_status = 'wc-dg-pending' OR post_status = 'wc-pending')", $order_string
        );

        $initiated_orders = $wpdb->get_col($initiated_orders);

        if (count($initiated_orders)) {
            $ids = [];
            
            $response = wp_remote_get($this->api_url . 'checkouts', $this->generate_header_request(null, $initiated_orders));
            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);

                if (empty($body['errors']) && self::HTTP_STATUS_OK == wp_remote_retrieve_response_code($response)) {
                    foreach($initiated_orders as $order_number) {
                        $order = $this->dg_get_order_by_order_number($order_number);
                        if (!empty($body[$order_number]) && $order) {
                            if ($body[$order_number] == "Pending Settlement") {
                                $order->update_status('processing', 'Payment authorized on ' . DG_CAPITAL_SHORT_NAME);
                                $order->payment_complete();
                            } elseif ($body[$order_number] == "Cancelled") {
                                $order->update_status('cancelled', 'Payment cancelled by ' . DG_CAPITAL_SHORT_NAME);
                            }    
                        }
                    }
                } else {
                    $this->custom_logs('error caught at '. __METHOD__ . ' ' . __LINE__);
                    $this->custom_logs($response); 
                }
            }
        }
    }

    /**
     * Adding debug logs conditionally
     *
     * @since 2.7.8
     *
     * @return void
     */
    private function custom_logs($message)
    {
        if (DG_CAPITAL_ENABLE_LOGS != true) return;
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        $file_path = plugin_dir_path(dirname(__FILE__)).'debug.log';
        if (!file_exists($file_path)) {
            file_put_contents($file_path, '');
        }
        file_put_contents($file_path, "\n" . date('Y-m-d h:i:s') ." :: ". get_option( 'siteurl' ).' ( '.get_option( 'admin_email' ).' ) :: ' . $message, FILE_APPEND | LOCK_EX);
    }
}
