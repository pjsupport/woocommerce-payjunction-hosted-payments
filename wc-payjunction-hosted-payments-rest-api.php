<?php

class PayJunction_REST {
    
    const PJ_LIVE_APP_KEY = '3028e3b4-08f8-4f68-8393-570d382e2a24';
    const PJ_LABS_APP_KEY = 'a8856bf2-e44b-47dc-8577-4ff74dc9e7af';
    
    const PJ_LIVE_DOMAIN = 'https://api.payjunction.com';
    const PJ_LABS_DOMAIN = 'https://api.payjunctionlabs.com';
    
    const PJ_GET = 'GET';
    const PJ_POST = 'POST';
    
    const CAPTURE_POSTURE = 'CAPTURE';
    
    public function __construct( $apilogin, $apipassword, $testmode = false, $debugging = false ) {
        require_once( dirname( __FILE__ ) . '/wc-payjunction-hosted-payments-tools.php' );
        $this->apilogin     = $apilogin;
        $this->apipassword  = $apipassword;
        $this->testmode     = $testmode;
        $this->app_key      = $this->testmode ? self::PJ_LABS_APP_KEY : self::PJ_LIVE_APP_KEY;
        $this->base_url     = $this->testmode ? self::PJ_LABS_DOMAIN : self::PJ_LIVE_DOMAIN;
        $this->attempts     = 0;
        $this->version      = class_exists( 'WC_PayJunction_HP::VERSION' ) ? WC_PayJunction_HP::VERSION : 'unknown';
        $this->debugging    = $debugging;
    }
    
    function get_transaction_details( $transaction_id ) {
        $url            = $this->get_api_url( $transaction_id );
        $args           = $this->get_args_for_api_request();
        $full_response  = wp_safe_remote_get( $url, $args );
        $this->handle_rate_limiting( $full_response, self::PJ_GET, $url, $args );
        self::throw_exception_on_error( $full_response, null );
        return json_decode( wp_remote_retrieve_body( $full_response ) );
    }
    
    function handle_rate_limiting( &$full_response, $method, $url, $args ) {
        
        if ( self::get_http_status( $full_response ) === 429 && $this->attempts < 5 ) {
            
            PayJunction_Tools::log_error( 'Rate limiting detected, attempting to resend request' );
            usleep( 100000 ); // 100 miliseconds
            $this->attempts++;
            
            if ( $method === self::PJ_GET ) {
                
                $full_response = wp_safe_remote_get( $url, $args );
                $this->handle_rate_limiting( $full_response, $method, $url, $args );
                
            } elseif ( $method === self::PJ_POST ) {
                
                $full_response = wp_safe_remote_post( $url, $args );
                $this->handle_rate_limiting( $full_response, $method, $url, $args );
                
            } else {
                throw new ErrorException( "Unimplemented method: $method" );
            }
            
        } elseif ( self::get_http_status( $full_response ) === 429 && $this->attempts >= 5 ) {
            throw new ErrorException( "Could not resolve rate limiting after 5 attempts." . wp_remote_retrieve_body( $full_response ) );
        }
        
        $this->attempts = 0;
        return $full_response;
    }
    
    static function get_http_status( $full_response ) {
        return wp_remote_retrieve_response_code( $full_response );
    }
    
    function refund_transaction( $transaction_id, $amount, $notes ) {
        
        try {
            $url            = $this->get_api_url();
            $args           = $this->get_args_for_api_request();
            $args['body']   = self::get_refund_options( $transaction_id, $amount, $notes );
            $full_response  = wp_safe_remote_post( $url, $args );
            
            $this->handle_rate_limiting( $full_response, self::PJ_POST, $url, $args );
            self::throw_exception_on_error( $full_response );
            
            return self::is_captured_refund( $full_response );
        
        } catch (Exception $ex) {
            
            PayJunction_Tools::log_error( "Refund failed: " . $ex->getMessage() );
            return new WP_Error( 'error', 'Refund failed: ' . $ex->getMessage() );
            
        } 
    }
    
    static function is_captured_refund( $full_response ) {
        
        $body = json_decode( wp_remote_retrieve_body( $full_response ) );
        
        if ( $body->status === self::CAPTURE_POSTURE ) {
            return $body;
        } else {
            return new WP_Error( 'error', "Refund was not captured, posture is " . $body->status . ", message is " . $body->response->message );
        }
        
    }
    
    function get_headers_for_api_request() {
        
        $headers                            = array();
        $headers[ 'X-PJ-Application-Key' ]  = $this->app_key;
        $headers[ 'Authorization' ]         = $this->get_basic_authorization();
        $headers[ 'User-Agent' ]            = 'WC-PJ-HP ' . $this->version;
        
        return $headers;
        
    }
    
    function get_basic_authorization() {
        $encoded = base64_encode( $this->apilogin . ':' . $this->apipassword );
        return 'Basic ' . $encoded;
    }
    
    static function get_refund_options( $transaction_id, $amount, $notes ) {
        
        $amounts_array  = self::get_refund_amounts_array( $amount );
        $refund_options = array( 'action' => 'REFUND', 'transactionId' => $transaction_id );
        
        if ( ! empty($notes) )
            $refund_options['note'] = $notes;
        
        return array_merge($amounts_array, $refund_options);
        
    }
    
    static function get_refund_amounts_array( $amount = '' ) {
        
        $args_body = array();
        
        if ( ! empty( $amount ) ) {
            $args_body['amountBase']        = $amount;
            $args_body['amountTip']         = 0;
            $args_body['amountTax']         = 0;
            $args_body['amountSurcharge']   = 0;
            $args_body['amountShipping']    = 0;
        }
        
        return $args_body;
    }
    
    function get_args_for_api_request() {
        return array( 'headers' => $this->get_headers_for_api_request() );
    }
    
    function get_api_url( $transaction_id = null ) {
        return $this->base_url . '/transactions' . ( empty( $transaction_id ) ? '' : "/$transaction_id/" );
    }
    
    static function throw_exception_on_error( $full_response, $order_id = null ) {
        
        if ( is_wp_error( $full_response ) )
            self::log_wp_error_then_throw_exception( $full_response, $order_id );
        
        if ( self::is_http_error( $full_response ) )
            self::log_http_error_then_throw_exception( $full_response, $order_id );
    }
    
    static function is_http_error( $response ) {
        $status = filter_var( wp_remote_retrieve_response_code( $response ), FILTER_VALIDATE_INT );
        
        return $status >= 400;
    }
    
    static function log_wp_error_then_throw_exception($error, $order_id = null) {
        if ( $order_id )
            PayJunction_Tools::log_error( "Error processing request for order id #$order_id" );
        
        PayJunction_Tools::log_error( $error->get_error_message() );
        throw new ErrorException( "Process halted due to a WordPress error: " . $error->get_error_message() );
    }
    
    static function log_http_error_then_throw_exception( $error, $order_id = null ) {
        if ( $order_id )
            PayJunction_Tools::log_error( "Error processing request for order id #$order_id" );
        
        $body = wp_remote_retrieve_body( $error );
        
        PayJunction_Tools::log_error( $body );
        throw new ErrorException( "Process halted due to a HTTP error:" . $body );
    }
}

?>