<?php

namespace TEC;

/**
 * Class Plugin_Register
 *
 * @since TBD
 */
class Plugin_Register extends \Tribe__Abstract_Plugin_Register {

	protected $main_class   = 'TEC\\Events_Elasticsearch';
	protected $dependencies = array(
		'parent-dependencies' => array(
			'Tribe__Events__Main'               => '5.14.0-dev',
		),
	);

	public function __construct() {
		$this->base_dir = \TRIBE_EVENTS_FILE;
		$this->version  = \Tribe__Events__Main::VERSION;

		$this->register_plugin();
	}
}
