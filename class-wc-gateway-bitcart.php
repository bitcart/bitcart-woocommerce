<?php

/*
Plugin Name: Bitcart for WooCommerce
Plugin URI:  https://wordpress.org/plugins/bitcart-for-woocommerce
Description: Enable your WooCommerce store to accept cryptocurrencies with Bitcart.
Author:      Bitcart
Text Domain: Bitcart
Author URI:  https://github.com/bitcart

Version:           1.0.6
License:           Copyright 2018-2020 Bitcart, MIT License
License URI:       https://github.com/bitcart/bitcart-woocommerce/blob/master/LICENSE
GitHub Plugin URI: https://github.com/bitcart/bitcart-woocommerce
 */

// Exit if accessed directly
if (false === defined('ABSPATH')) {
    exit();
}

define("BITCART_VERSION", "1.0.6");

// Ensures WooCommerce is loaded before initializing the Bitcart plugin
add_action('plugins_loaded', 'woocommerce_bitcart_init', 0);
register_activation_hook(__FILE__, 'woocommerce_bitcart_activate');

function woocommerce_bitcart_init()
{
    if (true === class_exists('WC_Gateway_Bitcart')) {
        return;
    }

    if (false === class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Bitcart extends WC_Payment_Gateway
    {
        private $is_initialized = false;

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {
            // General
            $this->id = 'bitcart';
            $this->icon = plugin_dir_url(__FILE__) . 'assets/img/icon.png';
            $this->has_fields = false;
            $this->order_button_text = __('Proceed to Bitcart', 'bitcart');
            $this->method_title = 'Bitcart';
            $this->method_description =
                'Bitcart allows you to accept cryptocurrency payments on your WooCommerce store.';

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->debug = 'yes' === $this->get_option('debug', 'no');

            // Define Bitcart settings
            $this->api_url = $this->get_option('api_url');
            $this->store_id = $this->get_option('store_id');
            $this->admin_url = $this->get_option('admin_url');

            // Define debugging & informational settings
            $this->debug_php_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            $this->debug_plugin_version = constant("BITCART_VERSION");

            $this->log(
                'Bitcart Woocommerce payment plugin object constructor called. Plugin is v' .
                $this->debug_plugin_version .
                ' and server is PHP v' .
                $this->debug_php_version
            );
            $this->log('    [Info] $this->api_url        = ' . $this->api_url);
            $this->log('    [Info] $this->store_id        = ' . $this->store_id);
            $this->log('    [Info] $this->admin_url        = ' . $this->admin_url);
            // Actions
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'process_admin_options')
            );

            // Valid for use and IPN Callback
            if (false === $this->is_valid_for_use()) {
                $this->enabled = 'no';
                $this->log('    [Info] The plugin is NOT valid for use!');
            } else {
                $this->enabled = 'yes';
                $this->log('    [Info] The plugin is ok to use.');
                add_action('woocommerce_api_wc_gateway_bitcart', array(
                    $this,
                    'ipn_callback',
                ));
            }

            $this->is_initialized = true;
        }

        public function is_bitcart_payment_method($order)
        {
            $actualMethod = '';
            if (method_exists($order, 'get_payment_method')) {
                $actualMethod = $order->get_payment_method();
            } else {
                $actualMethod = get_post_meta(
                    $order->get_id(),
                    '_payment_method',
                    true
                );
            }
            return $actualMethod === 'bitcart';
        }

        public function __destruct()
        {
        }

        public function is_valid_for_use()
        {
            // Check that API credentials are set
            if (
                true === is_null($this->api_url) ||
                true === is_null($this->store_id) ||
                true === is_null($this->admin_url)
            ) {
                return false;
            }
            $this->log('    [Info] Plugin is valid for use.');

            return true;
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields()
        {
            $this->log('    [Info] Entered init_form_fields()...');
            $log_file =
            'bitcart-' . sanitize_file_name(wp_hash('bitcart')) . '-log';
            $logs_href =
                get_bloginfo('wpurl') .
                '/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' .
                $log_file;

            $this->form_fields = array(
                'title' => array(
                    'title' => __('Title', 'bitcart'),
                    'type' => 'text',
                    'description' => __(
                        'Controls the name of this payment method as displayed to the customer during checkout.',
                        'bitcart'
                    ),
                    'default' => __('Bitcoin', 'bitcart'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Customer Message', 'bitcart'),
                    'type' => 'textarea',
                    'description' => __(
                        'Message to explain how the customer will be paying for the purchase.',
                        'bitcart'
                    ),
                    'default' =>
                    'You will be redirected to Bitcart to complete your purchase.',
                    'desc_tip' => true,
                ),
                'api_url' => array(
                    'title' => __('Bitcart API URL', 'bitcart'),
                    'type' => 'url',
                    'description' => __(
                        'The API URL of your Bitcart instance',
                        'bitcart'
                    ),
                    'desc_tip' => true,
                ),
                'store_id' => array(
                    'title' => __('Bitcart Store ID', 'bitcart'),
                    'type' => 'text',
                    'description' => __(
                        'The ID of the store used for checkout',
                        'bitcart'
                    ),
                    'desc_tip' => true,
                ),
                'admin_url' => array(
                    'title' => __('Bitcart Admin Panel URL', 'bitcart'),
                    'type' => 'url',
                    'description' => __(
                        'The URL of your admin panel, used for checkout',
                        'bitcart'
                    ),
                    'desc_tip' => true,
                ),
                'debug' => array(
                    'title' => __('Debug Log', 'bitcart'),
                    'type' => 'checkbox',
                    'label' => sprintf(
                        __(
                            'Enable logging <a href="%s" class="button">View Logs</a>',
                            'bitcart'
                        ),
                        $logs_href
                    ),
                    'default' => 'no',
                    'description' => sprintf(
                        __(
                            'Log Bitcart events, such as IPN requests, inside <code>%s</code>',
                            'bitcart'
                        ),
                        wc_get_log_file_path('bitcart')
                    ),
                    'desc_tip' => true,
                ),
                'notification_url' => array(
                    'title' => __('Notification URL', 'bitcart'),
                    'type' => 'url',
                    'description' => __(
                        'Bitcart will send IPNs for orders to this URL with the Bitcart invoice data',
                        'bitcart'
                    ),
                    'default' => '',
                    'placeholder' => WC()->api_request_url('WC_Gateway_Bitcart'),
                    'desc_tip' => true,
                ),
                'redirect_url' => array(
                    'title' => __('Redirect URL', 'bitcart'),
                    'type' => 'url',
                    'description' => __(
                        'After paying the Bitcart invoice, users will be redirected back to this URL',
                        'bitcart'
                    ),
                    'default' => '',
                    'placeholder' => $this->get_return_url(),
                    'desc_tip' => true,
                ),
                'support_details' => array(
                    'title' => __('Plugin & Support Information', 'bitcart'),
                    'type' => 'title',
                    'description' => sprintf(
                        __(
                            'This plugin version is %s and your PHP version is %s. If you need assistance, please join our telegram https://t.me/bitcart.  Thank you for using Bitcart!',
                            'bitcart'
                        ),
                        constant("BITCART_VERSION"),
                        PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
                    ),
                ),
            );

            $this->log(
                '    [Info] Initialized form fields: ' .
                var_export($this->form_fields, true)
            );
            $this->log('    [Info] Leaving init_form_fields()...');
        }

        /**
         * Validate Notification URL
         */
        public function validate_notification_url_field()
        {
            $notification_url = $this->get_option('notification_url', '');
            if (isset($_POST['woocommerce_bitcart_notification_url'])) {
                $got_url = esc_url_raw(
                    $_POST['woocommerce_bitcart_notification_url']
                );
                if (filter_var($got_url, FILTER_VALIDATE_URL) !== false) {
                    $notification_url = $got_url;
                } else {
                    $notification_url = '';
                }
            }
            return $notification_url;
        }

        /**
         * Validate Redirect URL
         */
        public function validate_redirect_url_field()
        {
            $redirect_url = $this->get_option('redirect_url', '');
            if (isset($_POST['woocommerce_bitcart_redirect_url'])) {
                $got_url = esc_url_raw($_POST['woocommerce_bitcart_redirect_url']);
                if (filter_var($got_url, FILTER_VALIDATE_URL) !== false) {
                    $redirect_url = $got_url;
                } else {
                    $redirect_url = '';
                }
            }
            return $redirect_url;
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page($order_id)
        {
            $this->log(
                '    [Info] Entered thankyou_page with order_id =  ' . $order_id
            );

            // Remove cart
            WC()->cart->empty_cart();

            // Intentionally blank.

            $this->log(
                '    [Info] Leaving thankyou_page with order_id =  ' . $order_id
            );
        }

        public function get_bitcart_redirect($order_id)
        {
            $redirect = get_post_meta($order_id, 'Bitcart_redirect', true);
            if ($redirect) {
                $invoice_id = get_post_meta($order_id, 'Bitcart_id', true);
                $invoice = json_decode($this->get_invoice($invoice_id));
                if (!property_exists($invoice, 'id')) {
                    return null;
                }
                $status = $invoice->status;
                if ($status === 'invalid' || $status === 'expired') {
                    $redirect = null;
                }
            }
            return $redirect;
        }

        public function send_request($url, $fields = null)
        {
            $options = array(
                'timeout' => 120,
                'redirection' => 10,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            );
            if (!is_null($fields)) {
                $options['method'] = 'POST';
                $options['body'] = json_encode($fields);
            }
            $this->log('    [Info] Sending request to ' . $url);
            $response = wp_remote_request($url, $options);
            $this->log(
                '    [Info] Status code: ' . wp_remote_retrieve_response_code($response)
            );
            return wp_remote_retrieve_body($response);
        }

        public function get_invoice($invoice_id)
        {
            return $this->send_request(
                sprintf('%s/%s', $this->api_url, 'invoices/' . $invoice_id)
            );
        }

        public function get_invoice_url($invoice_id)
        {
            $this->log(
                '    [Info] Invoice url: ' .
                sprintf("%s/%s", $this->admin_url, 'i/' . $invoice_id)
            );
            return sprintf("%s/%s", $this->admin_url, 'i/' . $invoice_id);
        }

        function perform_checks()
        {
            $url = $this->api_url;
            $this->log('    [Info] Set url to ' . sprintf('%s/%s', $url, 'invoices'));

            if (true === empty($this->store_id)) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process a payment but could not set this->store_id. The empty() check failed!'
                );
                throw new \Exception(
                    ' The Bitcart payment plugin was called to process a payment but could not set this->store_id. The empty() check failed!'
                );
            }
            if (true === empty($this->admin_url)) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process a payment but could not set this->admin_url. The empty() check failed!'
                );
                throw new \Exception(
                    ' The Bitcart payment plugin was called to process a payment but could not set this->admin_url. The empty() check failed!'
                );
            }
            return $url;
        }

        /**
         * Process the payment and return the result
         *
         * @param   int     $order_id
         * @return  array
         */
        public function process_payment($order_id)
        {
            $this->log(
                '    [Info] Entered process_payment() with order_id = ' .
                $order_id .
                '...'
            );

            if (true === empty($order_id)) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process a payment but the order_id was missing.'
                );
                throw new \Exception(
                    'The Bitcart payment plugin was called to process a payment but the order_id was missing. Cannot continue!'
                );
            }

            $order = wc_get_order($order_id);

            if (false === $order) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process a payment but could not retrieve the order details for order_id ' .
                    $order_id
                );
                throw new \Exception(
                    'The Bitcart payment plugin was called to process a payment but could not retrieve the order details for order_id ' .
                    $order_id .
                    '. Cannot continue!'
                );
            }

            $notification_url = $this->get_option(
                'notification_url',
                WC()->api_request_url('WC_Gateway_Bitcart')
            );
            $this->log(
                '    [Info] Generating payment form for order ' .
                $order->get_order_number() .
                '. Notify URL: ' .
                $notification_url
            );

            $this->log('    [Info] Changed order status result');
            $thanks_link = $this->get_return_url($order);

            $this->log(
                '    [Info] The variable thanks_link = ' . $thanks_link . '...'
            );

            // Redirect URL & Notification URL
            $redirect_url = $this->get_option('redirect_url', $thanks_link);

            if ($redirect_url !== $thanks_link) {
                $order_received_len = strlen('order-received');
                if (substr($redirect_url, -$order_received_len) === 'order-received') {
                    $this->log(
                        'substr($redirect_url, -$order_received_pos) === order-received'
                    );
                    $redirect_url = $redirect_url . '=' . $order->get_id();
                } else {
                    $redirect_url = add_query_arg(
                        'order-received',
                        $order->get_id(),
                        $redirect_url
                    );
                }
                $redirect_url = add_query_arg(
                    'key',
                    $order->get_order_key(),
                    $redirect_url
                );
            }

            $this->log(
                '    [Info] The variable redirect_url = ' . $redirect_url . '...'
            );

            $this->log(
                '    [Info] Notification URL is now set to: ' .
                $notification_url .
                '...'
            );

            // Setup the currency
            $currency_code = get_woocommerce_currency();

            $this->log(
                '    [Info] The variable currency_code = ' . $currency_code . '...'
            );

            $url = $this->perform_checks();

            $redirect = $this->get_bitcart_redirect($order_id);

            if ($redirect) {
                $this->log(
                    '    [Info] Existing Bitcart invoice has already been created, redirecting to it...'
                );
                $this->log('    [Info] Leaving process_payment()...');
                return array(
                    'result' => 'success',
                    'redirect' => $redirect,
                );
            }

            $this->log(
                '    [Info] Key and token empty checks passed.  Parameters in client set accordingly...'
            );
            $order_total = $order->calculate_totals();
            $order_number = $order->get_order_number();
            $fields = array(
                'price' => $order_total,
                'store_id' => $this->store_id,
                'order_id' => (string) $order_number,
                'buyer_email' => $order->get_billing_email(),
                'notification_url' => $notification_url,
                'redirect_url' => $redirect_url,
            );
            try {
                $this->log(
                    '    [Info] Attempting to generate invoice for ' .
                    $order->get_order_number() .
                    '...'
                );

                $invoice = $this->send_request(
                    sprintf('%s/%s', $url, 'invoices'),
                    $fields
                );

                if (false === isset($invoice) || true === empty($invoice)) {
                    $this->log(
                        '    [Error] The Bitcart payment plugin was called to process a payment but could not instantiate an invoice object.'
                    );
                    throw new \Exception(
                        'The Bitcart payment plugin was called to process a payment but could not instantiate an invoice object. Cannot continue!'
                    );
                } else {
                    $this->log('    [Info] Call to generate invoice was successful.');
                }
            } catch (\Exception $e) {
                $this->log(
                    '    [Error] Error generating invoice for ' .
                    $order->get_order_number() .
                    ', "' .
                    $e->getMessage() .
                    '"'
                );
                error_log($e->getMessage());

                return array(
                    'result' => 'success',
                    'messages' =>
                    'Sorry, but checkout with Bitcart does not appear to be working.',
                );
            }

            $responseData = json_decode($invoice);

            // If another Bitcart invoice was created before, returns the original one
            $redirect = $this->get_bitcart_redirect($order_id);
            if ($redirect) {
                $this->log(
                    '    [Info] Existing Bitcart invoice has already been created, redirecting to it...'
                );
                $this->log('    [Info] Leaving process_payment()...');
                return array(
                    'result' => 'success',
                    'redirect' => $redirect,
                );
            }

            update_post_meta(
                $order_id,
                'Bitcart_redirect',
                $this->get_invoice_url($responseData->id)
            );
            update_post_meta($order_id, 'Bitcart_id', $responseData->id);

            // Reduce stock levels
            if (function_exists('wc_reduce_stock_levels')) {
                wc_reduce_stock_levels($order_id);
            } else {
                $order->reduce_order_stock();
            }

            $this->log('    [Info] Bitcart invoice assigned ' . $responseData->id);
            $this->log('    [Info] Leaving process_payment()...');

            // Redirect the customer to the Bitcart invoice
            return array(
                'result' => 'success',
                'redirect' => $this->get_invoice_url($responseData->id),
            );
        }

        public function ipn_callback()
        {
            $this->log('    [Info] Entered ipn_callback()...');
            // Retrieve the Invoice ID and Network URL from the supposed IPN data
            $post = file_get_contents("php://input");

            if (true === empty($post)) {
                $this->log('    [Error] No post data sent to IPN handler!');
                error_log(
                    '[Error] Bitcart plugin received empty POST data for an IPN message.'
                );

                wp_die('No post data');
            } else {
                $this->log(
                    '    [Info] The post data sent to IPN handler is present...'
                );
            }

            $json = json_decode($post, true);

            if (true === empty($json)) {
                $this->log(
                    '    [Error] Invalid JSON payload sent to IPN handler: ' . $post
                );
                error_log(
                    '[Error] Bitcart plugin received an invalid JSON payload sent to IPN handler: ' .
                    $post
                );

                wp_die('Invalid JSON');
            } else {
                $this->log('    [Info] The post data was decoded into JSON...');
            }

            if (false === array_key_exists('id', $json)) {
                $this->log(
                    '    [Error] No invoice ID present in JSON payload: ' .
                    var_export($json, true)
                );
                error_log(
                    '[Error] Bitcart plugin did not receive an invoice ID present in JSON payload: ' .
                    var_export($json, true)
                );

                wp_die('No Invoice ID');
            } else {
                $this->log('    [Info] Invoice ID present in JSON payload...');
            }

            if (false === array_key_exists('status', $json)) {
                $this->log(
                    '    [Error] No invoice status present in JSON payload: ' .
                    var_export($json, true)
                );
                error_log(
                    '[Error] Bitcart plugin did not receive an invoice status present in JSON payload: ' .
                    var_export($json, true)
                );

                wp_die('No Invoice status');
            } else {
                $this->log('    [Info] Invoice Status present in JSON payload...');
            }

            $url = $this->perform_checks();

            $this->log(
                '    [Info] Key and token empty checks passed.  Parameters in client set accordingly...'
            );

            // Fetch the invoice to update the order
            try {
                $invoice = json_decode($this->get_invoice($json['id']));

                if (true === isset($invoice) && false === empty($invoice)) {
                    $this->log('    [Info] The IPN check appears to be valid.');
                } else {
                    $this->log('    [Error] The IPN check did not pass!');
                    wp_die('Invalid IPN');
                }
            } catch (\Exception $e) {
                $error_string = 'IPN Check: Can\'t find invoice ' . $json['id'];
                $this->log("    [Error] $error_string");
                $this->log("    [Error] " . $e->getMessage());

                wp_die($e->getMessage());
            }

            $order_id = $invoice->order_id;

            if (false === isset($order_id) && true === empty($order_id)) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process an IPN message but could not obtain the order ID from the invoice.'
                );
                throw new \Exception(
                    'The Bitcart payment plugin was called to process an IPN message but could not obtain the order ID from the invoice. Cannot continue!'
                );
            } else {
                $this->log('    [Info] Order ID is: ' . $order_id);
            }

            //this is for the basic and advanced woocommerce order numbering plugins
            //if we need to apply other filters, just add them in place of the this one
            $order_id = apply_filters('woocommerce_order_id_from_number', $order_id);

            $order = wc_get_order($order_id);
            $this->log(
                '$order = ' . $order . 'and order class = ' . get_class($order)
            );

            if (false === $order) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process an IPN message but could not retrieve the order details for order_id: "' .
                    $order_id .
                    '". If you use an alternative order numbering system, please see class-wc-gateway-bitcart.php to apply a search filter.'
                );
                throw new \Exception(
                    'The Bitcart payment plugin was called to process an IPN message but could not retrieve the order details for order_id ' .
                    $order_id .
                    '. Cannot continue!'
                );
            } else {
                $this->log('    [Info] Order details retrieved successfully...');
            }

            if (!$this->is_bitcart_payment_method($order)) {
                $this->log('    [Info] Not using bitcart payment method...');
                $this->log('    [Info] Leaving ipn_callback()...');
                return;
            }

            $expected_invoiceId = get_post_meta(
                $order_id,
                'Bitcart_id',
                true
            );

            if (
                false === isset($expected_invoiceId) ||
                true === empty($expected_invoiceId)
            ) {
                $this->log(
                    '    [Info] Receiving IPN for an order which has no expected invoice ID, ignoring the IPN...'
                );
                return;
            }

            if ($expected_invoiceId !== $json['id']) {
                $this->log(
                    '    [Error] Received IPN for order ' .
                    $order_id .
                    ' with Bitcart invoice id ' .
                    $json['id'] .
                    ' while expected Bitcart invoice is ' .
                    $expected_invoiceId
                );
                throw new \Exception(
                    'Received IPN for order ' .
                    $order_id .
                    ' with Bitcart invoice id ' .
                    $json['id'] .
                    ' while expected Bitcart invoice is ' .
                    $expected_invoiceId
                );
            }

            $current_status = $order->get_status();

            if (false === isset($current_status) || true === empty($current_status)) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process an IPN message but could not obtain the current status from the order.'
                );
                throw new \Exception(
                    'The Bitcart payment plugin was called to process an IPN message but could not obtain the current status from the order. Cannot continue!'
                );
            } else {
                $this->log(
                    '    [Info] The current order status for this order is ' .
                    $current_status
                );
            }

            $checkStatus = $invoice->status;

            if (false === isset($checkStatus) && true === empty($checkStatus)) {
                $this->log(
                    '    [Error] The Bitcart payment plugin was called to process an IPN message but could not obtain the current status from the invoice.'
                );
                throw new \Exception(
                    'The Bitcart payment plugin was called to process an IPN message but could not obtain the current status from the invoice. Cannot continue!'
                );
            } else {
                $this->log(
                    '    [Info] The current status for this invoice is ' . $checkStatus
                );
            }

            switch ($checkStatus) {
                case 'complete':
                    $this->log(
                        '    [Info] This order has not been updated yet so setting complete status...'
                    );

                    $order->payment_complete();
                    $order->update_status('wc-processing');
                    $order->add_order_note(
                        __(
                            'Bitcart invoice payment completed. Payment credited to your merchant account.',
                            'bitcart'
                        )
                    );
                    break;

                // This order is invalid for some reason.
                // Either it's a double spend or some other
                // problem occurred.
                case 'invalid':
                    $this->log(
                        '    [Info] This order has a problem so setting "invalid" status...'
                    );
                    $order->update_status(
                        'wc-failed',
                        __(
                            'Cryptocurrency payment is invalid for this order! The payment was not confirmed by the network within on time. Do not ship the product for this order!',
                            'bitcart'
                        )
                    );
                    break;

                case 'expired':
                    $this->log('    [Info] The invoice is in the "expired" status...');
                    $order->update_status(
                        'wc-cancelled',
                        __(
                            'Cryptocurrency payment has expired for this order! The payment was not broadcasted before its expiration. Do not ship the product for this order!',
                            'bitcart'
                        )
                    );
                    wc_increase_stock_levels($order_id);
                    break;

                // There was an unknown message received.
                default:
                    $this->log(
                        '    [Info] IPN response is an unknown message type. See error message below:'
                    );
                    $error_string = 'Unhandled invoice status: ' . $invoice->getStatus();
                    $this->log("    [Warning] $error_string");
            }
            $this->log('    [Info] Leaving ipn_callback()...');
        }

        public function log($message)
        {
            if (true === isset($this->debug) && 'yes' == $this->debug) {
                if (false === isset($this->logger) || true === empty($this->logger)) {
                    $this->logger = new WC_Logger();
                }

                $this->logger->add('bitcart', $message);
            }
        }
    }
    /**
     * Add Bitcart Payment Gateway to WooCommerce
     **/
    function wc_add_bitcart($methods)
    {
        $methods[] = 'WC_Gateway_Bitcart';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'wc_add_bitcart');

    if (!function_exists('bitcart_log')) {
        function bitcart_log($message)
        {
            $logger = new WC_Logger();
            $logger->add('bitcart', $message);
        }
    }
    /**
     * Add Settings link to the plugin entry in the plugins menu
     **/
    add_filter('plugin_action_links', 'bitcart_plugin_action_links', 10, 2);

    function bitcart_plugin_action_links($links, $file)
    {
        static $this_plugin;

        if (false === isset($this_plugin) || true === empty($this_plugin)) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($file == $this_plugin) {
            $log_file =
            'bitcart-' . sanitize_file_name(wp_hash('bitcart')) . '-log';
            $settings_link =
            '<a href="' .
            get_bloginfo('wpurl') .
                '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_bitcart">Settings</a>';
            $logs_link =
            '<a href="' .
            get_bloginfo('wpurl') .
                '/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' .
                $log_file .
                '">Logs</a>';
            array_unshift($links, $settings_link, $logs_link);
        }

        return $links;
    }

    function action_woocommerce_thankyou_bitcart($order_id)
    {
        $wc_order = wc_get_order($order_id);

        if ($wc_order === false) {
            return;
        }
        $order_data = $wc_order->get_data();
        $status = $order_data['status'];

        $payment_status = file_get_contents(
            plugin_dir_path(__FILE__) . 'templates/paymentStatus.tpl'
        );
        $payment_status = str_replace(
            '{$statusTitle}',
            _x('Payment Status', 'woocommerce_bitcart'),
            $payment_status
        );
        $status_description = _x('Payment processing', 'woocommerce_bitcart');
        echo str_replace('{$paymentStatus}', $status_description, $payment_status);
    }
    add_action(
        "woocommerce_thankyou_bitcart",
        'action_woocommerce_thankyou_bitcart',
        10,
        1
    );
}

function woocommerce_bitcart_failed_requirements()
{
    global $wp_version;
    global $woocommerce;

    $errors = array();
    if (extension_loaded('openssl') === false) {
        $errors[] =
            'The Bitcart payment plugin requires the OpenSSL extension for PHP in order to function. Please contact your web server administrator for assistance.';
    }
    // PHP 5.4+ required
    if (true === version_compare(PHP_VERSION, '5.4.0', '<')) {
        $errors[] =
            'Your PHP version is too old. The Bitcart payment plugin requires PHP 5.4 or higher to function. Please contact your web server administrator for assistance.';
    }

    // Wordpress 3.9+ required
    if (true === version_compare($wp_version, '3.9', '<')) {
        $errors[] =
            'Your WordPress version is too old. The Bitcart payment plugin requires Wordpress 3.9 or higher to function. Please contact your web server administrator for assistance.';
    }

    // WooCommerce required
    if (true === empty($woocommerce)) {
        $errors[] =
            'The WooCommerce plugin for WordPress needs to be installed and activated. Please contact your web server administrator for assistance.';
    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] =
        'Your WooCommerce version is too old. The Bitcart payment plugin requires WooCommerce 2.2 or higher to function. Your version is ' .
        $woocommerce->version .
            '. Please contact your web server administrator for assistance.';
    }

    if (false === empty($errors)) {
        return implode("<br>\n", $errors);
    } else {
        return false;
    }
}

// Activating the plugin
function woocommerce_bitcart_activate()
{
    // Check for Requirements
    $failed = woocommerce_bitcart_failed_requirements();

    $plugins_url = admin_url('plugins.php');

    // Requirements met, activate the plugin
    if ($failed === false) {
        // Deactivate any older versions that might still be present
        $plugins = get_plugins();
        update_option(
            'woocommerce_bitcart_version',
            constant("BITCART_VERSION")
        );
    } else {
        // Requirements not met, return an error message
        wp_die(
            $failed .
            '<br><a href="' .
            $plugins_url .
            '">Return to plugins screen</a>'
        );
    }
}
