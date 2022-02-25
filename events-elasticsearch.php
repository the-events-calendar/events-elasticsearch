<?php
/*
Plugin Name: The Events Calendar: Elasticsearch Integration
Description: This add-on provides Elasticsearch integration for The Events Calendar plugins.
Version: 2.0
Author: The Events Calendar
Author URI: https://evnt.is/1aor
Text Domain: events-elasticsearch
License: GPLv2 or later
*/

/*
Copyright 2017 by Modern Tribe Inc and the contributors

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define( 'EVENTS_ELASTICSEARCH_DIR', dirname( __FILE__ ) );
define( 'EVENTS_ELASTICSEARCH_FILE', __FILE__ );

if ( defined( 'EVENTS_ELASTIC_2' ) && EVENTS_ELASTIC_2 ) {
	// Load the required php min version functions
	require_once dirname( EVENTS_ELASTICSEARCH_FILE ) . '/src/functions/php-min-version.php';

	// Load the Composer autoload file.
	require_once dirname( EVENTS_ELASTICSEARCH_FILE ) . '/vendor/autoload.php';

	/**
	 * Verifies if we need to warn the user about min PHP version and bail to avoid fatals
	 */
	if ( tribe_is_not_min_php_version() ) {
		tribe_not_php_version_textdomain( 'events-elasticsearch', EVENTS_ELASTICSEARCH_FILE );

		/**
		 * Include the plugin name into the correct place
		 *
		 * @since  4.10
		 *
		 * @param array $names current list of names
		 *
		 * @return array
		 */
		function tribe_tickets_not_php_version_plugin_name( $names ) {
			$names['events-elasticsearch'] = esc_html__( 'Events Elasticsearch', 'event-tickets' );

			return $names;
		}

		add_filter( 'tribe_not_php_version_names', 'tribe_tickets_not_php_version_plugin_name' );
		if ( ! has_filter( 'admin_notices', 'tribe_not_php_version_notice' ) ) {
			add_action( 'admin_notices', 'tribe_not_php_version_notice' );
		}

		return false;
	}

	// the main plugin class
	require_once EVENTS_ELASTICSEARCH_DIR . '/src/TEC/Events_Elasticsearch.php';

	return \TEC\Events_Elasticsearch::instance();
}

include EVENTS_ELASTICSEARCH_DIR . '/src/Tribe/Main.php';

if ( class_exists( 'Tribe__Events__Elasticsearch__Main' ) ) {
	Tribe__Events__Elasticsearch__Main::instance();
}
