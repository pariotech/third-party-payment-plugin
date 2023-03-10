<?php

/**
 * Plugin Name: Noob Payment for Woocommerce
 * Plugin URI: https://pay4me.com/
 * Description: An ecommerce plugin to create a extra payment option with pay4me link
 * Version: 1.0
 * Author: OA, TM
 * Author URI: https://pay4me.com/
 * Text Domain: woocommerce plugin
 * Domain Path: /i18n/languages/
 * Requires at least: 5.9
 * Requires PHP: 7.2
 *
 * @package WooCommerce
 */



// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters(
    'active_plugins',
    get_option('active_plugins')
))) return; 

/**
 * Add the Pay4Me Gateway class.
 */

add_action('plugins_loaded', 'init_pay_4_me_class', 11);//added the priority

function init_pay_4_me_class()
{
    if (class_exists ('WC_Payment_Gateway'))
    {
        class WC_Pay_4_Me_Gateway extends WC_Payment_Gateway
        {

            /**
             * Construct the Pay4Me Gateway class.
             */
            public function __construct()
            {
                $this->id = 'pay_4_me';
                $this->icon = apply_filters(
                    'woocommerce_pay4me_icon', plugins_url('/assets/icon.jpg', __FILE__)
                   );
                $this->has_fields = false;
                $this->method_title = __('Pay4Me Gateway', 'woocommerce');
                $this->method_description = __('Add a pay4me gateway that generates a payment link and redirects the third party payer to the payment page.', 'woocommerce');
                // $this->supports = array('products');

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->duration  = $this->get_option('duration');//added
                $this->payment_page_id = $this->create_payment_page();
                $this->payment_page_url = get_permalink($this->payment_page_id);
                $this->init_form_fields();
                $this->init_settings();

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_thank_you_pay_4_me'. $this->id, array($this, 'thank_you_page'));//added
                add_action('wp', array($this, 'check_payment_link_redirect'));
            }

            /**
             * Initialize the Pay4Me Gateway form fields.
             */
            public function init_form_fields()
            {
                $this->form_fields = apply_filters(
                    'woo_pay4me_pay_fields', array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable Pay4Me Gateway', 'woocommerce'),
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('Pay4Me Gateway', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                        'default' => __('Pay via pay4me link.', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                    'link expiry duration' => array(
                        'title' => __('Link expiry duration', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the validity duration for the generated link', 'woocommerce'),
                        'default' => __('10', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                )
            );
            }

            /**
             * Process the payment and redirect to the Pay4Me Gateway thank you page.
             */
            public function process_payment($order_id)
            {
                global $woocommerce;

                $order = new WC_Order($order_id);

                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                foreach ($available_gateways as $key => $gateway) {
                    if ($gateway->id == 'pay4me') {
                        unset($available_gateways[$key]);
                    }
                }

                // Mark as on-hold (we're awaiting the payment).
                $order->update_status('on-hold', __('Awaiting payment via pay4me link.', 'woocommerce'));

                // Generate a pay4me link for the customer to complete payment.
                $custom_payment_link = $this->payment_page_url . '?order_id=' . $order_id .'&'.'payment_method=' . implode(',', array_keys($available_gateways));
                //shorten link url
                $minified_url = $this->shorten_url($custom_payment_link);

                // Store the pay4me link in the order meta.
                update_post_meta($order_id, 'Pay4Me Link', $custom_payment_link);

                // Redirect to the Pay4Me Gateway thank you page.
                return array(
                    'result' => 'success',
                    'redirect' => $this->payment_page_url,
                );
            }

            /**
             * Create the Pay4Me Gateway thank you page.
             */
            private function create_payment_page()
            {
                $payment_page_title = 'Pay4Me Checkout';
                $payment_page_content = '[woocommerce_checkout]';

                // Check if the Pay4Me Gateway thank you page already exists.
                $args = array(
                    'name' => sanitize_title($payment_page_title),
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                );

                $payment_page = get_posts($args);

                // If the Pay4Me Gateway thank you page doesn't exist, create it.
                if (empty($payment_page)) {
                    $payment_page_id = wp_insert_post(
                        array(
                            'post_title' => $payment_page_title,
                            'post_content' => $payment_page_content,
                            'post_status' => 'publish',
                            'post_type' => 'page',
                        )
                    );

                    add_filter('woocommerce_get_checkout_url', array($this, 'custom_checkout_url'), 10, 2);
                } else {
                    $payment_page_id = $payment_page[0]->ID;
                }

                return $payment_page_id;
            }

            /**
             * Custom checkout URL for the Pay4Me Gateway thank you page.
             */
            public function custom_checkout_url($url, $step)
            {
                if ($step === 'order-received') {
                    $order_id = get_query_var('order-received');
                    $order = wc_get_order($order_id);
                    $payment_method = $order->get_payment_method();

                    if ($payment_method === 'pay_4_me') {
                        $custom_payment_link = get_post_meta($order_id, 'Pay4Me Link', true);

                        if (!empty($custom_payment_link)) {
                            $url = $custom_payment_link;
                        }
                    }
                }

                return $url;
            }

            /**
             * Generate shortlink via bitly.
             */
            public function shorten_url($long_url)
            {
                $url = $long_url;
                $login = '';
                $api_key = '';
                $ch = curl_init('http://api.bitly.com/v3/shorten?login='.$login.'&apikey='.$api_key.'&longUrl='.$url);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
                $result = curl_exec($ch);
                $res = json_decode($result,true);
                return $res['link'];

            }

            /**
             * Start countdown...default set to 10 minutes
             */
            public function countdown()
            {
                // Set the target time for the countdown (10 minutes from now)
                $target_time = time() + (10 * 60);

                // Calculate the remaining time in seconds
                $remaining_time = $target_time - time();

                // Calculate the remaining minutes and seconds
                $minutes = floor($remaining_time / 60);
                $seconds = $remaining_time % 60;

                // Display the countdown timer
                $timer = str_pad($minutes, 2, "0", STR_PAD_LEFT) . ":" . str_pad($seconds, 2, "0", STR_PAD_LEFT);
                return $timer;
            }

            /**
             * Check for a payment link redirect.
             */
            public function check_payment_link_redirect()
            {
                if (isset($_GET['order_id'])) {
                    $order_id = intval($_GET['order_id']);
                    $order = wc_get_order($order_id);

                    // Redirect to the thank you page if the order is complete.
                    if ($order->has_status('completed')) {
                        wp_redirect($this->get_return_url($order));
                        exit;
                    }

                    // Redirect to the checkout page if the order is not on-hold.
                    if (!$order->has_status('on-hold')) {
                        wp_redirect(wc_get_checkout_url());
                        exit;
                    }

                    // Redirect to the thank you page if the order is on-hold and the payment method is Pay4Me Gateway.
                    if ($order->get_payment_method() === 'pay_4_me') {
                        wp_redirect($this->payment_page_url);
                        exit;
                    }
                }
            }

            /**
             * Output the Pay4Me Gateway thank you page.
             */
            public function thankyou_page()
            {
                if (isset($_GET['order_id'])) {
                    $order_id = intval($_GET['order_id']);
                    $order = wc_get_order($order_id);

                    if ($order->has_status('on-hold')) {
                        wc_get_template('checkout/thankyou.php', array('order' => $order));
                    } else {
                        wp_redirect($this->get_return_url($order));
                        exit;
                    }
                }
            }
            
        }
        add_filter('woocommerce_payment_gateways', 'add_to_woo_payment_gateway');
        function add_to_woo_payment_gateway($gateways)
        {
            $gateways[] = 'WC_Pay_4_Me_Gateway';
            return $gateways;  
        }

        add_action( 'woocommerce_order_status_changed', 'update_order_payment_method', 10, 3 );
        function update_order_payment_method( $order_id, $old_status, $new_status ) {
            if ( $new_status == 'processing' ) {
                $order = wc_get_order( $order_id );
                $payment_method = $order->get_payment_method();
                if ( $payment_method != 'pay4me' ) {
                    $order->update_meta_data( '_payment_method', 'pay4me' );
                    $order->save();
                }
            }
        }
    }
}
