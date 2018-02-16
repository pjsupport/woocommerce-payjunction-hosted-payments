<?php 

if ( ! defined( 'ABSPATH' )) {
    exit;
}

class PayJunction_Tools {

    const LOG_ERROR_TYPE = 'error';
    const LOG_DEBUG_TYPE = 'debug';
    
    public static function log_message( $level, $message ) {
        $context = array('source' => 'woocommerce-payjunction-hosted-payments');
        $logger = wc_get_logger();
        $logger->log( $level, $message, $context);
    }
    
    static function format_message_with_timestamp( $message ) {
        return date( DATE_ISO8601 ) . ' :: ' . $message . "\n";
    }
    
    public static function log_debug( $message ) {
        self::log_message('debug', $message);
    }
    
    public static function log_error( $message ) {
        self::log_message('error', $message);
    }
}
?>