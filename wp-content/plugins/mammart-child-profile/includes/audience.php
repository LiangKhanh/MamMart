<?php
/**
 * S6: Giao diện đọc dữ liệu cho Phúc (marketing) + dữ liệu giả để thử.
 *
 * mm_cp_get_marketing_audience() -> mảng các bé ĐỦ ĐIỀU KIỆN nhận marketing theo tuổi:
 *   [ 'child_id','customer_id','birth_date','age_months','stage_id','dob_consent_at','marketing_granted_at' ]
 * Điều kiện (cả hai): (1) đã đồng ý lưu ngày sinh (dob_consent_at != NULL),
 *                     (2) tài khoản có opt-in marketing còn hiệu lực (bản ghi mới nhất = granted, chưa rút).
 * Chỉ đọc; không trả tên bé (tối thiểu dữ liệu); lấy email qua customer_id. Tuổi/Stage tính lại lúc gọi.
 */
function mm_cp_get_marketing_audience( ?DateTimeImmutable $today = null ): array {
	global $wpdb;
	$c = mm_cp_t( 'child_profile' ); $m = mm_cp_t( 'marketing_consent' );
	$rows = $wpdb->get_results(
		"SELECT c.child_id,c.customer_id,c.birth_date,c.dob_consent_at,m.granted_at AS marketing_granted_at
		 FROM $c c JOIN $m m ON m.customer_id=c.customer_id AND m.consent_id=(SELECT MAX(consent_id) FROM $m WHERE customer_id=c.customer_id)
		 WHERE c.dob_consent_at IS NOT NULL AND m.status='granted' AND m.withdrawn_at IS NULL ORDER BY c.child_id", ARRAY_A );
	$st = mm_cp_stages(); $out = array();
	foreach ( $rows as $r ) {
		$a = mm_cp_age_months( $r['birth_date'], $today );
		if ( null === $a ) { continue; }
		$r['age_months'] = $a; $r['stage_id'] = mm_cp_stage_for( $a, $st );
		$out[] = $r;
	}
	return $out;
}

// ---- Công cụ dữ liệu giả (Công cụ > MM dữ liệu giả) ----
add_action( 'admin_menu', function () {
	add_management_page( 'MM dữ liệu giả', 'MM dữ liệu giả', 'manage_options', 'mm-seed', 'mm_cp_seed_page' );
} );

function mm_cp_seed_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap"><h1>Dữ liệu giả T2-YL</h1>';
	if ( ! empty( $_POST['mm_seed'] ) && check_admin_referer( 'mm_seed' ) ) { echo '<div class="notice notice-success"><p>' . esc_html( mm_cp_seed() ) . '</p></div>'; }
	echo '<form method="post">'; wp_nonce_field( 'mm_seed' );
	echo '<input type="hidden" name="mm_seed" value="1"><button class="button button-primary">Tạo / làm mới dữ liệu giả</button></form>';
	echo '<h2>Kết quả mm_cp_get_marketing_audience()</h2><table class="widefat striped"><tr><th>child_id</th><th>customer_id</th><th>birth_date</th><th>tuổi (tháng)</th><th>stage</th></tr>';
	foreach ( mm_cp_get_marketing_audience() as $r ) { printf( '<tr><td>%d</td><td>%d</td><td>%s</td><td>%d</td><td>%s</td></tr>', $r['child_id'], $r['customer_id'], esc_html( $r['birth_date'] ), $r['age_months'], esc_html( (string) $r['stage_id'] ) ); }
	echo '</table><p>Mong đợi (sau khi tạo): chỉ test_a, test_b, test_f xuất hiện. test_c (không opt-in), test_d (đã rút), test_e (chưa đồng ý lưu DOB) KHÔNG xuất hiện.</p></div>';
}

function mm_cp_seed() {
	global $wpdb;
	$today = new DateTimeImmutable( 'today', new DateTimeZone( MM_CP_TZ ) ); $now = current_time( 'mysql' );
	// [login, tên bé, DOB, dob_consent, marketing: none|granted|withdrawn]
	$cases = array(
		array( 'mm_test_a', 'Bé An (5 tháng)', $today->modify( '-5 months' )->format( 'Y-m-d' ), true, 'granted' ),
		array( 'mm_test_b', 'Bé Bình (đúng mốc 12 tháng)', $today->modify( '-12 months' )->format( 'Y-m-d' ), true, 'granted' ),
		array( 'mm_test_c', 'Bé Chi (không opt-in)', $today->modify( '-18 months' )->format( 'Y-m-d' ), true, 'none' ),
		array( 'mm_test_d', 'Bé Dũng (đã rút opt-in)', $today->modify( '-30 months' )->format( 'Y-m-d' ), true, 'withdrawn' ),
		array( 'mm_test_e', 'Bé Em (chưa đồng ý lưu DOB)', $today->modify( '-8 months' )->format( 'Y-m-d' ), false, 'granted' ),
		array( 'mm_test_f', 'Bé Phúc (sinh 31/01/2026)', '2026-01-31', true, 'granted' ),
	);
	$p = mm_cp_t( '' ); $n = 0;
	foreach ( $cases as $c ) {
		$u = get_user_by( 'login', $c[0] );
		$uid = $u ? $u->ID : wp_insert_user( array( 'user_login' => $c[0], 'user_pass' => wp_generate_password( 20 ), 'user_email' => $c[0] . '@example.invalid', 'role' => 'customer' ) );
		if ( is_wp_error( $uid ) ) { continue; }
		$wpdb->delete( $p . 'child_profile', array( 'customer_id' => $uid ) );
		$wpdb->delete( $p . 'marketing_consent', array( 'customer_id' => $uid ) );
		$m = mm_cp_age_months( $c[2] );
		$wpdb->insert( $p . 'child_profile', array( 'customer_id' => $uid, 'current_stage_id' => mm_cp_stage_for( (int) $m, mm_cp_stages() ), 'name' => $c[1], 'name_norm' => mm_cp_norm_name( $c[1] ), 'birth_date' => $c[2], 'dob_consent_at' => $c[3] ? $now : null, 'is_primary' => 1, 'created_at' => $now, 'updated_at' => $now ) );
		if ( 'granted' === $c[4] ) { $wpdb->insert( $p . 'marketing_consent', array( 'customer_id' => $uid, 'status' => 'granted', 'granted_at' => $now ) ); }
		if ( 'withdrawn' === $c[4] ) { $wpdb->insert( $p . 'marketing_consent', array( 'customer_id' => $uid, 'status' => 'withdrawn', 'granted_at' => $now, 'withdrawn_at' => $now ) ); }
		$n++;
	}
	return "Đã tạo/làm mới $n tài khoản test (mm_test_a..f, role customer, email @example.invalid).";
}