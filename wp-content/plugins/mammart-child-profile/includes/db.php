<?php
// S1: tạo 4 bảng + seed 3 Stage.
function mm_cp_install() {
	global $wpdb; require_once ABSPATH . "wp-admin/includes/upgrade.php";
	$p = $wpdb->prefix . "mm_"; $c = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$p}stage (
	 stage_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	 stage_name VARCHAR(60) NOT NULL,
	 min_age_months INT UNSIGNED NOT NULL,
	 max_age_months INT UNSIGNED NULL,
	 description VARCHAR(255) NULL,
	 display_order INT NOT NULL DEFAULT 0,
	 PRIMARY KEY  (stage_id)
	) $c;" );
	dbDelta( "CREATE TABLE {$p}child_profile (
	 child_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	 customer_id BIGINT UNSIGNED NOT NULL,
	 current_stage_id INT UNSIGNED NULL,
	 name VARCHAR(100) NOT NULL,
	 name_norm VARCHAR(100) NOT NULL,
	 birth_date DATE NOT NULL,
	 dob_consent_at DATETIME NULL,
	 is_primary TINYINT(1) NOT NULL DEFAULT 0,
	 created_at DATETIME NOT NULL,
	 updated_at DATETIME NOT NULL,
	 PRIMARY KEY  (child_id),
	 UNIQUE KEY uq_child (customer_id,name_norm,birth_date)
	) $c;" );
	dbDelta( "CREATE TABLE {$p}dob_change_request (
	 request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	 child_id BIGINT UNSIGNED NOT NULL,
	 old_birth_date DATE NOT NULL,
	 new_birth_date DATE NOT NULL,
	 status VARCHAR(10) NOT NULL DEFAULT \"pending\",
	 requested_at DATETIME NOT NULL,
	 reviewed_by BIGINT UNSIGNED NULL,
	 reviewed_at DATETIME NULL,
	 PRIMARY KEY  (request_id),
	 KEY idx_child_status (child_id,status)
	) $c;" );
	dbDelta( "CREATE TABLE {$p}marketing_consent (
	 consent_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	 customer_id BIGINT UNSIGNED NOT NULL,
	 status VARCHAR(10) NOT NULL,
	 granted_at DATETIME NULL,
	 withdrawn_at DATETIME NULL,
	 PRIMARY KEY  (consent_id),
	 KEY idx_customer (customer_id)
	) $c;" );
	if ( ! $wpdb->get_var( "SELECT COUNT(*) FROM {$p}stage" ) ) {
		$wpdb->query( "INSERT INTO {$p}stage (stage_name,min_age_months,max_age_months,display_order) VALUES (\"0–12 tháng\",0,12,1),(\"12–24 tháng\",12,24,2),(\"Từ 24 tháng\",24,NULL,3)" );
	}
}