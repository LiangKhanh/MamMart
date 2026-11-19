<?php
// S3: trang "Hồ sơ bé" trong My Account + form tạo hồ sơ.
define( 'MM_CP_MAX_CHILDREN', 5 ); // Đ3: GIẢ ĐỊNH tạm, chờ leader chốt
define( 'MM_CP_ENDPOINT', 'mm-children' );

function mm_cp_add_endpoint() { add_rewrite_endpoint( MM_CP_ENDPOINT, EP_ROOT | EP_PAGES ); }
add_action( 'init', 'mm_cp_add_endpoint' );

add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	$new = array();
	foreach ( $items as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'orders' === $k ) { $new[ MM_CP_ENDPOINT ] = 'Hồ sơ bé'; }
	}
	if ( ! isset( $new[ MM_CP_ENDPOINT ] ) ) { $new[ MM_CP_ENDPOINT ] = 'Hồ sơ bé'; }
	return $new;
} );
add_action( 'woocommerce_account_' . MM_CP_ENDPOINT . '_endpoint', 'mm_cp_render_page' );
add_action( 'template_redirect', 'mm_cp_handle_post' );

function mm_cp_t( $suffix ) { global $wpdb; return $wpdb->prefix . 'mm_' . $suffix; }

function mm_cp_stages() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT stage_id,stage_name,min_age_months,max_age_months FROM ' . mm_cp_t( 'stage' ) . ' ORDER BY display_order', ARRAY_A );
}

function mm_cp_children( $customer_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . mm_cp_t( 'child_profile' ) . ' WHERE customer_id=%d ORDER BY child_id', $customer_id ), ARRAY_A );
}

/** Đồng ý marketing hiện tại của tài khoản: bản ghi mới nhất là granted và chưa rút. */
function mm_cp_marketing_granted( $customer_id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT status,withdrawn_at FROM ' . mm_cp_t( 'marketing_consent' ) . ' WHERE customer_id=%d ORDER BY consent_id DESC LIMIT 1', $customer_id ), ARRAY_A );
	return $r && 'granted' === $r['status'] && empty( $r['withdrawn_at'] );
}

function mm_cp_norm_name( $name ) { return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $name ) ), 'UTF-8' ); }

function mm_cp_handle_post() {
	if ( empty( $_POST['mm_cp_action'] ) || 'create_child' !== $_POST['mm_cp_action'] || ! function_exists( 'wc_add_notice' ) ) { return; }
	if ( ! is_user_logged_in() ) { return; } // khách vãng lai không tạo được hồ sơ
	if ( ! isset( $_POST['_mm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mm_nonce'] ) ), 'mm_create_child' ) ) {
		wc_add_notice( 'Phiên làm việc đã hết hạn, vui lòng thử lại.', 'error' ); return;
	}
	global $wpdb;
	$uid  = get_current_user_id();
	$name = sanitize_text_field( wp_unslash( $_POST['child_name'] ?? '' ) );
	$dob  = sanitize_text_field( wp_unslash( $_POST['birth_date'] ?? '' ) );
	$err  = array();

	if ( '' === trim( $name ) ) { $err[] = 'Vui lòng nhập tên bé.'; }
	$v = mm_cp_validate_dob( $dob );
	if ( 'missing' === $v ) { $err[] = 'Vui lòng nhập ngày sinh của bé.'; }
	if ( 'invalid' === $v ) { $err[] = 'Ngày sinh không hợp lệ (định dạng YYYY-MM-DD).'; }
	if ( 'future' === $v ) { $err[] = 'Ngày sinh không được ở tương lai.'; }
	if ( empty( $_POST['dob_consent'] ) ) { $err[] = 'Cần đồng ý cho cửa hàng lưu ngày sinh để tạo hồ sơ.'; }
	if ( count( mm_cp_children( $uid ) ) >= MM_CP_MAX_CHILDREN ) { $err[] = 'Mỗi tài khoản tối đa ' . MM_CP_MAX_CHILDREN . ' hồ sơ bé.'; }
	if ( ! $err ) {
		$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT child_id FROM ' . mm_cp_t( 'child_profile' ) . ' WHERE customer_id=%d AND name_norm=%s AND birth_date=%s', $uid, mm_cp_norm_name( $name ), $dob ) );
		if ( $dup ) { $err[] = 'Hồ sơ bé này (cùng tên và ngày sinh) đã tồn tại.'; }
	}
	if ( $err ) { foreach ( $err as $e ) { wc_add_notice( $e, 'error' ); } return; }

	$months = mm_cp_age_months( $dob );
	$now    = current_time( 'mysql' );
	$first  = 0 === count( mm_cp_children( $uid ) );
	$wpdb->insert( mm_cp_t( 'child_profile' ), array(
		'customer_id'      => $uid,
		'current_stage_id' => mm_cp_stage_for( (int) $months, mm_cp_stages() ),
		'name'             => $name,
		'name_norm'        => mm_cp_norm_name( $name ),
		'birth_date'       => $dob,
		'dob_consent_at'   => $now,
		'is_primary'       => $first ? 1 : 0,
		'created_at'       => $now,
		'updated_at'       => $now,
	) );
	// Opt-in marketing: CHỈ khi khách tự tích; không tích sẵn; cấp tài khoản.
	if ( ! empty( $_POST['marketing_optin'] ) && ! mm_cp_marketing_granted( $uid ) ) {
		$wpdb->insert( mm_cp_t( 'marketing_consent' ), array( 'customer_id' => $uid, 'status' => 'granted', 'granted_at' => $now ) );
	}
	wc_add_notice( 'Đã tạo hồ sơ bé.', 'success' );
	wp_safe_redirect( wc_get_account_endpoint_url( MM_CP_ENDPOINT ) );
	exit;
}

function mm_cp_render_page() {
	$uid  = get_current_user_id();
	$st   = mm_cp_stages();
	$names = wp_list_pluck( $st, 'stage_name', 'stage_id' );
	echo '<h3>Hồ sơ bé</h3>';
	$kids = mm_cp_children( $uid );
	if ( $kids ) {
		echo '<table class="shop_table"><thead><tr><th>Tên</th><th>Ngày sinh</th><th>Tuổi (tháng)</th><th>Nhóm tuổi</th><th>Sửa ngày sinh</th></tr></thead><tbody>';
		foreach ( $kids as $k ) {
			$m = mm_cp_age_months( $k['birth_date'] );
			$s = null === $m ? null : mm_cp_stage_for( $m, $st );
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>', esc_html( $k['name'] ), esc_html( $k['birth_date'] ), esc_html( null === $m ? '-' : $m ), esc_html( $s ? $names[ $s ] : '-' ) );
			mm_cp_render_request_cell( $k );
			echo '</td></tr>';
		}
		echo '</tbody></table><p><small>Đổi ngày sinh cần quản trị viên duyệt.</small></p>';
	} else {
		echo '<p>Bạn chưa có hồ sơ bé nào.</p>';
	}
	if ( count( $kids ) >= MM_CP_MAX_CHILDREN ) { echo '<p>Đã đạt số hồ sơ tối đa.</p>'; return; }
	$val = function ( $k ) { return isset( $_POST[ $k ] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : ''; };
	?>
	<h4>Thêm hồ sơ bé</h4>
	<form method="post">
		<?php wp_nonce_field( 'mm_create_child', '_mm_nonce' ); ?>
		<input type="hidden" name="mm_cp_action" value="create_child">
		<p><label>Tên bé *<br><input type="text" name="child_name" maxlength="100" value="<?php echo $val( 'child_name' ); ?>" required></label></p>
		<p><label>Ngày sinh *<br><input type="date" name="birth_date" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo $val( 'birth_date' ); ?>" required></label></p>
		<p><label><input type="checkbox" name="dob_consent" value="1"> Tôi đồng ý cho Mầm Mart lưu ngày sinh của bé để gợi ý sản phẩm theo tuổi. *</label></p>
		<p><label><input type="checkbox" name="marketing_optin" value="1"> Tôi đồng ý nhận thông tin/ưu đãi theo tuổi bé (không bắt buộc).</label></p>
		<p><button type="submit" class="button">Lưu hồ sơ</button></p>
	</form>
	<?php
}