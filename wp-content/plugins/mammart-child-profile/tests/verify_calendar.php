<?php
/** Regression test for the PHP calendar logic under the WordPress UTC default. */
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 404 );
    exit;
}

define( 'MM_CP_TZ', 'Asia/Ho_Chi_Minh' );
date_default_timezone_set( 'UTC' );
require dirname( __DIR__ ) . '/includes/age.php';

$cases = array(
    array( '2026-09-24', '2026-11-23T16:59:59Z', 1 ),
    array( '2026-09-24', '2026-11-23T17:00:00Z', 2 ),
    array( '2026-05-24', '2026-11-23T16:59:59Z', 5 ),
    array( '2026-05-24', '2026-11-23T17:00:00Z', 6 ),
    array( '2026-11-24', '2026-11-23T16:59:59Z', null ),
    array( '2026-11-24', '2026-11-23T17:00:00Z', 0 ),
    array( '2026-01-31', '2026-02-28T00:00:00+07:00', 0 ),
    array( '2026-01-31', '2026-03-01T00:00:00+07:00', 1 ),
);

foreach ( $cases as [ $birth_date, $time, $expected ] ) {
    $result = mm_cp_age_months( $birth_date, new DateTimeImmutable( $time ) );
    if ( $expected !== $result ) {
        fwrite( STDERR, sprintf( "%s at %s: expected %s, got %s\n", $birth_date, $time, var_export( $expected, true ), var_export( $result, true ) ) );
        exit( 1 );
    }
}
printf( "OK: %d calendar cases\n", count( $cases ) );
