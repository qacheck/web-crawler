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
		//Web_Crawler::debug($products);
		if(!empty($products['products'])) {
			?>
			<table class="webcrl-view-products">
				<tr>
					<th>Thumbnail</th>
					<th>Slug</th>
					<th>Title</th>
					<th>Type</th>
					<th>Variant</th>
					<th>SKU</th>
					<th>Price unit</th>
					<th>Regular price</th>
					<th>Sale price</th>
					<th>Weight</th>
					<th>Dimensions</th>
					<th>Gallery</th>
				</tr>
				<?php
				foreach ($products['products'] as $key => $product) {
					$rowspan = count($product['variants']);
					foreach($product['variants'] as $index => $variant) {

						?>
						<tr>
							<td>
								<?php
								if( isset($product['images']) && !empty($product['images']) ) {
									?>
									<img src="<?=esc_url($product['images'][0]['src'])?>" width="80">
									<?php
								}
								?>
							</td>
							<td style="white-space:nowrap;"><?=esc_html($product['handle'])?></td>
							<td style="white-space:nowrap;"><?php
							if($index==0) {
								echo '<p>'.esc_html($product['title']).'</p>';
								echo '<p>'.esc_html($variant['title']).'</p>';
							} else {
								echo esc_html($variant['title']);
							}
							?></td>
							<td><?=esc_html($product['product_type'])?></td>
							<td><?php
							foreach ($product['options'] as $option) {
								?>
								<div style="white-space:nowrap;">
									<span><?=esc_html($option['name'])?>:</span>
									<span><?=esc_html($variant['option'.$option['position']])?></span>
								</div>
								<?php
							}
							?></td>
							<td><?=esc_html($variant['sku'])?></td>
							<td><?=esc_html('$')?></td>
							<td><?=esc_html($variant['compare_at_price'])?></td>
							<td><?=esc_html($variant['price'])?></td>
							<td><?=esc_html($variant['grams'])?>g</td>
							<td></td>
							<?php
							if($index==0) {
								echo '<td rowspan="'.$rowspan.'">';
								if(!empty($product['images'])) {
									foreach ($product['images'] as $img_i => $img) {
										$src = explode('?', $img['src']);
										if(in_array($variant['id'], $img['variant_ids'])) {
											echo '<p><a href="'.esc_url($src[0]).'" target="_blank">'.esc_url($src[0]).'</a></p>';
										} else {
											echo '<p><a href="'.esc_url($src[0]).'" target="_blank">'.esc_url($src[0]).'</a></p>';
										}
									}
								}
								echo '</td>';
							}
							?>
						</tr>
						<?php
					}
				}
				?>
			</table>
			<?php
		}
	}

}