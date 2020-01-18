<?php
/*
Plugin Name: Web Crawler
Plugin URI: https://sps.vn
Description: Scan and collect information from any url. Customize the information fields you need in a very flexible way.
Author: spsdev
Author URI: http://dev.sps.vn
Version: 1.1
Text Domain: web-crawler
*/
if (!defined('ABSPATH')) exit;

define('WEBCRL_PLUGIN_FILE', __FILE__);
define('WEBCRL_URL', untrailingslashit(plugins_url( '', WEBCRL_PLUGIN_FILE)));
define('WEBCRL_PATH', dirname(WEBCRL_PLUGIN_FILE));
define('WEBCRL_BASE', plugin_basename(WEBCRL_PLUGIN_FILE));

class Web_Crawler {

	public static $admin_slug = 'web-crawler';

	public $source_types = array();

	public function __construct() {
		register_activation_hook( WEBCRL_PLUGIN_FILE, array( $this, 'activate' ) );
		/*
		Khởi tạo các biến nếu cần thiết
		 */
		$default = array(
			'woocommerce' => array(
				'title' => 'Woocommerce',
				'crawler' => 'Woocommerce_Crawler'
			),
			'shopify' => array(
				'title' => 'Shopify',
				'crawler' => 'Shopify_Crawler'
			)
		);

		$this->source_types = apply_filters('webcrl_crawler_source_types', $default);

		add_action('plugins_loaded', array($this, 'session_start') );
		
		$this->include();
		
		/*
		 Gọi các hook
		 */
		$this->hooks();
	}

	public function session_start() {
		session_start();
	}

	public function hooks() {

		add_action('admin_menu', array($this, 'admin_settings_menu'), 50);
		
		add_action('wp_ajax_webcrl_view_crawled', array($this, 'webcrl_view_crawled'));

		add_action('wp_ajax_webcrl_scan', array($this, 'webcrl_scan'));
		//add_action('wp_ajax_nopriv_webcrl_scan', array($this, 'webcrl_scan'));

		add_action('wp_ajax_webcrl_remove_crawled', array($this, 'webcrl_remove_crawled'));
		//add_action('wp_ajax_nopriv_webcrl_remove_crawled', array($this, 'webcrl_remove_crawled'));

		add_filter( 'init', array( $this, 'init' ) );

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts') );

		// add_action( 'template_include', array($this, 'crawler_page') );
		add_filter( 'manage_product_posts_columns', array( $this, 'product_columns_header' ), 10, 1 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'product_columns_value' ), 10, 2 );
		
	}

	public function product_columns_value( $column, $post_id ) {
		switch ($column) {
			case 'thumbnail':
				echo '<div class="post-thumbnail">'.get_the_post_thumbnail( $post_id, 'thumbnail', array('style'=>'width:80px;height:auto;') ).'</div>';
				break;
			
			case 'variant':
				echo get_post_meta($post_id, '_variant', true);
				break;

			case 'sku':
				echo esc_html(get_post_meta($post_id, '_sku', true));
				break;

			case 'currency':
				echo esc_html(get_post_meta($post_id, '_currency_symbol', true));
				break;

			case 'regular_price':
				echo esc_html(get_post_meta($post_id, '_regular_price', true));
				break;

			case 'sale_price':
				echo esc_html(get_post_meta($post_id, '_sale_price', true));
				break;

			case 'weight':
				echo esc_html(get_post_meta($post_id, '_weight', true));
				break;

			case 'weight':
				echo esc_html(get_post_meta($post_id, '_dimensions', true));
				break;
		}
	}

	public function product_columns_header($columns) {
		$new_columns = array();

		$cb = $columns['cb'];
		$date = $columns['date'];
		unset($columns['cb']);
		unset($columns['date']);
		$new_columns = array(
			'cb' => $cb,
			'thumbnail' => __('Thumbnail')
		);

		$columns = array_merge($new_columns, $columns);

		$columns['variant'] = __('Variant');
		$columns['sku'] = __('SKU');
		$columns['currency'] = __('Currency');
		$columns['regular_price'] = __('Regular price');
		$columns['sale_price'] = __('Sale price');
		$columns['weight'] = __('Weight');
		$columns['dimensions'] = __('Dimensions');
		$columns['date'] = $date;

		return $columns;
	}

	public function webcrl_view_crawled() {
		if(!current_user_can('manage_options')) return;
		$st = self::get_session('webcrl_st', '');
		if($st!='' && isset($this->source_types[$st])) {
			$this->source_types[$st]['crawler']::view_products();
		} else {
			echo 'Source type error!';
		}
		die;
	}

	public function include() {
		require_once WEBCRL_PATH . '/simple_html_dom.php';
		require_once WEBCRL_PATH . '/class-woocommerce-crawler.php';
		require_once WEBCRL_PATH . '/class-shopify-crawler.php';
		require_once WEBCRL_PATH . '/arraytocsv.php';
	}

	public function activate() {
		set_transient( 'webcrl_flush', 1, 60 );
		//flush_rewrite_rules();
	}

	public function enqueue_scripts() {
		wp_enqueue_style('webcrl', WEBCRL_URL . '/style.css');
		wp_enqueue_script('webcrl', WEBCRL_URL . '/script.js', array('jquery'), '', true);
		$webcrl = array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('webcrl')
		);
		wp_localize_script('webcrl', 'webcrl', $webcrl);
	}

	public function admin_settings_menu() {
		add_menu_page('Web crawler', 'Web crawler', 'manage_options', self::$admin_slug, array($this, 'crawler_page'), 'dashicons-share-alt', 63);
	}

	public static function get_session($name, $default='') {
		$data = isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
		return $data;
	}

	public static function set_session($name, $data) {
		$_SESSION[$name] = $data;
	}

	public static function debug($var, $pre=true) {
		$debug = print_r($var,true);
		echo ($pre)?'<pre>'.$debug.'</pre>':$debug;
	}

	public static function post_exists( $post_name='', $type = '' ) {
		global $wpdb;

		$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
		$args = array();

		if ( !empty ( $post_type ) ) {
			$query .= ' AND post_type=%s';
			$args[] = $post_type;
		}

		if ( !empty ( $post_name ) ) {
			$query .= ' AND post_name=%s';
			$args[] = $post_name;
		}

		if ( !empty ( $args ) ) {
			$sql = $wpdb->prepare($query, $args);
			return (int) $wpdb->get_var($sql);
		}

		return 0;
	}

	public static function upload_attachment($url='', $post_id=0) {
		$attachmentId = false;
		if( !empty( $url )  ) {

			if ( !function_exists('media_handle_upload') ) {
			    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
			    require_once(ABSPATH . "wp-admin" . '/includes/file.php');
			    require_once(ABSPATH . "wp-admin" . '/includes/media.php');
			}

			$file = array();
			$file['name'] = 'attachment-'.wp_basename(urldecode($url));
			$file['tmp_name'] = download_url($url);
			
			$attach_name = sanitize_title(preg_replace('/\.[^.]+$/', '', sanitize_file_name($file['name'])));
			
			$attachmentId = self::post_exists( $attach_name, 'attachment' );
			//error_log($attachmentId.' | '.$attach_name);
			if(!$attachmentId) {
				if (is_wp_error($file['tmp_name'])) {
				    @unlink($file['tmp_name']);
				    //var_dump( $file['tmp_name']->get_error_messages( ) );
				} else {
				    $attachmentId = media_handle_sideload($file, $post_id);

				    if ( is_wp_error($attachmentId) ) {
				        @unlink($file['tmp_name']);
				        return 0;
				    }
				}
			}
			
			
		}
		return absint($attachmentId);
	}

	public function crawler_page() {
		if(!current_user_can('manage_options')) return;

		$su = self::get_session( 'webcrl_su', '' );
		$st = self::get_session( 'webcrl_st', '' );
		?>
		<div class="wrap">
			<h2><?php _e('Connections'); ?></h2>
			<div class="postbox">
				<div class="inside">
					<?php
					//echo esc_html(wp_remote_retrieve_body(wp_remote_get('https://themes.woocommerce.com/storefront/product/woo-single-1/', array('user-agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36'))));
					?>
					<div id="web-crawler-page">
						<div style="display:flex;align-items:stretch;">
							<div style="padding-right:16px;">
								<label>Source Type:<br>
									<select id="webcrl_source_type">
										<?php
										foreach ($this->source_types as $key => $value) {
											echo '<option value="'.esc_attr($key).'" '.selected($st,$key,false).'>'.esc_html($value['title']).'</option>';
										}
										?>
									</select>
								</label>
							</div>
							<div style="flex-grow:1;">
								<label>Source URL:<br>
								<input type="text" id="webcrl_su" name="webcrl_su" value="<?=esc_url($su)?>" class="widefat">
								<input type="hidden" id="webcrl_ua" name="webcrl_ua" value="<?=esc_attr($_SERVER['HTTP_USER_AGENT'])?>">
								</label>
								<p><button type="button" id="webcrl_do" class="webcrl-button button">Get products</button> <button type="button" id="webcrl_stop" class="webcrl-button button" disabled="disabled">Stop</button> <button type="button" id="webcrl_save" class="webcrl-button button">Save</button></p>

								<div id="webcrl-message"></div>
								<table id="webcrl-results">
									
								</table>
							</div>
						</div>
						<div id="webcrl-view-products"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function init() {
		register_post_type(
			'product',
			array(
				'labels'              => array(
					'name'                  => __( 'Products', 'web-crawler' ),
					'singular_name'         => __( 'Product', 'web-crawler' ),
					'all_items'             => __( 'All Products', 'web-crawler' ),
					'menu_name'             => _x( 'Products', 'Admin menu name', 'web-crawler' ),
					'add_new'               => __( 'Add New', 'web-crawler' ),
					'add_new_item'          => __( 'Add new product', 'web-crawler' ),
					'edit'                  => __( 'Edit', 'web-crawler' ),
					'edit_item'             => __( 'Edit product', 'web-crawler' ),
					'new_item'              => __( 'New product', 'web-crawler' ),
					'view_item'             => __( 'View product', 'web-crawler' ),
					'view_items'            => __( 'View Products', 'web-crawler' ),
					'search_items'          => __( 'Search Products', 'web-crawler' ),
					'not_found'             => __( 'No Products found', 'web-crawler' ),
					'not_found_in_trash'    => __( 'No Products found in trash', 'web-crawler' ),
					'parent'                => __( 'Parent product', 'web-crawler' ),
					'featured_image'        => __( 'product image', 'web-crawler' ),
					'set_featured_image'    => __( 'Set product image', 'web-crawler' ),
					'remove_featured_image' => __( 'Remove product image', 'web-crawler' ),
					'use_featured_image'    => __( 'Use as product image', 'web-crawler' ),
					'insert_into_item'      => __( 'Insert into product', 'web-crawler' ),
					'uploaded_to_this_item' => __( 'Uploaded to this product', 'web-crawler' ),
					'filter_items_list'     => __( 'Filter Products', 'web-crawler' ),
					'items_list_navigation' => __( 'Products navigation', 'web-crawler' ),
					'items_list'            => __( 'Products list', 'web-crawler' ),
				),
				'public'              => true,
				'show_ui'             => true,
				//'capability_type'     => 'product',
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => false,
				'hierarchical'        => false, // Hierarchical causes memory issues - WP loads all records!
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),
				'has_archive'         => false,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => false,
			)
		);

		if(get_transient( 'webcrl_flush' )) {
			flush_rewrite_rules();
			delete_transient('webcrl_flush');
		}
	}

	public function webcrl_remove_crawled() {
		if(!current_user_can('manage_options')) return;

		self::set_session( 'webcrl_crawled', array() );
		self::set_session( 'webcrl_products', array() );
		$ua = isset($_REQUEST['ua']) ? $_REQUEST['ua'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36';
		self::set_session( 'webcrl_ua', $ua );

		$su = isset($_REQUEST['su']) ? esc_url_raw(untrailingslashit($_REQUEST['su'])) : '';
		self::set_session( 'webcrl_su', $su );

		$st = isset($_REQUEST['st']) ? sanitize_key($_REQUEST['st']) : '';
		self::set_session( 'webcrl_st', $st );
		die;
	}

	public function webcrl_scan() {
		if(!current_user_can('manage_options')) return;
		// source url
		$st = isset($_REQUEST['st']) ? sanitize_key($_REQUEST['st']) : '';

		$response = array(
			'next_crawl' => array(),
			'crawled_url' => '',
			'error' => ''
		);

		if($st!='' && isset($this->source_types[$st])) {
			$response = $this->source_types[$st]['crawler']::crawler();
		} else {
			$response['error'] = 'Source type error!';
		}

		wp_send_json($response);

		die;
	}

}
new Web_Crawler;