<?php
/**
 * Mầm Mart UI (T2-AL) — giao diện khách hàng + giỏ hàng.
 * Phạm vi AL: trang chủ, danh mục, chi tiết, giỏ, khung tài khoản.
 * KHÔNG làm: checkout/phí/thanh toán (T2-NL), xem danh sách đơn (T2-LI), hồ sơ bé/lọc tuổi (T2-YL),
 * nhắc mua (T2-LO), ưu đãi (T2-PH). Các chỗ nối ghi trong Docs/T2-AL/DIEM_NOI.md.
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/vi-strings.php';

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'twentytwentyfive-style', get_template_directory_uri() . '/style.css', [], wp_get_theme( 'twentytwentyfive' )->get( 'Version' ) );
	wp_enqueue_style( 'mammart-ui', get_stylesheet_uri(), [ 'twentytwentyfive-style' ], wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/style.css' ) );
} );

/* ---------- Việt hóa chuỗi PHP (WooCommerce) và chuỗi khối JS (giỏ/mini-cart) ---------- */
add_filter( 'gettext', function ( $t, $orig, $domain ) {
	static $map = null;
	if ( null === $map ) { $map = mammart_vi_strings(); }
	return ( 'woocommerce' === $domain || 'default' === $domain ) && isset( $map[ $orig ] ) ? $map[ $orig ] : $t;
}, 20, 3 );
add_filter( 'gettext_with_context', function ( $t, $orig, $ctx, $domain ) {
	static $map = null;
	if ( null === $map ) { $map = mammart_vi_strings(); }
	$k = $ctx . "\x04" . $orig;
	return 'woocommerce' === $domain && isset( $map[ $k ] ) ? $map[ $k ] : $t;
}, 20, 4 );
add_filter( 'ngettext', function ( $t, $single, $plural, $n, $domain ) {
	$pm = mammart_vi_plurals();
	return 'woocommerce' === $domain && isset( $pm[ $single ] ) ? $pm[ $single ] : $t;
}, 20, 5 );
add_action( 'wp_enqueue_scripts', function () {
	$data = [ '' => [ 'domain' => 'woocommerce', 'lang' => 'vi', 'plural-forms' => 'nplurals=1; plural=0;' ] ];
	foreach ( mammart_vi_strings() as $k => $v ) { $data[ $k ] = [ $v ]; }
	if ( ! is_user_logged_in() ) { $data['Proceed to Checkout'] = [ 'Đăng nhập để mua hàng' ]; $data['Proceed to checkout'] = [ 'Đăng nhập để mua hàng' ]; }
	foreach ( mammart_vi_plurals() as $k => $v ) { $data[ $k ] = [ $v ]; }
	wp_add_inline_script( 'wp-i18n', 'wp.i18n.setLocaleData(' . wp_json_encode( $data ) . ', "woocommerce");', 'after' );
}, 100 );
add_filter( 'woocommerce_blocks_mini_cart_footer_buttons_text', fn() => 1 ); // giữ hook cho T2-NL nếu cần

/* ---------- Shortcode: điều hướng danh mục, lối vào Stage, lưới danh mục, chính sách ---------- */
add_shortcode( 'mammart_cat_nav', function () {
	$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => [ (int) get_option( 'default_product_cat' ) ] ] );
	$html  = '<nav class="alignwide" aria-label="Danh mục" style="max-width:1280px;margin:auto"><ul><li><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Tất cả sản phẩm</a></li>';
	foreach ( (array) $terms as $t ) { $html .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>'; }
	return $html . '</ul></nav>';
} );
add_shortcode( 'mammart_stage_entry', function () {
	// Chỉ là LỐI VÀO (wireframe 6.1 mục 2). Quy tắc Stage/hồ sơ bé/lọc thuộc T2-YL -> tạm vô hiệu, ghi trong DIEM_NOI.md (N-02).
	$s = [ [ '0–12 tháng', 'Bé sơ sinh & tập ăn dặm' ], [ '12–24 tháng', 'Bé biết đi, biết nói' ], [ 'Từ 24 tháng', 'Bé lớn hơn' ] ];
	$h = '<div class="mm-stage-grid alignwide" data-connection="T2-YL">';
	foreach ( $s as $x ) { $h .= '<div class="mm-card is-stage" aria-disabled="true"><span class="mm-badge">Sắp có</span><strong>' . esc_html( $x[0] ) . '</strong><br><span>' . esc_html( $x[1] ) . '</span></div>'; }
	return $h . '</div>';
} );
add_shortcode( 'mammart_categories', function () {
	$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => [ (int) get_option( 'default_product_cat' ) ] ] );
	$h = '<div class="mm-cat-grid alignwide">';
	foreach ( (array) $terms as $t ) {
		$thumb = get_term_meta( $t->term_id, 'thumbnail_id', true );
		if ( ! $thumb ) { // dùng ảnh sản phẩm đầu tiên của danh mục làm ảnh đại diện
			$ids = wc_get_products( [ 'category' => [ $t->slug ], 'limit' => 1, 'return' => 'objects' ] );
			$thumb = $ids ? $ids[0]->get_image_id() : 0;
		}
		$img = $thumb ? wp_get_attachment_image( $thumb, 'woocommerce_thumbnail' ) : '<span class="mm-cat-ph"></span>';
		$h  .= '<a class="mm-card mm-cat-card" href="' . esc_url( get_term_link( $t ) ) . '">' . $img . '<span class="mm-cat-name">' . esc_html( $t->name ) . '</span></a>';
	}
	return $h . '</div>';
} );
add_shortcode( 'mammart_policy', function () {
	$p = [ [ 'Giao hàng', 'TP.HCM 20.000đ · tỉnh khác 30.000đ' ], [ 'Thanh toán', 'COD hoặc chuyển khoản SePay' ], [ 'Hủy đơn', 'Tự hủy trước khi đóng gói, chọn lý do' ], [ 'Hỗ trợ', 'Hotline 1900 1234 (8:00–21:00)' ] ];
	$h = '<div class="mm-policy alignwide">';
	foreach ( $p as $x ) { $h .= '<div class="mm-card"><strong>' . esc_html( $x[0] ) . '</strong><br>' . esc_html( $x[1] ) . '</div>'; }
	return $h . '</div>';
} );

/* ---------- Giỏ: khách xem/sửa giỏ tự do, BẮT BUỘC đăng nhập khi thanh toán (BR-AL-07) ---------- */
function mammart_login_url_for_checkout() {
	return add_query_arg( 'redirect_to', rawurlencode( wc_get_page_permalink( 'checkout' ) ), wc_get_page_permalink( 'myaccount' ) );
}
// Khách chưa đăng nhập: nút thanh toán trong giỏ dẫn tới trang đăng nhập (giỏ được giữ), nhãn "Đăng nhập để mua hàng" (wireframe 6.4 mục 6).
add_filter( 'woocommerce_get_checkout_url', function ( $url ) {
	if ( is_user_logged_in() || is_admin() || wp_doing_cron() ) { return $url; }
	if ( function_exists( 'is_checkout' ) && is_checkout() ) { return $url; }
	return add_query_arg( 'redirect_to', rawurlencode( $url ), wc_get_page_permalink( 'myaccount' ) );
}, 20 );
add_filter( 'mammart_guest_label', fn() => 'Đăng nhập để mua hàng' );
add_filter( 'render_block_woocommerce/cart', function ( $html ) {
	if ( is_user_logged_in() ) { return $html; }
	$n = '<div class="mm-cart-notice" role="note">Bạn có thể xem và chỉnh giỏ hàng tự do. Bạn cần <a href="' . esc_url( mammart_login_url_for_checkout() ) . '">đăng nhập</a> khi thanh toán — giỏ hàng sẽ được giữ lại.</div>';
	return $n . $html;
} );
// Sau đăng nhập từ giỏ, quay lại trang thanh toán; giỏ khách được WooCommerce gộp vào giỏ tài khoản (xem TC-AL-09).
add_filter( 'woocommerce_login_redirect', function ( $redirect ) {
	$to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	return $to && wp_validate_redirect( $to, false ) ? $to : $redirect;
} );
add_filter( 'woocommerce_registration_redirect', function ( $redirect ) {
	$to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	return $to && wp_validate_redirect( $to, false ) ? $to : $redirect;
} );
// Truy cập thẳng trang thanh toán khi chưa đăng nhập -> chuyển sang đăng nhập, giữ giỏ (T2-NL sở hữu checkout, AL chỉ chặn đăng nhập).
add_action( 'template_redirect', function () {
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_user_logged_in() ) {
		wp_safe_redirect( mammart_login_url_for_checkout() );
		exit;
	}
} );

/* ---------- Tồn thấp: hiện "Chỉ còn X" ở chi tiết sản phẩm ---------- */
update_option( 'woocommerce_notify_low_stock_amount', 5 );
add_filter( 'woocommerce_get_availability_text', function ( $text, $product ) {
	if ( $product->managing_stock() && $product->is_in_stock() ) {
		$q = (int) $product->get_stock_quantity();
		if ( $q > 0 && $q <= 5 ) { return sprintf( 'Chỉ còn %d sản phẩm', $q ); }
		if ( $q > 5 ) { return 'Còn hàng'; }
	}
	return $product->is_in_stock() ? 'Còn hàng' : 'Hết hàng';
}, 20, 2 );

/* ---------- Khung tài khoản (wireframe: không làm thay danh sách đơn của T2-LI) ---------- */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	$new = [
		'dashboard'      => 'Tổng quan',
		'orders'         => 'Đơn hàng của tôi',          // T2-LI thay nội dung endpoint này
		'mm-baby'        => 'Hồ sơ của bé',               // T2-YL
		'mm-plans'       => 'Kế hoạch nhắc mua',          // T2-LO
		'mm-offers'      => 'Ưu đãi của tôi',             // T2-PH
		'edit-address'   => 'Địa chỉ',
		'edit-account'   => 'Thông tin tài khoản',
		'customer-logout'=> 'Đăng xuất',
	];
	return $new;
} );
foreach ( [ 'mm-baby' => 'T2-YL', 'mm-plans' => 'T2-LO', 'mm-offers' => 'T2-PH' ] as $ep => $owner ) {
	add_action( 'init', fn() => add_rewrite_endpoint( $ep, EP_ROOT | EP_PAGES ) );
	add_action( "woocommerce_account_{$ep}_endpoint", function () use ( $owner ) {
		echo '<div class="mm-account-note"><strong>Mục này do ' . esc_html( $owner ) . ' phụ trách.</strong><br>Điểm nối đã chừa sẵn trong khung tài khoản; chưa tích hợp module thật.</div>';
	} );
}
add_filter( 'woocommerce_get_query_vars', function ( $v ) { foreach ( [ 'mm-baby', 'mm-plans', 'mm-offers' ] as $e ) { $v[ $e ] = $e; } return $v; } );
add_filter( 'woocommerce_account_menu_item_classes', function ( $classes, $endpoint ) {
	if ( in_array( $endpoint, [ 'mm-baby', 'mm-plans', 'mm-offers' ], true ) ) { $classes[] = 'is-pending'; }
	return $classes;
}, 10, 2 );
add_action( 'woocommerce_account_dashboard', function () {
	echo '<div class="mm-account-note" style="margin-top:1rem"><strong>Khung tài khoản (T2-AL).</strong> Danh sách và chi tiết đơn hàng thuộc T2-LI; hồ sơ bé, nhắc mua, ưu đãi sẽ nối vào các mục “Sắp có”.</div>';
} );

/* Gợi ý "Tối đa N" trên dòng giỏ (BR-AL-03) — khối giỏ tự đưa số lượng về mức tồn, thêm chữ để khách hiểu vì sao. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) { return; }
	wp_enqueue_script( 'mammart-cart-hint', get_stylesheet_directory_uri() . '/assets/cart-hint.js', [], filemtime( get_stylesheet_directory() . '/assets/cart-hint.js' ), true );
} );
add_filter( 'ngettext', function ( $t, $s, $p, $n, $d ) { return $t; }, 1, 5 );

/* Bộ lọc giá + còn hàng (wireframe 6.2 mục 2). Dùng tham số URL chuẩn của WooCommerce: min_price, max_price, filter_stock_status. Lọc theo độ tuổi (Stage) thuộc T2-YL. */
add_shortcode( 'mammart_filters', function () {
	$min = isset( $_GET['min_price'] ) ? absint( $_GET['min_price'] ) : '';
	$max = isset( $_GET['max_price'] ) ? absint( $_GET['max_price'] ) : '';
	$in  = isset( $_GET['filter_stock_status'] ) && 'instock' === $_GET['filter_stock_status'];
	$act = esc_url( remove_query_arg( [ 'paged', 'min_price', 'max_price', 'filter_stock_status' ] ) );
	ob_start(); ?>
	<form class="mm-filter" method="get" action="<?php echo $act; ?>">
		<strong>Khoảng giá (đ)</strong>
		<div class="mm-filter-row"><input type="number" name="min_price" min="0" step="1000" placeholder="Từ" value="<?php echo esc_attr( $min ); ?>"><span>–</span><input type="number" name="max_price" min="0" step="1000" placeholder="Đến" value="<?php echo esc_attr( $max ); ?>"></div>
		<label class="mm-check"><input type="checkbox" name="filter_stock_status" value="instock" <?php checked( $in ); ?>> Chỉ hiện sản phẩm còn hàng</label>
		<button class="wp-element-button" type="submit">Áp dụng</button>
		<?php if ( $min || $max || $in ) : ?><a class="mm-filter-reset" href="<?php echo $act; ?>">Xóa bộ lọc</a><?php endif; ?>
	</form>
	<?php return ob_get_clean();
} );
add_action( 'init', function () { // đổi tên danh mục mặc định cho khỏi hiện "Uncategorized"
	$id = (int) get_option( 'default_product_cat' );
	if ( $id && ( $t = get_term( $id, 'product_cat' ) ) && ! is_wp_error( $t ) && 'Uncategorized' === $t->name ) { wp_update_term( $id, 'product_cat', [ 'name' => 'Khác', 'slug' => 'khac' ] ); }
} );

/* BR-AL-05: giá đổi sau khi thêm -> giỏ hiển thị giá mới VÀ báo cho khách (WooCommerce mặc định không báo). */
add_filter( 'woocommerce_add_cart_item_data', function ( $data, $pid, $vid ) {
	$p = wc_get_product( $vid ? $vid : $pid );
	if ( $p ) { $data['mm_price_seen'] = (float) $p->get_price(); }
	return $data;
}, 10, 3 );
add_action( 'woocommerce_cart_loaded_from_session', function ( $cart ) {
	foreach ( $cart->get_cart() as $key => $item ) {
		if ( ! isset( $item['mm_price_seen'], $item['data'] ) ) { continue; }
		$now = (float) $item['data']->get_price();
		if ( abs( $now - $item['mm_price_seen'] ) > 0.001 ) {
			$cart->cart_contents[ $key ]['mm_price_seen'] = $now;
			wc_add_notice( sprintf( 'Giá của “%s” đã được cập nhật: %s → %s.', $item['data']->get_name(), wc_price( $item['mm_price_seen'] ), wc_price( $now ) ), 'notice' );
		}
	}
} );
