<?php
    /**
     * Define the core manager of the price changer.
     * It contains final prices calculations functions and all the scheduling utils.
     *
     * @package    WC_Price_Changer
     * @subpackage WC_Price_Changer/includes
     * @author     Edoardo Mazzucchielli <edoardo.mazzu@lotrek.it>
     */

    if ( !class_exists( 'WCPC_Manager' ) ) {
        class WCPC_Manager {

            public function __construct() {
                add_action( 'wcpc_apply_price_change', array( $this, 'apply_price_change' ), 10 );
                add_action( 'wcpc_remove_price_change', array( $this, 'remove_price_change' ), 10 );
            }

            public function apply_price_change( $args ) {
                $product_ids = $args['ids'];
                $mode = $args['mode'];
                $operation = $args['operation'];
                $value = $args['value'];
                $enable_translations = $args['enable_translations'];

                foreach ( $product_ids as $product_id ) {
                    $product = wc_get_product( $product_id );
                    $final_price = $this->calculate_price( $product, $operation, $mode, $value );
                    if ( $enable_translations ) {
                        $wpml_trid = apply_filters( 'wpml_element_trid', '', $product->get_id() );
                        $wpml_product_translations = apply_filters( 'wpml_get_element_translations', '', $wpml_trid );
                        if ( $operation == 'inc' ) {
                            foreach( $wpml_product_translations as $translation ) {
                                $product_translation = wc_get_product( $translation->element_id );
                                $product_translation->set_price( $final_price );
                                $product_translation->set_regular_price( $final_price );
                                $product_translation->save();
                            }
                        } else {
                            foreach( $wpml_product_translations as $translation ) {
                                $product_translation = wc_get_product( $translation->element_id );
                                $product_translation->set_sale_price( $final_price );
                                $product_translation->save();
                            }
                        }
                    } else {
                        if ( $operation == 'inc' ) {
                            $product->set_price( $final_price );
                            $product->set_regular_price( $final_price );
                        } else
                            $product->set_sale_price($final_price);
                        $product->save();
                    }
                }
            }

            public function remove_price_change( $args ) {
                $product_ids = $args['ids'];
                $mode = $args['mode'];
                $operation = $args['operation'];
                $value = $args['value'];
                $enable_translations = $args['enable_translations'];

                if ( $enable_translations ) {
                    foreach ( $product_ids as $product_id ) {
                        $product = wc_get_product( $product_id );
                        $product_price = (float) $product->get_regular_price();
                        $wpml_trid = apply_filters( 'wpml_element_trid', '', $product->get_id() );
                        $wpml_product_translations = apply_filters( 'wpml_get_element_translations', '', $wpml_trid );
                        if ( $operation == 'inc' ) {
                            if ( $mode == 'percentage' ) {
                                foreach( $wpml_product_translations as $translation ) {
                                    $product_translation = wc_get_product( $translation->element_id );
                                    $product_translation->set_price( sprintf( "%.2f",  ( $product_price / ( 1 + ( $value / 100 ) ) ) ) );
                                    $product_translation->set_regular_price( sprintf( "%.2f",  ( $product_price / ( 1 + ( $value / 100 ) ) ) ) );
                                    $product_translation->save();
                                }
                            } else {
                                foreach( $wpml_product_translations as $translation ) {
                                    $product_translation = wc_get_product( $translation->element_id );
                                    $product_translation->set_price( sprintf( "%.2f",  $product_price - $value ) );
                                    $product_translation->set_regular_price( sprintf( "%.2f",  $product_price - $value ) );
                                    $product_translation->save();
                                }
                            }
                        } else {
                            foreach( $wpml_product_translations as $translation ) {
                                $product_translation = wc_get_product( $translation->element_id );
                                $product_translation->set_regular_price( $product->get_regular_price() );
                                $product_translation->set_price( $product->get_regular_price() );
                                update_post_meta( $product_translation->get_id(), '_price', $product->get_regular_price() );
                                $product_translation->set_sale_price( '' );
                                $product_translation->save();
                            }
                        }
                    }
                } else {
                    foreach ( $product_ids as $product_id ){
                        $product = wc_get_product( $product_id );
                        $product_price = (float) $product->get_regular_price();
                        if ( $choice == 'inc' ) {
                            if ( $operation == 'percentage' ) {
                                $product->set_price( sprintf( "%.2f",  ( $product_price / ( 1 + ( $value / 100 ) ) ) ) );
                                $product->set_regular_price( sprintf( "%.2f",  ( $product_price / ( 1 + ( $value / 100 ) ) ) ) );
                            } else {
                                $product->set_price( sprintf( "%.2f",  $product_price - $value ) );
                                $product->set_regular_price( sprintf( "%.2f",  $product_price - $value ) );
                            }
                        }
                        else $product->set_sale_price( '' );
                        $product->save();
                    }
                }
            }


            public static function calculate_price( $product, $mode, $operation, $value ) {
                $final_price = 0;
                if ( $operation == 'dec' )
                    $value = 0 - $value;
                if ( $mode == 'percentage' )
                    $final_price = $product->get_regular_price() + ( $product->get_regular_price() / 100 ) * $value;
                elseif ( $mode == 'unit' )
                    $final_price = $product->get_regular_price() + $value;
                return $final_price;
            }

            public static function create_schedule( $args ) {
                wp_schedule_single_event( $args['datetime_start'], 'wcpc_apply_price_change', $args );
                wp_schedule_single_event( $args['datetime_end'], 'wcpc_remove_price_change', $args );
            }

            public static function remove_schedule() {}

            public static function get_scheduled_actions() {
                $jobs = get_option( 'cron' );
                $queue_jobs = array();
                $active_jobs = array();

                foreach( $jobs as $timestamp => $job ) {
                    if ( is_array( $job ) and array_key_exists( 'wcpc_apply_price_change', $job ) )
                        $queue_jobs[$timestamp] = $job;
                    if ( is_array( $job ) and array_key_exists( 'wcpc_remove_price_change', $job ) )
                        $active_jobs[$timestamp] = $job;
                }
                return array( 'queue' => $queue_jobs, 'active' => $active_jobs );
            }

        }
    }

?>