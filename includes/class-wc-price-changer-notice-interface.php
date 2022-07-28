<?php
 /**
 * Define the notice interface class.
 *
 * @package    WC_Price_Changer
 * @subpackage WC_Price_Changer/includes
 * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
 */

    if ( !class_exists( 'WCPC_Notice_Interface' ) ) {
        class WCPC_Notice_Interface {

            var $scheduled_actions = array();

            public function __construct() {
                $this->load_dependencies();
                $this->manager = WCPC_Manager::get_instance();
                $this->display();
            }

            private function load_dependencies() {
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-price-changer-manager.php';
            }

            private function display() {
                $this->display_action_handling_responses();

                $queue_actions = $this->manager->get_queue_actions();
                $active_actions = $this->manager->get_active_actions();

                if ( $active_actions )
                    $this->display_active_actions();
                elseif ( $queue_actions )
                    $this->display_queue_actions();
            }

            private function display_action_handling_responses() {
                if ( isset( $_GET['action_delete_success'] ) && $_GET['action_delete_success'] )
                    $this->display_delete_success();

                if ( isset( $_GET['action_delete_error'] ) && $_GET['action_delete_error'] )
                    $this->display_delete_error();
            }

            private function display_queue_actions() {
                echo '
                    <div id="can-view-activities" class="notice notice-success">
                        <p>There are price change actions in queue.</p>
                        ' . $this->display_actions_table() . '
                        <a id="link-activities" name="view-activities" onclick="startAnimation()">View all actions</a>
                    </div>
                    ';
            }
            
            private function display_active_actions() {
                echo '
                    <div class="notice notice-warning">
                        <p>There are active price change actions.</p>
                        ' . $this->display_actions_table() . '
                        <a id="link-activities" name="view-activities" onclick="startAnimation()">View all actions</a>
                    </div>
                    ';
            }

            public function display_delete_success() {
                echo '
                    <div id="can-view-activities" class="notice notice-success">
                        <p>Deleted scheduled actions.</p>
                    </div>
                    ';
            }

            public function display_delete_error() {
                echo '
                    <div id="can-view-activities" class="notice notice-error">
                        <p>Cannot delete scheduled actions.</p>
                    </div>
                    ';
            }

            private function display_actions_table() {
                $all_actions = array();
                $queue_actions = $this->manager->get_queue_actions();
                $active_actions = $this->manager->get_active_actions();

                foreach ( $queue_actions as $timestamp => $action )
                    $all_actions[$timestamp] = $action;

                foreach ( $active_actions as $timestamp => $action )
                    $all_actions[$timestamp] = $action;
                ksort( $all_actions );

                $rows_html = '';
                foreach ( $all_actions as $timestamp => $time_actions ) {
                    foreach ( $time_actions as $action => $job ) {
                        $data = reset( $job )['args'];
                        $date = new DateTime();
                        $date->setTimestamp( $timestamp );
                        if ( $action == 'wcpc_apply_price_change' ) {
                            $text = 'Begin ';
                            $style = 'background-color: #daf1dc';
                        } elseif ( $action == 'wcpc_remove_price_change' ) {
                            $text = 'End ';
                            $style = 'background-color: #fff1cc';
                        }
                        if ( $data['operation'] == 'dec' )
                            $type = 'of price discount ';
                        elseif ( $data['operation'] == 'inc' )
                            $type = "of price increase "; 
                        if ( $data['mode'] == 'unit' )
                            $value = 'by ' . wc_price( $data['value'] );
                        elseif ( $data['mode'] == 'percentage' )
                            $value = 'by ' .  $data['value'] . ' %';

                        
                        $rows_html .= '
                            <tr style="' . $style . '">
                                <td style="padding-left: 10px">' . $text . $type . $value . '</td>
                                <td>' . $date->format( 'd/m/Y' ) . '</td>
                                <td>' . $date->format( 'H:i:s' ) . '</td>
                                <td>' . implode( ', ', $data['products']) . '</td>
                                <td>' . $this->get_remove_action_button( $data['id'] ) . '</td>
                            </tr>
                        ';
                    }
                }
                
                $table_html = '
                    <div id="div-table-jobs" class="div-table-jobs-hidden">
                        <table class="table-jobs">
                            <thead style="text-align: left">
                                <tr style="background-color: #e6e6e6">
                                    <th style="padding-left: 10px">Event</th>
                                    <th>Date</th>
                                    <th>Hour</th>
                                    <th>Products</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>'
                                . $rows_html .
                            '</tbody>
                        </table>
                    </div>
                ';
                return $table_html;
            }

            private function get_remove_action_button( $id ) {
                $query_args = array(
                    'page'      => 'price-changer',
                    'action'    => 'wcpc-remove-scheduled-event',
                    'event_id'  => rawurlencode( $id )
                );
                $link = add_query_arg( $query_args, admin_url( 'admin.php' ) );
                $link = wp_nonce_url( $link, "wcpc-remove-scheduled-event_{$query_args['action']}_{$id}" );
                return '<a href="' . esc_url( $link ) . '" >Remove</a>';
            }

        }
    }

?>