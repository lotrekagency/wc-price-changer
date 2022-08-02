<?php
 /**
 * Define the action interface class.
 *
 * @package    WC_Price_Changer
 * @subpackage WC_Price_Changer/includes
 * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
 */

    if ( !class_exists( 'WCPC_Action_Interface' ) ) {

        class WCPC_Action_Interface {

            var $products;
            var $mode;
            var $operation = 'dec';
            var $manager;

            function __construct() {
                $this->load_dependencies();
                $this->manager = WCPC_Manager::get_instance();
                $this->get_data();
                $this->get_session();
                $this->apply_price_changes();
                $this->display();
            }

            private function load_dependencies() {
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-manager.php';
            }

            private function get_data() {
                $this->products = array_map( 'WCPC_Manager::get_product', $_POST['products'] );
                $this->mode = $_POST['action'];
                $this->operation = isset( $_POST['operation'] ) ? $_POST['operation'] : $this->operation;
                $this->change_value = isset( $_POST['value'] ) ? $_POST['value'] : $this->change_value;
                $this->datetime_start = isset( $_POST['datetime_start'] ) ? $_POST['datetime_start'] : $this->datetime_start;
                $this->datetime_end = isset( $_POST['datetime_end'] ) ? $_POST['datetime_end'] : $this->datetime_end;
                $this->only_variations = isset( $_POST['only_variations'] ) ? $_POST['only_variations'] : $this->only_variations;
                $this->enable_translations = isset( $_POST['enable_translations'] ) ? $_POST['enable_translations'] : $this->enable_translations;
            }

            private function get_session() {
                $this->viewing_mode = $_SESSION['wcpc-viewing'];
            }

            private function apply_price_changes() {
                if ( $this->is_price_change_applied() ) {
                    if ( $this->products ) {
                        $args = array(
                            'operation' => $this->operation,
                            'mode' => $this->mode,
                            'value' => $this->change_value,
                            'datetime_start' => $this->parse_datetime( $this->datetime_start )->format('U'),
                            'datetime_end' => $this->parse_datetime( $this->datetime_end )->format('U'),
                        );
                        $scheduled_products = $this->products;
                        if ( $this->only_variations )
                            $scheduled_products = array_filter( $scheduled_products, function ( $product ) {
                                return $product->is_type( 'variation' );
                            } );
                        $args['products'] = array_map( function ( $product ) {
                                return $product->get_id();
                            }, $scheduled_products );

                        $this->manager->create_schedule( $args );
                    }
                }
            }

            private function is_preview_mode() {
                return isset( $_POST['preview'] );
            }

            private function is_price_change_applied() {
                return isset( $_POST['submit'] );
            }

            private function parse_datetime( $string ) {
                return new DateTime( $string );
            }

            public function display() {
                if ( $this->is_price_change_applied() )
                    return;

                $display_html = '
                    <div id="poststuff">
                        <div class="flex-box-container">';

                $display_html .= '
                            <div class="postbox interface-card actions-box">
                                <div class="inside">
                                    ' . $this->display_form() . '
                                </div>
                            </div>';
                 
                $display_html .= '
                            <div class="postbox interface-card products-box scrollable-container">
                                <div class="inside">
                                    ' . $this->display_products_table() . '
                                </div>
                            </div>';

                $display_html .= '
                        </div>
                        <br class="clear">
                    </div>';
            
                echo $display_html;
            }

            private function display_form() {
                foreach ( $this->products as $product )
                    $product_hidden_html .= '<input type="hidden" name="products[]" value="' . ( is_object( $product ) ? $product->get_id() : $product ) . '">';
                $form_html = '
                    <form method="post">
                        ' . $product_hidden_html . '
                        <input type="hidden" name="operation" value="' . $this->operation . '">
                        <input type="hidden" name="action" value="' . $this->mode . '">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="operation">Type of change</label>
                                </th>
                                <td>
                                    <select name="operation" id="operation">
                                        <option value="dec" ' . ($this->operation == 'dec' ? 'selected' : '') . '>Decrease</option>
                                        <option value="inc" ' . ($this->operation == 'inc' ? 'selected' : '') . '>Increase</option>
                                    </select>
                                </td>
                            </tr>';

                if ( $this->mode == 'unit' ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="value">Change unit value (' . get_woocommerce_currency_symbol() . ')</label>
                            </th>
                            <td>
                                <input type="number" name="value" required="true" step="0.01" min="0.01" value="' . $this->change_value . '">
                            </td>
                        </tr>';
                }
                if ( $this->mode == 'percentage' ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="value">Change percentage value (%)</label>
                            </th>
                            <td>
                                <input type="number" name="value" required="true" min="1" max="100" value="' . $this->change_value . '">
                            </td>
                        </tr>';
                }

                $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="datetime_start">Start datetime</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime_start" value="' . $this->datetime_start . '">
                            </td>
                        </tr>';

                $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="datetime_end">End datetime</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime_end" value="' . $this->datetime_end . '">
                            </td>
                        </tr>';
                
                if ( $this->viewing_mode == 'variations' ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="only_variations">Apply price change only on variations</label>
                            </th>
                            <td>
                                <input type="checkbox" name="only_variations" ' . ( $this->only_variations ? 'checked' : '' ) . '>
                            </td>
                        </tr>';
                }

                if ( $this->is_wpml_enabled() ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="enable_translations">Apply price change on all product translations</label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_translations" ' . ( $this->enable_translations ? 'checked' : '' ) . '>
                            </td>
                        </tr>';
                }
                
                $form_html .= '</table>';
                $form_html .= '<div class="action-buttons">' . get_submit_button( 'Preview', 'secondary', 'preview', false ) . get_submit_button( 'Apply', 'primary', 'submit', false ) . '</div>';
                $form_html .= '</form>';
                return $form_html;
            }

            private function display_products_table() {
                if ( $this->is_preview_mode() )
                    return $this->display_preview();
                return $this->display_products();
            }


            private function display_products() {
                $products = $this->products;
                if ( $this->only_variations ) {
                    $products = array_filter( $products, function ( $product ) {
                        return $product->is_type( 'variation' );
                    } );
                }

                $table_html =  '
                    <div class="table-products">
                        <table class="widefat">
                            <tbody>
                                <tr class="alternate">
                                    <th><strong>ID</strong></th>
                                    <th><strong>Product</strong></th>
                                    <th><strong>Price</strong></th>
                                </tr>';
                foreach ( $products as $product ) {
                    $table_html .= '
                        <tr>
                            <th>' . $product->get_id() . '</th>
                            <th>' . $product->get_name() . '</th>
                            <th>' . wc_price( $product->get_regular_price() ) . '</th>
                        </tr>';
                }              
                $table_html .= '</tbody>
                    </table></div>
                ';
                return $table_html;
            }

            private function display_preview() {
                $products = $this->products;
                if ( $this->only_variations ) {
                    $products = array_filter( $products, function ( $product ) {
                        return $product->is_type( 'variation' );
                    } );
                }

                $table_html =  '
                    <div class="table-products">
                        <table class="widefat">
                            <tbody>
                                <tr class="alternate">
                                    <th><strong>ID</strong></th>
                                    <th><strong>Product</strong></th>
                                    <th><strong>Price</strong></th>
                                    <th><strong>Price change (' . ( $this->operation == 'dec' ? '↓' : '↑' ) . ')</strong></th>
                                </tr>';
                foreach ( $products as $product ) {
                    $table_html .= '
                        <tr>
                            <th>' . $product->get_id() . '</th>
                            <th>' . $product->get_name() . '</th>
                            <th>' . wc_price( $product->get_regular_price() ) . '</th>
                            <th>' . wc_price( WCPC_Manager::calculate_price( $product, $this->mode, $this->operation, $this->change_value ) ) . '</th>
                        </tr>';
                }              
                $table_html .= '</tbody>
                    </table></div>
                ';
                return $table_html;
            }

            private function is_wpml_enabled() {
                return class_exists( 'Sitepress' );
            }

        }
    }
?>