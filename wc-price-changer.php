<?php
/**
 * Plugin Name:       WC Price Changer
 * Description:       Manage your products prices smartly.
 * Version:           0.0.1
 * Author:            Lotrèk
 * Author URI:        https://lotrek.it/
 */
define('ALTERNATE_WP_CRON', true);
init_plugin();

function init_plugin(){
  session_start();
  add_action('admin_menu', 'setup_menu');
  add_action('apply_price_changes', 'apply');
  add_action('action_change_prices', 'change_prices', 10, 4);
  add_action('action_remove_prices', 'remove_prices', 10, 4);
  if (!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
  }
}

function setup_menu(){
  if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
    if (isset($_POST['viewing'])){
      $_SESSION['viewing'] = $_POST['viewing'];
    }
    add_submenu_page(
      'woocommerce',
      'Price Changer',
      'WC Price Changer',
      'manage_options',
      'price-changer',
      'setup_page'
    );
    if(isset($_POST['submit']))
    {
      $products = $_SESSION['products'];
      if ( isset($_POST['only-variations']) ){
        $variations = array();
        foreach($products as $product){
          array_push($variations, $product);
          if ($product instanceof WC_Product_Variable){
            foreach($product->get_available_variations() as $product_variation){
              array_push($variations, wc_get_product($product_variation['variation_id']));
            }
          }
        }
        $products = $variations;
      }
      $action_args = array($products, $_POST['choice'], (float) $_POST['value'], $_SESSION['submit-type']);
      if($_POST['datetime-start']){
        $datetime_start = new DateTime($_POST['datetime-start'], new DateTimeZone(get_option('timezone_string')));
        wp_schedule_single_event($datetime_start->format('U'), 'action_change_prices', $action_args);
        if($_POST['datetime-end']){
          $datetime_end = new DateTime($_POST['datetime-end'], new DateTimeZone(get_option('timezone_string')));
          wp_schedule_single_event($datetime_end->format('U'), 'action_remove_prices', $action_args);
        }
        add_action( 'admin_notices', 'action_notice_schedule_change' );
      }
      else{
        do_action('action_change_prices', $products, $_POST['choice'], (float)$_POST['value'], $_SESSION['submit-type']);
        add_action( 'admin_notices', 'action_notice_direct_change' );
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

  function __construct(){
    $selected_categories = '';
    if(isset($_POST['categories'])){
      $selected_categories = $_POST['categories'];
    }
    $this->products = wc_get_products(array('status' => 'publish', 'category' => $selected_categories));
    if($_SESSION['viewing'] == 'variations'){
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
    switch( $column_name ) {
      case 'name':
        return (($_SESSION['viewing'] == 'variations' and !$item->is_type('variation')) ? ('<strong>' . $item->get_name() . '</strong>') : $item->get_name());
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
      ?>
      <div class="alignright actions bulkactions">
      <?php
      echo '<select name="viewing">\n';
      echo '<option value="products" ' . (($_SESSION['viewing'] == 'products') ? 'selected' : '') .'>Solo prodotti</option>';
      echo "\t" . '<option value="variations" ' . (($_SESSION['viewing'] == 'variations') ? 'selected' : '') . '>Prodotti e variazioni</option>\n';
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
          echo '<th scope="row" class="check-column">';
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
      $_SESSION['products'] = $_POST['products'];
      switch ( $action ) {
        case 'price-change-unit':
          $_SESSION['submit-type'] = 'unit';
          setup_price_changer('unit');
          break;
        case 'price-change-percentage':
          $_SESSION['submit-type'] = 'percentage';
          setup_price_changer('percentage');
          break;
        default:
          return;
          break;
      }
    }
    return;
  }
}

function setup_page(){
  $myListTable = new ProductList();
  echo '<div class="wrap"><h1>WC Price Changer</h1>';
  check_active_jobs();
  $myListTable->prepare_items();
  if(isset($_POST['preview'])){
    setup_price_changer($_SESSION['submit-type']);
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
      <input type="radio" name="choice" value="dec" <? if($_POST['choice'] == 'dec' or !isset($_POST['choice'])){ echo 'checked'; } ?>>Decremento</input>
      </td>
      <td>
      <br><input type="radio" name="choice" value="inc" <? if($_POST['choice'] == 'inc'){ echo 'checked'; } ?>>Incremento</input>
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
      <?php
      }
    ?>
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
        if($_SESSION['viewing'] == 'variations'){
          echo '<tr><td><br></td></tr>';
          echo '<tr><td>';
          echo '<input type="checkbox" name="only-variations" checked>';
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
            $products = $_SESSION['products'];
            foreach ($products as $product){
              $product_retrieved = wc_get_product($product);
              if ( (isset( $_POST['viewing'] ) and $_POST['viewing'] == 'variations') or (isset( $_POST['only-variations']) and $_POST['only-variations'] == 'on') ){
                if ( !$product_retrieved->is_type('variation') ) {
                  continue;
                }
              }
              echo '<tr><td>' . $product . '</td>';
              echo '<td>' . $product_retrieved->get_name() . '</td>';
              echo '<td>' . $product_retrieved->get_regular_price() . '</td>';
              if(isset($_POST['preview'])){
                echo '<td>' . calculate_final_price((float)$product_retrieved->get_regular_price(), $_POST['choice'], $_POST['value'], $_SESSION['submit-type']) . '</td>';
              }
              echo '</tr>';
            }
          ?>
          </tbody>
        </table>
      </td>
      </tr>
      </table>
    </form>
  </div>
  <hr>
  <?php
}

function change_prices($ids, $choice, $value, $operation){
  foreach ( $ids as $product ){
    $product_retrieved = wc_get_product($product);
    set_prices($product_retrieved, calculate_final_price((float)$product_retrieved->get_regular_price(), $choice, $value, $operation), $choice);
  }
}

function set_prices($product, $new_price, $choice){
  if ( $choice == 'inc' ){
    $product->set_price($new_price);
    $product->set_regular_price($new_price);
  } else {
    $product->set_sale_price($new_price);
  }
  $product->save();
}

function remove_prices($ids, $choice, $value, $operation){
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

function notice_queue_jobs() {
  ?>
  <div class="notice notice-success">
      <p><?php _e( 'Ci sono eventi di cambio prezzi in coda.', '' ); ?></p>
  </div>
  <?php
}

function notice_active_jobs() {
  ?>
  <div class="notice notice-warning">
      <p><?php _e( 'Ci sono cambi di prezzo attivi.', '' ); ?></p>
  </div>
  <?php
}

function check_active_jobs() {
  $jobs = get_option( 'cron' );
  $queue_jobs = array();
  $active_jobs = array();
  foreach($jobs as $job){
    if ( is_array($job) and array_key_exists( 'action_change_prices', $job) ){
      array_push($queue_jobs, $job['action_change_prices']);
    }
    if ( is_array($job) and array_key_exists( 'action_remove_prices', $job) ){
      array_push($active_jobs, $job['action_remove_prices']);
    }
  }
  if ( $queue_jobs ) {
    notice_queue_jobs();
  }
  if ( $active_jobs ) {
    notice_active_jobs();
  }
}
?>
