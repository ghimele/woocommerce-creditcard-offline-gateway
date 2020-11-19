<?php
/**
 * Plugin Name: WooCommerce Credit Card Offline Gateway
 * Plugin URI: 
 * Description: This plugin clones the Cheque gateway to create a credit card offline payment method.
 * Author: Michele Vomera
 * Author URI: https://www.panzaepresenza.com/
 * Version: 1.0.0
 * Text Domain: wc-credit-card-offline-gateway
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2020 PanzaePresenza, and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-CreditCard-Offline-Gateway
 * @author    Michele Vomera
 * @category  Admin
 * @copyright Copyright: (c) 2020 PanzaePresenza, and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This plugin clones the Cheque gateway to create a credit card offline payment method.
 */
 
//use the CreditCard validator plugin
use CreditCards\CreditCardTypeConfigList;
use CreditCards\CreditCardTypeConfig;
use CreditCards\CreditCardValidator;

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_ccoffline_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_CC_Offline';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_ccoffline_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_offline_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=offline_gateway' ) . '">' . __( 'Configure', 'wc-gateway-ccoffline' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_offline_gateway_plugin_links' );

/**
 * Offline Payment Gateway
 *
 * Provides the Credit Card Offline Payment Gateway;
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_CC_Offline
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_ccoffline_gateway_init', 11 );

function wc_ccoffline_gateway_init() {

	class WC_Gateway_CC_Offline extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'cc_offline_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = true;
			$this->method_title       = __( 'Credit Card Offline', 'wc-gateway-ccoffline' );
			$this->method_description = __( 'Allows Credit Card offline payments. Orders are marked as "on-hold" when received.', 'wc-gateway-ccoffline' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
		    include_once 'includes/CreditCardValidator/CreditCardTypeConfigList.php';
		    include_once 'includes/CreditCardValidator/CreditCardTypeConfig.php';
		    include_once 'includes/CreditCardValidator/CreditCardValidator.php';
		    
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  			
			// Admin Email 
			add_action( 'woocommerce_email_after_order_table', array( $this, 'email_CreditCardDetails' ), 10, 3 );

			// Save Credit Card details
			add_action( 'woocommerce_checkout_update_order_meta',array($this, 'save_CreditCard' ));
			
			//add_action( 'wp_enqueue_scripts', array($this,'custom_scripts') );
			
			//add_action('woocommerce_api_loaded', 'creditcard_load_api');
			//add_action('wp_footer', array($this,'credit_card_script'));
		}
	
		/*
		 * Initialize Gateway Settings Form Fields
		*/
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_offline_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-ccoffline' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Offline Payment', 'wc-gateway-ccoffline' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-ccoffline' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-ccoffline' ),
					'default'     => __( 'Offline Payment', 'wc-gateway-ccoffline' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-ccoffline' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-ccoffline' ),
					'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'wc-gateway-ccoffline' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-ccoffline' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-ccoffline' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
            // ok, let's display some description before the payment form
            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // I recommend to use inique IDs, because other gateways could already use #cc_number, #cc_expdate, #cc_name
            echo '<p class="form-row form-row-wide validate-required" id="cc_number_field">
					<label for="cc_number_field">Numero Carta <span class="required">*</span></label>
					<span class="woocommerce-input-wrapper" id="validateCard">
						<input type="text" class="input-text" name="cc_number" id="cc_number" placeholder="xxxx xxxx xxxx xxxx" autocomplete="off">
						<i class="icon-ok"></i>
					</span>
				  </p>
				  <p class="form-row form-row-wide validate-required" id="cc_expdate_field">
					<label for="cc_expdate_field">Scadenza <span class="required">*</span></label>
					<span class="woocommerce-input-wrapper">
						<input type="text" class="input-text hasDatepicker" name="cc_expdate" id="cc_expdate" placeholder="MM / YY" value autocomplete="off">
					</span>
				  </p>
				  <p class="form-row form-row-wide validate-required" id="cc_name_field">
					<label for="cc_name_field">Intestatario <span class="required">*</span></label>
					<span class="woocommerce-input-wrapper">
						<input type="text" class="input-text" name="cc_name" id="cc_name" placeholder value autocomplete="off">
					</span>
				  </p>
                ';
		}
        
		public function credit_card_script() {
            echo"<script type='text/javascript'>
                jQuery(document).ready(function($){
                        $('#cc_expdate').datepicker({
                            changeMonth: true,
                            changeYear: true,
                            showButtonPanel: true,
                            dateFormat: 'mm/yy',
                            minDate:'m' // restrict to show month greater than current month
                });
            });
            </script>";
        }
        
        public function credit_card_script2() {
            echo"<script type='text/javascript'>
                jQuery(document).ready(function($){
                        $('#cc_expdate').datepicker({
                            changeMonth: true,
                            changeYear: true,
                            showButtonPanel: true,
                            dateFormat: 'mm/yy',
                            minDate:'m', // restrict to show month greater than current month
            
                    onClose: function(dateText, inst) {
                        // set the date accordingly
                        var month = $('ui-datepicker-div .ui-datepicker-month :selected').val();
                        var year = $('#ui-datepicker-div .ui-datepicker-year :selected').val();
                        $(this).datepicker('setDate', new Date(year, month, 1));
                    },
            
                    beforeShow : function(input, inst) {
                        if ((datestr = $(this).val()).length > 0) {
                            year = datestr.substring(datestr.length-4, datestr.length);
                            month = jQuery.inArray(datestr.substring(0, datestr.length-5), $(this).datepicker('option', 'monthNames'));
                            $(this).datepicker('option', 'defaultDate', new Date(year, month, 1));
                            $(this).datepicker('setDate', new Date(year, month, 1));
                        }
                    }
                });
            });
            </script>";
        }


		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails sent to the administrator
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_CreditCardDetails( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions &&  $sent_to_admin && $this->id === $order->payment_method ) {
			    
			    echo "<div style='margin-bottom: 40px;'>";
			    echo "<h2>Dettagli Carta di Credito</h2>";
			    echo "<table border='1' cellpadding='6' cellspacing='0' width='600' style='color: #636363; border: 1px; solid: #e5e5e5; vertical-align: middle; width: 100%; font-family: 'Helvetica Neue' , Helvetica, Roboto, Arial, sans-serif'>";
                echo "<tr>";
                echo "<th width='40%' style='color: #636363; vertical-align:middle; padding: 12px; text-align: left; solid: #e5e5e5;'><strong>Nome:</strong></th>";
                echo "<td style='color: #636363; vertical-align:middle; padding: 12px; text-align: left; solid: #e5e5e5;'>";
                echo sanitize_text_field( $_POST['cc_name']);
                echo "</td>";
                echo "</tr>";
                
                echo "<tr>";
                echo "<th width='40%' style=' color: #636363; vertical-align:middle; padding: 12px; text-align: left; solid: #e5e5e5;'><strong>Numero Carta di Credito:</strong></th>";
                echo "<td style='color: #636363; vertical-align:middle; padding: 12px; text-align: left; solid: #e5e5e5;'>";
                echo sanitize_text_field( $_POST['cc_number']);
                echo "</td>";
                echo "</tr>";
                
                echo "<tr>";
                echo "<th width='40%' style=' color: #636363; vertical-align:middle; padding: 12px; text-align: left; solid: #e5e5e5;'><strong>Scadenza:</strong></th>";
                echo "<td style='color: #636363; vertical-align:middle; padding: 12px; text-align: left; solid: #e5e5e5;'>";
                echo sanitize_text_field( $_POST['cc_expdate']);
                echo "</td>";
                echo "</tr>";
         
                echo "</table>";
                
                echo"</div>";
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as processing
			$order->update_status( 'wc-processing', __( 'Pagamento con carta di credito', 'wc-gateway-ccoffline' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}

		// save all custom field values
		public function save_CreditCard( $order_id ){ 
			if ( ! empty( $_POST['cc_number'] ) ) {
				update_post_meta( $order_id, 'cc_number', sanitize_text_field( $_POST['cc_number'] ) );
			}
			if ( ! empty( $_POST['cc_expdate'] ) ) {
				update_post_meta( $order_id, 'cc_expdate',sanitize_text_field( $_POST['cc_expdate'] ) );
			}
			if ( ! empty( $_POST['cc_name'] ) ) {
				update_post_meta( $order_id, 'cc_name',sanitize_text_field( $_POST['cc_name'] ) );
			}    
		}

		// Validate fields
		public function validate_fields(){
            $cc_numberValid = false;
            $cc_expdateValid = false;
            $cc_nameValid = false;
            
            $validator = new CreditCardValidator();
            
			if( empty( $_POST[ 'cc_number' ]) ) {
				wc_add_notice(  '<strong>Numero Carta di Credito</strong> è un campo obbligatorio!', 'error' );
			}
			else if(!$validator->isValid($_POST[ 'cc_number' ])){
			    wc_add_notice(  '<strong>Numero Carta di Credito</strong> non è un campo valido!', 'error' );
			}
			else{
			    $cc_numberValid = true;
			}
			
			if( empty( $_POST[ 'cc_expdate' ]) ) {
				wc_add_notice(  '<strong>Scadenza Carta di Credito</strong> è un campo obbligatorio!', 'error' );
			}
			else if(!$validator->isValidDate( $_POST[ 'cc_expdate' ]) ){
			    wc_add_notice(  '<strong>Scadenza Carta di Credito</strong> non è un campo valido!', 'error' );
			}
			else{
			    $cc_expdateValid = true;
			}
			
			if( empty( $_POST[ 'cc_name' ]) ) {
				wc_add_notice(  '<strong>Nome Carta di Credito</strong> è un campo obbligatorio!', 'error' );
			}
			else if(is_numeric($_POST[ 'cc_name' ])){
			    wc_add_notice(  '<strong>Nome Carta di Credito</strong> non è un campo valido!', 'error' );
			}
			else {
			    $cc_nameValid = true;
			}
			
			return ($cc_numberValid && $cc_expdateValid && $cc_nameValid);
		 
		}
		
		
        public function custom_scripts(){
            
            wp_register_style('jquery-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.css');
            wp_enqueue_style('jquery-ui-css');
            
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            //wp_enqueue_script('expirationDatePicker', plugin_dir_url( __FILE__ ) . 'js/expirationDatePicker.js', array('jquery', 'jquery-ui-core','jquery-ui-datepicker' ));
            //wp_enqueue_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array(),true,false );
            //wp_enqueue_script( 'jquery-validation', 'https://ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js', array(),true,false );
            //<script src="http://ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js"></script>
            //<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
            
            //wp_enqueue_script( 'creditcard-jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js', array(  ),true,false );
            
            //wp_enqueue_script( 'creditcard-validator', plugin_dir_url( __FILE__ ) . 'js/jquery.creditCardValidator.js', array(  ),true,false );
            
            //wp_enqueue_script( 'creditcard-js', plugin_dir_url( __FILE__ ) . 'js/creditcard.js', array(  ),true,false );
            
            //wp_register_style( 'creditcard-css', plugin_dir_url( __FILE__ ) . 'css/card.css' ,array(  ) );
            //wp_enqueue_style( 'creditcard-css' );
            
            //wp_register_style( 'jQueryUi-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.css' ,array());

            
            //'jquery'
            //wp_enqueue_script('custom', get_stylesheet_directory_uri().'///scripts/custom.js', 
    //array(), false, true);
        }

		
  } // end \WC_Gateway_CC_Offline class

}