<?php
// S5: nhãn tuổi SKU (meta) + nhập từ CSV + bộ lọc theo tuổi bé / tuổi nhập tay.
define( 'MM_CP_UNLABELED_POLICY', 'exclude' ); // 'exclude' = ẩn SP chưa có nhãn khi lọc (GIẢ ĐỊNH, chờ leader); 'include' = vẫn hiện

// ---- Nhập nhãn từ Data/sample_age_labels.csv (Công cụ > MM nhập nhãn tuổi) ----
add_action( 'admin_menu', function () {
	add_management_page( 'MM nhập nhãn tuổi', 'MM nhập nhãn tuổi', 'manage_woocommerce', 'mm-import-labels', 'mm_cp_import_page' );
} );

function mm_cp_import_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	echo '<div class="wrap"><h1>Nhập nhãn tuổi sản phẩm mẫu</h1>';
	if ( ! empty( $_POST['mm_import'] ) && check_admin_referer( 'mm_import_labels' ) ) {
		$res = mm_cp_import_labels( ABSPATH . 'Data/sample_age_labels.csv' );
		echo '<div class="notice notice-success"><p>' . esc_html( $res ) . '</p></div>';
	}
	echo '<p>Đọc <code>Data/sample_age_labels.csv</code> ở thư mục gốc Mầm Mart. SKU chưa có thì tạo sản phẩm mẫu (giá 100.000đ, chỉ để demo); SKU có rồi thì chỉ cập nhật nhãn. Chạy lại nhiều lần vẫn an toàn.</p><form method="post">';
	wp_nonce_field( 'mm_import_labels' );
	echo '<input type="hidden" name="mm_import" value="1"><button class="button button-primary">Nhập nhãn</button></form></div>';
}

function mm_cp_import_labels( $file ) {
	if ( ! is_readable( $file ) ) { return 'Không đọc được file CSV.'; }
	$fh = fopen( $file, 'r' ); $head = fgetcsv( $fh ); $head[0] = ltrim( $head[0], "\xEF\xBB\xBF" );
	$created = $updated = $unlabeled = 0;
	while ( ( $row = fgetcsv( $fh ) ) !== false ) {
		$r = array_combine( $head, $row );
		$id = wc_get_product_id_by_sku( $r['sku'] );
		if ( ! $id ) {
			$p = new WC_Product_Simple();
			$p->set_name( $r['name'] ); $p->set_sku( $r['sku'] ); $p->set_regular_price( '100000' ); $p->set_status( 'publish' );
			$id = $p->save(); $created++;
		} else { $updated++; }
		if ( '' === $r['age_min_months'] ) {
			delete_post_meta( $id, '_mm_age_min' ); delete_post_meta( $id, '_mm_age_max_excl' ); $unlabeled++;
		} else {
			update_post_meta( $id, '_mm_age_min', (int) $r['age_min_months'] );
			if ( '' === $r['age_max_months_exclusive'] ) { delete_post_meta( $id, '_mm_age_max_excl' ); } else { update_post_meta( $id, '_mm_age_max_excl', (int) $r['age_max_months_exclusive'] ); }
		}
		update_post_meta( $id, '_mm_age_label_raw', $r['age_label_raw'] );
		update_post_meta( $id, '_mm_age_source_url', $r['source_url'] );
		update_post_meta( $id, '_mm_age_accessed_on', $r['accessed_on'] );
	}
	fclose( $fh );
	return "Tạo mới $created, cập nhật $updated, không có nhãn tuổi $unlabeled.";
}

// Hiện nhãn + nguồn trên trang sản phẩm
add_action( 'woocommerce_single_product_summary', function () {
	global $product; $id = $product->get_id();
	$raw = get_post_meta( $id, '_mm_age_label_raw', true ); $src = get_post_meta( $id, '_mm_age_source_url', true );
	if ( $raw ) { printf( '<p class="mm-age-label">Độ tuổi: %s <small>(theo nhãn nhà bán, <a href="%s" target="_blank" rel="noopener">nguồn</a>)</small></p>', esc_html( $raw ), esc_url( $src ) ); }
	elseif ( $src ) { echo '<p class="mm-age-label"><small>Sản phẩm không có nhãn tuổi.</small></p>'; }
}, 25 );

// ---- Xác định tuổi dùng để lọc (nhập tay HOẶC bé của chính mình) ----
function mm_cp_filter_age() {
	if ( isset( $_GET['mm_age'] ) && '' !== $_GET['mm_age'] ) { // thủ công: chỉ áp cho lượt xem này, không ghi vào hồ sơ
		$a = filter_var( wp_unslash( $_GET['mm_age'] ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0, 'max_range' => 240 ) ) );
		return false === $a ? null : $a;
	}
	if ( ! empty( $_GET['mm_child'] ) && is_user_logged_in() ) {
		global $wpdb;
		$dob = $wpdb->get_var( $wpdb->prepare( 'SELECT birth_date FROM ' . mm_cp_t( 'child_profile' ) . ' WHERE child_id=%d AND customer_id=%d', (int) $_GET['mm_child'], get_current_user_id() ) );
		return $dob ? mm_cp_age_months( $dob ) : null; // DOB lỗi/thiếu -> không lọc
	}
	return null;
}

add_action( 'woocommerce_product_query', function ( $q ) {
	$age = mm_cp_filter_age();
	if ( null === $age ) { return; }
	$min = array( 'key' => '_mm_age_min', 'value' => $age, 'type' => 'NUMERIC', 'compare' => '<=' );
	$max = array( 'relation' => 'OR', array( 'key' => '_mm_age_max_excl', 'compare' => 'NOT EXISTS' ), array( 'key' => '_mm_age_max_excl', 'value' => $age, 'type' => 'NUMERIC', 'compare' => '>' ) );
	$cond = array( 'relation' => 'AND', $min, $max );
	if ( 'include' === MM_CP_UNLABELED_POLICY ) { $cond = array( 'relation' => 'OR', $cond, array( 'key' => '_mm_age_min', 'compare' => 'NOT EXISTS' ) ); }
	$mq = (array) $q->get( 'meta_query' ); $mq[] = $cond; $q->set( 'meta_query', $mq );
} );

// ---- Ô lọc trên trang cửa hàng ----
add_action( 'woocommerce_before_shop_loop', function () {
	$kids = is_user_logged_in() ? mm_cp_children( get_current_user_id() ) : array();
	$sel_c = isset( $_GET['mm_child'] ) ? (int) $_GET['mm_child'] : 0;
	$age = isset( $_GET['mm_age'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['mm_age'] ) ) ) : '';
	echo '<form method="get" class="mm-age-filter" style="margin:0 0 1em">';
	foreach ( $_GET as $k => $v ) { if ( ! in_array( $k, array( 'mm_age', 'mm_child' ), true ) && is_scalar( $v ) ) { printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $k ), esc_attr( $v ) ); } }
	if ( $kids ) {
		echo 'Theo bé: <select name="mm_child"><option value="">— Tất cả —</option>';
		foreach ( $kids as $k ) { printf( '<option value="%d"%s>%s</option>', $k['child_id'], selected( $sel_c, (int) $k['child_id'], false ), esc_html( $k['name'] ) ); }
		echo '</select> hoặc ';
	}
	echo 'Tuổi bé (tháng): <input type="number" name="mm_age" min="0" max="240" value="' . $age . '" style="width:6em"> <button class="button">Lọc</button> <a href="' . esc_url( remove_query_arg( array( 'mm_age', 'mm_child' ) ) ) . '">Xóa lọc</a></form>';
	if ( null !== mm_cp_filter_age() ) { echo '<p><small>Đang lọc cho bé ' . (int) mm_cp_filter_age() . ' tháng. Sản phẩm chưa có nhãn tuổi ' . ( 'exclude' === MM_CP_UNLABELED_POLICY ? 'được ẩn.' : 'vẫn hiển thị.' ) . '</small></p>'; }
}, 15 );
