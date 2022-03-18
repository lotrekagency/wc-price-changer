<?php
 /**
 * Define the plugin core class.
 *
 * @package    WC_Price_Changer
 * @subpackage WC_Price_Changer/includes
 * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
 */

    if ( !class_exists( 'WC_Price_Changer' ) ) {
        class WC_Price_Changer {

            var $list_table;
            var $action_interface;

            public function __construct() {
                $this->load_dependencies();
                $this->load_hooks();
            }

            public function load_dependencies() {
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-product-list.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-action-interface.php';
            }

            public function load_hooks() {
                add_action( 'admin_menu', array( $this, 'define_menu' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );
            }

            public function add_scripts() {
                wp_enqueue_style( 'wpc-interface-style', plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/style.css' );
            }

            public function define_menu() {
                add_submenu_page(
                    'woocommerce',
                    'Price Changer',
                    'WC Price Changer',
                    'manage_options',
                    'price-changer',
                    array( $this, 'setup_admin_page' )
                  );
            }

            public function setup_admin_page() {
                echo '<div class="wrap">';
                echo '<h1>WC Price Changer</h1>';
                $this->display_action();
                $this->list_table = new WCP_Product_List();
                echo '</div>';
            }

            public function display_action() {
                if ( isset( $_POST['action'] ) && $_POST['action'] != -1 ) {
                    $this->action_interface = new WCP_Action_Interface();
                }
            }
        }
    }
?>