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
            var $choice = 'dec';

            function __construct() {
                $this->load_dependencies();
                $this->get_data();
                $this->display();
            }

            private function load_dependencies() {
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-manager.php';
            }

            private function get_data() {
                $this->products = $_POST['products'];
                $this->mode = $_POST['action'];
                $this->choice = isset( $_POST['choice'] ) ? $_POST['choice'] : $this->choice;
                $this->change_value = isset( $_POST['value'] ) ? $_POST['value'] : $this->change_value;
            }

            private function is_preview_mode() {
                return isset( $_POST['preview'] );
            }

            public function display() {
                $display_html = '
                    <div id="poststuff">
                        <div id="post-body" class="metabox-holder columns-2">
                            <div id="post-body-content" class="interface-card">
                                <div class="meta-box-sortables ui-sortable">
                                    <div class="postbox interface-card">
                                        <div class="inside">
                                            ' . $this->display_form() . '
                                        </div>
                                    </div>
                                </div>
                            </div>';
                
                if ( ! $this->is_preview_mode() ) 
                    $display_html .= '
                            <div id="postbox-container-1" class="postbox-container">
                                <div class="meta-box-sortables">
                                    <div class="postbox interface-card">
                                        <div class="inside">
                                            ' . $this->display_products() . '
                                        </div>
                                    </div>
                                </div>
                            </div>';
                
                if ( $this->is_preview_mode() ) 
                    $display_html .= '
                            <div id="postbox-container-1" class="postbox-container">
                                <div class="meta-box-sortables">
                                    <div class="postbox interface-card">
                                        <div class="inside">
                                            ' . $this->display_preview() . '
                                        </div>
                                    </div>
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
                    $product_hidden_html .= '<input type="hidden" name="products[]" value="' . $product . '">';
                $form_html = '
                    <form method="post">
                        ' . $product_hidden_html . '
                        <input type="hidden" name="choice" value="' . $this->choice . '">
                        <input type="hidden" name="action" value="' . $this->mode . '">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="choice">Type of change</label>
                                </th>
                                <td>
                                    <select name="choice" id="choice">
                                        <option value="dec" ' . ($this->choice == 'dec' ? 'selected' : '') . '>Decrease</option>
                                        <option value="inc" ' . ($this->choice == 'inc' ? 'selected' : '') . '>Increase</option>
                                    </select>
                                </td>
                            </tr>';

                if ( $this->mode == 'price-change-unit' ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="value">Change unit value (' . get_woocommerce_currency_symbol() . ')</label>
                            </th>
                            <td>
                                <input type="number" name="value" required="true" step="0.01" min="0.01">
                            </td>
                        </tr>';
                }
                if ( $this->mode == 'price-change-percentage' ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="value">Change percentage value (%)</label>
                            </th>
                            <td>
                                <input type="number" name="value" required="true" min="1" max="100">
                            </td>
                        </tr>';
                }

                if (TRUE) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="enable_translations">Apply price change on all product translations</label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_translations" checked>
                            </td>
                        </tr>';
                }

                $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="datetime-start">Start datetime</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime-start">
                            </td>
                        </tr>';

                $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="datetime-end">End datetime</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime-end">
                            </td>
                        </tr>';
                
                $form_html .= '</table>';
                $form_html .= '<p class="submit">' . get_submit_button( 'Preview', 'secondary', 'preview', false ) . get_submit_button( 'Apply', 'primary', 'submit', false ) . '</p>';
                $form_html .= '</form>';
                return $form_html;
            }

            private function display_products() {
                $table_html =  '
                    <div class="table-products">
                        <table class="widefat">
                            <tbody>
                                <tr class="alternate">
                                    <th>Product</th>
                                    <th>Price</th>
                                </tr>';
                foreach ( $this->products as $product ) {
                    $product = new WC_Product( $product );
                    $table_html .= '
                        <tr>
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
                $table_html =  '
                    <div class="table-products">
                        <table class="widefat">
                            <tbody>
                                <tr class="alternate">
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Price change (' . ( $this->choice == 'dec' ? '↓' : '↑' ) . ')</th>
                                </tr>';
                foreach ( $this->products as $product ) {
                    $product = new WC_Product( $product );
                    $table_html .= '
                        <tr>
                            <th>' . $product->get_name() . '</th>
                            <th>' . wc_price( $product->get_regular_price() ) . '</th>
                            <th>' . wc_price( WCPC_Manager::calculate_price( $product, $this->mode, $this->choice, $this->change_value ) ) . '</th>
                        </tr>';
                }              
                $table_html .= '</tbody>
                    </table></div>
                ';
                return $table_html;
            }

        }
    }
?>