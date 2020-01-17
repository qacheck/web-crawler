<?php

class Shopify_Crawler {
	/*
	 source url lấy thông qua $_REQUEST['su'];
	 các sản phẩm lấy được lưu tạm vào bảng options với option_name là 'webcrl_products'
	 */
	public static function crawler() {
		$su = isset($_REQUEST['su']) ? esc_url_raw(untrailingslashit($_REQUEST['su'])) : '';
		
		$return = array(
			'next_crawl' => array(),
			'crawled_url' => '',
			'error' => ''
		);

		$su_parser = parse_url($su);
		$domain_pattern = '/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/';

		if(isset($su_parser['host']) && preg_match($domain_pattern, $su_parser['host']) ) {
			$return['crawled_url'] = $su;
			$products_json = $su.'/products.json';

			$ua = Web_Crawler::get_session('webcrl_ua', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36');
			
			$products = Web_Crawler::get_session( 'webcrl_products', array() );

			$products_json = wp_remote_retrieve_body(wp_remote_get( $su.'/products.json', array( 'user-agent' => $ua ) ));

			$products = json_decode($products_json, true);

			Web_Crawler::set_session( 'webcrl_products', $products );
		}

		//$return['error'] = 'Shopify Crawler';

		return $return;
	}

	public static function view_products() {
		$products = Web_Crawler::get_session('webcrl_products', array());
		print_r($products);
		
	}

}