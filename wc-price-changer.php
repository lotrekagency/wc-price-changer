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
        if(isset($_POST['products']) and isset($_POST['value'])){
          $action_args = array($_POST['products'], $_POST['choice'], (float) $_POST['value'], $_POST['submit-type']);
          if($_POST['datetime-start']){
            wp_schedule_single_event(strtotime($_POST['datetime-start']) - 3600, 'action_change_prices', $action_args);
            if($_POST['datetime-end']){
              wp_schedule_single_event(strtotime($_POST['datetime-end']) - 3600, 'action_remove_prices', $action_args);
            }
            add_action( 'admin_notices', 'action_notice_schedule_change' );
          }
          else{
            do_action('action_change_prices', $_POST['products'], $_POST['choice'], (float)$_POST['value'], $_POST['submit-type']);
            add_action( 'admin_notices', 'action_notice_direct_change' );
          }
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
    if(isset($_POST['viewing'])){
      if($_POST['viewing'] == 'variations'){
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
          return $item->get_name();
        case 'category':
          return implode( wp_get_post_terms( $item->get_id(), 'product_cat', ['fields' => 'names'] ) );
        case 'price':
          return $item->get_regular_price();
        case 'sale_price':
          return $item->get_sale_price() ? $item->get_sale_price() : '-';
        case 'id':
          return $item->get_id();
        default:
            return print_r( $item, true ) ;
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

    submit_button( __( 'Apply' ), 'action', '', false, array( 'id' => "doaction$two" ) );
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
          echo '<option value="products">Solo prodotti</option>';
          echo "\t" . '<option value="variations">Prodotti e variazioni</option>\n';
          echo "</select>\n";

          $categories = get_terms( ['taxonomy' => 'product_cat'] );
          echo '<select name="categories">\n';
          echo '<option value="">Tutte le categorie</option>';
          foreach ( $categories as $category ) {
              echo "\t" . '<option value="' . $category->slug . '">' . $category->name . "</option>\n";
          }
          echo "</select>\n";
          submit_button( 'Filtra', '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
          ?>
          </div>
          <?php
      }
  }

  function process_bulk_action() {
    $action = $this->current_action();
    //setup_price_changer('unit');
    switch ( $action ) {
      case 'price-change-unit':
        setup_price_changer('unit');
        break;
      case 'price-change-percentage':
        setup_price_changer('percentage');
        break;
      default:
        return;
        break;
    }
    return;
  }
}

function setup_page(){
  $myListTable = new ProductList();
  echo '<div class="wrap"><h1>WC Price Changer</h1>';
  $myListTable->prepare_items();
  if(isset($_POST['preview'])){
    setup_price_changer($_POST['submit-type']);
  }
?>
  <form method="post">
    <input type="hidden" name="page" value="ttest_list_table">
<?php
  $myListTable->display();
?>
  </form>
<?php
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
<?php
  $products = $_POST['products'];
  foreach($products as $product){
    echo '<input type="hidden" name="products[]" value=' . $product . '>';
  }

?>
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
      <input type="number" value="<?php echo $_POST['value'];?>" name="value" name="value" step="0.01" min="0.01">
      </td>
    <?php
    } else if($type == 'percentage'){
    ?>
      <td>
      <label for="price">Valore percentuale di modifica (%)</label><br>
      <input type="number" name="value" name="price" min="1" max="100">
      </td>
    <?php
    }
  ?>
    </tr>
    <tr>
      <td>
      <label for="datetime-start">Data e ora di inizio</label><br>
      <input type="datetime-local" name="datetime-start" min="<?php echo date('Y-m-d\TH:i'); ?>"></input>
      </td>
      <td>
      <label for="datetime-end">Data e ora di fine</label><br>
      <input type="datetime-local" name="datetime-end" min="<?php echo date('Y-m-d\TH:i'); ?>"></input>
      </td>
    </tr>

    <?php
      echo '<tr><td><br></td></tr>';
      echo '<tr>';
      echo '<input type="hidden" name="submit-type" value=' . $type . '>';
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
          $products = $_POST['products'];
          foreach ($products as $product){
            $product_retrieved = wc_get_product($product);
            echo '<tr><td>' . $product . '</td>';
            echo '<td>' . $product_retrieved->get_name() . '</td>';
            echo '<td>' . $product_retrieved->get_regular_price() . '</td>';
            if(isset($_POST['preview'])){
              echo '<td>' . calculate_final_price($product_retrieved->get_regular_price(), $_POST['choice'], $_POST['value'], $_POST['submit-type']) . '</td>';
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
    $product_retrieved_price = (float)$product_retrieved->get_regular_price();
    if ( $operation == 'percentage' ){
      $value = ( $product_retrieved_price / 100 ) * $value;
    }
    if ( $choice == 'inc' ){
      $product_retrieved->set_price(sprintf("%.2f",  $product_retrieved_price + $value));
      $product_retrieved->set_regular_price(sprintf("%.2f",  $product_retrieved_price + $value));
    } else {
      $product_retrieved->set_sale_price(sprintf("%.2f",  $product_retrieved_price - $value));
    }
    $product_retrieved->save();
  }
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

?>
