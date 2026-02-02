<?php
class PayPingClassiPress extends APP_Gateway{
    protected $options;
    public function __construct(){
        parent::__construct('PayPing', array(
            'admin' => __('پی‌پینگ', 'PayPing'),
            'dropdown' => __('پی‌پینگ', 'PayPing')
        ));
    }
    public function form(){
        $setting = array(
                array( 'title' => __('پی‌پینگ', 'PayPing'), 'fields' => array( array( 'title' => __('توکن', 'PayPing'), 'name' => 'TokenCode', 'type' => 'text' ) ) ) );
        return $setting;
    }
    
    public function process( $order, $options ){
        $Amount = $order->get_total();
        $Currency = $order->get_currency();
        $OrderID = $order->get_id();
        if( $Currency === 'IRT' ){
            $Amount = $Amount * 1;
        }elseif( $Currency === 'IRR' ){
            $Amount = $Amount / 10;
        }
        $callbackURL = $order->get_return_url();
        $description = $order->get_description();
        
        $CancelUrl = $order->get_cancel_url();
        $IPAdress = $order->get_ip_address();
        $UserID = $order->get_author();
        
        if( !isset( $_POST["refid"] ) ):
            $body = array(
                'payerName'      => '',
                'Amount'         => $Amount,
                'payerIdentity'  => '',
                'returnUrl'      => $callbackURL,
                'Description'    => $description,
                'clientRefId'    => (string) $OrderID,
            );

            $args = array(
                'timeout'      => 45,
                'redirection'  => '5',
                'httpsversion' => '1.0',
                'blocking'     => true,
                'headers'      => array(
                                      'Authorization' => 'Bearer ' . $options['TokenCode'],
                                      'Content-Type'  => 'application/json',
                                      'Accept'        => 'application/json'
                                  ),
                'body'         => json_encode( $body ),
                'cookies'      => array()
            );

            $PayResponse = wp_remote_post( "https://api.payping.ir/v2/pay", $args );
            $ResponseXpId = wp_remote_retrieve_headers( $PayResponse )['x-paypingrequest-id'];
            if( is_wp_error( $PayResponse ) ){
                $Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا ' . $PayResponse->get_error_message() . '<br/> شماره خطای پی‌پینگ: ' . $ResponseXpId;
                return $Message;
            }else{
                $code = wp_remote_retrieve_response_code( $PayResponse );
                if( $code === 200 ){
                    if ( isset( $PayResponse["body"] ) && $PayResponse["body"] != '' ) {
                        $CodePay = wp_remote_retrieve_body( $PayResponse );
                        $CodePay =  json_decode( $CodePay, true );
                        wp_redirect( sprintf( 'https://api.payping.ir/v2/pay/gotoipg/%s', $CodePay["code"] ) );
                        exit;
                    }else{
                        $Message = ' تراکنش ناموفق بود- کد خطا : ' . $ResponseXpId;
                        return $Message;
                    }
                }elseif( $code == 400 ){
                    $Message = wp_remote_retrieve_body( $PayResponse ) . '<br /> کد خطا: ' . $ResponseXpId;
                    return $Message;
                }else{
                    $Message = wp_remote_retrieve_body( $PayResponse ) . '<br /> کد خطا: ' . $ResponseXpId;
                    return $Message;
                }
            }
        
        else:
        
            $data = array( 'refId' => $_POST['refid'], 'amount' => $Amount );
            $args = array(
                'body' => json_encode($data),
                'timeout' => '45',
                'redirection' => '5',
                'httpsversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                'Authorization' => 'Bearer ' . $options['TokenCode'],
                'Content-Type'  => 'application/json',
                'Accept' => 'application/json'
                ),
             'cookies' => array()
            );
            $response = wp_remote_post( 'https://api.payping.ir/v2/pay/verify', $args );
            $XPP_ID = $response["headers"]["x-paypingrequest-id"];
            if( is_wp_error( $response ) ){
                wp_redirect( $CancelUrl );
                return false;
            }else{	
                $code = wp_remote_retrieve_response_code( $response );
                if( $code === 200 ){
                    if( isset( $_POST["refid"] ) and $_POST["refid"] != '' ){
                        $order->complete();
                        return true;
                    }else{
                        wp_redirect( $CancelUrl );
                        return false;
                    }
                }else{
                    wp_redirect( $CancelUrl );
                    return false;
                }
            }
     endif;
    }
}
appthemes_register_gateway('PayPingClassiPress');
