<?php

class Tribe__Events__Community__Tickets__Main {
	/**
	 * option name to save all plugin options under
	 * as a serialized array
	 */
	const OPTIONNAME = 'tribe_community_events_tickets_options';

	/**
	 * The current version of Community Events Tickets
	 */
	const VERSION = '4.4.4';

	/**
	 * required The Events Calendar Version
	 */
	const REQUIRED_TEC_VERSION = '4.5.4';

	const REQUIRED_ET_VERSION = '4.4';

	private $hook_prefix = 'tribe-events-community-tickets-';
	private $event_form;
	private $payment_options_form;
	private $templates;
	private $pue;
	public $option_defaults = array(
		'purchase_limit' => 0,
		'enable_community_tickets' => false,
		'enable_image_uploads' => true,
		'enable_split_payments' => false,
		'site_fee_type' => 'none',
		'site_fee_percentage' => 0,
		'site_fee_flat' => 0,
		'payment_fee_setting' => 'absorb',
		'paypal_sandbox' => false,
		'paypal_api_username' => '',
		'paypal_api_password' => '',
		'paypal_api_signature' => '',
		'paypal_application_id' => '',
		'paypal_receiver_email' => '',
		'paypal_invoice_prefix' => '',
	);
	private $payment_fee_setting_options = array(
		'absorb',
		'pass',
	);
	public $routes = array();
	public $plugin_dir;
	public $plugin_path;
	public $plugin_slug;
	public $plugin_url;

	/**
	 * singleton to instantiate the Tickets clas
	 */
	public static function instance() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self;
		}

		return $instance;
	}

	/**
	 * constructor!
	 */
	public function __construct() {
		$this->plugin_path = trailingslashit( EVENTS_COMMUNITY_TICKETS_DIR );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = trailingslashit( plugins_url( $this->plugin_dir ) );
		$this->plugin_slug = 'events-community-tickets';

		// hook to 11 to make sure this gets initialized after events-tickets-woo
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_cart_fees' ) );
	}

	/**
	 * bootstrap the plugin with the plugins_loaded action
	 */
	public function plugins_loaded() {
		$this->autoloading();
		$this->register_active_plugin();

		add_filter( 'user_has_cap', array( $this, 'user_has_edit_event_tickets_cap' ), 1, 3 );
		add_filter( 'user_has_cap', array( $this, 'user_has_sell_event_tickets_cap' ), 2, 3 );
		add_filter( 'user_has_cap', array( $this, 'user_has_edit_tribe_events_cap' ), 2, 3 );

		// this allows event owners to fetch/read orders on posts they own
		add_filter( 'user_has_cap', array( $this, 'user_has_read_private_shop_orders' ), 10, 3 );

		$mopath = $this->plugin_dir . 'lang/';
		$domain = 'tribe-events-community-tickets';

		if ( class_exists( 'Tribe__Main' ) && ! is_admin() && ! class_exists( 'Tribe__Events__Community__Tickets__PUE__Helper' ) ) {
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

		$this->pue = new Tribe__Events__Community__Tickets__PUE( "{$this->plugin_path}/events-community-tickets.php" );

		require_once $this->plugin_path . 'src/functions/template-tags.php';

		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_resources' ) );

			$this->event_form();
			$this->payment_options_form();
			$this->templates();

			add_filter( 'user_has_cap', array( $this, 'give_subscribers_upload_files_cap' ), 10, 3 );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'register_resources' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_resources' ), 11 );

		add_action( 'tribe_community_events_enqueue_resources', array( $this, 'maybe_enqueue_frontend' ) );

		add_filter( 'tribe_events_template_paths', array( $this, 'add_template_paths' ) );
		add_action( 'wp_router_generate_routes', array( $this, 'generate_routes' ) );
		add_action( 'tribe_ce_event_list_table_row_actions', array( $this, 'report_links' ) );

		add_filter( 'tribe_community_settings_tab', array( $this, 'community_tickets_settings' ) );

		if ( $this->is_split_payments_enabled() ) {
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_adapter' ) );
		}

		add_action( 'woocommerce_order_item_meta_start', array( $this, 'add_order_item_details' ), 10, 3 );

		add_action( 'wp_ajax_tribe-ticket-add-Tribe__Events__Tickets__Woo__Main', array( $this, 'ajax_handler_ticket_save' ), 9 );
		add_action( 'wp_ajax_tribe-ticket-edit-Tribe__Events__Tickets__Woo__Main', array( $this, 'ajax_handler_ticket_save' ), 9 );

		// determine if the ticket price can be updated
		add_filter( 'tribe_tickets_can_update_ticket_price', array( $this, 'can_update_ticket_price' ), 10, 2 );
		add_filter( 'tribe_tickets_disallow_update_ticket_price_message', array( $this, 'disallow_update_ticket_price_message' ), 10, 2 );

		add_action( 'tribe_tickets_plus_after_event_details_list', array( $this, 'order_report_organizer_data' ), 10, 2 );
		add_filter( 'tribe_events_orders_report_site_fees_note', array( $this, 'order_report_site_fees_note' ), 10, 2 );

		// Control the user's ability to delete tickets
		add_filter( 'tribe_tickets_current_user_can_delete_ticket', array( $this, 'can_delete_existing_ticket' ), 10, 2 );

		// compatibility with Event Tickets Plus
		add_action( 'wp_ajax_tribe-ticket-add-Tribe__Tickets__Woo__Main', array( $this, 'ajax_handler_ticket_save' ), 9 );
		add_action( 'wp_ajax_tribe-ticket-edit-Tribe__Tickets__Woo__Main', array( $this, 'ajax_handler_ticket_save' ), 9 );

		// ticket fee settings
		add_filter( 'tribe_tickets_pass_fees_to_user', array( $this, 'pass_fees_to_user' ), 10, 2 );
		add_filter( 'tribe_tickets_fee_percent', array( $this, 'get_fee_percent' ) );
		add_filter( 'tribe_tickets_fee_flat', array( $this, 'get_fee_flat' ) );

		// prevent the adding of too many items to the cart
		add_action( 'woocommerce_add_to_cart', array( $this, 'enforce_purchase_limit_on_add' ), 10, 2 );
		add_filter( 'woocommerce_stock_amount_cart_item', array( $this, 'enforce_purchase_limit_on_update' ), 10, 2 );
		add_filter( 'tribe_tickets_default_purchase_limit', array( $this, 'set_default_purchase_limit' ) );

		add_action( 'wootickets_tickets_after_quantity_input', array( $this, 'maybe_display_purchase_limit' ) );
	}

	/**
	 * register the community-tickets paths with TEC
	 */
	public function add_template_paths( $paths ) {
		$paths['community-tickets'] = $this->plugin_path;
		return $paths;
	}

	/**
	 * Registers this plugin as being active for other tribe plugins and extensions
	 *
	 * @return bool Indicates if Tribe Common wants the plugin to run
	 */
	public function register_active_plugin() {
		if ( ! function_exists( 'tribe_register_plugin' ) ) {
			return true;
		}

		return tribe_register_plugin( EVENTS_COMMUNITY_TICKETS_FILE, __CLASS__, self::VERSION );
	}

	/**
	 * Auto-appends the author ID onto the SKU
	 */
	public function ajax_handler_ticket_save() {
		if ( ! isset( $_POST['formdata'] ) ) {
			return;
		}

		if ( ! isset( $_POST['post_ID'] ) ) {
			return;
		}

		if ( ! $post = get_post( $_POST['post_ID'] ) ) {
			return;
		}

		$form_data = wp_parse_args( $_POST['formdata'] );

		if ( empty( $form_data['ticket_name'] ) ) {
			$form_data['ticket_name'] = 'ticket';
		}

		// make sure the SKU is set to the correct value
		$form_data['ticket_woo_sku'] = "{$post->ID}-{$post->post_author}-" . sanitize_title( $form_data['ticket_name'] );

		$_POST['formdata'] = http_build_query( $form_data );
	}

	/**
	 * Add the adapter.
	 *
	 * @param  array $methods WooCommerce payment methods.
	 *
	 * @return array          PayPal Adaptive Payments gateway.
	 */
	public function add_adapter( $methods ) {
		$methods[] = 'Tribe__Events__Community__Tickets__Adapter__WooCommerce_PayPal';

		return $methods;
	}

	/**
	 * gateway object accessor method
	 *
	 * @param $gateway string Which static gateway to retrieve
	 *
	 * @return array Array of instantiated gateways
	 */
	public function gateway( $gateway ) {
		static $gateways = array();

		if ( empty( $gateways[ $gateway ] ) ) {
			$gateway_class = "Tribe__Events__Community__Tickets__Gateway__{$gateway}";

			if ( ! class_exists( $gateway_class ) ) {
				return new WP_Error( "{$gateway_class} does not exist" );
			}

			$gateways[ $gateway ] = new $gateway_class;
		}

		return $gateways[ $gateway ];
	}

	/**
	 * Registers scripts and styles
	 */
	public function register_resources() {
		wp_register_script(
			'event-tickets',
			Tribe__Template_Factory::getMinFile( tribe_tickets_resource_url( 'tickets.js' ), true ),
			array( 'jquery', 'jquery-ui-datepicker' ),
			apply_filters( 'tribe_events_js_version', Tribe__Tickets__Main::VERSION )
		);

		wp_register_script(
			'events-community-tickets-admin',
			Tribe__Template_Factory::getMinFile( $this->plugin_url . 'src/resources/js/events-community-tickets-admin.js', true ),
			array( 'jquery' ),
			apply_filters( 'tribe_events_js_version', self::VERSION )
		);

		wp_register_script(
			'events-community-tickets',
			Tribe__Template_Factory::getMinFile( $this->plugin_url . 'src/resources/js/events-community-tickets.js', true ),
			array( 'jquery' , 'tribe-bumpdown' ),
			apply_filters( 'tribe_events_js_version', self::VERSION )
		);

		wp_register_style(
			'event-tickets',
			Tribe__Template_Factory::getMinFile( tribe_tickets_resource_url( 'tickets.css' ), true ),
			array(),
			apply_filters( 'tribe_events_css_version', Tribe__Tickets__Main::VERSION )
		);

		wp_register_style(
			'events-community-tickets-admin',
			Tribe__Template_Factory::getMinFile( $this->plugin_url . 'src/resources/css/events-community-tickets-admin.css', true ),
			array(),
			apply_filters( 'tribe_events_css_version', self::VERSION )
		);

		wp_register_style(
			'events-community-tickets',
			Tribe__Template_Factory::getMinFile( $this->plugin_url . 'src/resources/css/events-community-tickets.css', true ),
			array( 'tribe-bumpdown-css' ),
			apply_filters( 'tribe_events_css_version', self::VERSION )
		);
	}

	public function maybe_enqueue_admin_resources( $screen ) {
		if (
			'tribe_events_page_tribe-common' === $screen
		) {
			wp_enqueue_style( 'events-community-tickets-admin' );
			wp_enqueue_script( 'events-community-tickets-admin' );
		}

		if (
			'tribe_events_page_tickets-orders' === $screen
			|| 'tribe_events_page_tickets-attendees' === $screen
		) {
			wp_enqueue_style( 'events-community-tickets' );
			wp_enqueue_style( 'events-community-tickets-admin' );
		}
	}

	public function maybe_enqueue_frontend() {
		$nonces = array(
			'add_ticket_nonce'    => wp_create_nonce( 'add_ticket_nonce' ),
			'edit_ticket_nonce'   => wp_create_nonce( 'edit_ticket_nonce' ),
			'remove_ticket_nonce' => wp_create_nonce( 'remove_ticket_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script( 'events-community-tickets', 'TribeTickets', $nonces );

		if ( ! is_admin() ) {
			// enqueue the styles so our page nav looks ok
			wp_enqueue_style( 'events-community-tickets' );
		}
	}

	/**
	 * Event form object accessor method
	 */
	public function event_form() {
		if ( ! $this->event_form ) {
			$this->event_form = new Tribe__Events__Community__Tickets__Event_Form( $this );
		}

		return $this->event_form;
	}

	/**
	 * Payment options form object accessor method
	 */
	public function payment_options_form() {
		if ( ! $this->payment_options_form ) {
			$this->payment_options_form = new Tribe__Events__Community__Tickets__Payment_Options_Form;
			$this->payment_options_form->set_default( 'payment_fee_setting', $this->get_payment_fee_setting() );
		}

		return $this->payment_options_form;
	}

	/**
	 * Templates object accessor method
	 */
	public function templates() {
		if ( ! $this->templates ) {
			$this->templates = new Tribe__Events__Community__Tickets__Templates;
		}

		return $this->templates;
	}

	/**
	 * Sets up class autoloading for this plugin
	 */
	public function autoloading() {
		if ( ! class_exists( 'Tribe__Autoloader' ) ) {
			return;
		}

		$autoloader = Tribe__Autoloader::instance();
		$autoloader->register_prefix( 'Tribe__Events__Community__Tickets__', dirname( __FILE__ ), 'events-community-tickets' );
		$autoloader->register_autoloader();
	}

	/**
	 * Each plugin required by this one to run
	 *
	 * @return array {
	 *      Required plugin
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
				'short_name'   => 'Event Tickets',
				'class'        => 'Tribe__Tickets__Main',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=event-tickets&TB_iframe=true',
				'min_version'  => self::REQUIRED_ET_VERSION,
				'ver_compare'  => 'Tribe__Tickets__Main::VERSION',
			),
			array(
				'short_name'   => 'Event Tickets Plus',
				'class'        => 'Tribe__Tickets_Plus__Main',
				'thickbox_url' => '//theeventscalendar.com/product/wordpress-event-tickets-plus/?TB_iframe=true',
			),
			array(
				'short_name'   => 'The Events Calendar',
				'class'        => 'Tribe__Events__Main',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true',
				'min_version'  => self::REQUIRED_ET_VERSION,
				'ver_compare'  => 'Tribe__Events__Main::VERSION',
			),
			array(
				'short_name'   => 'Community Events',
				'class'        => 'Tribe__Events__Community__Main',
				'thickbox_url' => '//theeventscalendar.com/product/wordpress-community-events/?TB_iframe=true',
			),
			array(
				'short_name'   => 'WooCommerce',
				'class'        => 'WooCommerce',
				'thickbox_url' => 'plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true',
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
			$links[] = sprintf(
				'<a href="%1$s" class="thickbox" title="%2$s">%3$s</a>',
				esc_attr( $plugin['thickbox_url'] ),
				esc_attr( $plugin['short_name'] ),
				esc_html( $plugin['short_name'] )
			);
		}

		$message = sprintf(
			esc_html__( 'To begin using The Events Calendar: Community Events Tickets, please install and activate the latest versions of %1$s, %2$s, %3$s, %4$s, and %5$s.', 'tribe-events-community-events' ),
			$links[0], $links[1], $links[2], $links[3], $links[4]
		);

		printf(
			'<div class="error"><p>%s</p></div>',
			$message
		);
	}

	/**
	 * Add the upload_files capability to a user when they are uploading files
	 */
	public function give_subscribers_upload_files_cap( $all_caps, $caps, $args ) {
		// bail if there isn't a cap or user_id
		if ( empty( $caps[0] ) || empty( $args[1] ) ) {
			return $all_caps;
		}

		$cap = $caps[0];
		$user_id = absint( $args[1] );

		if ( 'upload_files' !== $cap ) {
			return $all_caps;
		}

		// if the user isn't logged in, bail
		if ( ! is_user_logged_in() ) {
			return $all_caps;
		}

		// if the user isn't uploading media, bail
		if ( false === strpos( $_SERVER['REQUEST_URI'], '/wp-admin/async-upload.php' ) ) {
			return $all_caps;
		}

		// if the user is originating the request from the dashboard, bail
		if ( false !== strpos( $_SERVER['HTTP_REFERER'], '/wp-admin/' ) ) {
			return $all_caps;
		}

		$base_url = get_bloginfo( 'url' );

		// if the request did not come from the site, bail
		if ( ! preg_match( "@^{$base_url}@", $_SERVER['HTTP_REFERER'] ) ) {
			return $all_caps;
		}

		$all_caps['upload_files'] = true;

		return $all_caps;
	}

	/**
	 * returns whether or not community tickets is enabled in settings
	 */
	public function is_enabled() {
		$options = get_option( self::OPTIONNAME );

		if ( empty( $options['enable_community_tickets'] ) ) {
			return false;
		}

		if (
			! empty( $options['enable_split_payments'] )
			&& (
				empty( $options['paypal_api_username'] )
				|| empty( $options['paypal_api_password'] )
				|| empty( $options['paypal_api_signature'] )
				|| empty( $options['paypal_application_id'] )
				|| empty( $options['paypal_receiver_email'] )
				|| empty( $options['paypal_invoice_prefix'] )
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * returns whether or not split payments are enabled
	 */
	public function is_split_payments_enabled() {
		$options = get_option( self::OPTIONNAME );

		if ( empty( $options['enable_split_payments'] ) ) {
			return false;
		}

		return (bool) $options['enable_split_payments'];
	}

	/**
	 * returns whether or not tickets are enabled for a given event
	 *
	 * @param mixed $event WP_Post or ID for event
	 *
	 * @return boolean
	 */
	public function is_enabled_for_event( $event ) {
		// if split payments are not enabled, we don't need to worry about the creator's paypal email address
		if ( ! $this->is_split_payments_enabled() ) {
			return true;
		}

		if ( ! $event ) {
			// if an event isn't passed in, assume we're creating an event and use the currently logged in user
			$user_id = get_current_user_id();
		} else {
			if ( ! $event instanceof WP_Post ) {
				if ( ! is_numeric( $event ) ) {
					return false;
				}

				$event = get_post( $event );
			}

			if ( ! $event ) {
				return false;
			}

			$user_id = $event->post_author;
		}

		$user_meta = $this->payment_options_form()->get_meta( $user_id );

		return filter_var( $user_meta['paypal_account_email'], FILTER_VALIDATE_EMAIL );
	}

	/**
	 * gets an event's payment fee setting
	 */
	public function get_payment_fee_setting( $event = null ) {
		if ( $event && ! $event instanceof WP_Post ) {
			$event = get_post( $event );
		}

		$options = get_option( self::OPTIONNAME, array() );

		$payment_fee_setting = isset( $options['payment_fee_setting'] ) ? $options['payment_fee_setting'] : $this->option_defaults['payment_fee_setting'];

		// if split payments are enabled, get the event creator's options
		if ( $event && $this->is_split_payments_enabled() ) {
			$event_creator = get_user_by( 'id', $event->post_author );
			$creator_options = Tribe__Events__Community__Tickets__Payment_Options_Form::get_meta( $event_creator->ID );

			if ( isset( $creator_options['payment_fee_setting'] ) ) {
				$payment_fee_setting = $creator_options['payment_fee_setting'];
			}
		}

		// default to absorb if the payment_fee_setting is unknown
		if ( ! in_array( $payment_fee_setting, $this->payment_fee_setting_options ) ) {
			$payment_fee_setting = $this->option_defaults['payment_fee_setting'];
		}

		return $payment_fee_setting;
	}

	/**
	 * add routes
	 */
	public function generate_routes( $router ) {
		$this->routes['payment-options'] = new Tribe__Events__Community__Tickets__Route__Payment_Options( $router );
		$this->routes['attendees-report'] = new Tribe__Events__Community__Tickets__Route__Attendees_Report( $router );
		$this->routes['sales-report'] = new Tribe__Events__Community__Tickets__Route__Sales_Report( $router );
	}

	/**
	 * Redirects if the user is not logged in
	 *
	 * @param int|null $event_id
	 */
	public function require_login( $event_id = null ) {
		if ( ! is_user_logged_in() ) {
			wp_redirect( events_get_events_link() );
			die;
		}

		if ( empty( $event_id ) ) {
			return;
		}

		$event = get_post( $event_id );

		if ( ! empty( $event ) && get_current_user_id() != $event->post_author ) {
			wp_redirect( events_get_events_link() );
			die;
		}
	}

	/**
	 * Hooked to the tribe_ce_event_list_table_row_actions action to add navigation for reports
	 */
	public function report_links( $post ) {
		if ( ! current_user_can( 'edit_event_tickets' ) ) {
			return;
		}

		if ( ! tribe_events_has_tickets( $post ) ) {
			return;
		}
		?>
		<br/>
		<strong><?php echo esc_html__( 'Reports:', 'tribe-events-community-tickets' ); ?></strong>
		<a href="<?php echo esc_url( $this->routes['attendees-report']->url( $post->ID ) ); ?>"><?php echo apply_filters( $this->hook_prefix . 'event-list-attendees-button-text', __( 'Attendees', 'tribe-events-community-tickets' ) ); ?></a>
		| <a href="<?php echo esc_url( $this->routes['sales-report']->url( $post->ID ) ); ?>"><?php echo apply_filters( $this->hook_prefix . 'event-list-sales-button-text', __( 'Sales', 'tribe-events-community-tickets' ) ); ?></a>
		<?php
	}

	/**
	 * Filter the community settings tab to include community tickets settings
	 *
	 * @param $settings array Field settings for the community settings tab in the dashboard
	 */
	public function community_tickets_settings( $settings ) {
		include $this->plugin_path . 'src/admin-views/payment-options.php';

		return $settings;
	}

	public function calculate_cart_fees( $wc_cart ) {
		$cart = new Tribe__Events__Community__Tickets__Cart;
		$cart->calculate_cart_fees( $wc_cart );
	}

	/**
	 * Get purchase limit
	 */
	public function get_purchase_limit( $ticket_id = null ) {
		$options = get_option( self::OPTIONNAME, self::instance()->option_defaults );

		$purchase_limit = isset( $options['purchase_limit'] ) ? absint( $options['purchase_limit'] ) : 0;

		if ( ! empty( $ticket_id ) ) {
			$ticket_limit = absint( get_post_meta( $ticket_id, '_ticket_purchase_limit', true ) );
			if ( '' !== $ticket_limit ) {
				$purchase_limit = absint( $ticket_limit );
			}
		}

		return $purchase_limit;
	}

	/**
	 * Determines whether or not the currently logged in user has the correct cap
	 *
	 * @param array $all_caps User capabilities
	 * @param array $caps Caps being checked
	 * @param array $args Additional user_cap args
	 */
	public function user_has_edit_event_tickets_cap( $all_caps, $caps, $args ) {
		static $options;

		// bail if there isn't a cap or user_id
		if ( empty( $caps[0] ) || empty( $args[1] ) ) {
			return $all_caps;
		}

		$cap = $caps[0];
		$user_id = $args[1];

		// bail if this isn't the cap we care about
		if ( 'edit_event_tickets' !== $cap ) {
			return $all_caps;
		}

		if ( ! $options ) {
			$options = get_option( self::OPTIONNAME );
		}

		if ( ! isset( $all_caps[ $cap ] ) ) {
			// assume the user has it - by default all users with accounts have it
			$all_caps[ $cap ] = (bool) $options['edit_event_tickets_cap'];
		}

		// if split payments is enabled, let users create tickets
		if ( ! $this->is_enabled() ) {
			$all_caps[ $cap ] = false;
			return $all_caps;
		}

		return $all_caps;
	}

	/**
	 * Determines whether or not the currently logged in user has the correct cap to sell tickets
	 * (has PayPal info entered if split payments is enabled)
	 *
	 * @param array $all_caps User capabilities
	 * @param array $caps Caps being checked
	 * @param array $args Additional user_cap args
	 */
	public function user_has_sell_event_tickets_cap( $all_caps, $caps, $args ) {
		static $options;

		// bail if there isn't a cap or user_id
		if ( empty( $caps[0] ) || empty( $args[1] ) ) {
			return $all_caps;
		}

		$cap = $caps[0];
		$user_id = $args[1];

		// bail if this isn't the cap we care about
		if ( 'sell_event_tickets' !== $cap ) {
			return $all_caps;
		}

		if ( ! $options ) {
			$options = get_option( self::OPTIONNAME );
		}

		if ( ! isset( $all_caps[ $cap ] ) ) {
			// assume the user has it - by default all users with accounts have it
			$all_caps[ $cap ] = user_can( $user_id, 'edit_event_tickets' );
		}

		// if split payments is enabled, let users create tickets
		if ( $this->is_split_payments_enabled() ) {
			// if enabled, make sure the user has their paypal email set
			$meta = get_user_meta( $user_id, Tribe__Events__Community__Tickets__Payment_Options_Form::$meta_key, true );
			if ( empty( $meta['paypal_account_email'] ) ) {
				$all_caps[ $cap ] = false;
				return $all_caps;
			}
		}

		return $all_caps;
	}

	/**
	 * Alter the read_private_shop_orders cap if the order contains a ticket that is attached to an event
	 * created by the current user
	 *
	 * @param array $all_caps User capabilities
	 * @param array $caps Caps being checked
	 * @param array $args Additional user_cap args
	 */
	public function user_has_read_private_shop_orders( $all_caps, $caps, $args ) {
		// bail if there isn't a cap or user_id
		if ( empty( $caps[0] ) || empty( $args[1] ) ) {
			return $all_caps;
		}

		$cap = $caps[0];
		$user_id = absint( $args[1] );

		// bail if this isn't the cap we care about
		if ( 'read_private_shop_orders' !== $cap ) {
			return $all_caps;
		}

		// if there isn't a post id for the order, bail
		if ( empty( $args[2] ) ) {
			return $all_caps;
		}

		$order_id = absint( $args[2] );

		$query_args = array(
			'post_type' => 'tribe_wooticket',
			'post_status' => 'publish',
			'meta_key' => '_tribe_wooticket_order',
			'meta_value' => $order_id,
		);

		$posts = get_posts( $query_args );
		foreach ( $posts as $post ) {
			$event_id = get_post_meta( $post->ID, '_tribe_wooticket_event', true );
			$event = get_post( $event_id );
			if ( $user_id === (int) $event->post_author ) {
				$all_caps[ $cap ] = true;
				return $all_caps;
			}
		}

		return $all_caps;
	}

	/**
	 * injects information about an order's line item
	 *
	 * @param int $item_id Item ID
	 * @param WC_Order_Item $item WooCommerce Order Item object
	 * @param WC_Order $order WooCommerce Order object
	 */
	public function add_order_item_details( $item_id, $item, $order ) {
		// if there isn't a product ID, bail
		if ( empty( $item['product_id'] ) ) {
			return;
		}

		// if there isn't a product post for the product id, bail
		if ( ! $product = get_post( $item['product_id'] ) ) {
			return;
		}

		// if there isn't an event ID, bail
		if ( ! $event_id = get_post_meta( $product->ID, '_tribe_wooticket_for_event', true ) ) {
			return;
		}

		// if there isn't an event post for the event ID, bail
		if ( ! $event = get_post( $event_id ) ) {
			return;
		}

		$title = get_the_title( $event_id );
		include Tribe__Events__Templates::getTemplateHierarchy( 'community-tickets/modules/email-item-event-details' );
	}

	/**
	 * injects organizer data into the orders report
	 *
	 * @param WP_Post $event Event post
	 * @param WP_User $organizer Community Organizer user object
	 */
	public function order_report_organizer_data( $event, $organizer ) {
		include Tribe__Events__Templates::getTemplateHierarchy( 'community-tickets/modules/orders-report-after-organizer' );
	}

	/**
	 * injects a meta note about site fees in the Order Report
	 *
	 * @param WP_Post $event Event post
	 * @param WP_User $organizer Community Organizer user object
	 */
	public function order_report_site_fees_note( $event, $organizer ) {
		$options = get_option( self::OPTIONNAME, self::instance()->option_defaults );
		$gateway = self::instance()->gateway( 'PayPal' );

		$flat = $gateway->fee_flat;
		$percentage = $gateway->fee_percentage;

		if ( 'flat' === $options['site_fee_type'] || 'flat-and-percentage' === $options['site_fee_type'] ) {
			$flat += (float) $options['site_fee_flat'];
		}

		if ( 'percentage' === $options['site_fee_type'] || 'flat-and-percentage' === $options['site_fee_type'] ) {
			$percentage += (float) $options['site_fee_percentage'];
		}

		$flat_message = sprintf(
			__( 'Site Fee: %s per order', 'tribe-events-community-events' ),
			esc_html( tribe_format_currency( number_format( $flat, 2 ) ) )
		);

		$percentage_message = sprintf(
			__( 'Site Fee Percentage: %s%%', 'tribe-events-community-events' ),
			esc_html( $percentage )
		);

		if ( 'flat' === $options['site_fee_type'] ) {
			return $flat_message;
		} elseif ( 'percentage' === $options['site_fee_type'] ) {
			return $percentage_message;
		} elseif ( 'flat-and-percentage' === $options['site_fee_type'] ) {
			return "{$flat_message}, {$percentage_message}";
		}
	}

	/**
	 * Filters whether or not the ticket price can be updated
	 *
	 * @param boolean $can_update Can the ticket price be updated?
	 * @param WP_Post $ticket Ticket object
	 *
	 * @return boolean
	 */
	public function can_update_ticket_price( $can_update, $ticket ) {
		if ( empty( $ticket->ID ) ) {
			return $can_update;
		}

		$total_sales = get_post_meta( $ticket->ID, 'total_sales', true );

		if ( $total_sales ) {
			$can_update = false;
		}

		return $can_update;
	}

	/**
	 * Filters the user's ability to delete existing tickets.
	 *
	 * The default behaviour is to disallow deletion of tickets once sales have
	 * been made. This can be overridden via the following filter hook, which
	 * this callback itself runs on:
	 *
	 *     tribe_tickets_current_user_can_delete_ticket
	 *
	 * @param bool   $can_delete
	 * @param int    $ticket_id
	 *
	 * @return bool
	 */
	public function can_delete_existing_ticket( $can_delete, $ticket_id ) {
		$event = tribe_events_get_ticket_event($ticket_id);
		if ( tribe_community_events_is_community_event( $event ) ) {
			$total_sales = get_post_meta( $ticket_id, 'total_sales', true );

			if ( $total_sales ) {
				return false;
			}
		}

		return $can_delete;
	}

	/**
	 * Filters the no-update message to display when updating tickets is disallowed
	 *
	 * @param string $message Message to display to user
	 * @param WP_Post $ticket Ticket object
	 *
	 * @return string
	 */
	public function disallow_update_ticket_price_message( $message, $ticket ) {
		if ( empty( $ticket->ID ) ) {
			return $message;
		}

		$total_sales = get_post_meta( $ticket->ID, 'total_sales', true );

		if ( $total_sales ) {
			$message = esc_html__( 'Editing ticket prices is not allowed once purchases have been made.', 'tribe-events-community-tickets' );
		}

		return $message;
	}

	/**
	 * Filters whether or not fees are being passed to the end user (purchaser)
	 *
	 * @param boolean $pass_fees Whether or not to pass fees to user
	 * @param int $event_id Event post ID
	 *
	 * return boolean
	 */
	public function pass_fees_to_user( $pass_fees, $event_id ) {
		$fee_setting = $this->get_payment_fee_setting( $event_id );

		return 'pass' === $fee_setting;
	}

	/**
	 * Filters the fee percentage to apply to a ticket/order
	 *
	 * @param float $fee_percent Fee percentage
	 *
	 * return float
	 */
	public function get_fee_percent( $fee_percent ) {
		return $this->gateway( 'PayPal' )->fee_percentage();
	}

	/**
	 * Filters the flat fee to apply to a ticket/order
	 *
	 * @param float $fee_flat Flat fee
	 *
	 * return float
	 */
	public function get_fee_flat( $fee_flat ) {
		return $this->gateway( 'PayPal' )->fee_flat();
	}

	/**
	 * Filters add-to-cart quantity to enforce purchase limit
	 *
	 * @param string $cart_item_key WooCommerce cart item index
	 * @param int $product_id WC_Product ID
	 */
	public function enforce_purchase_limit_on_add( $cart_item_key, $product_id ) {
		$item = WC()->cart->cart_contents[ $cart_item_key ];

		// if this isn't a product with an event, then let's not mess with the quantity
		if ( ! get_post_meta( $product_id, '_tribe_wooticket_for_event', true ) ) {
			return;
		}

		$purchase_limit = $this->get_purchase_limit( $product_id );

		if ( $item['quantity'] < $purchase_limit || 0 === $purchase_limit ) {
			return;
		}

		WC()->cart->set_quantity( $cart_item_key, $purchase_limit, false );
	}

	/**
	 * Filters update-cart quantity to enforce purchase limit
	 *
	 * @param int $quantity Amount to add/update
	 * @param string $cart_item_key WooCommerce cart item index
	 *
	 * @return int
	 */
	public function enforce_purchase_limit_on_update( $quantity, $cart_item_key ) {
		$product = WC()->cart->cart_contents[ $cart_item_key ];

		// if this isn't a product with an event, then let's not mess with the quantity
		if ( ! get_post_meta( $product['product_id'], '_tribe_wooticket_for_event', true ) ) {
			return $quantity;
		}

		$purchase_limit = $this->get_purchase_limit( $product['product_id'] );

		if ( $purchase_limit > 0 && $quantity > $purchase_limit ) {
			$quantity = $purchase_limit;
		}

		return $quantity;
	}

	/**
	 * Filters update-cart quantity to enforce purchase limit
	 *
	 * @param int $purchase_limit The purchase limit for an item
	 */
	public function set_default_purchase_limit( $purchase_limit ) {
		$purchase_limit = $this->get_purchase_limit();

		return $purchase_limit;
	}

	/**
	 * Maybe display a notice about purchase limits
	 */
	public function maybe_display_purchase_limit( $ticket ) {
		if ( empty( $ticket->ID ) ) {
			return;
		}

		$purchase_limit = $this->get_purchase_limit( $ticket->ID );

		if ( ! $purchase_limit ) {
			return;
		}

		?>
		<div class="tribe-events-community-tickets-purchase-limit">
			<?php
			printf(
				esc_html__( 'Limited to %1$s per order.', 'tribe-events-community-events' ),
				$purchase_limit
			);
			?>
		</div>
		<?php
	}

	/**
	 * Asserts whether the user is editing an own auto-draft event.
	 *
	 * @return bool
	 */
	protected function editing_ticket_on_own_autodraft( ) {
		if ( empty( $_POST['post_ID'] ) || empty( $_POST['nonce'] ) ) {
			return false;
		}

		$post = get_post( $_POST['post_ID'] );

		if ( empty( $post ) || ! tribe_is_event( $post->ID ) ) {
			return false;
		}

		$legit_actions = array( 'add_ticket_nonce', 'edit_ticket_nonce', 'remove_ticket_nonce' );
		do {
			$can = wp_verify_nonce( $_POST['nonce'], current( $legit_actions ) );
		} while ( ! $can && next( $legit_actions ) );

		return $can
		       && ( $post->post_author == get_current_user_id() )
		       && ( $post->post_status == 'auto-draft' );
	}

	/**
	 * Alter the `edit_tribe_events` cap if the request comes during an AJAX
	 * ticket save/update/remove operation on an event auto-draft.
	 *
	 * Even if the option that allows users that submitted Community
	 * Events to edit their events later is off users submitting the first
	 * draft of an event should still be able to create, update and remove
	 * tickets from the event draft.
	 *
	 * @param array $all_caps User capabilities
	 * @param array $caps Caps being checked
	 * @param array $args Additional user_cap args
	 */
	public function user_has_edit_tribe_events_cap( $all_caps, $caps ) {
		if ( empty( $caps[0] ) || 'edit_tribe_events' !== $caps[0] ) {
			return $all_caps;
		}

		if ( Tribe__Main::instance()->doing_ajax() && $this->editing_ticket_on_own_autodraft() ) {
			$all_caps['edit_tribe_events'] = true;
		}

		return $all_caps;
	}
}
