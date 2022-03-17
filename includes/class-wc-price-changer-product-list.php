<?php
    /**
     * Define the product list table.
     *
     * @package    WC_Price_Changer
     * @subpackage WC_Price_Changer/includes
     * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
     */

    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

    class WCP_Product_List extends WP_List_Table {

        var $products = array();
    
        function __construct() {
            parent::__construct( array(
                'singular'  => __( 'product', 'wc-price-changer' ),
                'plural'    => __( 'products', 'wc-price-changer' ),
                'ajax'      => false
            ) );
            $this->init();       
        }

        public function init() {
            $this->get_items();
            $this->prepare_items();
            $this->display();
        }

        public function get_items() {
            $this->products = wc_get_products(array());
        }

        public function prepare_items() {
            $columns = $this->get_columns();
            $this->_column_headers = array( $columns );
            //$this->process_bulk_action();
            $this->items = $this->products;
        }

        public function display() {
            echo '<form method="post">';
            parent::display();
            echo '</form>';
        }

        function column_default( $item, $column_name ) {
            switch( $column_name ) {
                case 'name':
                    return $item->get_name();
                    //return (($_SESSION['viewing'] == 'variations' and !$item->is_type('variation')) ? ('<strong>' . $item->get_name() . '</strong>') : $item->get_name());
                case 'category':
                    return implode( wp_get_post_terms( $item->get_id(), 'product_cat', ['fields' => 'names'] ) );
                case 'price':
                    return $item->get_regular_price();
                case 'sale_price':
                    return $item->get_sale_price() ? $item->get_sale_price() : '-';
                case 'id':
                    return $item->get_id();
                default:
                    return;
            }
        }

        public function get_columns() {
            return array(
                'cb'            => '<input type="checkbox"/>',
                'name'          => __( 'Name', 'wc-price-changer' ),
                'category'      => __( 'Category', 'wc-price-changer' ),
                'price'         => __( 'Price', 'wc-price-changer' ),
                'sale_price'    => __( 'Sale price', 'wc-price-changer' ),
                'id'            => __( 'ID', 'wc-price-changer' ),
            );
        }

        public function get_bulk_actions() {
            return array(
                'price-change-unit'         => __( 'Change prices by unit value', 'wc-price-changer' ),
                'price-change-percentage'   => __( 'Change prices by percentage value', 'wc-price-changer' )
            );
        }

        // public function process_bulk_action() {
        //     $action = $this->current_action();
        //     switch( $action ) {
        //         case 'price-change-unit':
        //             echo 'ciao';
        //             return;
        //         case 'price-change-percentage':
        //             return;
        //         default:
        //             return;
        //     }
        // }

        public function column_cb( $item ) {
            return sprintf( '<input type="checkbox" name="products[]" value="%s" />', $item->get_id() );
        }

        public function no_items() {
            return __( 'No products available.', 'wc-price-changer' );
        }
    }
?>