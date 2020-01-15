<?php
/**
 * Plugin Name:       WC Price Changer
 * Description:       Manage your products prices smartly.
 * Version:           0.0.1
 * Author:            Lotrèk
 * Author URI:        https://lotrek.it/
 */
add_action('admin_menu', 'setup_menu');
 
function setup_menu(){
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        add_submenu_page(
            'woocommerce',
            'Price Changer',
            'Price Changer',
            'manage_options',
            'price-changer',
            'setup_page'
        );
    }
}
 
function setup_page(){
    echo "<h1>WC Price Changer</h1>";
}
?>