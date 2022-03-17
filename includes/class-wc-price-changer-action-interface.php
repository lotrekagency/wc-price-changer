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

            function __construct() {
                $this->load_dependencies();
                $this->display();
            }

            public function load_dependencies() {

            }

            public function display() {

                echo '
                    <div class="postbox">
                    <div class="wrap form-price-changer inside">
                        <form>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="choice">Tipo di modifica</label>
                                    </th>
                                    <td>
                                        <select name="choice" id="choice">
                                            <option value="dec">Decremento</option>
                                            <option value="dec">Incremento</option>
                                        </select>
                                    </td>
                                </tr>';
                
                if (TRUE) {
                    echo '
                        <tr>
                            <th scope="row">
                                <label for="value">Valore di modifica (€)</label>
                            </th>
                            <td>
                                <input type="number" name="value" required="true" step="0.01" min="0.01">
                            </td>
                        </tr>';
                }
                if (TRUE) {
                    echo '
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
                    echo '
                        <tr>
                            <th scope="row">
                                <label for="enable_translations">Modifica prezzo anche per le traduzioni dei prodotti</label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_translations" checked>
                            </td>
                        </tr>';
                }

                echo '
                        <tr>
                            <th scope="row">
                                <label for="datetime-start">Data e ora di inizio</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime-start">
                            </td>
                        </tr>';

                echo '
                        <tr>
                            <th scope="row">
                                <label for="datetime-end">Data e ora di fine</label>
                            </th>
                            <td>
                                <input type="datetime-local" name="datetime-end">
                            </td>
                        </tr>';
                

                echo '</table>';
                echo '<p class="submit">' . submit_button( 'Anteprima', 'secondary', 'preview', false ) . submit_button( 'Apply', 'primary', 'submit', false ) . '</p>';
                echo '</form></div>';
                
                echo '</div>';
            }

        }
    }
?>