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
	 * Required Events Calendar Version.
	 *
	 * @var string
	 */
	const REQUIRED_TEC_VERSION = '5.14.0';

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

		$this->plugin_path = trailingslashit( EVENTS_ELASTICSEARCH_DIR );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = trailingslashit( plugins_url( $this->plugin_dir ) );
		$this->plugin_slug = 'events-elasticsearch';

		// Hook to 11 to make sure this gets initialized after tec
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 11 );
	}

	/**
	 * Registers this plugin as being active for other tribe plugins and extensions
	 */
	protected function register_active_plugin() {
		$this->registered = new Plugin_Register();
	}

	/**
	 * Bootstrap the plugin with the plugins_loaded action.
	 *
	 * @since TBD
	 */
	public function plugins_loaded() {


		if ( ! $this->should_run() ) {
			// Display notice indicating which plugins are required
			add_action( 'admin_notices', [ $this, 'admin_notices' ] );

			return;
		}

		$this->register_active_plugin();

		$mopath = $this->plugin_dir . 'lang/';
		$domain = 'events-elasticsearch';

		// Start Up Common.
		\Tribe__Main::instance();

		add_action( 'tribe_common_loaded', [ $this, 'bootstrap' ], 0 );

		// This will load `wp-content/languages/plugins` files first
		\Tribe__Main::instance()->load_text_domain( $domain, $mopath );

		$this->pue = new PUE( "{$this->plugin_path}/events-elasticsearch.php" );
	}

	/**
	 * Load Text Domain on tribe_common_loaded as it requires common
	 *
	 * @since TBD
	 */
	public function bootstrap() {
		// Initialize the Service Provider for Tickets.
		tribe_register_provider( Provider::class );

		/**
		 * Fires once Events Elasticsearch has completed basic setup.
		 */
		do_action( 'tec_events_elasticsearch_plugin_loaded' );
	}

	/**
	 * Each plugin required by this one to run
	 *
	 * @param string $short_name   Shortened title of the plugin
	 * @param string $class        Main PHP class
	 * @param string $thickbox_url URL to download plugin
	 * @param string $min_version  Optional. Minimum version of plugin needed.
	 * @param string $ver_compare  Optional. Constant that stored the currently active version.
	 *                             }
	 *
	 * @return array {
	 *      List of required plugins.
	 *
	 */
	protected function get_requisite_plugins() {

		$requisite_plugins = [
			[
				'short_name'   => 'The Events Calendar',
				'class'        => 'Tribe__Events__Main',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true',
				'min_version'  => static::REQUIRED_TEC_VERSION,
				'ver_compare'  => 'Tribe__Events__Main::VERSION',
			],
			[
				'short_name'   => 'ElasticPress',
				'class'        => 'ElasticPress\\Elasticsearch',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=elasticpress&TB_iframe=true',
			],
		];

		return $requisite_plugins;

	}

	/**
	 * Should the plugin run? Are all of the appropriate items in place?
	 */
	public function should_run() {

		foreach ( $this->get_requisite_plugins() as $plugin ) {
			if ( ! class_exists( $plugin['class'] ) ) {
				return false;
			}

			if ( ! isset( $plugin['min_version'] ) || ! isset( $plugin['ver_compare'] ) ) {
				continue;
			}

			$active_version = constant( $plugin['ver_compare'] );

			if ( null === $active_version ) {
				return false;
			}

			if ( version_compare( $plugin['min_version'], $active_version, '>=' ) ) {
				return false;
			}
		}

		return true;

	}

	/**
	 * Hooked to the admin_notices action
	 */
	public function admin_notices() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$links = [];

		foreach ( $this->get_requisite_plugins() as $plugin ) {
			$links[] = sprintf( '<a href="%1$s" class="thickbox" title="%2$s">%3$s</a>', esc_attr( $plugin['thickbox_url'] ), esc_attr( $plugin['short_name'] ), esc_html( $plugin['short_name'] ) );
		}

		$message = sprintf( esc_html__( 'To begin using The Events Calendar: Elasticsearch Integration, please install and activate the latest versions of %1$s and %2$s.', 'tribe-events-elasticsearch' ), $links[0], $links[1] );

		printf( '<div class="error"><p>%s</p></div>', $message );

	}
}