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
            var $manager;

            public function __construct() {
                $this->load_dependencies();
                $this->load_hooks();
                $this->manager = WCPC_Manager::get_instance();
                $this->load_session();
            }

            public function load_dependencies() {
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-product-list.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-action-interface.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-notice-interface.php';
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-manager.php';
            }

            public function load_hooks() {
                add_action( 'admin_menu', array( $this, 'define_menu' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );
            }

            public function add_scripts() {
                wp_enqueue_style( 'wcpc-style', plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/style.css' );
                wp_enqueue_script( 'wcpc-script', plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/script.js' );
            }

            public function load_session() {
                session_start();
                
                if ( isset( $_POST['wcpc-viewing'] ) )
                    $_SESSION['wcpc-viewing'] = $_POST['wcpc-viewing'];
                if ( !isset( $_SESSION['wcpc-viewing'] ) )
                    $_SESSION['wcpc-viewing'] = 'products';

                if ( isset( $_POST['wcpc-category'] ) )
                    $_SESSION['wcpc-category'] = $_POST['wcpc-category'];
            }

            private function is_action_selected() {
                return isset( $_POST['action'] ) && $_POST['action'] != -1;
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
                $this->display_notices();
                $this->display_action();
                $this->list_table = new WCPC_Product_List();
                echo '</div>';
            }

            public function display_notices() {
                $this->notice_interface = new WCPC_Notice_Interface();
            }

            public function display_action() {
                if ( $this->is_action_selected() ) {
                    $this->action_interface = new WCPC_Action_Interface();
                }
            }
        }
    }
?>