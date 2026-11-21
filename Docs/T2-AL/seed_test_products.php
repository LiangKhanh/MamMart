<?php
/**
 * T2-AL — Nạp sản phẩm THỬ cho ca kiểm giỏ hàng (dữ liệu giả, không thay CSV của T2-KH).
 * Chạy: php Docs/T2-AL/seed_test_products.php   (từ thư mục gốc của repo, đã cấu hình wp-config)
 * Chạy lại nhiều lần an toàn: bỏ qua SKU đã có.
 */
$root = dirname( __DIR__, 2 );
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
if ( ! class_exists( 'WooCommerce' ) ) { fwrite( STDERR, "WooCommerce chua bat\n" ); exit( 1 ); }

function mm_cat( $name, $parent = 0 ) {
	$t = term_exists( $name, 'product_cat' );
	if ( ! $t ) { $t = wp_insert_term( $name, 'product_cat', [ 'parent' => $parent ] ); }
	return is_array( $t ) ? (int) $t['term_id'] : (int) $t;
}
function mm_img( $slug ) {
	$src = $GLOBALS['root'] . "/Data/t2al_images/$slug.jpg";
	if ( ! file_exists( $src ) ) { return 0; }
	$tmp = wp_tempnam( $slug . '.jpg' ); copy( $src, $tmp );
	$id = media_handle_sideload( [ 'name' => "$slug.jpg", 'tmp_name' => $tmp ], 0 );
	return is_wp_error( $id ) ? 0 : $id;
}

$cats = [
	'ta'   => mm_cat( 'Tã & Bỉm' ),
	'ku'   => mm_cat( 'Khăn ướt' ),
	'ad'   => mm_cat( 'Ăn dặm' ),
	'sua'  => mm_cat( 'Sữa & Thực phẩm' ),
	'ss'   => mm_cat( 'Quần áo & Sơ sinh' ),
	'bs'   => mm_cat( 'Bình sữa & Ty ngậm' ),
];

// type: simple | variable ; vars: [ [label, price, stock], ... ]
$products = [
	[ 'sku'=>'MM-TA-DAN', 'name'=>'Tã dán Mầm Mart Natural', 'type'=>'variable', 'cat'=>'ta', 'img'=>'ta-dan', 'attr'=>'Size', 'featured'=>true,
	  'desc'=>'Tã dán mềm mại, thấm hút tốt, chọn theo cân nặng của bé. (Dữ liệu thử)',
	  'vars'=>[ ['NB (dưới 5kg)',189000,40], ['S (4–8kg)',199000,25], ['M (5–11kg)',209000,0], ['L (9–14kg)',219000,3] ] ],
	[ 'sku'=>'MM-TA-QUAN', 'name'=>'Tã quần Mầm Mart Soft', 'type'=>'variable', 'cat'=>'ta', 'img'=>'ta-quan', 'attr'=>'Size',
	  'desc'=>'Tã quần co giãn, tiện cho bé hay vận động. Sản phẩm thử chỉ có một biến thể còn hàng. (Dữ liệu thử)',
	  'vars'=>[ ['L (9–14kg)',229000,0], ['XL (12–18kg)',239000,12] ] ],
	[ 'sku'=>'MM-KU-100', 'name'=>'Khăn ướt Mầm Mart 100 tờ', 'type'=>'simple', 'cat'=>'ku', 'img'=>'khan-uot', 'price'=>40000, 'stock'=>100, 'featured'=>true,
	  'desc'=>'Khăn ướt không cồn, dùng hằng ngày. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-KU-080', 'name'=>'Khăn ướt không mùi 80 tờ', 'type'=>'simple', 'cat'=>'ku', 'img'=>'khan-uot-2', 'price'=>35000, 'sale'=>29000, 'stock'=>2,
	  'desc'=>'Tồn thấp (còn 2) và đang giảm giá — dùng để thử cảnh báo vượt tồn. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-AD-BINH', 'name'=>'Bình ăn dặm silicone', 'type'=>'simple', 'cat'=>'ad', 'img'=>'binh-an-dam', 'price'=>120000, 'stock'=>30, 'featured'=>true,
	  'desc'=>'Bình bóp ăn dặm silicone an toàn. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-AD-BATTHIA', 'name'=>'Bộ thìa bát ăn dặm', 'type'=>'variable', 'cat'=>'ad', 'img'=>'bo-thia-bat', 'attr'=>'Màu',
	  'desc'=>'Bộ thìa bát cho bé tập ăn. Màu xanh hết hàng. (Dữ liệu thử)',
	  'vars'=>[ ['Hồng',99000,10], ['Xanh',99000,0] ] ],
	[ 'sku'=>'MM-SS-NB', 'name'=>'Bộ đồ sơ sinh NB', 'type'=>'simple', 'cat'=>'ss', 'img'=>'do-so-sinh', 'price'=>150000, 'stock'=>1,
	  'desc'=>'Chỉ còn 1 bộ — thử giới hạn số lượng. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-SUA-800', 'name'=>'Sữa bột công thức 800g', 'type'=>'simple', 'cat'=>'sua', 'img'=>'sua-bot', 'price'=>520000, 'stock'=>0,
	  'desc'=>'Hết hàng toàn bộ — thử nút bị vô hiệu. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-QA-COTTON', 'name'=>'Bộ quần áo cotton cho bé', 'type'=>'variable', 'cat'=>'ss', 'img'=>'quan-ao', 'attr'=>'Size', 'featured'=>true,
	  'desc'=>'Cotton mềm, thoáng. (Dữ liệu thử)',
	  'vars'=>[ ['3–6 tháng',135000,15], ['6–9 tháng',145000,8], ['9–12 tháng',155000,5] ] ],
	[ 'sku'=>'MM-BS-240', 'name'=>'Bình sữa cổ rộng 240ml', 'type'=>'simple', 'cat'=>'bs', 'img'=>'binh-sua', 'price'=>110000, 'stock'=>50,
	  'desc'=>'Bình sữa cổ rộng, chống sặc. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-BS-TYNGAM', 'name'=>'Ty ngậm silicone', 'type'=>'simple', 'cat'=>'bs', 'img'=>'ty-ngam', 'price'=>65000, 'stock'=>40,
	  'desc'=>'Ty ngậm silicone mềm. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-KS-6', 'name'=>'Khăn sữa cotton (set 6 cái)', 'type'=>'simple', 'cat'=>'ss', 'img'=>'khan-sua', 'price'=>89000, 'stock'=>60,
	  'desc'=>'Set 6 khăn sữa cotton. (Dữ liệu thử)' ],
	[ 'sku'=>'MM-AN-HIDDEN', 'name'=>'Sản phẩm ẩn (thử bị ẩn giữa chừng)', 'type'=>'simple', 'cat'=>'ss', 'price'=>10000, 'stock'=>5, 'hidden'=>true,
	  'desc'=>'Dùng để thử dòng giỏ bị ẩn/ngừng bán. (Dữ liệu thử)' ],
];

foreach ( $products as $d ) {
	if ( wc_get_product_id_by_sku( $d['sku'] ) ) { echo "skip {$d['sku']}\n"; continue; }
	$p = $d['type'] === 'variable' ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] ); $p->set_sku( $d['sku'] ); $p->set_status( 'publish' );
	$p->set_description( $d['desc'] ); $p->set_short_description( $d['desc'] );
	$p->set_category_ids( [ $cats[ $d['cat'] ] ] );
	$p->set_featured( ! empty( $d['featured'] ) );
	if ( ! empty( $d['hidden'] ) ) { $p->set_catalog_visibility( 'visible' ); }
	if ( ! empty( $d['img'] ) ) { $iid = mm_img( $d['img'] ); if ( $iid ) { $p->set_image_id( $iid ); } }
	if ( $d['type'] === 'simple' ) {
		$p->set_regular_price( $d['price'] ); if ( ! empty( $d['sale'] ) ) { $p->set_sale_price( $d['sale'] ); }
		$p->set_manage_stock( true ); $p->set_stock_quantity( $d['stock'] ); $p->set_backorders( 'no' );
		$p->set_stock_status( $d['stock'] > 0 ? 'instock' : 'outofstock' );
		$id = $p->save();
	} else {
		$opts = array_map( fn( $v ) => $v[0], $d['vars'] );
		$attr = new WC_Product_Attribute(); $attr->set_name( $d['attr'] ); $attr->set_options( $opts );
		$attr->set_visible( true ); $attr->set_variation( true );
		$p->set_attributes( [ $attr ] );
		$id = $p->save();
		foreach ( $d['vars'] as $i => $v ) {
			$var = new WC_Product_Variation(); $var->set_parent_id( $id );
			$var->set_attributes( [ sanitize_title( $d['attr'] ) => $v[0] ] );
			$var->set_sku( $d['sku'] . '-' . ( $i + 1 ) );
			$var->set_regular_price( $v[1] ); $var->set_manage_stock( true ); $var->set_stock_quantity( $v[2] );
			$var->set_backorders( 'no' ); $var->set_stock_status( $v[2] > 0 ? 'instock' : 'outofstock' );
			$var->save();
		}
		WC_Product_Variable::sync( $id );
	}
	if ( ! empty( $d['hidden'] ) ) { /* ẩn sau khi test: xem kịch bản TC-AL-14 */ }
	echo "created {$d['sku']} (#$id)\n";
}
echo "done\n";
