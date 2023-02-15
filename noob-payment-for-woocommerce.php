<?php
/**
 * Plugin Name: Noob Payment for Woocommerce
 * Plugin URI: https://pay4me.com/
 * Description: An ecommerce plugin to create a extra payment option with pay4me link
 * Version: 1.0
 * Author: OA
 * Author URI: https://pay4me.com/
 * Text Domain: woocommerce plugin
 * Domain Path: /i18n/languages/
 * Requires at least: 5.9
 * Requires PHP: 7.2
 *
 * @package WooCommerce
 */

if (! in_array ('woocommerce/woocommerce.php', apply_filters(
    'active_plugins', get_option('active_plugins')))) return;//check if woocommerce is installed

    add_action('plugins_loaded', 'nob_payment_init', 11);

    function nob_payment_init( )
    {
        if (class_exists ('WC_Payment_Gateway'))
        {
            class WC_Noob_pay_Gateway extends WC_Payment_Gateway
            {
                 public function __construct()
                 {
                    $this->id = 'noob-payment';
                    $this->icon = apply_filters(
                    'woocommerce_noob_icon', plugins_url('/assets/icon.png', __FILE__)
                   );
                    $this->has_fields = false;
                    $this->method_title = __('Noob Payment', 'noob-pay-woo');
                    $this->method_description = __('Noob local content payment systems', 'noob-pay-woo');

                    $this->title = $this->get_option('title');
                    $this->description = $this->get_option('description');
                    $this->duration  = $this->get_option('duration');
                 
                    $this->init_form_fields();
                    $this->init_settings();

                    add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));//save the settings in db
                    add_action('woocommerce_thank_you_'. $this->id, array($this, 'thank_you_page'));
                }
                //Define the content of the form fields
                public function init_form_fields()
                 {
                    $this->form_fields = apply_filters(
                        'woo_noob_pay_fields', array(
                            'enabled'=>array(
                                'title' =>__('Enable/Disable', 'noob-pay-woo' ),
                                    'type'=> 'checkbox',
                                    'label'=>__('Enable or Disable Noob Payment', 'noob-pay-woo' ),
                                    'default'=>'no'
                            ),
                            'title'=>array(
                                'title' =>__('Noobs Payment Gateway Title', 'noob-pay-woo' ),
                                    'type'=> 'text',
                                    'default'=>__('Enter Noobs Payment Gateway Title', 'noob-pay-woo' ),
                                    'desc_tip'=> true,
                                    'description' => __('Add a new payment title that users will see when they are on the checkout page
                                    ', 'noob-pay-woo' ),
                            ),
                            'description'=>array(
                                'title' =>__('Noobs Payment Gateway Description', 'noob-pay-woo' ),
                                    'type'=> 'textarea',
                                    'default'=>__('Enter Noobs Payment Gateway Description', 'noob-pay-woo' ),
                                    'desc_tip'=> true,
                                    'description' => __('Add a new payment description that users will see when they are on the checkout page
                                    ', 'noob-pay-woo' ),
                            ),
                            'duration'=>array(
                                'title' =>__('Add the Pay4me Duration', 'noob-pay-woo' ),
                                    'type'=> 'text',
                                    'default'=>__('Enter the pay4me duration', 'noob-pay-woo' ),
                                    'desc_tip'=> true,
                                    'description' => __('Add a new instruction that will feature on the tankyou page and in emails
                                    ', 'noob-pay-woo' ),
                            ),
                        )
                        );
                 }

                 public function process_payments($order_id)
                 {
                    $order = wc_get_order($order_id);//get the customer order details
                    $order->update_status('pending-payment',__('Awaiting Noob Payment', 'noob-pay-woo'));//update the order status to pending payment while waiting for order processing
                    $this->generate_pay4melink_with_orderid($order_id);//generate  the pay4me link with the order id 
                    $this->initiate_payment_time_duration();
                    $this->clear_payment_with_link_generated();
                    $order->reduce_order_stock();
                    WC()->cart->empty();

                    return array(
                        'result'=>'success',
                        'redirect'=>$this->get_return_url($order)
                    );
                 }

                 public function generate_pay4melink_with_orderid($order_id)
                 {
                    
                 }

                 public function clear_payment_with_link_generated()
                 {
                    
                 }
                public function thank_you_page()
                {
                    if ($this->instructions)
                    {
                        echo wpautop ($this->instructions)
                    }
                }
            }
        }
    }
    add_filter('woocommerce_payment_gateways', 'add_to_woo_payment_gateway');

    function add_to_woo_payment_gateway($gateways)
    {
        $gateways[] = 'WC_Noob_pay_Gateway';
        return $gateways;  
    }
?>