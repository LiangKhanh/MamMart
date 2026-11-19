<?php
// S4: khách gửi yêu cầu sửa ngày sinh; quản trị duyệt/từ chối. Mỗi bé tối đa 1 yêu cầu chờ duyệt.

function mm_cp_last_request( $child_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . mm_cp_t( 'dob_change_request' ) . ' WHERE child_id=%d ORDER BY request_id DESC LIMIT 1', $child_id ), ARRAY_A );
}

/** Ô "Yêu cầu sửa" trong bảng hồ sơ (My Account). */
function mm_cp_render_request_cell( $child ) {
	$r = mm_cp_last_request( $child['child_id'] );
	if ( $r && 'pending' === $r['status'] ) { echo 'Đang chờ duyệt: ' . esc_html( $r['new_birth_date'] ); return; }
	if ( $r ) { echo 'Lần gần nhất: ' . ( 'approved' === $r['status'] ? 'đã duyệt' : 'bị từ chối' ) . '<br>'; }
	?>
	<form method="post">
		<?php wp_nonce_field( 'mm_req_dob', '_mm_nonce' ); ?>
		<input type="hidden" name="mm_cp_action" value="request_dob">
		<input type="hidden" name="child_id" value="<?php echo (int) $child['child_id']; ?>">
		<input type="date" name="new_birth_date" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required>
		<button type="submit" class="button">Gửi yêu cầu</button>
	</form>
	<?php
}

add_action( 'template_redirect', function () {
	if ( empty( $_POST['mm_cp_action'] ) || 'request_dob' !== $_POST['mm_cp_action'] || ! function_exists( 'wc_add_notice' ) || ! is_user_logged_in() ) { return; }
	if ( ! isset( $_POST['_mm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mm_nonce'] ) ), 'mm_req_dob' ) ) { wc_add_notice( 'Phiên làm việc đã hết hạn.', 'error' ); return; }
	global $wpdb;
	$uid = get_current_user_id();
	$cid = (int) ( $_POST['child_id'] ?? 0 );
	$new = sanitize_text_field( wp_unslash( $_POST['new_birth_date'] ?? '' ) );
	$child = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . mm_cp_t( 'child_profile' ) . ' WHERE child_id=%d AND customer_id=%d', $cid, $uid ), ARRAY_A ); // chỉ hồ sơ của chính mình
	$v = mm_cp_validate_dob( $new );
	$msg = null;
	if ( ! $child ) { $msg = 'Không tìm thấy hồ sơ bé.'; }
	elseif ( 'ok' !== $v ) { $msg = array( 'missing' => 'Vui lòng nhập ngày sinh mới.', 'invalid' => 'Ngày sinh không hợp lệ.', 'future' => 'Ngày sinh không được ở tương lai.' )[ $v ]; }
	elseif ( $new === $child['birth_date'] ) { $msg = 'Ngày sinh mới trùng ngày sinh hiện tại.'; }
	else {
		$last = mm_cp_last_request( $cid );
		if ( $last && 'pending' === $last['status'] ) { $msg = 'Đã có yêu cầu đang chờ duyệt.'; }
	}
	if ( $msg ) { wc_add_notice( $msg, 'error' ); return; }
	$wpdb->insert( mm_cp_t( 'dob_change_request' ), array( 'child_id' => $cid, 'old_birth_date' => $child['birth_date'], 'new_birth_date' => $new, 'status' => 'pending', 'requested_at' => current_time( 'mysql' ) ) );
	wc_add_notice( 'Đã gửi yêu cầu, vui lòng chờ quản trị viên duyệt.', 'success' );
	wp_safe_redirect( wc_get_account_endpoint_url( MM_CP_ENDPOINT ) ); exit;
} );

// ---- Quản trị ----
add_action( 'admin_menu', function () {
	add_menu_page( 'Yêu cầu sửa ngày sinh', 'Sửa ngày sinh bé', 'manage_woocommerce', 'mm-dob-requests', 'mm_cp_admin_page', 'dashicons-calendar-alt', 56 );
} );

function mm_cp_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT r.*, c.name, c.customer_id FROM ' . mm_cp_t( 'dob_change_request' ) . ' r JOIN ' . mm_cp_t( 'child_profile' ) . " c ON c.child_id=r.child_id WHERE r.status='pending' ORDER BY r.request_id", ARRAY_A );
	echo '<div class="wrap"><h1>Yêu cầu sửa ngày sinh</h1>';
	if ( ! empty( $_GET['mm_msg'] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mm_msg'] ) ) ) . '</p></div>'; }
	if ( ! $rows ) { echo '<p>Không có yêu cầu nào đang chờ.</p></div>'; return; }
	echo '<table class="widefat striped"><thead><tr><th>Bé</th><th>Tài khoản</th><th>Cũ</th><th>Mới</th><th>Gửi lúc</th><th>Xử lý</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r['name'] ) . '</td><td>#' . (int) $r['customer_id'] . '</td><td>' . esc_html( $r['old_birth_date'] ) . '</td><td>' . esc_html( $r['new_birth_date'] ) . '</td><td>' . esc_html( $r['requested_at'] ) . '</td><td>';
		foreach ( array( 'approve' => 'Duyệt', 'reject' => 'Từ chối' ) as $act => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'mm_review_' . $r['request_id'] );
			echo '<input type="hidden" name="action" value="mm_cp_review"><input type="hidden" name="request_id" value="' . (int) $r['request_id'] . '"><input type="hidden" name="decision" value="' . $act . '">';
			echo '<button class="button' . ( 'approve' === $act ? ' button-primary' : '' ) . '">' . $label . '</button></form> ';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

add_action( 'admin_post_mm_cp_review', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Không có quyền.', 403 ); }
	global $wpdb;
	$rid = (int) ( $_POST['request_id'] ?? 0 );
	check_admin_referer( 'mm_review_' . $rid );
	$dec = ( $_POST['decision'] ?? '' ) === 'approve' ? 'approve' : 'reject';
	$req = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . mm_cp_t( 'dob_change_request' ) . " WHERE request_id=%d AND status='pending'", $rid ), ARRAY_A );
	$msg = 'Yêu cầu không còn ở trạng thái chờ.';
	if ( $req ) {
		$now = current_time( 'mysql' );
		$status = 'rejected'; $msg = 'Đã từ chối yêu cầu.';
		if ( 'approve' === $dec ) {
			$child = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . mm_cp_t( 'child_profile' ) . ' WHERE child_id=%d', $req['child_id'] ), ARRAY_A );
			$dup = $child ? $wpdb->get_var( $wpdb->prepare( 'SELECT child_id FROM ' . mm_cp_t( 'child_profile' ) . ' WHERE customer_id=%d AND name_norm=%s AND birth_date=%s AND child_id<>%d', $child['customer_id'], $child['name_norm'], $req['new_birth_date'], $child['child_id'] ) ) : 1;
			if ( 'ok' !== mm_cp_validate_dob( $req['new_birth_date'] ) || $dup ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'mm-dob-requests', 'mm_msg' => 'Không duyệt được: ngày sinh không hợp lệ hoặc trùng hồ sơ khác. Hãy từ chối.' ), admin_url( 'admin.php' ) ) ); exit;
			}
			$m = mm_cp_age_months( $req['new_birth_date'] );
			$wpdb->update( mm_cp_t( 'child_profile' ), array( 'birth_date' => $req['new_birth_date'], 'current_stage_id' => mm_cp_stage_for( (int) $m, mm_cp_stages() ), 'updated_at' => $now ), array( 'child_id' => $req['child_id'] ) );
			$status = 'approved'; $msg = 'Đã duyệt: cập nhật ngày sinh và nhóm tuổi.'; // không cấp mã hồ sơ mới
		}
		$wpdb->update( mm_cp_t( 'dob_change_request' ), array( 'status' => $status, 'reviewed_by' => get_current_user_id(), 'reviewed_at' => $now ), array( 'request_id' => $rid ) );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'mm-dob-requests', 'mm_msg' => $msg ), admin_url( 'admin.php' ) ) ); exit;
} );