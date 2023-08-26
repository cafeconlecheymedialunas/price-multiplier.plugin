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

add_action( 'woocommerce_init', 'mg_price_multiplier_woocommerce_init_action' );

/**
 * Function for `woocommerce_init` action-hook.
 * 
 * @return void
 */
function mg_price_multiplier_woocommerce_init_action(){

	define('MG_DOMAIN_NAME','mg_price_multiplier');
    global$woocommerce;
    $symbol = get_woocommerce_currency_symbol();
    define('MG_FIELD_LABEL',"Multiplicador ($symbol):");
}



function mg_price_multiplier_woocommerce_product_custom_fields()
{
  $args = array(
      'id' => 'mg_price_multiplier_field',
      'label' => MG_FIELD_LABEL,
      
  );
  woocommerce_wp_text_input($args);
}


add_action('woocommerce_product_options_advanced', 'mg_price_multiplier_woocommerce_product_custom_fields');
add_action('woocommerce_process_product_meta', 'mg_price_multiplier_woocommerce_product_custom_fields_save');

function mg_price_multiplier_woocommerce_product_custom_fields_save($post_id)
{
    // Custom Product Text Field
    $woocommerce_custom_product_text_field = $_POST['mg_price_multiplier_field'];
    if (!empty($woocommerce_custom_product_text_field))
        update_post_meta($post_id, 'mg_price_multiplier_field', esc_attr($woocommerce_custom_product_text_field));
    
}




add_filter( 'woocommerce_get_settings_products' , 'mg_price_multiplier_add_extra_general_settings' , 10, 2 );

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



 
function mg_price_multiplier_get_multiplier($product_id){
    $general_multiplier = get_option( 'general_multiplier', true );
    $single_multiplier = get_post_meta( $product_id, 'mg_price_multiplier_field', true );
    $multiplier = ($single_multiplier)?$single_multiplier:$general_multiplier;
    $multiplier = ($multiplier)?$multiplier:1;
    return (int) $multiplier;
}
 

//add_filter( 'woocommerce_product_get_price', 'mg_price_multiplier_woocommerce_change_price_by_addition', 10, 2 );

//add_filter( 'woocommerce_product_get_regular_price', 'mg_price_multiplier_woocommerce_change_price_by_addition', 10, 2 );
//add_filter( 'woocommerce_product_get_sale_price', 'mg_price_multiplier_woocommerce_change_price_by_addition', 10, 2 );

// Variations (of a variable product)

function mg_price_multiplier_woocommerce_change_price_by_addition($price, $product) {
	
    global $post;

    $post_id = $post->ID;

     if($price < 1) return $price;

     $multiplier = (int) mg_price_multiplier_get_multiplier($post_id);

     if(!is_numeric($multiplier) ||  $multiplier < 1) return $price;

    $product = wc_get_product( $post_id );

    $price = ( $price * $multiplier);
	
    return  $price;
}
add_filter( 'woocommerce_product_variation_get_regular_price', 'mg_price_multiplier_variation_price' , 99, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'mg_price_multiplier_variation_price' , 99, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'mg_price_multiplier_variation_price', 99, 2 );

function mg_price_multiplier_variation_price($price,$variation){
    return (int) $price * 100;
}

add_filter( 'woocommerce_add_cart_item_data', 'mg_price_multiplier_add_cart_item_data', 10, 3 );
 
function mg_price_multiplier_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
     // get product id & price
    $product = wc_get_product( $product_id );
    $price = (int) $product->get_price();
    // extra pack checkbox

    $multiplier = mg_price_multiplier_get_multiplier($product_id); 
    $cart_item_data['new_price'] = $price * $multiplier;
    
    return $cart_item_data;
}

add_action( 'woocommerce_before_calculate_totals', 'mg_price_multiplier_before_calculate_totals', 10, 1 );
 
function mg_price_multiplier_before_calculate_totals( $cart_obj ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
    return;
    }
    // Iterate through each cart item
    
    foreach( $cart_obj->get_cart() as $key=>$value ) {
 
        $multiplier = mg_price_multiplier_get_multiplier($value['data']->get_id()); 
        if(!isset($multiplier) || empty($multiplier)) return;
        $price = $value['data']->get_price( );
        if(!isset($price) || empty($price)) return;
        $new_price = $price *$multiplier;
        if(!isset($new_price) || empty($new_price)) return;
        
        $value['data']->set_price( ( $new_price) );
        
    }
}

add_action( 'admin_head', function () { 
    ?>
    <style type="text/css">
        label[for="general_multiplier"]{
            vertical-align: top;
            text-align: left;
            padding: 20px 10px 20px 0;
            width: 200px;
            line-height: 1.3;
            font-weight: 600;
            font-size:13px;
            color:black;
        }
        input#general_multiplier{
                min-width:100%;
            }
        @media(min-width:768px){
            input#general_multiplier{
                min-width:398px;
                margin-left:9%;
                max-width:714px;
            }
        }

    </style>
    <?php
});