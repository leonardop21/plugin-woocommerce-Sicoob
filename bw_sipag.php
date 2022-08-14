<?php

/*
 * Projeto: bw-sipag
 * Arquivo: bw_sipag.php
 * ---------------------------------------------------------------------
 * Autor: Leonardo Nascimento
 * ---------------------------------------------------------------------
 * Data da criação: 19/08/2020 5:56:06 pm
 * Last Modified:  22/10/2020 2:09:56 pm
 * Modified By: Leonardo Nascimento / MAC OS
 * ---------------------------------------------------------------------
 * Copyright (c) 2020 Leo
 * HISTORY:
 * Date         By  Comments
 * ----------   --- ---------------------------------------------------------
 */

/*
        ****** ATENÇÃO ******
    Plugin Name: Bewweb Sicoob
    Plugin URI: 
    Description: Pagamento via cartão de crédito através do sipag
    Author: Leonardo Nascimento 
    Author URI: 
    Version: 1.0
*/

if (!defined('ABSPATH')) {
    http_response_code(404);
	exit; // Exit if accessed directly.
}


add_filter( 'woocommerce_payment_gateways', 'bewweb_add_gateway_class' );
function bewweb_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Bewweb_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'bewweb_init_gateway_class');

function bewweb_init_gateway_class() {

	class WC_Bewweb_Gateway extends WC_Payment_Gateway {
        const VERSION = '1.0';
        public function __construct() {
            $this->id = 'bewweb_sipag'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Bewweb Sipag';
            $this->method_description = 'Sipag'; // will be displayed on the options page
            $this->order_button_text  = 'Pagar com Sipag';
            $this->debug  = $this->get_option( 'debug' );

            // Active logs.
            if ( 'yes' === $this->debug ) {
                $this->log = wc_get_logger();
            }

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products',
                'refunds'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // We need custom JavaScript to obtain a token
            // add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Habilitar Bewweb Sipag',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => 'Título',
                    'type'        => 'text',
                    'description' => 'Título exibido durante o checkout',
                    'default'     => 'Sipag - Cartão de crédito',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Descrição',
                    'type'        => 'textarea',
                    'description' => 'Descrição exibido durante o checkout.',
                    'default'     => 'Receba com cartão de crédito através do Sipag direto na sua conta bancária',
                )
        );
    }

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
            	// ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.

                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_credit_card_form_start', $this->id );


            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Número do cartão de crédito <span class="required">*</span></label>
                    <input name="sipag_cc" class="input-text" type="tel" autocomplete="off" required="required">
                </div>
                <div class="form-row form-row-first">
                    <label>Data de expiração <span class="required">*</span></label>
                    <input name="sipag_de" class="input-text" type="tel" autocomplete="off" placeholder="MM / YY" minlength="4" maxlength="4" required="required">
                </div>
                <div class="form-row form-row-last">
                    <label>Código de segurança (CVC) <span class="required">*</span></label>
                    <input name="sipag_cv" class="input-text" type="password" autocomplete="off" placeholder="CVC" minlength="3" maxlength="3" required="required">
                </div>
                <div class="clear"></div>';
            do_action( 'woocommerce_credit_card_form_end', $this->id );
            echo '<div class="clear"></div></fieldset>';

        }

		/*
 		 * Fields validation, more in Step 5
		 */
        public function validate_fields() {
            if(empty($_POST['sipag_cc'])){
                wc_add_notice( 'Insira o número do cartão de crédito', 'error' );
                return false;
            }elseif(empty($_POST['sipag_de'])){ 
                wc_add_notice( 'Insira a data de expiração do cartão de crédito MÊS/ANO ex: 10/25', 'error' );
                return false;
            }elseif(empty($_POST['sipag_de'])) {
                wc_add_notice( 'Insira o código de segurança do cartão de crédito. ', error);
                return false;
            }
            return true;
        }

        /*
            * We're processing the payments here, everything about it is in Step 5
        */
        public function process_payment( $order_id ) {
            global $woocommerce;
            // we need it to get any order detailes
            $order = wc_get_order( $order_id );

            $card = $_POST['sipag_cc'];
            $date_card = str_split($_POST['sipag_de'], 2);
            $cvc_card = $_POST['sipag_cv'];
            $total = WC()->cart->total;

            $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
            $xml .= '<SOAP-ENV:Header />';
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<ipgapi:IPGApiOrderRequest xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1" xmlns:ipgapi="http://ipg-online.com/ipgapi/schemas/ipgapi">';
            $xml .= '<v1:Transaction>';
            $xml .= '<v1:CreditCardTxType>
            <v1:StoreId>2743093525</v1:StoreId>';
            $xml .= '<v1:Type>sale</v1:Type>';
            $xml .= '</v1:CreditCardTxType>';
            $xml.='<v1:CreditCardData>
            <v1:CardNumber>'.$card.'</v1:CardNumber>
            <v1:ExpMonth>'.$date_card[0].'</v1:ExpMonth>
            <v1:ExpYear>'.$date_card[1].'</v1:ExpYear>
            <v1:CardCodeValue>'.$cvc_card.'</v1:CardCodeValue>
            </v1:CreditCardData>';
            $xml .= '<v1:cardFunction>credit</v1:cardFunction>';
            $xml .= '<v1:Payment>';
            $xml .= '<v1:ChargeTotal>'.$total.'</v1:ChargeTotal>';
            $xml .= '<v1:Currency>986</v1:Currency>';
            $xml .= '</v1:Payment>';
            $xml .= '</v1:Transaction>';
            $xml .= '</ipgapi:IPGApiOrderRequest>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://www2.ipg-online.com/ipgapi/services"); //Produção
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, 'login:senha'); //Loja e Senha do usuário fornecido pela sipag/firstdata
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSLCERT, 'caminho chave pem'); //Caminho do certificado assume que está no mesmo diretório
            curl_setopt($ch, CURLOPT_SSLKEY, 'caminho chave key'); //Caminho da chave do certificado assume que está no mesmo diretório
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, 'password'); //Senha do certificado fornecido pela sipag/firstdata
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            //LOG DE CONEXÃO CURL - Cria um arquivo TXT com o log da conexão
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('curl.txt', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            $result = curl_exec($ch);
            curl_close ($ch);
            // echo $result;
            $your_xml_response = $result;
            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:', 'ipgapi:'], '', $your_xml_response);
            $xml = simplexml_load_string($clean_xml);
            // Error
            $statusError = $xml->Body->Fault->detail->IPGApiOrderResponse;
            $errors = explode(':', $statusError->ErrorMessage);

            // Success
            $statusSuccess = $xml->Body->IPGApiOrderResponse;

            if($errors[0] == 'SGS-002303') {
                $errorMsg = 'Cartão de crédito inválido, por favor, verifique e tente novamente.';
            } elseif($errors[0] == 'SGS-005002'){
                $errorMsg = 'Bandeira do cartão de crédito não suportado. Por favor, tente novamente com outro cartão.';
            } elseif($errors[0] == 'SGS-050054') {
             $errorMsg ='Ops, algo está errado no cartão, por favor, verifique os dados digitados e tente novamente';
            }else {
                $errorMsg = "Ocorreu algum problema com a sua compra, tente novamente mais tarde!";
            }

            if(!is_wp_error($response)) {
                if ($statusSuccess->TransactionResult == 'APPROVED' ) {
                    $order->update_meta_data('sipag_code', esc_attr( $statusSuccess->ApprovalCode));
                    $order->update_meta_data('sipag_orderId', esc_attr( $statusSuccess->OrderId));
                    $order->update_meta_data('sipag_transaction_time', esc_attr( $statusSuccess->TransactionTime));

                        // we received the payment
                    $order->payment_complete();
                    $order->reduce_order_stock();

                    $dir = "caminho-para-guardar-o-log" . date('d-m-Y_H:s:i') . "_orderId_" . $order_id . '.txt';
                    $myfile = file_put_contents($dir, $result.PHP_EOL , FILE_APPEND | LOCK_EX);

                    $order->add_order_note(
                        "<strong>*NÃO REMOVER ESTA NOTA*</strong> <br/></br>".
                        'Código de aprovação: ' . $statusSuccess->ApprovalCode . "\n \n" .
                        'Num ref: '. $statusSuccess->ProcessorReferenceNumber . "\n \n" .
                        'Date: '. $statusSuccess->TDateFormatted . "\n \n" .
                        'Terminal ID: '. $statusSuccess->TerminalID . "\n \n" .
                        'Tempo de transação: '. $statusSuccess->TransactionTime . "\n \n" .
                        'Cartão: '. $statusSuccess->Brand . "\n \n" .
                        'País: '. $statusSuccess->Country . "\n \n" .
                        'Método: '. $statusSuccess->PaymentType . "\n \n" .
                        'Ordem Sipag: '. $statusSuccess->OrderId . "\n \n" .
                        'Trans. sipag: '. $statusSuccess->IpgTransactionId . "\n \n"
                        , false );

                    $to = "email@email.com";
                    $subject = 'Retorno de Compra Nº #' . $order->get_id() . ' - SIPAG';
                    $message = 'Dados de retorno do Sipag para a compra #' . $order->get_id() . "\n \n".
                    'Nome do cliente: ' . $_POST['billing_first_name'] . ' ' . $_POST['billing_last_name'] . "\n \n" .
                    'E-mail do cliente: ' . $_POST['billing_email'] . "\n \n" .
                    'Código de aprovação: ' . $statusSuccess->ApprovalCode . "\n \n" .
                    'Num ref: '. $statusSuccess->ProcessorReferenceNumber . "\n \n" .
                    'Date: '. $statusSuccess->TDateFormatted . "\n \n" .
                    'Terminal ID: '. $statusSuccess->TerminalID . "\n \n" .
                    'Tempo de transação: '. $statusSuccess->TransactionTime . "\n \n" .
                    'Cartão: '. $statusSuccess->Brand . "\n \n" .
                    'País: '. $statusSuccess->Country . "\n \n" .
                    'Método: '. $statusSuccess->PaymentType . "\n \n" .
                    'Ordem Sipag: '. $statusSuccess->OrderId . "\n \n" .
                    'Transação. sipag: '. $statusSuccess->IpgTransactionId . "\n \n \n".
                    'IP: ' . $_SERVER['REMOTE_ADDR'] . "\n \n";
                    $headers = '';
                    wp_mail( $to, $subject, $message, $headers);
                        // Empty cart
                    $woocommerce->cart->empty_cart();

                        // Redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }else {
                    wc_add_notice($errorMsg, 'error' );
                    return;
                }
            } else {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order($order_id);

            if($amount < $order->get_total()) {
                $order->add_order_note(
                    "<strong>*O VALOR ESTORNADO É INVÁLIDO*</strong> <br/></br>".
                    'Não é possível estornar um valor menor do que o pago. Para esse processo, utilize o reembolso manual'
                );
                return array(
                    'result' => 'error',
                    'redirect' => get_permalink(get_the_ID())
                );
            }elseif($amount > $order->get_total()) {
                $order->add_order_note(
                    "<strong>*ERRO AO ESTORNAR VALOR*</strong> <br/></br>".
                    'Não é possível estornar um valor maior do que o pago.'
                );
                return array(
                    'result' => 'error',
                    'redirect' => get_permalink(get_the_ID())
                );
            }


            $sipag_transaction_time = get_post_meta($order_id, 'sipag_transaction_time', true );
            $sipag_orderId = get_post_meta($order_id, 'sipag_orderId', true );

            $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
            $xml .= '<SOAP-ENV:Header />';
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<ipgapi:IPGApiOrderRequest xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1" xmlns:ipgapi="http://ipg-online.com/ipgapi/schemas/ipgapi">';
            $xml .= '<v1:Transaction>';
            $xml .= '<v1:CreditCardTxType>
            <v1:StoreId>2743093525</v1:StoreId>
            <v1:Type>void</v1:Type>
            </v1:CreditCardTxType>';
            $xml .= '<v1:Payment>
            <v1:ChargeTotal>'.$amount.'</v1:ChargeTotal>
            <v1:Currency>986</v1:Currency>
            </v1:Payment>';
            $xml.='<v1:TransactionDetails>
            <v1:OrderId>'.$sipag_orderId.'</v1:OrderId>
            <v1:TDate>'.$sipag_transaction_time.'</v1:TDate>
            </v1:TransactionDetails>';
            $xml .= '</v1:Transaction>';
            $xml .= '</ipgapi:IPGApiOrderRequest>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www2.ipg-online.com/ipgapi/services"); //Produção
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, 'login:senha'); //Loja e Senha do usuário fornecido pela sipag/firstdata
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSLCERT, 'caminho-do-cert-pem'); //Caminho do certificado assume que está no mesmo diretório
            curl_setopt($ch, CURLOPT_SSLKEY, 'caminho-key'); //Caminho da chave do certificado assume que está no mesmo diretório
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, 'password'); //Senha do certificado fornecido pela sipag/firstdata
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
                    //LOG DE CONEXÃO CURL - Cria um arquivo TXT com o log da conexão
            curl_setopt($ch, CURLOPT_VERBOSE, true);
                $verbose = fopen('curl.txt', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);

            $result = curl_exec($ch);
            curl_close ($ch);

            $your_xml_response = $result;
            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:', 'ipgapi:'], '', $your_xml_response);
            $xml = simplexml_load_string($clean_xml);
                // Error
            $statusError = $xml->Body->Fault->detail->IPGApiOrderResponse;
            $errors = explode(':', $statusError->ErrorMessage);

            if($errors[0]){
                $order->add_order_note(
                    "<strong>*NÃO REMOVER ESTA NOTA*</strong> <br/></br>".
                    'Ocorreu um problema ao tentar estornar a compra. ' . "\n \n" .
                    'Resposta Sipag: ' . $errors[1]
                );

                $dir = "caminho-log-error" . date('d-m-Y_H:s:i') . "_orderId_" . $order_id . '.txt';
                $myfile = file_put_contents($dir, $result.PHP_EOL , FILE_APPEND | LOCK_EX);

                return array(
                    'result' => 'error',
                    'redirect' => get_permalink(get_the_ID())
                );
            }
                // Success
            $statusSuccess = $xml->Body->IPGApiOrderResponse;

            $statusSuccess = $xml->Body->IPGApiOrderResponse;

            if($statusSuccess->TransactionResult == 'APPROVED' ){
                $order->add_order_note(
                    "<strong>*NÃO REMOVER ESTA NOTA*</strong> <br/></br>".
                    'Compra estornada com sucesso' . "\n \n" .
                    'Resposta Sipag: ' . $statusSuccess->TransactionResult
                );

                $dir = "/caminho-log-success" . date('d-m-Y_H:s:i') . "_orderId_" . $order_id . '.txt';
                $myfile = file_put_contents($dir, $result.PHP_EOL , FILE_APPEND | LOCK_EX);
                $order->update_status( 'refunded', '', true );

                return array(
                'result' => 'success',
                'redirect' => get_permalink(get_the_ID())
                );
            }

            return array(
                'result' => 'success',
                'redirect' => get_permalink(get_the_ID())
            );
        }

    }
}