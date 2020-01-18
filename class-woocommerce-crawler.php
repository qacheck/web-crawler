<?php

class Woocommerce_Crawler {
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

		if( isset($su_parser['host']) && preg_match($domain_pattern, $su_parser['host']) ) {
			
			$ua = Web_Crawler::get_session('webcrl_ua', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36');
			// lấy danh sách url đã quét
			$crawled = Web_Crawler::get_session( 'webcrl_crawled', array() );
			$products = Web_Crawler::get_session( 'webcrl_products', array() );

			// đưa url vào đanh sách đã quét
			$crawled[] = $su;

			// khởi tạo danh sách quét mới
			$next_crawl = array();

			$source = wp_remote_retrieve_body(wp_remote_get( $su, array( 'user-agent' => $ua ) ));
			$html = str_get_html($source);

			$return['crawled_url'] = $su;

			if( $html instanceof simple_html_dom ) {

				// Xử lý lấy thông tin trên trang đang quét
				$html_product_page = $html->find('body.woocommerce.single-product',0);
				if( $html_product_page ) {
					$html_product = $html_product_page->find('.product.type-product',0);
					if( $html_product && !$html_product->hasClass('product-type-grouped') ) {
						$product = array();
						$paths = explode('/', $su);
						
						$product['slug'] = end($paths);
						
						$html_title = $html_product->find('.product_title.entry-title',0);
						
						$product['title'] = ($html_title)?trim($html_title->plaintext):'';

						$product['type'] = '';

						if( $html_product->hasClass('product-type-variable') ) {
							$product['type'] = 'variable';
						} else if( $html_product->hasClass('product-type-simple') ) {
							$product['type'] = 'simple';
						} else if( $html_product->hasClass('product-type-external') ) {
							$product['type'] = 'external';
						}

						// else if( $html_product->hasClass('product-type-grouped') ) {
						// 	$product['type'] = 'grouped';
						// }

						$html_price = $html_product->find('p.price',0);

						$product['regular_price'] = null;
						$product['sale_price'] = null;

						$product['attributes'] = array();
						$product['variants'] = array();
						$product['gallery'] = array();

						$product['sku'] = '';
						$html_sku = $html_product->find('sku',0);
						if($html_sku) {
							$product['sku'] = html_entity_decode(trim($html_sku->plaintext));
						}

						$product['currency_symbol'] = '';
						if($html_price) {
							$currency_symbol = $html_price->find('.woocommerce-Price-currencySymbol',0);

							$product['currency_symbol'] = ($currency_symbol)?html_entity_decode(trim($currency_symbol->plaintext)):'';
						}

						foreach ($html_product->find('.woocommerce-product-gallery__image') as $key => $value) {
							$html_image = $value->find('a',0);
							if($html_image) {
								//$src = explode('?', esc_url_raw($html_image->src));
								$product['gallery'][] = esc_url($html_image->href);
							}
						}

						switch ($product['type']) {
							case 'simple':
							case 'external':
								if( $html_product->hasClass('sale') ) {
									$product['sale'] = true;
									if($html_price) {
										$html_regular_price = $html_price->find('del .woocommerce-Price-amount.amount',0);
										if($html_regular_price) {
											$product['regular_price'] = floatval(preg_replace('/[^\d\.]/','',$html_regular_price->plaintext));
										}

										$html_sale_price = $html_price->find('ins .woocommerce-Price-amount.amount',0);
										if($html_sale_price) {
											$product['sale_price'] = floatval(preg_replace('/[^\d\.]/','',$html_sale_price->plaintext));
										}
									}
									
								} else {
									$product['sale'] = false;
									$html_regular_price = $html_price->find('.woocommerce-Price-amount.amount',0);
									if($html_regular_price) {
										$product['regular_price'] = floatval(preg_replace('/[^\d\.]/','',$html_regular_price->plaintext));
									}
								}

								break;

							case 'variable':
								if($html_price) {
									$prices = array();
									foreach ($html_price->find('.woocommerce-Price-amount.amount') as $key => $value) {
										$prices[] = floatval(preg_replace('/[^\d\.]/','',$value->plaintext));
									}
									$product['regular_price'] = implode(' - ', $prices);
								}

								$html_variable_form = $html_product->find('form[data-product_variations]',0);
								if($html_variable_form) {
									$variable_data = htmlspecialchars_decode($html_variable_form->getAttribute('data-product_variations'));
									//error_log($variable_data);
									$product['variants'] = json_decode($variable_data,true);
								}

								$html_variable_table = $html_product->find('table.variations',0);
								if($html_variable_table) {
									$attributes = array();
									foreach ($html_variable_table->find('select[data-attribute_name]') as $key => $value) {
										$attr = str_replace('attribute_pa_','',$value->getAttribute('data-attribute_name'));
										foreach ($value->find('option') as $k => $v) {
											$attr_value = htmlspecialchars_decode(trim($v->getAttribute('value')));
											if($attr_value!='') {
												$attributes[$attr][] = $attr_value;
											}
										}
									}
									$product['attributes'] = $attributes;
								}
								
								break;
						}
						
						$html_excerpt = $html_product->find('.woocommerce-product-details__short-description',0);

						$product['excerpt'] = ($html_excerpt)?strip_tags(trim($html_excerpt->innertext())):'';

						$html_content = $html_product->find('#tab-description',0);
						$product['content'] = ($html_content)?preg_replace('/\s(id|class)=\"[^\"]+\"/','',trim($html_content->innertext())):'';

						$html_information = $html_product->find('#tab-additional_information',0);
						$product['information'] = ($html_information)?preg_replace('/\s(id|class)=\"[^\"]+\"/','',trim($html_information->innertext())):'';

						$product['weight'] = 'N/A';
						$html_weight = $html_product->find('.woocommerce-product-attributes-item--weight',0);
						if($html_weight) {
							$product['weight'] = html_entity_decode(trim($html_weight->find('.woocommerce-product-attributes-item__value',0)->plaintext));
						}

						$product['dimensions'] = 'N/A';
						$html_weight = $html_product->find('.woocommerce-product-attributes-item--dimensions',0);
						if($html_weight) {
							$product['dimensions'] = html_entity_decode(trim($html_weight->find('.woocommerce-product-attributes-item__value',0)->plaintext));
						}

						$products[] = $product;

						Web_Crawler::set_session( 'webcrl_products', $products );
					}
				}

				// quét tất cả các url trên trang đang quét để có thể đưa vào danh sách quét mới
				foreach( $html->find('a') as $element ) {
					if($element->href!='') {
						$href = untrailingslashit($element->href);

						$href_parser = parse_url($href);

						$get_href = '';

						if( isset($href_parser['host']) && !preg_match('/.+wp-content\/uploads.+/', $href)) {
							if($href_parser['host']==$su_parser['host']) {
								$get_href = esc_url_raw($href);
							}
						}

						// else {
						// 	if( isset($href_parser['scheme']) && $href_parser['scheme']!='http' && $href_parser['scheme']!='https' ){
						// 		$get_href = '';
						// 	} else {
						// 		if ( preg_match('/^\/[^\/]+.*/', $href) ) {
						// 			$get_href = $su_parser['host'].'/'.ltrim($href,'/');
						// 		} else if ( preg_match('/^\/\/[^\/]+.*/', $href) ) {
						// 			$get_href = 'http:'.$href;
						// 		} else if ( preg_match('/[^\:]*\:[^\:]*/', $href) ) {
						// 			$get_href = '';
						// 		} else if (  preg_match('/^\#/', $href)  ) {
						// 			$get_href = '';
						// 		} else {
						// 			$get_href = $su.'/'.ltrim($href,'/');
						// 		}
						// 	}
						
						// }

						if( $get_href != '' ) {
							$temp = explode('?', $get_href);
							$temp = explode('#', $temp[0]);
							$get_href = esc_url_raw( untrailingslashit($temp[0]) );
						}


						if( $get_href != '' ) {

							if( !in_array($get_href, $crawled) ) {
								$next_crawl[] = $get_href;
							}
						}

					}
				}

			} else {

				$return['error'] = 'Nguồn không lấy được!';
			}
			
			Web_Crawler::set_session('webcrl_crawled', $crawled);
			$return['next_crawl'] = array_values(array_unique($next_crawl));

		} else {
			$return['error'] = 'Lỗi URL nguồn!';
		}

		return $return;
	}

	public static function view_products() {
		$products = Web_Crawler::get_session('webcrl_products', array());
		if(!empty($products)) {
			//Web_Crawler::debug($products);
			$su = Web_Crawler::get_session('webcrl_su', '');
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
				foreach ($products as $key => $product) {
					if( $product['type']=='simple' || $product['type']=='external' || ($product['type']=='variable' && empty($product['variants'])) ) {

						$product_id = Web_Crawler::post_exists($product['slug'],'product');

						$post_data = array(
							'post_title' => $product['title'],
							'post_type' => 'product',
							'post_name' => $product['slug'],
							'post_status' => 'publish',
							'post_content' => $product['content'],
							'post_excerpt' => $product['excerpt'],
						);

						if($product_id) {
							$post_data['ID'] = $product_id;
						}

						$product_id = wp_insert_post($post_data);

						if($product_id) {
							if(!empty($product['gallery'])) {
								$thumbnail_id = Web_Crawler::upload_attachment($product['gallery'][0], 0);
								
								set_post_thumbnail( $product_id, $thumbnail_id );
								update_post_meta( $product_id, '_gallery', $product['gallery'] );
							}

							update_post_meta( $product_id, '_variant', '' );
							update_post_meta( $product_id, '_sku', $product['sku'] );
							update_post_meta( $product_id, '_currency_symbol', $product['currency_symbol'] );
							update_post_meta( $product_id, '_regular_price', $product['regular_price'] );
							update_post_meta( $product_id, '_sale_price', $product['sale_price'] );
							update_post_meta( $product_id, '_weight', $product['weight'] );
							update_post_meta( $product_id, '_dimensions', $product['dimensions'] );
						}

						?>
						<tr>
							<td>
								<?php
								if(!empty($product['gallery'])) {
									?>
									<img src="<?=esc_url($product['gallery'][0])?>" width="80">
									<?php
								}
								?>
							</td>
							<td><?=esc_html($product['slug'])?></td>
							<td><?=esc_html($product['title'])?></td>
							<td><?=esc_html($product['type'])?></td>
							<td></td>
							<td><?=esc_html($product['sku'])?></td>
							<td><?=esc_html($product['currency_symbol'])?></td>
							<td><?=esc_html($product['regular_price'])?></td>
							<td><?=esc_html($product['sale_price'])?></td>
							<td><?=esc_html($product['weight'])?></td>
							<td><?=esc_html($product['dimensions'])?></td>
							<td><?php
							if(!empty($product['gallery'])) {
								foreach ($product['gallery'] as $index => $src) {
									echo '<p><a href="'.esc_url($src).'" target="_blank">'.esc_url($src).'</a></p>';
								}
							}
							?></td>
						</tr>
						<?php
					} else if( $product['type']=='variable' ) {
						foreach($product['variants'] as $vi => $variant) {
							$product_slug = $product['slug'].'-'.$variant['variation_id'];
							
							$product_id = Web_Crawler::post_exists($product_slug,'product');

							$post_data = array(
								'post_title' => $product['title'],
								'post_type' => 'product',
								'post_name' => $product_slug,
								'post_status' => 'publish',
								'post_content' => $product['content'],
								'post_excerpt' => $product['excerpt'],
							);

							if($product_id) {
								$post_data['ID'] = $product_id;
							}

							$product_id = wp_insert_post($post_data);

							if($product_id) {
								if( isset($variant['image']) && !empty($variant['image']) ) {
									$thumbnail_id = Web_Crawler::upload_attachment($variant['image']['url'], 0);
									set_post_thumbnail( $product_id, $thumbnail_id );
								} else {
									if(!empty($product['gallery'])) {
										$thumbnail_id = Web_Crawler::upload_attachment($product['gallery'][0], 0);
										set_post_thumbnail( $product_id, $thumbnail_id );
									}
								}

								if(!empty($product['gallery'])) {
									update_post_meta( $product_id, '_gallery', $product['gallery'] );
								}

								$_variant = '';
								if(!empty($variant['attributes'])) {
									foreach ($variant['attributes'] as $key => $value) {
										$attr = str_replace('attribute_pa_','',$key);
										ob_start();
										?>
										<p>
											<span><?=esc_html($attr)?>: </span>
											<span><?=esc_html($value)?></span>
										</p>
										<?php
										$_variant .= ob_get_clean();
									}
								}

								update_post_meta( $product_id, '_variant', $_variant );
								update_post_meta( $product_id, '_sku', $variant['sku'] );
								update_post_meta( $product_id, '_currency_symbol', $product['currency_symbol'] );
								update_post_meta( $product_id, '_regular_price', $variant['display_regular_price'] );
								update_post_meta( $product_id, '_sale_price', $variant['display_price'] );
								update_post_meta( $product_id, '_weight', $variant['weight'] );
								update_post_meta( $product_id, '_dimensions', $variant['dimensions'] );
							}

							?>
							<tr>
								<td>
									<?php
									if( isset($variant['image']) && !empty($variant['image'] ) ) {
										?>
										<img src="<?=esc_url($variant['image']['url'])?>" width="80">
										<?php
									} else if( !empty($product['gallery']) ) {
										?>
										<img src="<?=esc_url($product['gallery'][0])?>" width="80">
										<?php
									}
									?>
								</td>
								<td><?=esc_html($product['slug'])?></td>
								<td><?=esc_html($product['title'])?></td>
								<td><?=esc_html($product['type'])?></td>
								<td><?php
								foreach ($variant['attributes'] as $key => $value) {
									$attr = str_replace('attribute_pa_','',$key);
									?>
									<div style="white-space:nowrap;">
										<span><?=esc_html($attr)?>:</span>
										<span><?=esc_html($value)?></span>
									</div>
									<?php
								}
								?></td>
								<td><?=esc_html($variant['sku'])?></td>
								<td><?=esc_html($product['currency_symbol'])?></td>
								<td><?=esc_html($variant['display_regular_price'])?></td>
								<td><?=esc_html($variant['display_price'])?></td>
								<td><?=esc_html($variant['weight'])?></td>
								<td><?=esc_html($variant['dimensions'])?></td>
								<td><?php
								if( isset($variant['image']) && !empty($variant['image']) ) {
									echo '<a href="'.esc_url($variant['image']['url']).'" target="_blank">'.esc_url($variant['image']['url']).'</a>';
								}
								?></td>
							</tr>
							<?php
						}
					}
				}
				?>
			</table>
			<?php
		}
	}
}