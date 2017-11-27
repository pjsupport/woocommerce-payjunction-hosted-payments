<?php 

class PayJunction_Tools {

    const LOG_ERROR_TYPE = 'error';
    const LOG_DEBUG_TYPE = 'debug';
    const LOG_MAX_BYTES = 1048576; // 1 MB
    
    public static function log_message( $file, $message ) {
        $line_to_write = self::format_message_with_timestamp( $message );
        try {
            if ( ! defined( 'ABSPATH' ) ) throw new ErrorException( 'ABSPATH is not defined, cannot determine log file location! Printing to STDOUT as a backup' );
            $fsize = filesize( $file );
            // truncate the log file to zero bytes if it is already 1 MB or larger
            $log = $fsize < self::LOG_MAX_BYTES ? fopen( $file, 'a' ) : fopen( $file, 'w' );
            fwrite( $log, $line_to_write );
            fclose( $log );
        } catch ( Exception $ex ) {
            // print exception to STDERR and message to STDOUT as a backup
            fwrite( STDERR, $ex->getMessage() );
            fwrite( STDOUT, $line_to_write );
        }
    }
    
    static function format_message_with_timestamp( $message ) {
        return date( DATE_ISO8601 ) . ' :: ' . $message . "\n";
    }
    
    public static function log_debug( $message ) {
        self::log_message( self::get_log_path( self::LOG_DEBUG_TYPE ), $message );
    }
    
    public static function log_error( $message ) {
        self::log_message( self::get_log_path( self::LOG_ERROR_TYPE ), $message );
    }
    
    static function get_log_path( $type ) {
        switch( $type ) {
            case self::LOG_DEBUG_TYPE:
                return ABSPATH . '/payjunctionDebug.log';
            case self::LOG_ERROR_TYPE:
                return ABSPATH . '/payjunctionError.log';
            default:
                throw new ErrorException( "$type is not a valid option" );
        }
    }

}
?>