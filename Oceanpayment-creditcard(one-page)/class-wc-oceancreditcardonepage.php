<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Oceanpayment CreditCard Payment Gateway
 *
 * Provides a Oceanpayment CreditCard Payment Gateway, mainly for testing purposes.
 *
 * @class 		WC_Gateway_Oceancreditcardonepage
 * @extends		WC_Payment_Gateway
 * @version		1.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Oceanpayment
 */
class WC_Gateway_Oceancreditcardonepage extends WC_Payment_Gateway {

    const SEND			= "[Sent to Oceanpayment]";
    const PUSH			= "[PUSH]";
    const BrowserReturn	= "[Browser Return]";


    protected $_precisionCurrency = array(
        'BIF','BYR','CLP','CVE','DJF','GNF','ISK','JPY','KMF','KRW',
        'PYG','RWF','UGX','UYI','VND','VUV','XAF','XOF','XPF'
    );

    
    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'oceancreditcardonepage';       
        $this->has_fields         = true;
        $this->method_title       = __( 'Oceanpayment CreditCard One-Page', 'woocommerce' );
        $this->method_description = __( '', 'woocommerce' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->instructions = $this->get_option( 'instructions', $this->description );

        // Actions
        add_action( 'woocommerce_api_wc_gateway_oceancreditcardonepage', array( $this, 'check_ipn_response' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'valid-oceancreditcardonepage-standard-itn-request', array( $this, 'successful_request' ) );
        add_action( 'woocommerce_api_return_' . $this->id, array( $this, 'return_payment' ) );
        add_action( 'woocommerce_api_notice_' . $this->id, array( $this, 'notice_payment' ) );
        // Customer Emails
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    function payment_fields() {
        strpos($this->settings['submiturl'],'test') != false ? $submiturl = 'true' : $submiturl = '';
        strpos($this->settings['submiturl'],'test') != false ? $testnote = '<br><span style="color:red">Note: In the test state all transactions are not deducted and cannot be shipped or services provided. The interface needs to be closed in time after the test is completed to avoid consumers from placing orders.</span><br>' : $testnote = '';
        $html_array = array();
       	if(!empty($this->settings['logo'])){
           foreach ($this->settings['logo'] as $key => $value){
               $url = 'images/'.$value.'.png';
               $html_array[] = '<img style="height:40px;float:none;" src="' . WC_HTTPS::force_https_url( plugins_url($url , __FILE__ ) ) . '" />';
           } 
       	} 
       	$html = implode('', $html_array);
        wp_register_script( 'opjquery',  plugins_url('js/opjquery.js', __FILE__ ) , '', '', true );
        wp_enqueue_script('opjquery');
        wp_register_script( 'onepage-carddata', 'https://secure.oceanpayment.com/pages/js/onepage-carddata.js' , '', '', true );
        wp_enqueue_script('onepage-carddata');
        $description = str_replace(array("\r\n","\r","\n"), ' ', $this->description);
        ?>

        <div id='op-payment-icons'>
            <script>
                document.getElementById('op-payment-icons').innerHTML='<?php echo wp_kses_post('<div class="status-box">'.$description.$testnote.'</div>'.$html) ?>';
            </script>
        </div>

        <fieldset>
            <div id="oceanpayment-element" class="op-payment-icons"></div>
            <input name="card_data" id="card_data" value="" type="hidden"/>
            <input name="errorMsg" id="errorMsg" value=""  type="hidden">
            <script>
                jQuery(function() {
                    //如需修改支付语言，可传入语言代码
                    onePageCardData.init("<?php echo esc_html($submiturl);?>","<?php echo esc_url_raw($this->settings['cssurl'])?>","<?php echo esc_html($this->settings['language'])?>","<?php echo esc_html($this->settings['public_key']); ?>","<?php echo esc_html($this->settings['SSL'].sanitize_text_field($_SERVER['HTTP_HOST'])); ?>");

                    $("#op-payment-icons img").css("display", "inline-block");
                });
                var oceanpaymentCallBack = function(data){
                    $("#errorMsg").val(data.errorMsg);
                    $("#card_data").val(data.card_data);
                     
                }                                               
            </script>            
        </fieldset>          
        <?php 

    }

    /**
   * 处理付款
   * @param int $order_id
   * @return array
   */
  public function process_payment($order_id) 
  {
    global $woocommerce;

    if($_POST['card_data'] == ''){
        wc_add_notice("Credit card can't to empty!" , 'error' );
            error_log($_POST['errorMsg']);
        
    }else{

        if($_POST['errorMsg'] != ''){
            wc_add_notice($_POST['errorMsg'] , 'error' );
            error_log($_POST['errorMsg']);
        }else{

            error_log("start_payment\n");
            $order = wc_get_order($order_id);
            error_log($order);  
            //请求网关支付结果
            $result = $this->op_payment($order,sanitize_text_field($_POST['card_data']));
            error_log('this is result:'.$result['body']);
        //    error_log('this is result:'.$result);
            //解析返回结果
            $xml = simplexml_load_string($result['body']);
            $pay_url            = (string)$xml->pay_url;
            $account			= (string)$xml->account;
            $terminal			= (string)$xml->terminal;
            $payment_id 		= (string)$xml->payment_id;
            $order_number		= (string)$xml->order_number;
            $order_currency		= (string)$xml->order_currency;
            $order_amount		= (string)$xml->order_amount;
            $payment_status		= (string)$xml->payment_status;
            $payment_details	= (string)$xml->payment_details;
            $back_signValue 	= (string)$xml->signValue;
            $order_notes		= (string)$xml->order_notes;
            $card_number		= (string)$xml->card_number;
            $payment_authType	= (string)$xml->payment_authType;
            $payment_risk 		= (string)$xml->payment_risk;

            $local_signValue  = hash("sha256",$account.$terminal.$order_number.$order_currency.$order_amount.$order_notes.$card_number.
                    $payment_id.$payment_authType.$payment_status.$payment_details.$payment_risk.$this->settings['securecode']);

            if (strtolower($local_signValue) == strtolower($back_signValue)) {
                //3D直接重定向
                if($pay_url !== ''){
                    return array(
                        'result' => 'success',
                        'redirect' =>  $pay_url
                    );
                }
                
                if($payment_status == 1){ //支付成功

                    $order->update_status("processing");
                    if (!empty( $payment_id ) ) {
                        $order->set_transaction_id( $payment_id );
                    }
                    // 空购物车
                    $woocommerce->cart->empty_cart();
                    error_log("支付成功");
                    // 重定向到“感谢页面”
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                    wc_add_notice( $payment_details, 'success' );
                
                }elseif($payment_status == -1){  //预授权
                    $order->update_status("processing");
                    if (!empty( $payment_id ) ) {
                        $order->set_transaction_id( $payment_id );
                    }
                    // 空购物车
                    $woocommerce->cart->empty_cart();
                    error_log("预授权支付");
                    // 重定向到“感谢页面”
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                    wc_add_notice( $payment_details, 'success' );
                
                }else{   //支付失败
                    if (!empty( $payment_id ) ) {
                        $order->set_transaction_id( $payment_id );
                    }
                    $payment_details  =  (string)$xml->payment_details;
                    $error_message = "Sorry, please check your inputs and try again.<br />";
                    if($payment_details !== false){
                        wc_add_notice($payment_details, 'error' );
                        error_log($payment_details);
                    }else{
                        wc_add_notice($error_message , 'error' );
                        error_log($error_message);
                    }
                    return;
                }
            }
        }
    }
  
  }
    //请求到支付网关
    public function op_payment($order,$card_data)
    {
        $billing = $order->get_data()["billing"]; 
        $shipping = $order->get_data()["shipping"];
        $isMobile = $this->isMobile() ? 'Mobile' : 'PC';
            //Oceanpayment账户
        $account			= $this->settings['account'];
        //账户号下的终端号
        $terminal			= $this->settings['terminal'];
        //securecode 获取本地存储的securecode，不需要用form表单提交
        $secureCode			= $this->settings['securecode'];
        //订单号的交易币种，采用国际标准ISO 4217，请参考附录H.1
        $order_currency		= $order->get_data()["currency"];
        //订单号的交易金额；最大支持小数点后2位数，如：1.00、5.01；如果交易金额为0，不需要发送至钱海支付系统
        $order_amount		= number_format($order->get_total(), 2, '.', '');
        //返回支付信息的网站URL地址；用于浏览器跳转
        $backUrl			= WC()->api_request_url( 'return_' . $this->id );
        $noticeUrl			= WC()->api_request_url( 'notice_' . $this->id );
        //网站订单号
        $order_number		= method_exists($order, 'get_id') ? $order->get_id() : $order->id;

        //消费者的名，如果没有该值可默认传：消费者id或N/A
        $billing_firstName	= empty($billing['first_name']) ? 'N/A' : $this->utf8_substr($billing['first_name'], 0, 64);
        //消费者的姓，如果没有该值可默认传：消费者id或N/A
        $billing_lastName	= empty($billing['last_name']) ? 'N/A' : $this->utf8_substr($billing['last_name'], 0, 64);
        //消费者的邮箱，如果没有该值可默认传：消费者id@域名或简称.com
        $billing_email		= $billing['email'];

        
        /*==================*
        *        参数      *
        *==================*/
        $data = array(
            'account'=>$account,
            'terminal'=>$terminal,
            'signValue'=>hash("sha256",$account.$terminal.$order_number.$order_currency.$order_amount.$billing_firstName.$billing_lastName.$billing_email.$secureCode),
            'backUrl'=>$backUrl,
            'noticeUrl'=>$noticeUrl,
            'methods'=>'Credit Card',
            'card_data'=>$card_data,
            'order_number'=>$order_number,
            'order_currency'=>$order_currency,
            'order_amount'=>$order_amount,
            'order_notes'=>'',
            'billing_firstName'=>$billing_firstName,
            'billing_lastName'=>$billing_lastName,
            'billing_email'=>$billing_email,
            'billing_phone'=>empty($billing['phone']) ? '' : str_replace( array( '(', '-', ' ', ')', '.' ), '', $billing['phone']),
            'billing_country'=>empty($billing['country']) ? '' : $this->utf8_substr($billing['country'], 0, 32),
            'billing_state'=>$this->get_creditcard_state( $billing['country'], $billing['state']),
            'billing_city'=>is_numeric($billing['city']) ? 'NULL' : $billing['city'],
            'billing_address'=>$billing['address_1'].$billing['address_1'],
            'billing_zip'=>$billing['postcode'],
            'billing_ip'=>sanitize_text_field($_SERVER["REMOTE_ADDR"]),
            'ship_firstName'=>empty($shipping['first_name']) ? '' : $this->utf8_substr($billing['first_name'], 0, 64),
            'ship_lastName'=>empty($shipping['last_name']) ? '' : $this->utf8_substr($shipping['last_name'], 0, 64),
            'ship_phone'=>empty($billing['phone']) ? '' : $this->utf8_substr($billing['phone'], 0, 32),
            'ship_country'=>empty($shipping['country']) ? '' : $this->utf8_substr($shipping['country'], 0, 32),
            'ship_state'=>$this->get_creditcard_state( $shipping['country'], $shipping['state']),
            'ship_city'=>is_numeric($shipping['city']) ? 'NULL' : $shipping['city'],
            'ship_addr'=>$shipping['address_1'].$shipping['address_2'],
            'ship_zip'=>$shipping['postcode'],
            'ship_email'=>$billing_email,
            'productSku'=>$this->get_product($order,'sku'),
            'productName'=>$this->get_product($order,'name'),
            'productNum'=>$this->get_product($order,'num'),
            'cart_info'=>'Woocommerce|V1.2.0|'.$isMobile,
            'cart_api'=>'V1.0',
        );


        //提交地址

        $url_pay = $this->settings['submiturl'];
        $result_data = $this->curl_send($url_pay,$data);
        return $result_data;

    }

    function curl_send($url, $data){
        $args = array(
            'body'        => http_build_query($data),
            'timeout'     => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'cookies'     => array(),
        );
        $response = wp_remote_post( $url, $args );
        return $response;
    }

    public function utf8_substr($str,$start=0) {
        if(empty($str)){
            return false;
        }
        if (function_exists('mb_substr')){
            if(func_num_args() >= 3) {
                $end = func_get_arg(2);
                return mb_substr($str,$start,$end,'utf-8');
            }
            else {
                mb_internal_encoding("UTF-8");
                return mb_substr($str,$start);
            }

        }
        else {
            $null = "";
            preg_match_all("/./u", $str, $ar);
            if(func_num_args() >= 3) {
                $end = func_get_arg(2);
                return join($null, array_slice($ar[0],$start,$end));
            }
            else {
                return join($null, array_slice($ar[0],$start));
            }
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Oceanpayment Credit Card Payment', 'woocommerce' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'     => __( 'Credit Card Payment', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'woocommerce' ),
                'type'        => 'textarea',
                'css'         => 'width: 400px;',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
                'default'     => __( '', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'account' => array(
                'title'       => __( 'Account', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Oceanpayment\'s Account.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'terminal' => array(
                'title'       => __( 'Terminal', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Oceanpayment\'s Terminal.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'securecode' => array(
                'title'       => __( 'SecureCode', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Oceanpayment\'s SecureCode.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'public_key' => array(
                'title'       => __( 'Public Key', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Oceanpayment\'s Public_Key.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'submiturl' => array(
                'title'       => __( 'Submiturl', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Note: In the test state all transactions are not deducted and cannot be shipped or services provided. The interface needs to be closed in time after the test is completed to avoid consumers from placing orders.', 'woocommerce' ),
                'desc_tip'    => true,
                'options'     => array(
                    'https://secure.oceanpayment.com/gateway/direct/pay' => __( 'Production', 'woocommerce' ),
                    'https://test-secure.oceanpayment.com/gateway/direct/pay'   => __( 'Sandbox', 'woocommerce' ),
                ),
            ),
            'SSL' => array(
                'title'       => __( 'SSL', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Oceanpayment\'s Submiturl.', 'woocommerce' ),
                'desc_tip'    => true,
                'options'     => array(
                    'https://' => __( 'https', 'woocommerce' ),
                    'http://'   => __( 'http', 'woocommerce' ),
                ),
            ),
            'language' => array(
                'title'       => __( 'Payment Language', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Oceanpayment\'s Submiturl.', 'woocommerce' ),
                'desc_tip'    => true,
                'options'     => array(
                    'en' => __( 'English', 'woocommerce' ),
                    'de' => __( 'German', 'woocommerce' ),
                    'fr' => __( 'French', 'woocommerce' ),
                    'it' => __( 'Italian', 'woocommerce' ),
                    'es' => __( 'Spanish', 'woocommerce' ),
                    'pt' => __( 'Portuguese', 'woocommerce' ),
                    'ru' => __( 'Russian', 'woocommerce' ),
                    'ja' => __( 'Japanese', 'woocommerce' ),
                    'ko' => __( 'Korean', 'woocommerce' ),
                    'ar' => __( 'Arabic', 'woocommerce' ),
                    'tr' => __( 'Turkish', 'woocommerce' ),
                    'zh_CN' => __( 'Simplified Chinese', 'woocommerce' ),
                    'zh_HK' => __( 'Traditional Chinese ', 'woocommerce' ),
                    'nb' => __( 'Norway', 'woocommerce' ),
                    'sv' => __( 'Sweden', 'woocommerce' ),
                    'nl' => __( 'Netherlands', 'woocommerce' ),
                    'da' => __( 'Danish', 'woocommerce' ),
                    'fi' => __( 'Finnish', 'woocommerce' ),
                    'pl' => __( 'Polish', 'woocommerce' ),
                    'ms' => __( 'Malay', 'woocommerce' ),
                    'th' => __( 'Thai', 'woocommerce' ),
                    'fil' => __( 'Filipino', 'woocommerce' ),
                    'id' => __( 'Indonesian', 'woocommerce' ),
                    'vi' => __( 'Vietnamese', 'woocommerce' ),
                ),
            ),
            'cssurl' => array(
                'title'       => __( 'CSS URL', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Online css style file to overwrite the original style of the Oceanpayment payment page to achieve the effect of modification. The cssUrl is empty by default when it is initialized.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'logo' => array(
                'title' => __('Payment Logos', 'woocommerce'),
                'type' => 'multiselect',
                'description' => __( 'Accept Payment Logos.', 'woocommerce' ),
                'class' => 'chosen_select',
                'css' => 'width: 350px;',                
                
                'options' => array(                   
                    'VISA' => 'VISA',
                    'Mastercard' => 'Mastercard',
                    'Maestro'=>'Maestro',
                    'American' => 'American Express',
                    'Electron'=>'Electron',                    
                    'JCB' => 'JCB',
                    'Diners' => 'Diners Club',
                    'Discover' => 'Discover',
                    'UnionPay'=>'UnionPay'
                ),
                'default' => array(
                    'VISA',
                    'Mastercard',
                    'Maestro',
                    'American',
                    'Electron',
                    'JCB',
                    'Diners',
                    'Discover',
                    'UnionPay',
                ),
            ),
            'log' => array(
                'title'       => __( 'Write The Logs', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Whether to write logs', 'woocommerce' ),
                'desc_tip'    => true,
                'options'     => array(
                    'true'    => __( 'True', 'woocommerce' ),
                    'false'   => __( 'False', 'woocommerce' ),
                ),
            ),           
        );
    }

    /**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && 'oceancreditcardonepage' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}


    /**
     * 异步通知
     */
    function notice_payment( $order ) {

        //获取推送输入流XML
        $xml_str = file_get_contents("php://input");

        //判断返回的输入流是否为xml
        if($this->xml_parser($xml_str)){
            $xml = simplexml_load_string($xml_str);

            //把推送参数赋值到$return_info
            $return_info['response_type']		= (string)$xml->response_type;
            $return_info['account']			    = (string)$xml->account;
            $return_info['terminal']			= (string)$xml->terminal;
            $return_info['payment_id']			= (string)$xml->payment_id;
            $return_info['order_number']		= (string)$xml->order_number;
            $return_info['order_currency']		= (string)$xml->order_currency;
            $return_info['order_amount']		= (string)$xml->order_amount;
            $return_info['payment_status']		= (string)$xml->payment_status;
            $return_info['payment_details']	    = (string)$xml->payment_details;
            $return_info['signValue']			= (string)$xml->signValue;
            $return_info['order_notes']		    = (string)$xml->order_notes;
            $return_info['card_number']		    = (string)$xml->card_number;
            $return_info['card_type']			= (string)$xml->card_type;
            $return_info['card_country']		= (string)$xml->card_country;
            $return_info['payment_authType']	= (string)$xml->payment_authType;
            $return_info['payment_risk']		= (string)$xml->payment_risk;
            $return_info['methods']			    = (string)$xml->methods;
            $return_info['payment_country']	    = (string)$xml->payment_country;
            $return_info['payment_solutions']	= (string)$xml->payment_solutions;

            //用于支付结果页面显示响应代码
            $getErrorCode		= explode(':', $return_info['payment_details']);
            $errorCode			= $getErrorCode[0];


            //匹配终端号   判断是否3D交易
            if($return_info['terminal'] == $this->settings['terminal']){
                $secureCode = $this->settings['securecode'];
            }elseif($return_info['terminal'] == $this->settings['secure_terminal']){
                //3D
                $secureCode = $this->settings['secure_securecode'];
            }else{
                $secureCode = '';
            }


            $local_signValue  = hash("sha256",$return_info['account'].$return_info['terminal'].$return_info['order_number'].$return_info['order_currency'].$return_info['order_amount'].$return_info['order_notes'].$return_info['card_number'].$return_info['payment_id'].$return_info['payment_authType'].$return_info['payment_status'].$return_info['payment_details'].$return_info['payment_risk'].$secureCode);


            $order = wc_get_order( $return_info['order_number'] );


            if($this->settings['log'] == 'true'){
                $this->postLog($return_info, self::PUSH);
            }
            strpos($this->settings['submiturl'],'test') != false ? $testorder = 'TEST ORDER - ' : $testorder = '';

            if($return_info['response_type'] == 1){

                //加密校验
                if(strtoupper($local_signValue) == strtoupper($return_info['signValue'])){

                    //支付状态
                    if ($return_info['payment_status'] == 1) {
                        //成功
                        $order->update_status( 'processing', __( $testorder.$return_info['payment_details'], 'oceanpayment-creditcard-One-Page-gateway' ) );
                        wc_reduce_stock_levels( $return_info['order_number'] );
                    } elseif ($return_info['payment_status'] == -1) {
                        //待处理
                        if(empty($this->completed_orders()) || !in_array($return_info['order_number'], $this->completed_orders())){
                            $order->update_status( 'on-hold', __( $testorder.$return_info['payment_details'], 'oceanpayment-creditcard-One-Page-gateway' ) );
                        }
                    } elseif ($return_info['payment_status'] == 0) {
                        //失败
                        //是否点击浏览器后退造成订单号重复 20061
                        if($errorCode == '20061'){
                            
                        }else{
                            if(empty($this->completed_orders()) || !in_array($return_info['order_number'], $this->completed_orders())){
                                $order->update_status( 'failed', __( $testorder.$return_info['payment_details'], 'oceanpayment-creditcard-One-Page-gateway' ) );
                            }
                        }

                    }

                }else{
                    $order->update_status( 'failed', __( $testorder.$return_info['payment_details'], 'oceanpayment-creditcard-One-Page-gateway' ) );
                }


                echo "receive-ok";
            }
        }
        exit;

    }

    /**
     * 浏览器返回
     */
    function return_payment( $order ) {

        
        //返回账户
        $account          = $this->settings['account'];
        //返回终端号
        $terminal         = $this->settings['terminal'];
        //返回Oceanpayment 的支付唯一号
        $payment_id       = sanitize_text_field($_REQUEST['payment_id']);
        //返回网站订单号
        $order_number     = sanitize_text_field($_REQUEST['order_number']);
        //返回交易币种
        $order_currency   = sanitize_text_field($_REQUEST['order_currency']);
        //返回支付金额
        $order_amount     = sanitize_text_field($_REQUEST['order_amount']);
        //返回支付状态
        $payment_status   = sanitize_text_field($_REQUEST['payment_status']);
        //返回支付详情
        $payment_details  = sanitize_text_field($_REQUEST['payment_details']);

        //用于支付结果页面显示响应代码
        $getErrorCode		= explode(':', $payment_details);
        $errorCode			= $getErrorCode[0];

        //返回交易安全签名
        $back_signValue   = sanitize_text_field($_REQUEST['signValue']);
        //返回备注
        $order_notes      = sanitize_text_field($_REQUEST['order_notes']);
        //未通过的风控规则
        $payment_risk     = sanitize_text_field($_REQUEST['payment_risk']);
        //返回支付信用卡卡号
        $card_number      = sanitize_text_field($_REQUEST['card_number']);
        //返回交易类型
        $payment_authType = sanitize_text_field($_REQUEST['payment_authType']);
        //解决方案
        $payment_solutions = sanitize_text_field($_REQUEST['payment_solutions']);

        //匹配终端号   判断是否3D交易
        if($terminal == $this->settings['terminal']){
            $secureCode = $this->settings['securecode'];
        }elseif($terminal == $this->settings['secure_terminal']){
            //3D
            $secureCode = $this->settings['secure_securecode'];
        }else{
            $secureCode = '';
        }

        
        //SHA256加密
        $local_signValue = hash("sha256",$account.$terminal.$order_number.$order_currency.$order_amount.$order_notes.$card_number.
            $payment_id.$payment_authType.$payment_status.$payment_details.$payment_risk.$secureCode);


        $order = wc_get_order( $order_number );


        if($this->settings['log'] === 'true') {
            $this->postLog($_REQUEST, self::BrowserReturn);
		}

        strpos($this->settings['submiturl'],'test') != false ? $testorder = 'TEST ORDER - ' : $testorder = '';
        //加密校验
        if(strtoupper($local_signValue) == strtoupper($back_signValue)){
            
            //支付状态
            if ($payment_status == 1) {
                //成功
                $order->update_status( 'processing', __( $testorder.$payment_details, 'oceanpayment-creditcard-One-Page-gateway' ) );
                WC()->cart->empty_cart();
                $url = $this->get_return_url( $order );
                wc_add_notice( $testorder.$payment_details, 'success' );
            } elseif ($payment_status == -1) {
                //待处理
                if(empty($this->completed_orders()) || !in_array($order_number, $this->completed_orders())){
                    $order->update_status( 'on-hold', __( $testorder.$payment_details, 'oceanpayment-creditcard-One-Page-gateway' ) );
                }                
                $url = $this->get_return_url( $order );
                wc_add_notice( $testorder.$payment_details, 'success' );
            } elseif ($payment_status == 0) {
                //失败

                //是否点击浏览器后退造成订单号重复 20061
                if($errorCode == '20061'){
                    $url = esc_url( wc_get_checkout_url() );
                }else{
                    if(empty($this->completed_orders()) || !in_array($order_number, $this->completed_orders())){
                        $order->update_status( 'failed', __( $testorder.$payment_details, 'oceanpayment-creditcard-One-Page-gateway' ) );
                    }
                    $url = esc_url( wc_get_checkout_url() );
                    wc_add_notice( $testorder.$payment_details, 'error' );
                    wc_add_notice( $payment_solutions, 'error' );
                }

            }

        }else{
            $order->update_status( 'failed', __( $testorder.$payment_details, 'oceanpayment-creditcard-One-Page-gateway' ) );
            $url = esc_url( wc_get_checkout_url() );
            wc_add_notice( $testorder.$payment_details, 'error' );
        }


        //页面跳转
        Header("Location: $url");exit;

    }
    
    /**
     * 获取产品信息
     * @param unknown $order
     * @param unknown $sort
     * @return multitype:NULL
     */
    public function get_product($order,$type){
        $product_array = array();      
        foreach ($order->get_items() as $item_key => $item ){
            $item_data = $item->get_data();
            $product = $item->get_product();
            if($type == 'num'){
                $item_data['quantity'] != '' ? $product_array[] = substr($item_data['quantity'], 0,50) : $product_array[] = 'N/A';
            }elseif($type == 'sku'){
                $product->get_sku() != '' ? $product_array[] = substr($product->get_sku(), 0,500) : $product_array[] = 'N/A';
            }elseif($type == 'name'){
                $product->get_name() != '' ? $product_array[] = substr($product->get_name(), 0,500) : $product_array[] = 'N/A';
            }         
        }
        return implode(';', $product_array);
    }


    /**
     * 获取州/省
     */
    public function get_creditcard_state( $cc, $state ) {
        $iso_cn = ["北京"=>"BJ","天津"=>"TJ","河北"=>"HB","内蒙古"=>"NM","辽宁"=>"LN","黑龙江"=>"HL","上海"=>"SH","浙江"=>"ZJ","安徽"=>"AH","福建"=>"FJ","江西"=>"JX","山东"=>"SD","河南"=>"HA","湖北"=>"HB","湖南"=>"HN","广东"=>"GD","广西"=>"GX","海南"=>"HI","四川"=>"SC","贵州"=>"GZ","云南"=>"YN","西藏"=>"XZ","重庆"=>"CQ","陕西"=>"SN","甘肃"=>"GS","青海"=>"QH","宁夏"=>"NX","新疆"=>"XJ"];
        $states = WC()->countries->get_states( $cc );

        if('CN' === $cc){
            if ( isset( $iso_cn[$states[$state]] ) ) {
                return $iso_cn[$states[$state]];
            }
        }

        return $state;
    }

    /**
     * log
     */
    public function postLog($data, $logType){

        //记录发送到oceanpayment的post log
        $filedate = date('Y-m-d');
        $newfile  = fopen( dirname( __FILE__ )."/oceanpayment_log/" . $filedate . ".log", "a+" );
        $post_log = date('Y-m-d H:i:s').$logType."\r\n";
        foreach ($data as $k=>$v){
            $post_log .= $k . " = " . $v . "\r\n";
        }
        $post_log = $post_log . "*************************************\r\n";
        $post_log = $post_log.file_get_contents( dirname( __FILE__ )."/oceanpayment_log/" . $filedate . ".log");
        $filename = fopen( dirname( __FILE__ )."/oceanpayment_log/" . $filedate . ".log", "r+" );
        fwrite($filename,$post_log);
        fclose($filename);
        fclose($newfile);

    }


    /**
     * 格式化金额
     */
    function formatAmount($order_amount, $order_currency){

        if(in_array($order_currency, $this->_precisionCurrency)){
            $order_amount = round($order_amount, 0);
        }else{
            $order_amount = round($order_amount, 2);
        }

        return $order_amount;

    }

    /**
     * 是否存在相同订单号
     * @return unknown
     */
    function completed_orders(){
    
        global $wpdb;
    
        $query = $wpdb->get_results("
    
            SELECT pm.meta_value AS user_id, pm.post_id AS order_id
            FROM {$wpdb->prefix}postmeta AS pm
            LEFT JOIN {$wpdb->prefix}posts AS p
            ON pm.post_id = p.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed','wc-Processing')
            AND pm.meta_key = '_customer_user'
            ORDER BY pm.meta_value ASC, pm.post_id DESC
            ");
    
        // We format the array by user ID
        foreach($query as $result)
            $results[] = $result->order_id;
    
        return $results;
    }


    /**
     * 检验是否移动端
     */
    function isMobile(){
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])){
            return true;
        }
        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset ($_SERVER['HTTP_VIA'])){
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 判断手机发送的客户端标志
        if (isset ($_SERVER['HTTP_USER_AGENT'])){
            $clientkeywords = array (
                'nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel',
                'lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm',
                'operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile'
            );
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))){
                return true;
            }
        }
        // 判断协议
        if (isset ($_SERVER['HTTP_ACCEPT'])){
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))){
                return true;
            }
        }
        return false;
    }


    /**
     * 钱海支付Html特殊字符转义
     */
    function OceanHtmlSpecialChars($parameter){

        //去除前后空格
        $parameter = trim($parameter);

        //转义"双引号,<小于号,>大于号,'单引号
        $parameter = str_replace(array("<",">","'","\""),array("&lt;","&gt;","&#039;","&quot;"),$parameter);

        return $parameter;

    }


    /**
     *  通过JS跳转出iframe
     */
    public function getJslocationreplace($url)
    {
        return wp_add_inline_script( 'op-iframe','parent.location.replace("'.$url.'")');

    }


    /**
     *  判断是否为xml
     */
    function xml_parser($str){
        $xml_parser = xml_parser_create();
        if(!xml_parse($xml_parser,$str,true)){
            xml_parser_free($xml_parser);
            return false;
        }else {
            return true;
        }
    }

}
