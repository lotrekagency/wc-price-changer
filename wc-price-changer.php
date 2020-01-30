<?php
/**
 * Plugin Name:       WC Price Changer
 * Description:       Manage your products prices smartly.
 * Version:           0.0.1
 * Author:            Lotrèk
 * Author URI:        https://lotrek.it/
 */

init_plugin();

function init_plugin(){
  add_action('admin_menu', 'setup_menu');
  add_action('apply_price_changes', 'apply');
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
        if(isset($_POST['products']) and isset($_POST['price-input'])){
          change_prices($_POST['products'], $_POST['price-input']);
        }
      }
  }
}

class ProductList extends WP_List_Table {

  var $products = array();

  function __construct(){
    $this->products = wc_get_products(array('status' => 'publish'));
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
        'name' => __( 'Name', '' ),
        'price' => __( 'Price', '' ),
        'id' => __('ID', '')
    );
     return $columns;
  }

  function column_default( $item, $column_name ) {
    switch( $column_name ) {
        case 'name':
          return $item->get_title();
        case 'price':
          return $item->get_price();
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
      'price-change'    => 'Change price of selected products'
    );
    return $actions;
  }

  function process_bulk_action() {
    $action = $this->current_action();
    switch ( $action ) {
      case 'price-change':
        setup_price_changer();
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

function setup_price_changer(){
?>
<style>
.form-price-changer{
  display: inline-block;
  vertical-align: top;
}
</style>
<div class="wrap form-price-changer">
  <form method="post">
    <label for="price-input">Insert price:</label>
    <?php
      $products = $_POST['products'];
      foreach($products as $product){
        echo '<input type="hidden" name="products[]" value=' . $product . '>';
      }
    ?>
    <input type="text" name="price-input" id="price-input"></input>
    <?php submit_button('Apply');?>
  </form>
</div>
<?php
}

function change_prices($ids, $price){
  foreach ( $ids as $product ){
    $product_retrieved = wc_get_product($product);
    $product_retrieved->set_price($price);
    $product_retrieved->set_regular_price($price);
    $product_retrieved->save();
  }
}
?>
