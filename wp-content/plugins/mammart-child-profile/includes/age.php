<?php
// S2: tính tuổi (tháng) + khớp nhãn SKU. Hàm thuần, không phụ thuộc WordPress.

/** Ngày kỷ niệm thứ $n tháng; ngày 29-31 thiếu ngày -> ngày 1 tháng kế (Đ5). */
function mm_cp_anniversary( DateTimeImmutable $dob, int $n ): DateTimeImmutable {
	$y = (int) $dob->format( "Y" ); $m = (int) $dob->format( "n" ) + $n; $d = (int) $dob->format( "j" );
	$y += intdiv( $m - 1, 12 ); $m = ( $m - 1 ) % 12 + 1;
	$first = new DateTimeImmutable( sprintf( "%04d-%02d-01", $y, $m ) );
	if ( $d > (int) $first->format( "t" ) ) { return $first->modify( "+1 month" ); }
	return new DateTimeImmutable( sprintf( "%04d-%02d-%02d", $y, $m, $d ) );
}

/** Kiểm tra DOB. Trả ok | missing | invalid | future. */
function mm_cp_validate_dob( $dob_str, ?DateTimeImmutable $today = null ): string {
	$dob_str = trim( (string) $dob_str );
	if ( $dob_str === "" ) { return "missing"; }
	$dob = DateTimeImmutable::createFromFormat( "!Y-m-d", $dob_str );
	if ( ! $dob || $dob->format( "Y-m-d" ) !== $dob_str ) { return "invalid"; }
	$today = $today ?: new DateTimeImmutable( "today", new DateTimeZone( MM_CP_TZ ) );
	return $dob > $today->setTime( 0, 0 ) ? "future" : "ok";
}

/** Số tháng đã đủ; null nếu DOB thiếu/sai/tương lai. */
function mm_cp_age_months( $dob_str, ?DateTimeImmutable $today = null ): ?int {
	$today = $today ?: new DateTimeImmutable( "today", new DateTimeZone( MM_CP_TZ ) );
	if ( mm_cp_validate_dob( $dob_str, $today ) !== "ok" ) { return null; }
	$dob = new DateTimeImmutable( $dob_str ); $today = $today->setTime( 0, 0 );
	$n = ( (int) $today->format( "Y" ) - (int) $dob->format( "Y" ) ) * 12 + (int) $today->format( "n" ) - (int) $dob->format( "n" );
	while ( $n > 0 && mm_cp_anniversary( $dob, $n ) > $today ) { $n--; }
	while ( mm_cp_anniversary( $dob, $n + 1 ) <= $today ) { $n++; }
	return $n;
}

/** Stage theo nửa khoảng [min,max) (Đ15). */
function mm_cp_stage_for( int $months, array $stages ): ?int {
	foreach ( $stages as $s ) {
		if ( $months >= (int) $s["min_age_months"] && ( $s["max_age_months"] === null || $months < (int) $s["max_age_months"] ) ) { return (int) $s["stage_id"]; }
	}
	return null;
}

/** SKU khớp tuổi bé? true/false; null nếu SKU không có nhãn (theo cấu hình). */
function mm_cp_sku_matches( int $months, $min, $max_excl ): ?bool {
	if ( $min === null || $min === "" ) { return null; }
	return $months >= (int) $min && ( $max_excl === null || $max_excl === "" || $months < (int) $max_excl );
}