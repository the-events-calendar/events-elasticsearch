<?php

class Tribe__Events__Elasticsearch__Main {

	/**
	 * Option name to save all plugin options under
	 * as a serialized array.
	 */
	const OPTIONNAME = 'tribe_events_elasticsearch_options';

	/**
	 * The current version of Elasticsearch Integration.
	 *
	 * @var string
	 */
	const VERSION = '0.1';

	/**
	 * Required Events Calendar Version.
	 *
	 * @var string
	 */
	const REQUIRED_TEC_VERSION = '4.5.4';

	/**
	 * Prefix to use for hooks.
	 *
	 * @var string
	 */
	private $hook_prefix = 'tribe-events-elasticsearch-';

	/**
	 * PUE object for handling updates.
	 *
	 * @var Tribe__Events__Elasticsearch__PUE
	 */
	private $pue;

	/**
	 * Option defaults to use.
	 *
	 * @var array
	 */
	public $option_defaults = array(
		'enable_events_elasticsearch' => true,
		'enable_ep_integrate'         => true,
		'enable_ep_integrate_admin'   => false,
	);

	/**
	 * Plugin directory name.
	 *
	 * @var string
	 */
	public $plugin_dir;

	/**
	 * Plugin local path.
	 *
	 * @var string
	 */
	public $plugin_path;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	public $plugin_slug;

	/**
	 * Plugin url.
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Singleton to instantiate the class.
	 *
	 * @return Tribe__Events__Elasticsearch__Main
	 */
	public static function instance() {

		/**
		 * @var $instance null|Tribe__Events__Elasticsearch__Main
		 */
		static $instance;

		if ( ! $instance ) {
			$instance = new self;
		}

		return $instance;

	}

	/**
	 * Constructor method.
	 */
	public function __construct() {

		$this->plugin_path = trailingslashit( EVENTS_ELASTICSEARCH_DIR );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = trailingslashit( plugins_url( $this->plugin_dir ) );
		$this->plugin_slug = 'events-elasticsearch';

		// Hook to 11 to make sure this gets initialized after tec
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );

	}

	/**
	 * Bootstrap the plugin with the plugins_loaded action.
	 */
	public function plugins_loaded() {

		$this->autoloading();
		$this->register_active_plugin();

		$mopath = $this->plugin_dir . 'lang/';
		$domain = 'tribe-events-elasticsearch';

		if ( class_exists( 'Tribe__Main' ) && ! is_admin() && ! class_exists( 'Tribe__Events__Elasticsearch__PUE__Helper' ) ) {
			tribe_main_pue_helper();
		}

		// If we don't have Common classes load the old fashioned way
		if ( ! class_exists( 'Tribe__Main' ) ) {
			load_plugin_textdomain( $domain, false, $mopath );
		} else {
			// This will load `wp-content/languages/plugins` files first
			Tribe__Main::instance()->load_text_domain( $domain, $mopath );
		}

		if ( ! $this->should_run() ) {
			// Display notice indicating which plugins are required
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );

			return;
		}

		$this->pue = new Tribe__Events__Elasticsearch__PUE( "{$this->plugin_path}/events-elasticsearch.php" );

		// Setup the EP integration
		tribe_singleton( 'tec.elasticsearch.elasticpress', new Tribe__Events__Elasticsearch__ElasticPress );

	}

	/**
	 * Registers this plugin as being active for other tribe plugins and extensions.
	 *
	 * @return bool Indicates if Tribe Common wants the plugin to run
	 */
	public function register_active_plugin() {

		if ( ! function_exists( 'tribe_register_plugin' ) ) {
			return true;
		}

		return tribe_register_plugin( EVENTS_ELASTICSEARCH_FILE, __CLASS__, self::VERSION );

	}

	/**
	 * Sets up class autoloading for this plugin
	 */
	public function autoloading() {

		if ( ! class_exists( 'Tribe__Autoloader' ) ) {
			return;
		}

		$autoloader = Tribe__Autoloader::instance();
		$autoloader->register_prefix( 'Tribe__Events__Elasticsearch__', dirname( __FILE__ ), 'events-elasticsearch' );
		$autoloader->register_autoloader();

	}

	/**
	 * Each plugin required by this one to run
	 *
	 * @return array {
	 *      List of required plugins.
	 *
	 *      @param string $short_name   Shortened title of the plugin
	 *      @param string $class        Main PHP class
	 *      @param string $thickbox_url URL to download plugin
	 *      @param string $min_version  Optional. Minimum version of plugin needed.
	 *      @param string $ver_compare  Optional. Constant that stored the currently active version.
	 * }
	 */
	protected function get_requisite_plugins() {

		$requisite_plugins = array(
			array(
				'short_name'   => 'The Events Calendar',
				'class'        => 'Tribe__Events__Main',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true',
				'min_version'  => self::REQUIRED_TEC_VERSION,
				'ver_compare'  => 'Tribe__Events__Main::VERSION',
			),
			array(
				'short_name'   => 'ElasticPress',
				'class'        => 'EP_API',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=elasticpress&TB_iframe=true',
			),
		);

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

		$links = array();

		foreach ( $this->get_requisite_plugins() as $plugin ) {
			$links[] = sprintf( '<a href="%1$s" class="thickbox" title="%2$s">%3$s</a>', esc_attr( $plugin['thickbox_url'] ), esc_attr( $plugin['short_name'] ), esc_html( $plugin['short_name'] ) );
		}

		$message = sprintf( esc_html__( 'To begin using The Events Calendar: Elasticsearch Integration, please install and activate the latest versions of %1$s, %2$s, %3$s, %4$s, and %5$s.', 'tribe-events-community-events' ), $links[0], $links[1], $links[2], $links[3], $links[4] );

		printf( '<div class="error"><p>%s</p></div>', $message );

	}

	/**
	 * Check whether the plugin is enabled.
	 *
	 * @return boolean
	 */
	public function is_enabled() {

		$options = get_option( self::OPTIONNAME, $this->option_defaults );

		if ( empty( $options['enable_events_elasticsearch'] ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Get the option from the main plugin options array.
	 *
	 * @param string     $option_name
	 * @param mixed|null $default
	 *
	 * @return mixed|null
	 */
	public static function get_option( $option_name, $default = null ) {

		$main = self::instance();

		$options = get_option( self::OPTIONNAME, $main->option_defaults );

		$option_value = null;

		if ( isset( $options[ $option_name ] ) ) {
			$option_value = $options[ $option_name ];
		}

		return $option_value;

	}

	/**
	 * Update the option for the main plugin options array.
	 *
	 * @param string $option_name
	 * @param mixed  $option_value
	 */
	public static function update_option( $option_name, $option_value ) {

		$options = get_option( self::OPTIONNAME );

		$options[ $option_name ] = $option_value;

		update_option( self::OPTIONNAME, $options, 'yes' );

	}

	/**
	 * Filter the community settings tab to include the plugin settings
	 *
	 * @param $settings array Field settings for the settings tab in the dashboard
	 */
	public function events_elasticsearch_settings( $settings ) {

		include $this->plugin_path . 'src/admin-views/elasticsearch-options.php';

		return $settings;

	}

}
