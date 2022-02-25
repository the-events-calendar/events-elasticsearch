<?php

namespace TEC;

class Events_Elasticsearch {

	/**
	 * The current version of Elasticsearch Integration.
	 *
	 * @var string
	 */
	const VERSION = '2.0.0';

	/**
	 * Static Singleton Holder
	 *
	 * @since TBD
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Get (and instantiate, if necessary) the instance of the class
	 *
	 * @since TBD
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * @since TBD
	 */
	public function __construct() {


	}
}