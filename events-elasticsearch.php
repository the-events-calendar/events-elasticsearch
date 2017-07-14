<?php
/*
Plugin Name: The Events Calendar: Elasticsearch integration
Description: This add-on provides Elasticsearch integration for The Events Calendar with the ElasticPress plugin.
Version: 0.1
Author: Modern Tribe, Inc.
Author URI: http://m.tri.be/21
Text Domain: tribe-events-elasticsearch
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

include EVENTS_ELASTICSEARCH_DIR . '/src/Tribe/Main.php';

if ( class_exists( 'Tribe__Events__Elasticsearch__Main' ) ) {
	Tribe__Events__Elasticsearch__Main::instance();
}
