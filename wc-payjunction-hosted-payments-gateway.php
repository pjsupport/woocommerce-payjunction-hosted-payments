<?php
/*
Plugin Name: PayJunction Hosted Payments Gateway Module for WooCommerce
Description: Credit Card Processing Module for WooCommerce using the PayJunction Hosted Payments service
Version: 1.0.3
Plugin URI: https://company.payjunction.com/support/WooCommerce
Author: Matthew E. Cooper
Author URI: https://www.payjunction.com
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( dirname( __FILE__ ) . '/wc-payjunction-hosted-payments-tools.php' );
require_once( dirname( __FILE__ ) . '/wc-payjunction-hosted-payments-callback.php' );
require_once( dirname( __FILE__ ) . '/wc-payjunction-hosted-payments-rest-api.php' );

add_action( 'plugins_loaded', 'payjunction_hp_init', 0 );

function payjunction_hp_init() {
    class WC_PayJunction_HP extends WC_Payment_Gateway {
        
        const VERSION = "1.0.3";
        
        const PJ_TESTMODE_META_KEY = '_pj_hp_test_mode';
        const PJ_TESTMODE_TRUE = 'labs';
        const PJ_TESTMODE_FALSE = 'prod';
        
        public function __construct() {
            $this->id = 'payjunction_hp_gateway';
            $this->supports = array( 'refunds' );
            $this->has_fields = false;
            $this->method_title = 'PayJunction Hosted Payments';
            $this->method_description = 'Processes payments via the Hosted Payments service by PayJunction';
            $this->order_button_text = 'Make Payment';
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->shopname             = $this->settings[ 'shopname'       ];
            $this->sb_shopname          = $this->settings[ 'sb_shopname'    ];
            $this->title                = $this->settings[ 'title'          ];
            $this->customerror          = $this->settings[ 'customerror'    ];
            $this->testmode             = $this->settings[ 'testmode'       ] === 'yes' ? true :false;
            $this->description          = $this->settings[ 'description'    ];
            $this->pay_desc             = $this->settings[ 'pay_desc'       ];
            $this->debugging            = $this->settings[ 'debugging'      ] === 'yes' ? true : false;
            $this->apilogin             = $this->settings[ 'apilogin'       ];
            $this->apipassword          = $this->settings[ 'apipassword'    ];
            $this->sb_apilogin          = $this->settings[ 'sb_apilogin'    ];
            $this->sb_apipassword       = $this->settings[ 'sb_apipassword' ];
            
	        $this->view_transaction_url = $this->testmode
                ? "https://d1.www.payjunctionlabs.com/trinity/vt#/transactions/%s/view"
		        : "https://www.payjunction.com/trinity/vt#/transactions/%s/view";
		
            // Instantiate the callback handler. It will hook itself into the WC callback API through 
            // it's constructor
            $this->response_handler     = new WC_Gateway_PayJunction_Response( $this->apilogin, $this->apipassword, $this->customerror,
                $this->testmode, $this->sb_apilogin, $this->sb_apipassword, $this->debugging );
            
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
        
        function init_form_fields() {
            $this->form_fields  = array(
                
                'module_title'      => array(
                    'title'             => 'PayJunction Hosted Payments',
                    'type'              => 'title'),
                'enabled'           => array(
                    'title'             => 'Enable',
                    'type'              => 'checkbox',
                    'label'             => 'Enable the PayJunction Hosted Payments service in Woocommerce',
                    'default'           => 'yes'),
                'testmode'          => array(
                    'title'             => 'Enable Test Mode',
                    'description'       => 'Enable this mode to prevent live processing of credit cards for testing purposes',
                    'type'              => 'checkbox',
                    'default'           => 'no'),
                'debugging'         => array(
                	'title'             => 'Enable Debugging Mode',
                	'description'       => 'Enabling this option causes extra information to be logged in a payjunctionDebug.log file in the root'
                	                    . ' directory of the Wordpress install.',
                	'type'              => 'checkbox',
                	'default'           => 'no'),
                'shopname'          => array(
                    'title'             => 'Production Hosted Payments Shop Name',
                    'description'       => 'Please see our guide <a target="_blank" href="https://support.payjunction.com/hc/en-us/articles/115000089613-How-Do-I-Manage-My-Hosted-Payment-Shops-">here</a>'
                                        . ' for help with obtaining your Shop name or to create a new Shop for Woocommerce',
                    'type'              => 'text',
                    'default'           => ''),
                'sb_shopname'       => array(
                    'title'             => 'SANDBOX Hosted Payments Shop Name',
                    'description'       => 'Sets the shopname to use when Test Mode is enabled.',
                    'type'              => 'text',
                    'default'           => 'pj-qs-01'),
                'apilogin'          => array(
                    'title'             => 'Production API Login',
                    'description'       => 'Please see our guide <a target="_blank" href="https://support.payjunction.com/hc/en-us/articles/213978008-How-to-Create-or-Update-the-API-Login-and-or-Password">here</a>'
                                        . ' for instructions on creating or updating your API credentials',
                    'type'              => 'text'),
                'apipassword'       => array(
                    'title'             => 'Production API Password',
                    'description'       => 'Please see our guide <a target="_blank" href="https://support.payjunction.com/hc/en-us/articles/213978008-How-to-Create-or-Update-the-API-Login-and-or-Password">here</a>'
                                        . ' for instructions on creating or updating your API credentials',
                    'type'              => 'password'),
                'sb_apilogin'       => array(
                    'title'             => 'SANDBOX API Login',
                    'description'       => 'Sets the API login name to use when Test Mode is enabled.',
                    'type'              => 'text',
                    'default'           => 'pj-ql-01'),
                'sb_apipassword'    => array(
                    'title'             => 'SANDBOX API Password',
                    'description'       => 'Sets the API password to use when Test Mode is enabled.',
                    'type'              => 'password',
                    'default'           => 'pj-ql-01p'),
                'pay_desc'          => array(
                    'title'             => 'Payment Description for Hosted Payments',
                    'description'       => 'Sets the description for the payment after the user is redirected to the secure checkout page. Use %oi to insert the WC order id.',
                    'type'              => 'textarea',
                    'default'           => 'Payment on Woocommerce order #%oi'),
                'title'             => array(
                    'title'             => 'Payment Option Title',
                    'description'       => 'Sets the title of the payment option for the customer on the checkout screen',
                    'type'              => 'text',
                    'default'           => 'Credit/Debit - Secure Checkout'),
                'description'       => array(
                    'title'             => 'Payment Option Description',
                    'description'       => 'Description of the payment method for the customer at checkout',
                    'type'              => 'textarea',
                    'default'           => 'Secure hosted checkout page provided by <a target="_blank" href="https://www.payjunction.com">PayJunction</a>' 
                                        . ' Once payment is complete, you will be redirected back to the store.'),
                'customerror'       => array(
                    'title'             => 'Custom Error Message',
                    'description'       => 'Sets a custom error message on unrecoverable errors when trying to redirect to the secure checkout or when validating the payment details with PayJunction.',
                    'type'              => 'textarea',
                    'default'           => 'There was an error with the payment process. Please contact us directly for assistance.'),
                'simpleamounts'     => array(
                	'title'             => 'Simple Amounts',
                	'description'       => 'In the event that a third-party plugin causes issues with setting the correct amount you can enable this option'
                	                    . ' to only fetch the total amount for the order and not attempt to break down the tax and shipping.',
                	'type'              => 'checkbox',
                	'default'           => 'no')
            );
        }
        
        function process_payment( $order_id ) {
            if ( $this->debugging )
                PayJunction_Tools::log_debug( 'Running: process_payment' );
            try {
                
                $order = new WC_Order( $order_id );
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Order #$order->id created" );
                if ( $this->testmode ) 
                    $order->add_order_note("TEST TRANSACTION on PJLABS");
                
                $this->set_transaction_mode_on_order( $order );
                $payment_link = $this->generate_hosted_payment_link( $order );
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Payment link created: $payment_link" );
                
                return array(
                    'result'    => 'success',
                    'redirect'  => $payment_link );
                    
            } catch ( Exception $ex ) {
                PayJunction_Tools::log_error( $ex->getMessage() );
                wc_add_notice( $this->customerror );
                exit;
            }
        }
        
        static function verify_wc_order( $order ) {
            return $order && !empty( $order );
        }
        
        
        function process_refund( $order_id, $amount = null, $reason = '') {
            
            if ( $this->debugging )
                PayJunction_Tools::log_debug( "Attempting to refund: order_id=$order_id, amount=$amount, reason=$reason" );
            
            try {
                
                if ( !$amount || $amount <= 0 )
                    return new WP_Error( 'error', 'You must enter an amount to refund.' );
                
                $order = wc_get_order( $order_id );
                
                if ( ! self::verify_wc_order( $order ) ) {
                    PayJunction_Tools::log_error( "Attempted refund but order does not appear to exist." );
                    return new WP_Error( 'error', "Order not found: #$order_id" );
                }
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Order #$order_id was found" );
                
                $transaction_id = $order->get_transaction_id();
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Transaction ID from order: $transaction_id" );
                
                $order_testmode = self::get_transaction_mode_on_order( $order );
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Order processed through: $order_testmode" );
                
                $conn = $order_testmode === self::PJ_TESTMODE_TRUE 
                    ? new PayJunction_REST( $this->sb_apilogin, $this->sb_apipassword, true, $this->debugging ) 
                    : new PayJunction_REST( $this->apilogin, $this->apipassword, false, $this->debugging );
                
                $notes = "Refund Order #$order_id via Woocommerce:" . "\n$reason";
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Sending to PayJunction_REST for processing" );
                    
                $result = $conn->refund_transaction( $transaction_id, $amount, $notes );
                $has_error = $result instanceof WP_Error;
                
                if ( $this->debugging )
                    PayJunction_Tools::log_debug( "Refund result: " . ( ! $has_error ? "Refunded" : $result->get_error_message() ) );

                return $result;
            
            } catch ( Exception $ex ) {
                PayJunction_Tools::log_error( 'Caught exception: ' . $ex->getMessage() );
                return new WP_Error( 'error', "Exception: " . $ex->getMessage() );
            }
        }
        
        
        function set_transaction_mode_on_order( $order ) {
            $mode = $this->testmode ? self::PJ_TESTMODE_TRUE : self::PJ_TESTMODE_FALSE;
            if ( ! add_post_meta( $order->get_id(), self::PJ_TESTMODE_META_KEY, $mode, true ) ) {
                update_post_meta( $order->get_id(), self::PJ_TESTMODE_META_KEY, $mode );
            }
        }
        
        static function get_transaction_mode_on_order( $order ) {
            return get_post_meta( $order->get_id(), self::PJ_TESTMODE_META_KEY, true );
        }
        
        function generate_hosted_payment_link( $order ) {
            
            $order_id = $order->get_id();
            $base_url = $this->testmode ? 'payjunctionlabs' : 'payjunction';
            
            $query_array_full = array_merge(
                array( 'store' => $this->get_shop_name(), 'invoice' => "woocommerce #$order_id" ),
                $this->get_order_description_query_array( $order_id ),
                $this->get_amounts_query_array( $order ),
                self::get_billing_query_array( $order ),
                self::get_shipping_query_array( $order ),
                self::get_order_id_query_array( $order_id ),
                $this->get_relay_url( $order ) );
            
            $payment_link = 'https://www.' . $base_url . '.com/trinity/quickshop/add_to_cart_snap.action?'. http_build_query( $query_array_full );
            
            return $payment_link;
            
        }
        
        function get_shop_name() {
            return $this->testmode ? $this->sb_shopname : $this->shopname;
        }
        
        function get_relay_url($order) {
            return array( 'relay' => WC()->api_request_url( strtolower( get_class( $this->response_handler ) ) ) );
        }
        
        static function get_billing_query_array( $order ) {
            
            $query_array = array();
            $query_array[ 'billingFirstName'     ] = $order->get_billing_first_name();
            $query_array[ 'billingLastName'      ] = $order->get_billing_last_name();
            $query_array[ 'billingPhone'         ] = $order->get_billing_phone();
            $query_array[ 'billingStreetAddress' ] = $order->get_billing_address_1();
            $query_array[ 'billingCity'          ] = $order->get_billing_city();
            $query_array[ 'billingZip'           ] = $order->get_billing_postcode();
            $query_array[ 'billingCountry'       ] = $order->get_billing_country();
            $query_array[ 'billingEmail'         ] = $order->get_billing_email();
            $query_array[ 'billingCompany'       ] = $order->get_billing_company();
            
            return $query_array;
        }
        
        static function get_shipping_query_array( $order ) {
            
            $query_array = array();
            $query_array[ 'shippingFirstName'        ] = $order->get_shipping_first_name();
            $query_array[ 'shippingLastName'         ] = $order->get_shipping_last_name();
            $query_array[ 'shippingStreetAddress'    ] = $order->get_shipping_address_1();
            
            /* Need to combine the street address if address 2 exists */
            $shippingAddress2 = $order->get_shipping_address_2();
            if ( ! empty( $shippingAddress2 ) ) {
                (string)$query_array[ 'shippingStreetAddress' ] .= ' ' . $shippingAddress2;
            }
            
            $query_array[ 'shippingCity'    ] = $order->get_shipping_city();
            $query_array[ 'shippingZip'     ] = $order->get_shipping_postcode();
            $query_array[ 'shippingCountry' ] = $order->get_shipping_country();
            
            return $query_array;
            
        }
        
        static function get_order_id_query_array( $order_id ) {
            return array( 'wcOrderId' => $order_id );
        }
        
        function get_order_description_query_array( $order_id ) {
            
            $query_array = array();
            $query_array[ 'description' ] = str_replace('%oi', $order_id, $this->pay_desc);
            return $query_array;
            
        }
        
        function get_amounts_query_array( $order ) {
            
            $query_array = array();
            if ( ! $this->simpleamounts ) {
                $query_array[ 'price' ] = number_format( (float)$order->get_subtotal(), 2, '.', '' );
                self::set_amount_shipping( $query_array, $order );
                self::set_amount_tax( $query_array, $order );
            } else {
                $query_array[ 'price' ] = number_format( (float)$order->get_total(), 2, '.', '' );
            }
            
            return $query_array; 
            
        }
        
        static function set_amount_tax( &$query_array, $order ) {
            
            $amount_tax = $order->get_total_tax();
            $amount_shipping_tax = $order->get_shipping_tax();
            
            if ( !empty( $amount_tax ) && (float)$amount_tax > 0 ) {
                
                $query_array[ 'need_to_tax' ] = 'Yes';
                
                if ( !empty($amount_shipping_tax) && (float)$amount_shipping_tax > 0 ) {
                    $query_array[ 'tax_amount' ] = self::get_full_tax_amount_formatted( $amount_tax, $amount_shipping_tax );
                } else {
                    $query_array[ 'tax_amount' ] = number_format( (float)$amount_tax, 2, '.', '' );
                }
                
            }
            
            return $querry_array;
            
        }
        
        static function set_amount_shipping( &$query_array, $order ) {
            
            $amount_shipping = (float)$order->get_total_shipping();
            if ($amount_shipping) {
                $query_array[ 'need_to_ship'    ] = 'Yes';
                $query_array[ 's_h_amount'       ] = number_format($amount_shipping, 2, '.', '');
            }
            return $query_array;
            
        }
        
        static function get_full_tax_amount_formatted( $tax, $shipping_tax ) {
            
            $total_tax = $tax + $shipping_tax;
            return number_format( (float)$total_tax, 2, '.', '' );
            
        }
    }
}


function add_pj_hp_gateway( $methods ) {
    
    $methods[] = 'WC_PayJunction_HP';
    return $methods;
    
}

add_filter( 'woocommerce_payment_gateways', 'add_pj_hp_gateway' );

?>
