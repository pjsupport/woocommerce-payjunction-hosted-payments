<?php

class WC_Gateway_PayJunction_Response {

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
    
    function process_signature_webhook( $data ) {
        // placeholder
        PayJunction_Tools::log_debug($data);
    }
    
    function process_hosted_payment_relay( $data ) {
        try {
            
            if ( self::valid_relay_response( $data ) ) {
                
                $transactionId  = $data[ 'qs_tracking_code' ];
                $order          = wc_get_order($data['wcOrderId']);
                
                if ( $this->verify_amount_via_api( $transactionId, $order->get_total() ) ) {
                    // Lines up, we are good to go
                    update_post_meta( $order->get_id(), '_transaction_id', $transactionId );
                    $order->payment_complete( $transactionId );
                    wp_redirect( WC_Payment_Gateway::get_return_url( $order ) );
                    exit;
                } else {
                    $api_amount = $this->get_transaction_amount_via_api( $transactionId );
                    $order_total = $order->get_total();
                    throw new ErrorException("Amount from WC order ($order_total) does not match total from transaction in PayJunction ($api_amount)");
                }
                
            } else {
                throw new ErrorException("Could not validate POST information.");
            }
            
        } catch (Exception $ex) {
            
            PayJunction_Tools::log_error( $ex->getMessage() );
            wp_die( $this->customerror, 'Error on Checkout', array( 'response' => 500 ) );
            
        }
    }
    
    function process_response() {
        if ($this->debugging) {
            PayJunction_Tools::log_debug("Connection received on callback URL");
        }
        $data = array();
        if (!empty($_POST)) {
            $data = $_POST;
        } else {
            $data = $_GET;
            if ($this->debugging) {
                PayJunction_Tools::log_debug("POST body was empty, trying to process from GET parameters");
            }
        }
            
        if ($this->debugging) {
            PayJunction_Tools::log_debug("Data received:");
            PayJunction_Tools::log_debug("\n" . wc_print_r($data, true));
        }
        
        self::is_signature_webhook($data)
            ? $this->process_signature_webhook($data)
            : $this->process_hosted_payment_relay($data);
        
    }
    
    static function is_signature_webhook( $data ) {
        return ! empty($data['type']) &&
               $data['type'] == 'TRANSACTION_SIGNATURE';
    }
    
    static function valid_relay_response( $response ) {
        // Verify the information we need is in the response
        return  self::has_valid_transaction_id( $response ) &&
                self::has_valid_order_id( $response );
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
    
    function get_transaction_amount_via_api( $transactionId ) {
        $conn = $this->get_api_instance( $this->pjlabs );
        return $conn->get_transaction_details( $transactionId )->amountTotal;
    }
    
    function verify_amount_via_api( $transactionId, $amount ) {
        $api_amount = $this->get_transaction_amount_via_api( $transactionId );
        
        if ($this->debugging) {
            PayJunction_Tools::log_debug("Validating amounts: WC Order: $amount === API: $api_amount?");
        }
        
        return number_format( (float)$amount, 2, '.', '' ) === number_format( (float)$api_amount, 2, '.', '' );
    }
}

?>