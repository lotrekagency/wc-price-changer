<?php
    /**
     * Define the product list table.
     *
     * @package    WC_Price_Changer
     * @subpackage WC_Price_Changer/includes
     * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
     */

    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

    class WCPC_Product_List extends WP_List_Table {

        var $products = array();
        var $product_categories = array();
        var $viewing_mode = 'products';
        var $viewing_category;
    
        function __construct() {
            parent::__construct( array(
                'singular'  => __( 'product', 'wc-price-changer' ),
                'plural'    => __( 'products', 'wc-price-changer' ),
                'ajax'      => false
            ) );
            $this->init();       
        }

        public function init() {
            $this->get_variables();
            $this->get_items();
            $this->prepare_items();
            $this->display();
        }

        public function get_variables() {
            $this->viewing_mode = isset( $_POST['viewing'] ) ? $_POST['viewing'] : $this->viewing_mode;
            $this->viewing_category = isset( $_POST['category'] ) ? $_POST['category'] : '';
        }

        public function get_items() {
            $this->products = wc_get_products( array(
                'status' => 'publish',
                'category' => $this->viewing_category,
                'limit' => -1
            ) );
            if ( $this->viewing_mode == 'variations' ) {
                $variations = array();
                foreach ( $this->products as $product ) {
                    array_push( $variations, $product );
                    if ( $product instanceof WC_Product_Variable ) {
                        foreach ( $product->get_available_variations() as $product_variation )
                            array_push( $variations, wc_get_product( $product_variation['variation_id'] ) );
                    }
                }
                $this->products = $variations;
            }
            $this->product_categories = get_terms( ['taxonomy' => 'product_cat'] );
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
                    return wc_price( $item->get_regular_price() );
                case 'sale_price':
                    return $item->get_sale_price() ? wc_price( $item->get_sale_price() ) : '-';
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
                'unit'         => __( 'Change prices by unit value', 'wc-price-changer' ),
                'percentage'   => __( 'Change prices by percentage value', 'wc-price-changer' )
            );
        }

        public function extra_tablenav( $which ) {
            if ( $which == 'top' ) {
                echo $this->product_filters();
            }
        }

        public function product_filters() {
            echo '<div class="alignright actions bulkactions">';
            echo $this->product_filter_select();
            echo $this->product_catagory_filter_select();
            submit_button( 'Filter', '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
            echo '</div>';
        }

        public function product_filter_select() {
            return '
                <select name="viewing">
                    <option value="products"' . ( $this->viewing_mode == 'products' ? ' selected' : '' ) . '>Only products</option>
                    <option value="variations"' . ( $this->viewing_mode == 'variations' ? ' selected' : '' ) . '>Products and variations</option>
                </select>
            ';
        }

        public function product_catagory_filter_select() {
            $html = '<select name="category"><option value="">Tutte le categorie</option>';
            foreach ( $this->product_categories as $category ) 
                $html .= '<option value="' . $category->slug . '"' . ( $this->viewing_category == $category->slug ? ' selected' : '' ) . '>' . $category->name . '</option>';
            $html .= '</select>';
            return $html;
        }

        public function get_queue_products_ids() {
            return array();
        }

        public function get_active_products_ids() {
            return array();
        }

        public function column_cb( $item ) {
            return sprintf( '<input type="checkbox" name="products[]" value="%s" />', $item->get_id() );
        }

        public function no_items() {
            return __( 'No products available.', 'wc-price-changer' );
        }

        protected function single_row_columns( $item ) {
            list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
        
            foreach ( $columns as $column_name => $column_display_name ) {
                $classes = "$column_name column-$column_name";
                if ( $primary === $column_name ) {
                    $classes .= ' has-row-actions column-primary';
                }
            
                if ( in_array( $column_name, $hidden ) ) {
                    $classes .= ' hidden';
                }
                $data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';
            
                $attributes = "class='$classes' $data";
            
                if ( 'cb' === $column_name ) {
                    $column_cb_style = '';
                    if ( in_array( $item->get_id(), $this->get_queue_products_ids() ) )
                        $column_cb_style = 'border-left: 4px solid #fff; border-left-color: #46b450;';
                    else if ( in_array( $item->get_id(), $this->get_active_products_ids() ) )
                        $column_cb_style = 'border-left: 4px solid #fff; border-left-color: #ffb900;';
                    echo '<th style="' . $column_cb_style . '" scope="row" class="check-column">';
                    echo $this->column_cb( $item );
                    echo '</th>';
                } elseif ( method_exists( $this, '_column_' . $column_name ) ) {
                    echo call_user_func(
                        array( $this, '_column_' . $column_name ),
                        $item,
                        $classes,
                        $data,
                        $primary
                    );
                } elseif ( method_exists( $this, 'column_' . $column_name ) ) {
                    echo "<td $attributes>";
                    echo call_user_func( array( $this, 'column_' . $column_name ), $item );
                    echo $this->handle_row_actions( $item, $column_name, $primary );
                    echo '</td>';
                } else {
                    $style_variation = "";
                    if ( $item->is_type( 'variation' ) and $column_name == "name" )
                    $style_variation = 'style="padding-left: 30px"';
                    echo "<td $style_variation $attributes>";
                    echo $this->column_default( $item, $column_name );
                    echo $this->handle_row_actions( $item, $column_name, $primary );
                    echo '</td>';
                }
            }
          }
    }
?>