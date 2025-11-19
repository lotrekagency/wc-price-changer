<?php
/**
 * Plugin Name:       WC Price Changer
 * Description:       Manage your products prices smartly.
 * Version:           1.2.2
 * Author:            Lotrèk
 * Author URI:        https://lotrek.it/
 */

init_plugin();

function init_plugin(){
  // Rimosso session_start() - usiamo WordPress Options API
  add_action('admin_enqueue_scripts', 'add_scripts');
  add_action('admin_menu', 'setup_menu');
  add_action('apply_price_changes', 'apply');
  add_action('action_change_prices', 'change_prices', 10, 5);
  add_action('action_remove_prices', 'remove_prices', 10, 5);
  if (!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
  }
}

function setup_menu(){
  if ( class_exists( 'WooCommerce' ) ) {
    if (isset($_POST['viewing'])){
      update_option('wc_price_changer_viewing', sanitize_text_field($_POST['viewing']));
    }
    $viewing = get_option('wc_price_changer_viewing', 'products');
    
    add_submenu_page(
      'woocommerce',
      'Price Changer',
      'WC Price Changer',
      'manage_options',
      'price-changer',
      'setup_page'
    );
    add_submenu_page(
      'woocommerce',
      'Gestione Cron',
      'WC Cron Manager',
      'manage_options',
      'price-changer-cron',
      'setup_cron_manager_page'
    );
    if(isset($_POST['submit']))
    {
      $products = get_option('wc_price_changer_products', array());
      if ( isset($_POST['only-variations']) ){
        $variations = array();
        foreach($products as $product){
          $product_retrieved = wc_get_product($product);
          if ( $product_retrieved->is_type('variation') ) {
            array_push($variations, $product);
          }
        }
        $products = $variations;
      }
      if ( $products ) {
        $enable_translations = isset($_POST['enable_translations']) ? true : false;
        $action_args = array($products, $_POST['choice'], (float) $_POST['value'], get_option('wc_price_changer_submit_type', ''), $enable_translations);
        if($_POST['datetime-start']){
          $datetime_start = new DateTime($_POST['datetime-start'], new DateTimeZone('Europe/Berlin'));
          wp_schedule_single_event($datetime_start->format('U'), 'action_change_prices', $action_args);
          if($_POST['datetime-end']){
            $datetime_end = new DateTime($_POST['datetime-end'], new DateTimeZone('Europe/Berlin'));
            wp_schedule_single_event($datetime_end->format('U'), 'action_remove_prices', $action_args);
          }
          add_action( 'admin_notices', 'action_notice_schedule_change' );
        }
        else{
          $enable_translations_direct = isset($_POST['enable_translations']) ? true : false;
          do_action('action_change_prices', $products, $_POST['choice'], (float)$_POST['value'], get_option('wc_price_changer_submit_type', ''), $enable_translations_direct);
          add_action( 'admin_notices', 'action_notice_direct_change' );
        }
      } else {
        add_action( 'admin_notices', 'action_notice_products_error' );
      }
    }
    if ( isset( $_POST['bulk-action'] ) ){
      if ( !isset( $_POST['products'] ) ){
        add_action( 'admin_notices', 'action_notice_no_products' );
      }
    }
  }
}

class ProductList extends WP_List_Table {

  var $products = array();
  var $active_jobs = array();
  var $queue_jobs = array();

  function __construct(){
    $selected_categories = '';
    if(isset($_POST['categories'])){
      $selected_categories = $_POST['categories'];
    }
    $this->products = wc_get_products(array('status' => 'publish', 'category' => $selected_categories, 'limit' => -1));
    $viewing = get_option('wc_price_changer_viewing', 'products');
    if($viewing == 'variations'){
      $variations = array();
      foreach($this->products as $product){
        array_push($variations, $product);
        if ($product instanceof WC_Product_Variable){
          foreach($product->get_available_variations() as $product_variation){
            array_push($variations, wc_get_product($product_variation['variation_id']));
          }
        }
      }
      $this->products = $variations;
    }

    parent::__construct( array(
        'singular'  => __( 'prodotto', '' ),
        'plural'    => __( 'prodotti', '' ),
        'ajax'      => false
    ) );

    add_action( 'admin_head', array( &$this, 'admin_header' ) );
    $this->check_cron_jobs();
    }

  function admin_header() {
    $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
    if( 'my_list_test' != $page )
    return;
  }

  function no_items() {
    _e( 'Non sono presenti prodotti.' );
  }

  function column_cb($item) {
    return sprintf(
        '<input type="checkbox" name="products[]" value="%s" />', $item->get_id()
    );
}

  function get_columns(){
    $columns = array(
        'cb'        => '<input type="checkbox"/>',
        'name' => __( 'Nome', '' ),
        'category' => __('Categoria', ''),
        'price' => __( 'Prezzo', '' ),
        'sale_price' => __('Prezzo scontato', ''),
        'id' => __('ID', ''),
    );
     return $columns;
  }

  function column_default( $item, $column_name ) {
    $viewing = get_option('wc_price_changer_viewing', 'products');
    switch( $column_name ) {
      case 'name':
        return (($viewing == 'variations' and !$item->is_type('variation')) ? ('<strong>' . $item->get_name() . '</strong>') : $item->get_name());
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

  function prepare_items() {
    $columns  = $this->get_columns();
    $hidden   = array();
    $this->_column_headers = array( $columns, $hidden);
    $per_page = 5;
    $current_page = $this->get_pagenum();
    $total_items = count( $this->products );
    //$this->found_data = array_slice( $this->products,( ( $current_page-1 )* $per_page ), $per_page );
    $this->set_pagination_args( array(
      'total_items' => $total_items
    ) );
    $this->process_bulk_action();
    $this->items = $this->products;
  }

  function get_bulk_actions() {
    $actions = array(
      'price-change-unit'    => 'Modifica i prezzi di un valore unitario',
      'price-change-percentage'    => 'Modifica i prezzi di un valore percentuale'
    );
    return $actions;
  }

  protected function bulk_actions( $which = '' ) {
    if ( is_null( $this->_actions ) ) {
      $this->_actions = $this->get_bulk_actions();
      /**
       * Filters the list table Bulk Actions drop-down.
       *
       * The dynamic portion of the hook name, `$this->screen->id`, refers
       * to the ID of the current screen, usually a string.
       *
       * This filter can currently only be used to remove bulk actions.
       *
       * @since 3.5.0
       *
       * @param string[] $actions An array of the available bulk actions.
       */
      $this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );  // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
      $two            = '';
    } else {
        $two = '2';
    }

    if ( empty( $this->_actions ) ) {
        return;
    }

    echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
    echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";

    foreach ( $this->_actions as $name => $title ) {
      $class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

      echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
    }

    echo "</select>\n";

    submit_button( __( 'Apply' ), 'action', 'bulk-action', false, array( 'id' => "doaction$two" ) );
    echo "\n";
  }

  public function display() {
    $singular = $this->_args['singular'];

    $this->display_tablenav( 'top' );

    $this->screen->render_screen_reader_content( 'heading_list' );
    ?>
    <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
    <thead>
    <tr>
        <?php $this->print_column_headers(); ?>
    </tr>
    </thead>

    <tbody id="the-list"
        <?php
        if ( $singular ) {
            echo " data-wp-lists='list:$singular'";
        }
        ?>
        >
        <?php $this->display_rows_or_placeholder(); ?>
    </tbody>

    <tfoot>
    <tr>
        <?php $this->print_column_headers( false ); ?>
    </tr>
    </tfoot>

    </table>
    <?php
  }

  protected function extra_tablenav( $which ) {
    $move_on_url = '&cat-filter=';
    if ( $which == "top" ){
      $viewing = get_option('wc_price_changer_viewing', 'products');
      ?>
      <div class="alignright actions bulkactions">
      <?php
      echo '<select name="viewing">\n';
      echo '<option value="products" ' . (($viewing == 'products') ? 'selected' : '') .'>Solo prodotti</option>';
      echo "\t" . '<option value="variations" ' . (($viewing == 'variations') ? 'selected' : '') . '>Prodotti e variazioni</option>\n';
      echo "</select>\n";
      $categories = get_terms( ['taxonomy' => 'product_cat'] );
      echo '<select name="categories">\n';
      echo '<option value="">Tutte le categorie</option>';
      foreach ( $categories as $category ) {
          echo "\t" . '<option value="' . $category->slug . '"' . ((isset($_POST['categories']) and $_POST['categories'] == $category->slug) ? 'selected' : '') . '>' . $category->name . "</option>\n";
      }
      echo "</select>\n";
      submit_button( 'Filtra', '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
      ?>
      </div>
      <?php
    }
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
      // Comments column uses HTML in the display name with screen reader text.
      // Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
      $data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';

      $attributes = "class='$classes' $data";

      if ( 'cb' === $column_name ) {
        $column_cb_style = '';
        if ( in_array( $item->get_id(), $this->get_queue_products_ids() ) ){
          $column_cb_style = 'border-left: 4px solid #fff; border-left-color: #46b450;';
        }
        else if ( in_array( $item->get_id(), $this->get_active_products_ids() ) ){
          $column_cb_style = 'border-left: 4px solid #fff; border-left-color: #ffb900;';
        }
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
        if ($item->is_type('variation') and $column_name == "name"){
          $style_variation = 'style="padding-left: 30px"';
        }
        echo "<td $style_variation $attributes>";
        echo $this->column_default( $item, $column_name );
        echo $this->handle_row_actions( $item, $column_name, $primary );
        echo '</td>';
      }
    }
  }

  function process_bulk_action() {
    $action = $this->current_action();
    if ( isset( $_POST['products'] ) ) {
      update_option('wc_price_changer_products', array_map('intval', $_POST['products']));
      switch ( $action ) {
        case 'price-change-unit':
          update_option('wc_price_changer_submit_type', 'unit');
          setup_price_changer('unit');
          break;
        case 'price-change-percentage':
          update_option('wc_price_changer_submit_type', 'percentage');
          setup_price_changer('percentage');
          break;
        default:
          return;
          break;
      }
    }
    return;
  }

  function check_cron_jobs() {
    $jobs = get_option( 'cron' );
    $now = time();
    foreach($jobs as $timestamp => $job){
      $is_past = $timestamp <= $now;

      if ( is_array($job) and array_key_exists( 'action_change_prices', $job) ){
        // Se l'evento è nel passato, è ATTIVO (cambio già applicato)
        // Se è nel futuro, è IN CODA (cambio da applicare)
        if ($is_past) {
          array_push($this->active_jobs, $job['action_change_prices']);
        } else {
          array_push($this->queue_jobs, $job['action_change_prices']);
        }
      }
      if ( is_array($job) and array_key_exists( 'action_remove_prices', $job) ){
        // Gli eventi remove_prices non vengono mostrati con bordi colorati
        // perché rappresentano la fine di uno sconto già gestito
      }
    }
  }

  function get_active_products_ids(){
    $active_products = array();
    foreach($this->active_jobs as $job){
      $args = reset($job)['args'][0];
      foreach($args as $id){
        array_push($active_products, $id);
      }
    }
    return $active_products;
  }

  function get_queue_products_ids(){
    $queue_products = array();
    foreach($this->queue_jobs as $job){
      $args = reset($job)['args'][0];
      foreach($args as $id){
        array_push($queue_products, $id);
      }
    }
    return $queue_products;
  }

}

function setup_page(){
  $myListTable = new ProductList();
  echo '<div class="wrap"><h1>WC Price Changer</h1>';
  check_active_jobs($myListTable->active_jobs, $myListTable->queue_jobs);
  $myListTable->prepare_items();
  if(isset($_POST['preview'])){
    setup_price_changer(get_option('wc_price_changer_submit_type', ''));
  }
  echo '<form method="post">';
  $myListTable->display();
  echo '</form>';
  echo '</div>';
}

function setup_price_changer($type){
  ?>
  <style>
  .form-price-changer{
    display: inline-block;
    vertical-align: top;
    width: 100%;
  }
  .table-form {
    width: 100%;
  }
  .table-selected {
    width: 100%;
    height: 100%;
  }
  </style>
  <div class="wrap form-price-changer">
    <form method="post">
    <table class="table-form">
      <tr>
      <td>
        <table>
      <tr>
      <td>
      <label for="choice">Tipo di modifica</label><br>
      <input type="radio" name="choice" value="dec" <?php if($_POST['choice'] == 'dec' or !isset($_POST['choice'])){ echo 'checked'; } ?>>Decremento</input>
      </td>
      <td>
      <br><input type="radio" name="choice" value="inc" <?php if($_POST['choice'] == 'inc'){ echo 'checked'; } ?>>Incremento</input>
      </td>
      </tr>

      <tr>

      <?php
      if($type == 'unit'){
      ?>
        <td>
        <label for="value">Valore di modifica (€)</label><br>
        <input type="number" value="<?php echo $_POST['value'];?>" name="value" required="required" step="0.01" min="0.01">
        </td>
      <?php
      } else if($type == 'percentage'){
      ?>
        <td>
        <label for="price">Valore percentuale di modifica (%)</label><br>
        <input type="number" value="<?php echo $_POST['value'];?>" name="value" required="required" min="1" max="100">
        </td>
      <?php } ?>
      <?php if ( class_exists( 'Sitepress' ) ) :?>
        <td>
          <input type="checkbox" name="enable_translations" checked>
          <label for="enable_translations">Modifica prezzo anche per le traduzioni dei prodotti.</label><br>
        </td>
      <?php endif; ?>
      </tr>
      <tr>
        <td>
        <label for="datetime-start">Data e ora di inizio</label><br>
        <input type="datetime-local" value="<?php echo $_POST['datetime-start'];?>" name="datetime-start" min="<?php echo date('Y-m-d\TH:i'); ?>"></input>
        </td>
        <td>
        <label for="datetime-end">Data e ora di fine</label><br>
        <input type="datetime-local" value="<?php echo $_POST['datetime-end'];?>" name="datetime-end" min="<?php echo date('Y-m-d\TH:i'); ?>"></input>
        </td>
      </tr>

      <?php
        $viewing = get_option('wc_price_changer_viewing', 'products');
        if($viewing == 'variations'){
          echo '<tr><td><br></td></tr>';
          echo '<tr><td>';
          echo '<input type="checkbox" name="only-variations" ' . (isset($_POST['only-variations']) ? 'checked' : '') . '>';
          echo '<label for="only-variations">Applica cambio di prezzo solo alle variazioni.</label>';
          echo '</td></tr>';
        }
        echo '<tr><td><br></td></tr>';
        echo '<tr>';
        echo '<td>';
        submit_button('Anteprima', 'secondary', 'preview', false );
        echo '</td>';
        echo '<td>';
        submit_button('Apply', 'primary', 'submit', false );
        echo '</td>';
        echo '</tr>';
      ?>
      </table>
      </td>
      <td>
        <p>Prodotti selezionati</p>
        <table class="table-selected">
          <thead style="text-align: left">
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Prezzo</th>
              <th>Prezzo modificato</th>
            </tr>
          </thead>
          <tbody style="overflow-y: scroll">
          <?php
            $products = get_option('wc_price_changer_products', array());
            $table_products = array();
            if ( isset($_POST['only-variations']) ) {
              foreach ( $products as $product) {
                $product_retrieved = wc_get_product($product);
                if ( !$product_retrieved->is_type('variation') ) {
                  continue;
                } else {
                  array_push($table_products, $product);
                }
              }
            } else {
              $table_products = $products;
            }
            if ( $table_products ) {
              foreach ($table_products as $product){
                $product_retrieved = wc_get_product($product);
                echo '<tr><td>' . $product . '</td>';
                echo '<td>' . $product_retrieved->get_name() . '</td>';
                echo '<td>' . $product_retrieved->get_regular_price() . '</td>';
                if(isset($_POST['preview'])){
                  echo '<td>' . calculate_final_price((float)$product_retrieved->get_regular_price(), $_POST['choice'], $_POST['value'], get_option('wc_price_changer_submit_type', '')) . '</td>';
                }
                echo '</tr>';
              }
              echo '</tbody></table>';
            } else {
              echo '</tbody></table>';
              echo '<p>Nessun prodotto selezionato</p>';
            }
          ?>
      </td>
      </tr>
      </table>
    </form>
  </div>
  <hr>
  <?php
}

function change_prices($ids, $choice, $value, $operation, $enable_translations){
  foreach ( $ids as $product ){
    $product_retrieved = wc_get_product($product);
    set_prices($product_retrieved, calculate_final_price((float)$product_retrieved->get_regular_price(), $choice, $value, $operation), $choice, $enable_translations);
  }
}

function set_prices($product, $new_price, $choice, $enable_translations){
  if ($enable_translations) {
    $wpml_trid = apply_filters( 'wpml_element_trid', '', $product->get_id());
    $wpml_product_translations = apply_filters( 'wpml_get_element_translations', '', $wpml_trid);

    if ( $choice == 'inc' ){
      foreach( $wpml_product_translations as $translation) {
        $product_translation = wc_get_product($translation->element_id);
        $product_translation->set_price($new_price);
        $product_translation->set_regular_price($new_price);
        $product_translation->save();
      }
    } else {
      foreach( $wpml_product_translations as $translation) {
        $product_translation = wc_get_product($translation->element_id);
        $product_translation->set_sale_price($new_price);
        $product_translation->save();
      }
    }
  }
  else {
    if ( $choice == 'inc' ){
      $product->set_price($new_price);
      $product->set_regular_price($new_price);
    } else {
      $product->set_sale_price($new_price);
    }
    $product->save();
  }
}

function remove_prices($ids, $choice, $value, $operation, $enable_translations){
  if ($enable_translations) {
    foreach ( $ids as $product ){
      $product_retrieved = wc_get_product($product);
      $product_retrieved_price = (float)$product_retrieved->get_regular_price();

      $wpml_trid = apply_filters( 'wpml_element_trid', '', $product_retrieved->get_id());
      $wpml_product_translations = apply_filters( 'wpml_get_element_translations', '', $wpml_trid);

      if ( $choice == 'inc' ){
        if ( $operation == 'percentage' ){
          foreach( $wpml_product_translations as $translation) {
            $product_translation = wc_get_product($translation->element_id);
            $product_translation->set_price(sprintf("%.2f",  ( $product_retrieved_price / ( 1 + ( $value / 100 ) ) ) ) );
            $product_translation->set_regular_price(sprintf("%.2f",  ( $product_retrieved_price / ( 1 + ( $value / 100 ) ) ) ) );
            $product_translation->save();
          }
        }
        else {
          foreach( $wpml_product_translations as $translation) {
            $product_translation = wc_get_product($translation->element_id);
            $product_translation->set_price(sprintf("%.2f",  $product_retrieved_price - $value));
            $product_translation->set_regular_price(sprintf("%.2f",  $product_retrieved_price - $value));
            $product_translation->save();
          }
        }
      } else {
        foreach( $wpml_product_translations as $translation) {
          $product_translation = wc_get_product($translation->element_id);
          $product_translation->set_regular_price($product_retrieved->get_regular_price());
          $product_translation->set_price($product_retrieved->get_regular_price());
          update_post_meta($product_translation->get_id(), '_price', $product_retrieved->get_regular_price());
          $product_translation->set_sale_price('');
          $product_translation->save();

        }
      }
    }
  }
  else {
    foreach ( $ids as $product ){
      $product_retrieved = wc_get_product($product);
      $product_retrieved_price = (float)$product_retrieved->get_regular_price();

      if ( $choice == 'inc' ){
        if ( $operation == 'percentage' ){
          $product_retrieved->set_price(sprintf("%.2f",  ( $product_retrieved_price / ( 1 + ( $value / 100 ) ) ) ) );
          $product_retrieved->set_regular_price(sprintf("%.2f",  ( $product_retrieved_price / ( 1 + ( $value / 100 ) ) ) ) );
        }
        else {
          $product_retrieved->set_price(sprintf("%.2f",  $product_retrieved_price - $value));
          $product_retrieved->set_regular_price(sprintf("%.2f",  $product_retrieved_price - $value));
        }
      } else {
        $product_retrieved->set_sale_price('');
      }
      $product_retrieved->save();
    }
  }
}

function calculate_final_price($price, $choice, $value, $operation){
  if ( $operation == 'percentage' ){
    $value = ( $price / 100 ) * $value;
  }
  if ( $choice == 'inc' ){
    return sprintf("%.2f",  $price + $value);
  } else {
    return sprintf("%.2f",  $price - $value);
  }
}

function action_notice_no_products() {
  ?>
  <div class="notice notice-warning is-dismissible">
      <p><?php _e( 'Nessun prodotto selezionato.', '' ); ?></p>
  </div>
  <?php
}

function action_notice_direct_change() {
  ?>
  <div class="notice notice-success is-dismissible">
      <p><?php _e( 'I prezzi dei prodotti selezionati sono stati modificati con successo.', '' ); ?></p>
  </div>
  <?php
}

function action_notice_schedule_change() {
  ?>
  <div class="notice notice-success is-dismissible">
      <p><?php _e( 'La modifica dei prezzi dei prodotti selezionati è stata messa in coda.', '' ); ?></p>
  </div>
  <?php
}

function construct_queue_table() {
  ?>
  <div id="div-table-jobs" class="div-table-jobs-hidden div-table-jobs-active">
      <table class="table-jobs wc-events-table">
        <thead>
          <tr>
            <th style='padding-left: 10px; width: 10%;'>Tipo</th>
            <th style='width: 35%;'>Evento</th>
            <th style='width: 15%;'>Data</th>
            <th style='width: 10%;'>Ora</th>
            <th style='width: 30%;'>Prodotti</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $jobs = get_option('cron');
        $all_jobs = array();
        foreach($jobs as $timestamp=>$job){
          if ( is_array($job) and array_key_exists( 'action_change_prices', $job) ){
            $all_jobs[$timestamp] = $job;
          }
          else if ( is_array($job) and array_key_exists( 'action_remove_prices', $job) ){
            $all_jobs[$timestamp] = $job;
          }
        }

        foreach( $all_jobs as $timestamp=>$time_jobs ){
          foreach ( $time_jobs as $action=>$job ) {
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));
            $text = '';
            $style = '';
            if( $action == 'action_change_prices' ){
              $text = 'Inizio ';
              $style = 'background-color: #daf1dc';
            } else {
              $text = 'Fine ';
              $style = 'background-color: #fff1cc';
            }
            $value = '';
            if ( reset($job)['args'][3] == 'unit' ) {
              $value = 'di ' .  reset($job)['args'][2] . ' €';
            }
            else {
              $value = 'del ' .  reset($job)['args'][2] . ' %';
            }
            $type = '';
            $type_icon = '';
            if (reset($job)['args'][1] == 'dec' ) {
              $type = 'dello sconto ';
              $type_icon = '<span class="dashicons dashicons-arrow-down-alt" style="color: #d63638; font-size: 16px; vertical-align: middle;"></span>';
            } else {
              $type = "dell' aumento ";
              $type_icon = '<span class="dashicons dashicons-arrow-up-alt" style="color: #00a32a; font-size: 16px; vertical-align: middle;"></span>';
            }
            $event_badge_color = $action == 'action_change_prices' ? '#46b450' : '#f0b849';
            echo '<tr>';
            echo "<td style='padding-left: 10px;'>";
            echo "<span class='event-badge' style='background: " . $event_badge_color . "; color: white; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block;'>" . strtoupper($text) . "</span>";
            echo "</td>";
            echo "<td>";
            echo $type_icon . " " . $text . $type . " <strong>" . $value . "</strong>";
            echo "</td>";
            echo '<td><strong>' . $date->format('d/m/Y') . '</strong></td>';
            echo '<td><strong>' . $date->format('H:i') . '</strong></td>';
            $products_list = reset($job)['args'][0];
            $products_count = count($products_list);
            echo '<td><span class="dashicons dashicons-products" style="color: #50575e; vertical-align: middle;"></span> <strong>' . $products_count . '</strong> prodott' . ($products_count === 1 ? 'o' : 'i') . ' <small style="color: #646970;">(' . implode(', ', array_slice($products_list, 0, 3)) . ($products_count > 3 ? '...' : '') . ')</small></td>';
            echo '</tr>';
          }
        }
        ?>
        </tbody>
      </table>
      </div>
  <?php
}

function notice_queue_jobs() {
  ?>
  <div id="can-view-activities" class="wc-price-events-card wc-events-queue">
    <div class="wc-events-header">
      <div class="wc-events-icon">
        <span class="dashicons dashicons-clock"></span>
      </div>
      <div class="wc-events-title">
        <h3>Eventi in coda</h3>
        <p>Ci sono cambi di prezzo programmati</p>
      </div>
    </div>
    <?php construct_queue_table(); ?>
    <button type="button" id="link-activities" class="button button-primary" onclick="startAnimation()" style="margin-top: 15px;">
      Nascondi tutte le attività
    </button>
  </div>
  <script>
  function startAnimation(){
    const notice = document.getElementById('div-table-jobs');
    notice.classList.toggle('div-table-jobs-active');
    const element = document.getElementById('link-activities');
    const isVisible = notice.classList.contains('div-table-jobs-active');
    element.textContent = isVisible ? 'Nascondi tutte le attività' : 'Visualizza tutte le attività';
  }
  </script>
  <?php
}

function notice_active_jobs() {
  ?>
  <div class="wc-price-events-card wc-events-active">
    <div class="wc-events-header">
      <div class="wc-events-icon">
        <span class="dashicons dashicons-warning"></span>
      </div>
      <div class="wc-events-title">
        <h3>Eventi attivi</h3>
        <p>Ci sono cambi di prezzo già applicati</p>
      </div>
    </div>
    <?php construct_queue_table(); ?>
    <button type="button" id="link-activities" class="button button-primary" onclick="startAnimation()" style="margin-top: 15px;">
      Nascondi tutte le attività
    </button>
  </div>
  <script>
  function startAnimation(){
    const notice = document.getElementById('div-table-jobs');
    notice.classList.toggle('div-table-jobs-active');
    const element = document.getElementById('link-activities');
    const isVisible = notice.classList.contains('div-table-jobs-active');
    element.textContent = isVisible ? 'Nascondi tutte le attività' : 'Visualizza tutte le attività';
  }
  </script>
  <?php
}

function notice_no_events() {
  ?>
  <div class="wc-price-events-card wc-events-empty">
    <div class="wc-events-header">
      <div class="wc-events-icon">
        <span class="dashicons dashicons-yes-alt"></span>
      </div>
      <div class="wc-events-title">
        <h3>Nessun evento attivo</h3>
        <p>Non ci sono cambi di prezzo programmati</p>
      </div>
    </div>
  </div>
  <?php
}

function action_notice_products_error() {
  ?>
  <div class="notice notice-error is-dismissible">
      <p><?php _e( 'Si è verificato un errore durante il selezionamento dei prodotti.', '' ); ?></p>
  </div>
  <?php
}

function check_active_jobs($active_jobs, $queue_jobs) {
  if ( $queue_jobs ) {
    notice_queue_jobs();
  }
  else if ( $active_jobs ) {
    notice_active_jobs();
  }
  else {
    notice_no_events();
  }
}

function add_scripts(){
  wp_enqueue_style( 'wc-price-changer-style', plugin_dir_url( __FILE__  ) . 'scripts/style.css', array(), '1.2.1');
  wp_enqueue_script( 'wc-price-changer-script', plugin_dir_url( __FILE__  ) . 'scripts/script.js', array(), '1.2.1', true);
}

function setup_cron_manager_page(){
  // Gestione esecuzione manuale cron
  if(isset($_POST['run_due_crons'])){
    $executed = 0;
    $crons = _get_cron_array();
    if(!is_array($crons)){
      $crons = array();
    }
    $now = time();

    foreach($crons as $timestamp => $hooks){
      if($timestamp <= $now){
        foreach($hooks as $hook => $events){
          if($hook === 'action_change_prices' || $hook === 'action_remove_prices'){
            foreach($events as $event){
              // Esegui l'azione
              do_action_ref_array($hook, $event['args']);
              $executed++;
            }
          }
        }
      }
    }

    // Rimuovi gli eventi eseguiti
    if($executed > 0){
      foreach($crons as $timestamp => $hooks){
        if($timestamp <= $now){
          foreach($hooks as $hook => $events){
            if($hook === 'action_change_prices' || $hook === 'action_remove_prices'){
              unset($crons[$timestamp][$hook]);
              if(empty($crons[$timestamp])){
                unset($crons[$timestamp]);
              }
            }
          }
        }
      }
      _set_cron_array($crons);
      echo '<div class="notice notice-success is-dismissible"><p>Eseguiti ' . $executed . ' eventi scaduti.</p></div>';
    } else {
      echo '<div class="notice notice-info is-dismissible"><p>Nessun evento scaduto da eseguire.</p></div>';
    }
  }

  // Gestione azioni
  if(isset($_POST['delete_event'])){
    $timestamp = intval($_POST['timestamp']);
    $hook = sanitize_text_field($_POST['hook']);
    $crons = _get_cron_array();
    if(!is_array($crons)){
      $crons = array();
    }
    if(isset($crons[$timestamp][$hook])){
      unset($crons[$timestamp][$hook]);
      if(empty($crons[$timestamp])){
        unset($crons[$timestamp]);
      }
      _set_cron_array($crons);
      echo '<div class="notice notice-success is-dismissible"><p>Evento eliminato con successo.</p></div>';
    }
  }

  if(isset($_POST['clear_all_events'])){
    $crons = _get_cron_array();
    if(!is_array($crons)){
      $crons = array();
    }
    $cleared = 0;
    foreach($crons as $timestamp => $hooks){
      foreach($hooks as $hook => $events){
        if($hook === 'action_change_prices' || $hook === 'action_remove_prices'){
          unset($crons[$timestamp][$hook]);
          $cleared++;
          if(empty($crons[$timestamp])){
            unset($crons[$timestamp]);
          }
        }
      }
    }
    _set_cron_array($crons);
    echo '<div class="notice notice-success is-dismissible"><p>Eliminati ' . $cleared . ' eventi del plugin.</p></div>';
  }

  // Recupera eventi cron
  $crons = _get_cron_array();
  if(!is_array($crons)){
    $crons = array();
  }
  $plugin_events = array();

  foreach($crons as $timestamp => $hooks){
    foreach($hooks as $hook => $events){
      if($hook === 'action_change_prices' || $hook === 'action_remove_prices'){
        foreach($events as $key => $event){
          $plugin_events[] = array(
            'timestamp' => $timestamp,
            'hook' => $hook,
            'args' => $event['args'],
            'key' => $key
          );
        }
      }
    }
  }

  // Ordina per timestamp
  usort($plugin_events, function($a, $b){
    return $a['timestamp'] - $b['timestamp'];
  });

  // Filtra eventi in base alla visualizzazione
  $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1';
  $now_filter = time();
  $filtered_events = $plugin_events;

  if (!$show_all) {
    // Nascondi eventi "action_remove_prices" passati (SCADUTI)
    $filtered_events = array_filter($plugin_events, function($event) use ($now_filter) {
      $is_past = $event['timestamp'] <= $now_filter;
      $is_remove = $event['hook'] === 'action_remove_prices';
      // Nascondi solo i "remove_prices" passati (eventi di fine già eseguiti)
      return !($is_past && $is_remove);
    });
  }

  $total_events = count($plugin_events);
  $shown_events = count($filtered_events);

  ?>
  <div class="wrap wc-price-changer-cron">
    <h1 class="wp-heading-inline">
      <span class="dashicons dashicons-clock" style="font-size: 28px; width: 28px; height: 28px;"></span>
      WC Price Changer - Gestione Cron
    </h1>
    <hr class="wp-header-end">

    <?php if($total_events > 0): ?>
      <div class="tablenav top" style="padding: 15px 0; border-bottom: 1px solid #ddd; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <form method="post" style="display: inline;">
            <?php wp_nonce_field('run_due_crons', 'run_crons_nonce'); ?>
            <input type="hidden" name="run_due_crons" value="1">
            <button type="submit" class="button button-primary" style="height: 36px;">
              <span class="dashicons dashicons-controls-play" style="vertical-align: middle; margin-top: -2px;"></span>
              Esegui eventi scaduti
            </button>
          </form>
          <form method="post" style="display: inline;">
            <?php wp_nonce_field('clear_all_cron', 'clear_all_nonce'); ?>
            <input type="hidden" name="clear_all_events" value="1">
            <button type="submit" class="button button-secondary" style="height: 36px;"
                   onclick="return confirm('Sei sicuro di voler eliminare tutti gli eventi schedulati del plugin?');">
              <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
              Pulisci tutti
            </button>
          </form>
          <div style="flex: 1;"></div>
          <div class="cron-stats">
            <span class="cron-stat-badge">
              <span class="dashicons dashicons-calendar-alt"></span>
              <strong><?php echo $total_events; ?></strong> eventi totali
            </span>
            <?php if (!$show_all && $shown_events < $total_events): ?>
              <span class="cron-stat-badge">
                <strong><?php echo $shown_events; ?></strong> visualizzati
              </span>
              <a href="?page=price-changer-cron&show_all=1" class="button button-small">Mostra tutti</a>
            <?php endif; ?>
            <?php if ($show_all): ?>
              <a href="?page=price-changer-cron" class="button button-small">Nascondi completati</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <table class="wp-list-table widefat fixed striped wc-cron-table">
        <thead>
          <tr>
            <th >Stato</th>
            <th style="width: 120px;">Tipo Evento</th>
            <th style="width: 150px;">Data e Ora</th>
            <th style="width: 120px;">Operazione</th>
            <th style="width: 100px;">Valore</th>
            <th>Prodotti (IDs)</th>
            <th style="width: 100px;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $now = time(); // Usa UTC per confrontare con i timestamp cron
          foreach($filtered_events as $event):
            $date = new DateTime();
            $date->setTimestamp($event['timestamp']);
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));

            $is_past = $event['timestamp'] <= $now;
            $is_start = $event['hook'] === 'action_change_prices';

            // Determina lo stato, icona e colore
            if($is_past && $is_start){
              $status = 'ATTIVO';
              $status_icon = 'dashicons-yes-alt';
              $status_color = '#f0b849';
              $row_style = '';
            } elseif(!$is_past && $is_start){
              $status = 'IN CODA';
              $status_icon = 'dashicons-clock';
              $status_color = '#46b450';
              $row_style = '';
            } elseif($is_past && !$is_start){
              $status = 'SCADUTO';
              $status_icon = 'dashicons-dismiss';
              $status_color = '#dc3232';
              $row_style = '';
            } else {
              $status = 'PROGRAMMATO';
              $status_icon = 'dashicons-calendar-alt';
              $status_color = '#00a0d2';
              $row_style = '';
            }

            $event_type = $is_start ? 'Inizio cambio' : 'Fine cambio';
            $event_icon = $is_start ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
            $operation_type = isset($event['args'][1]) && $event['args'][1] === 'inc' ? 'Incremento' : 'Decremento';
            $operation_icon = isset($event['args'][1]) && $event['args'][1] === 'inc' ? '📈' : '📉';
            $value_type = isset($event['args'][3]) && $event['args'][3] === 'percentage' ? '%' : '€';
            $value = isset($event['args'][2]) ? $event['args'][2] . ' ' . $value_type : 'N/A';
            $products = isset($event['args'][0]) ? implode(', ', array_slice($event['args'][0], 0, 10)) : 'N/A';
            $products_count = isset($event['args'][0]) ? count($event['args'][0]) : 0;
            if($products_count > 10){
              $products .= ' ... <em>(+' . ($products_count - 10) . ' altri)</em>';
            }
          ?>
          <tr style="<?php echo $row_style; ?>">
            <td>
              <span class="cron-status-badge cron-status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"
                    style="background-color: <?php echo $status_color; ?>;">
                <span class="dashicons <?php echo $status_icon; ?>"></span>
                <?php echo $status; ?>
              </span>
            </td>
            <td>
              <span class="dashicons <?php echo $event_icon; ?>" style="color: <?php echo $status_color; ?>;"></span>
              <?php echo $event_type; ?>
            </td>
            <td>
              <strong><?php echo $date->format('d/m/Y H:i'); ?></strong>
              <?php if($is_past): ?>
                <br><small class="cron-time-info" style="color: #999;">
                  <span class="dashicons dashicons-backup" style="font-size: 13px; width: 13px; height: 13px;"></span>
                  <?php echo human_time_diff($event['timestamp'], $now); ?> fa
                </small>
              <?php else: ?>
                <br><small class="cron-time-info" style="color: #999;">
                  <span class="dashicons dashicons-clock" style="font-size: 13px; width: 13px; height: 13px;"></span>
                  tra <?php echo human_time_diff($now, $event['timestamp']); ?>
                </small>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size: 18px; vertical-align: middle;"><?php echo $operation_icon; ?></span>
              <?php echo $operation_type; ?>
            </td>
            <td>
              <span class="cron-value-badge"><?php echo $value; ?></span>
            </td>
            <td>
              <details>
                <summary style="cursor: pointer; color: #2271b1;">
                  <span class="dashicons dashicons-products" style="font-size: 16px; width: 16px; height: 16px;"></span>
                  <strong><?php echo $products_count; ?></strong> prodott<?php echo $products_count === 1 ? 'o' : 'i'; ?>
                </summary>
                <div style="margin-top: 8px; padding: 8px; background: #f6f7f7; border-radius: 4px; font-size: 11px; font-family: monospace;">
                  <?php echo $products; ?>
                </div>
              </details>
            </td>
            <td>
              <form method="post" style="margin: 0;">
                <?php wp_nonce_field('delete_cron_event', 'delete_nonce'); ?>
                <input type="hidden" name="timestamp" value="<?php echo $event['timestamp']; ?>">
                <input type="hidden" name="hook" value="<?php echo $event['hook']; ?>">
                <input type="hidden" name="delete_event" value="1">
                <button type="submit" class="button button-small button-link-delete"
                        style="color: #d63638;"
                        onclick="return confirm('Eliminare questo evento?');">
                  <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px;"></span>
                  Elimina
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="tablenav bottom" style="padding-top: 15px; border-top: 1px solid #ddd; margin-top: 20px;">
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
          <div style="font-weight: 600; color: #1d2327;">Legenda stati:</div>
          <span class="cron-legend-item">
            <span class="cron-status-badge cron-status-in-coda" style="background-color: #46b450;">
              <span class="dashicons dashicons-clock"></span> IN CODA
            </span>
            <small>Evento futuro</small>
          </span>
          <span class="cron-legend-item">
            <span class="cron-status-badge cron-status-attivo" style="background-color: #f0b849;">
              <span class="dashicons dashicons-yes-alt"></span> ATTIVO
            </span>
            <small>Cambio applicato</small>
          </span>
          <span class="cron-legend-item">
            <span class="cron-status-badge cron-status-programmato" style="background-color: #00a0d2;">
              <span class="dashicons dashicons-calendar-alt"></span> PROGRAMMATO
            </span>
            <small>Fine futura</small>
          </span>
          <span class="cron-legend-item">
            <span class="cron-status-badge cron-status-scaduto" style="background-color: #dc3232;">
              <span class="dashicons dashicons-dismiss"></span> SCADUTO
            </span>
            <small>Da eseguire</small>
          </span>
        </div>
      </div>

    <?php else: ?>
      <div class="wc-cron-empty-state">
        <div class="wc-cron-empty-icon">
          <span class="dashicons dashicons-calendar-alt"></span>
        </div>
        <h2>Nessun evento programmato</h2>
        <p>Al momento non ci sono eventi di cambio prezzo programmati o attivi.</p>
        <p class="wc-cron-empty-hint">
          <span class="dashicons dashicons-info-outline" style="font-size: 16px; width: 16px; height: 16px;"></span>
          Puoi creare nuovi eventi dalla pagina <strong>WC Price Changer</strong>
        </p>
      </div>
    <?php endif; ?>

  </div>

  <style>
  /* Container principale */
  .wc-price-changer-cron {
    max-width: 1400px;
  }

  .wc-price-changer-cron .wp-heading-inline {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
  }

  /* Statistiche */
  .cron-stats {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .cron-stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: #f0f0f1;
    border-radius: 4px;
    font-size: 13px;
    color: #1d2327;
  }

  .cron-stat-badge .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: #50575e;
  }

  /* Tabella eventi */
  .wc-cron-table {
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
  }

  .wc-cron-table thead th {
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    font-weight: 600;
    color: #1d2327;
    padding: 12px 10px;
    text-align: left !important;
  }

  .wc-cron-table tbody tr {
    transition: background-color 0.2s;
  }

  .wc-cron-table tbody tr:hover {
    background-color: #f6f7f7;
  }

  .wc-cron-table td {
    vertical-align: middle !important;
    padding: 12px 10px;
    text-align: left !important;
  }

  /* Badge stato */
  .cron-status-badge {
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    color: white !important;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    max-width: fit-content;
  }

  .cron-status-badge .dashicons {
    font-size: 14px !important;
    width: 14px !important;
    height: 14px !important;
    line-height: 14px !important;
    margin: 0 !important;
  }

  /* Badge valore */
  .cron-value-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #f0f0f1;
    border-radius: 4px;
    font-weight: 600;
    font-size: 13px;
    color: #1d2327;
  }

  /* Info tempo */
  .cron-time-info {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 12px;
  }

  /* Legenda */
  .cron-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .cron-legend-item small {
    color: #646970;
    font-size: 12px;
  }

  /* Details prodotti */
  details summary {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 5px;
  }

  details summary::-webkit-details-marker {
    display: none;
  }

  details summary::before {
    content: '▸';
    display: inline-block;
    margin-right: 5px;
    transition: transform 0.2s;
  }

  details[open] summary::before {
    transform: rotate(90deg);
  }

  details[open] summary {
    margin-bottom: 8px;
  }

  /* Pulsanti */
  .button .dashicons,
  button .dashicons {
    vertical-align: middle !important;
    margin-top: 0 !important;
    line-height: 1 !important;
  }

  .button-link-delete .dashicons {
    vertical-align: middle !important;
    margin-right: 3px;
  }

  /* Empty State */
  .wc-cron-empty-state {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 60px 40px;
    text-align: center;
    margin-top: 30px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
  }

  .wc-cron-empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #f0f0f1 0%, #e5e5e5 100%);
    border-radius: 50%;
    margin-bottom: 24px;
  }

  .wc-cron-empty-icon .dashicons {
    font-size: 40px;
    width: 40px;
    height: 40px;
    color: #50575e;
  }

  .wc-cron-empty-state h2 {
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
    margin: 0 0 12px 0;
  }

  .wc-cron-empty-state p {
    font-size: 14px;
    color: #646970;
    margin: 0 0 8px 0;
    line-height: 1.6;
  }

  .wc-cron-empty-hint {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 24px !important;
    padding: 12px 20px;
    background: #f6f7f7;
    border-radius: 6px;
    font-size: 13px !important;
    color: #1d2327 !important;
  }

  .wc-cron-empty-hint .dashicons {
    color: #2271b1;
  }

  /* Responsive */
  @media screen and (max-width: 782px) {
    .cron-stats {
      flex-direction: column;
      align-items: flex-start;
      width: 100%;
    }

    .tablenav.top > div {
      flex-direction: column;
      align-items: flex-start;
      gap: 15px;
    }

    .wc-cron-table {
      font-size: 12px;
    }

    .cron-status-badge {
      font-size: 10px;
      padding: 3px 8px;
    }

    .wc-cron-empty-state {
      padding: 40px 20px;
    }

    .wc-cron-empty-icon {
      width: 60px;
      height: 60px;
    }

    .wc-cron-empty-icon .dashicons {
      font-size: 30px;
      width: 30px;
      height: 30px;
    }

    .wc-cron-empty-state h2 {
      font-size: 20px;
    }
  }
  </style>
  <?php
}
?>
