<?php
/**
 * Plugin Name:       WC Price Changer
 * Description:       Manage your products prices smartly.
 * Version:           1.2.0
 * Author:            Lotrèk
 * Author URI:        https://lotrek.it/
 * 
 * @packaged          WC_Price_Changer
 */

require plugin_dir_path( __FILE__ ) . 'includes/class-wc-price-changer.php';

function run_wpc() {
    new WC_Price_Changer();
}

run_wpc();
