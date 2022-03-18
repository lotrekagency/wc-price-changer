<?php
 /**
 * Define the action interface class.
 *
 * @package    WC_Price_Changer
 * @subpackage WC_Price_Changer/includes
 * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
 */

    if ( !class_exists( 'WCP_Action_Interface' ) ) {

        class WCP_Action_Interface {

            var $products;
            var $mode;
            var $choice = 'dec';

            function __construct() {
                $this->get_data();
                $this->display();
            }

            public function get_data() {
                $this->products = $_POST['products'];
                $this->mode = $_POST['action'];
                $this->choice = isset( $_POST['choice'] ) ? $_POST['choice'] : $this->choice;
            }

            public function display() {
                echo '
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
                            </div>
                            <div id="postbox-container-1" class="postbox-container">
                                <div class="meta-box-sortables">
                                    <div class="postbox interface-card">
                                        <div class="inside">
                                            ' . $this->display_products() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br class="clear">
                    </div>';
            }

            public function display_form() {
                $form_html = '
                    <form>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="choice">Tipo di modifica</label>
                                </th>
                                <td>
                                    <select name="choice" id="choice">
                                        <option value="dec" ' . ($this->choice == 'dec' ? 'selected' : '') . '>Decremento</option>
                                        <option value="inc" ' . ($this->choice == 'inc' ? 'selected' : '') . '>Incremento</option>
                                    </select>
                                </td>
                            </tr>';

                if ( $this->mode == 'price-change-unit' ) {
                    $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="value">Valore di modifica (€)</label>
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
                                <label for="value">Valore percentuale di modifica (%)</label>
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
                                <label for="enable_translations">Modifica prezzo anche per le traduzioni dei prodotti</label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_translations" checked>
                            </td>
                        </tr>';
                }

                $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="datetime-start">Data e ora di inizio</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime-start">
                            </td>
                        </tr>';

                $form_html .= '
                        <tr>
                            <th scope="row">
                                <label for="datetime-end">Data e ora di fine</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime-end">
                            </td>
                        </tr>';
                
                $form_html .= '</table>';
                $form_html .= '<p class="submit"><input type="submit" name="preview" id="preview" class="button" value="Preview"><input type="submit" name="submit" id="submit" class="button button-primary" value="Apply"></p>';
                $form_html .= '</form>';
                return $form_html;
            }

            public function display_products() {
                $table_html =  '
                    <div class="table-products">
                        <table class="widefat">
                            <tbody>
                                <tr class="alternate">
                                    <th>Product</th>
                                    <th>Price</th>
                                </tr>';
                foreach ( $this->products as $product ) {
                    $table_html .= '
                        <tr>
                            <th>' . $product . '</th>
                            <th></th>
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