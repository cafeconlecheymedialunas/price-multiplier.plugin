<?php
/**
* Plugin Name: MG Price Multiplier
* Plugin URI: https://www.your-site.com/
* Description: Un plugin desarrollado desde cero para actualizar los precios en Woocommerce.
* Version: 1.0.0
* Author: Mauro Gaitan Souvaje
* Author URI: https://github.com/cafeconlecheymedialunas
* Domain: mg_price_multiplier
**/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

$all_plugins = apply_filters('active_plugins', get_option('active_plugins'));

if (!stripos(implode($all_plugins), 'woocommerce.php')) {
    exit;
}

// Enqueue Styles
function wpdocs_selectively_enqueue_admin_script( $hook ) {

    wp_register_style( 'custom_wp_admin_css',  plugin_dir_url( __FILE__ ) . '/assets/main.css', false, '1.0.0' );
    wp_enqueue_style( 'custom_wp_admin_css' );
}
add_action( 'admin_enqueue_scripts', 'wpdocs_selectively_enqueue_admin_script' );

//------------------------ Add Custom field to Woocommerce Settings Products tab -------------------------------------------

function mg_price_multiplier_woocommerce_init_action(){

	define('MG_DOMAIN_NAME','mg_price_multiplier');
    global$woocommerce;
    $symbol = get_woocommerce_currency_symbol();
    define('MG_FIELD_LABEL',"Multiplicador ($symbol):");
}

add_action( 'woocommerce_init', 'mg_price_multiplier_woocommerce_init_action' );

function mg_price_multiplier_woocommerce_product_custom_fields()
{
  $args = array(
      'id' => 'mg_price_multiplier_field',
      'label' => MG_FIELD_LABEL,
      
  );
  woocommerce_wp_text_input($args);
}

add_action('woocommerce_product_options_advanced', 'mg_price_multiplier_woocommerce_product_custom_fields');

function mg_price_multiplier_woocommerce_product_custom_fields_save($post_id)
{
    $woocommerce_custom_product_text_field = $_POST['mg_price_multiplier_field'];
    if (!empty($woocommerce_custom_product_text_field))
        update_post_meta($post_id, 'mg_price_multiplier_field', esc_attr($woocommerce_custom_product_text_field));
    
}

add_action('woocommerce_process_product_meta', 'mg_price_multiplier_woocommerce_product_mg_price_multiplier_field_variations_save');

function mg_price_multiplier_add_extra_general_settings( $settings, $current_section ) {
        if( '' == $current_section ) {

            $custom_setting = array(
			    'name' => __( MG_FIELD_LABEL ),
			    'type' => 'text',
			    'id'   => 'general_multiplier' 
			);
            array_push($settings,$custom_setting);
        } 

        return $settings;
}

add_filter( 'woocommerce_get_settings_products' , 'mg_price_multiplier_add_extra_general_settings' , 10, 2 );


// Add Field in variation 
function mg_price_multiplier_field_variation_to_variations( $loop, $variation_data, $variation ) {
    woocommerce_wp_text_input( array(
        'id' => 'mg_price_multiplier_field_variation[' . $loop . ']',
        'class' => 'mg_price_multiplier_field_variation',
        'label' => __( MG_FIELD_LABEL, 'woocommerce' ),
        'value' => get_post_meta( $variation->ID, 'mg_price_multiplier_field_variation', true )
    ) );
 }
  
 add_action( 'woocommerce_variation_options_pricing', 'mg_price_multiplier_field_variation_to_variations', 10, 3 );
 
  
 add_action( 'woocommerce_save_product_variation', 'bbloomer_save_mg_price_multiplier_field_variation_variations', 10, 2 );
  
 function bbloomer_save_mg_price_multiplier_field_variation_variations( $variation_id, $i ) {
    $mg_price_multiplier_field_variation = $_POST['mg_price_multiplier_field_variation'][$i];
    if ( isset( $mg_price_multiplier_field_variation ) ) update_post_meta( $variation_id, 'mg_price_multiplier_field_variation', esc_attr( $mg_price_multiplier_field_variation ) );
 }
  
 add_filter( 'woocommerce_available_variation', 'bbloomer_add_mg_price_multiplier_field_variation_variation_data' );
  
 function bbloomer_add_mg_price_multiplier_field_variation_variation_data( $variations ) {
    $variations['mg_price_multiplier_field_variation'] = '<div class="woocommerce_mg_price_multiplier_field_variation">'.MG_FIELD_LABEL.' <span>' . get_post_meta( $variations[ 'variation_id' ], 'mg_price_multiplier_field_variation', true ) . '</span></div>';
    return $variations;
 }

//-------------------------------------------------   Change Price Flow ------------------------------------------------

function mg_price_multiplier_get_multiplier($product_id){
    $general_multiplier = get_option( 'general_multiplier', true );

    $product = wc_get_product( $product_id );

    $type = $product->get_type();

    
    $single_multiplier = get_post_meta( $product_id, 'mg_price_multiplier_field', true );
    if(  $type === "variation"  ){
        $single_multiplier_variation = get_post_meta( $product_id, 'mg_price_multiplier_field_variation', true );
        $single_multiplier = ($single_multiplier_variation)? $single_multiplier_variation: $single_multiplier;
    }
    

    $multiplier = ($single_multiplier)?$single_multiplier:$general_multiplier;

    $multiplier = ($multiplier)?$multiplier:1;

    return (int) $multiplier;
}

function calculate_price($price,$multiplier){

    $price = (int) $price;
    
    $multiplier = (int) $multiplier;

    if(!is_numeric($price) || $price == 1 || !is_numeric($multiplier) ||  $multiplier < 1) return $price;

    $price = ( $price * $multiplier);
	
    return  $price;
}

function mg_price_multiplier_change_price_single_product($price, $product) {
	
    if((int)$price < 1 || $price == "" ) return $price;
    $multiplier = (int) mg_price_multiplier_get_multiplier($product->get_ID());
    return  calculate_price($price,$multiplier);
}

add_filter( 'woocommerce_product_get_price', 'mg_price_multiplier_change_price_single_product', 10, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'mg_price_multiplier_change_price_single_product', 10, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'mg_price_multiplier_change_price_single_product', 10, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'mg_price_multiplier_change_price_single_product' , 99, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'mg_price_multiplier_change_price_single_product' , 99, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'mg_price_multiplier_change_price_single_product', 99, 2 );
add_filter( 'woocommerce_variation_prices_price', 'mg_price_multiplier_change_price_single_product', 99, 2 );



 






