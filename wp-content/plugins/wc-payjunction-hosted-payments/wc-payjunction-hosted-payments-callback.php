<?php

/*require_once(dirname(__FILE__) . './wc-payjunction-hosted-payments-gateway.php');
require_once(dirname(__FILE__) . './wc-payjunction-hosted-payments-tools.php');
require_once(dirname(__FILE__) . './wc-payjunction-hosted-payments-rest-api.php');*/

class WC_Gateway_PayJunction_Response {
    
    const PJ_APP_KEY = '3028e3b4-08f8-4f68-8393-570d382e2a24';
    const PJ_LABS_APP_KEY = 'a8856bf2-e44b-47dc-8577-4ff74dc9e7af';
    
    function __construct($apilogin, $apipassword, $customerror, $pjlabs = false, $sandbox_apilogin = '', $sandbox_apipassword = '', $debugging = false) {
        
        $this->version              = WC_PayJunction_HP::VERSION;
        $this->apilogin             = $apilogin;
        $this->apipassword          = $apipassword;
        $this->sandbox_apilogin     = $sandbox_apilogin;
        $this->sandbox_apipassword  = $sandbox_apipassword;
        $this->pjlabs               = $pjlabs;
        $this->customerror          = $customerror;
        $this->debugging            = $debugging;
        
        // Hook into the WC callback API
        add_action('woocommerce_api_'.strtolower( get_class( $this ) ), array( &$this, 'process_response' ) );
        
    }
    
    function process_response() {
        
        try {
            
            if ( self::valid_relay_response( $_POST ) ) {
                
                $transactionId  = $_POST[ 'qs_tracking_code' ];
                $order          = wc_get_order($_POST['wcOrderId']);
                
                if ( $this->verify_amount_via_api( $transactionId, $order->get_total() ) ) {
                    // Lines up, we are good to go
                    update_post_meta( $order->id, '_transaction_id', $transactionId );
                    $order->payment_complete( $transactionId );
                    wp_redirect( WC_Payment_Gateway::get_return_url( $order ) );
                    exit;
                }
                
            } else {
                throw new ErrorException("Could not validate POST information.");
            }
            
        } catch (Exception $ex) {
            
            PayJunction_Tools::log_error( $ex->getMessage() );
            wp_die( $this->customerror, 'Error on Checkout', array( 'response' => 500 ) );
            
        }
        
    }
    
    static function valid_relay_response( $response ) {
        // Verify the information we need is in the response
        return  self::has_valid_transaction_id( $_POST ) &&
                self::has_valid_order_id( $_POST );
    }
    
    static function has_valid_transaction_id( $post_object ) {
        $transaction_id = filter_var( $post_object[ 'qs_tracking_code' ], FILTER_VALIDATE_INT );
        return !empty($transaction_id);
    }
    
    static function has_valid_order_id( $post_object ) {
        $order_id = filter_var($post_object[ 'wcOrderId' ], FILTER_VALIDATE_INT );
        return ! empty( $order_id );
    }
    
    function get_api_instance( $testmode = false ) {
        if ( $testmode ) {
            return new PayJunction_REST( $this->sandbox_apilogin, $this->sandbox_apipassword, true );
        } else {
            return new PayJunction_REST( $this->apilogin, $this->apipassword, false );
        }
    }
    
    function verify_amount_via_api( $transactionId, $amount ) {
        $conn = $this->get_api_instance( $this->pjlabs );
        $api_amount = $conn->get_transaction_details( $transactionId )->amountTotal;
        return number_format( (float)$amount, 2, '.', '' ) === number_format( (float)$api_amount, 2, '.', '' );
    }
}

?>