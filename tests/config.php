<?php
$travis = getenv( 'TRAVIS' );

if ( ! defined( 'WP_TESTS_FORCE_KNOWN_BUGS' ) ) {
	define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );
}

// Don't override config vars if running on Travis-CI
if ( ! empty( $travis ) ) {
	return;
}

// Paths
$constants = [
	'WP_CONTENT_DIR' => realpath( dirname( __FILE__ ) . '/../../../../wp-content' ),
	'ABSPATH'        => realpath( dirname( __FILE__ ) . '/../../../../wp' ) . '/',
];

foreach ( $constants as $key => $value ) {
	if ( defined( $key ) ) {
		continue;
	}

	define( $key, $value );
}