<?php
/**
 * Handle ElasticPress integration with hooks and indexing.
 *
 * @todo Tribe__Events__Pro__Recurrence__Event_Query::include_parent_event(): Needs 'where' integration with EP ES args
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( class_exists( 'Tribe__Events__Elasticsearch__ElasticPress' ) ) {
	return;
}

class Tribe__Events__Elasticsearch__ElasticPress {

	/**
	 * Singleton to instantiate the class.
	 *
	 * @return Tribe__Events__Elasticsearch__ElasticPress
	 */
	public static function instance() {

		/**
		 * @var $instance null|Tribe__Events__Elasticsearch__ElasticPress
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

		if ( class_exists( 'Tribe__Events__Main' ) ) {
			$this->add_hooks();
		}

	}

	/**
	 * Add hooks for ElasticPress integration.
	 */
	public function add_hooks() {

		// Handle auto integration of ElasticPress for TEC post types
		add_action( 'pre_get_posts', array( $this, 'auto_integrate' ) );

		// Whitelist post types
		add_filter( 'ep_indexable_post_types', array( $this, 'whitelist_post_types' ), 10, 1 );

		// Whitelist taxonomies
		add_filter( 'ep_sync_taxonomies', array( $this, 'whitelist_taxonomies' ), 10, 2 );

		// Whitelist meta keys
		add_filter( 'ep_prepare_meta_whitelist_key', array( $this, 'whitelist_meta_keys' ), 10, 3 );

		// Add ElasticPress integration for TEC queries
		add_action( 'tribe_events_pre_get_posts', array( $this, 'add_ep_query_integration' ), 9 );

		// Remove TEC wpdb overrides for WP_Query
		add_action( 'tribe_events_pre_get_posts', array( $this, 'remove_tec_wpdb_overrides' ) );

		// Override custom SQL query used by TEC for month view template.
		add_action( 'tribe_events_month_get_events_in_month', array( $this, 'override_tec_events_in_month' ), 10, 3 );

		// Override ElasticPress post_date used with _EventStartDate
		add_filter( 'ep_post_sync_args', array( $this, 'override_ep_post_date' ), 10, 2 );

		// @todo Add EP mapping config overrides for TEC geo_point value of venue

	}

	/**
	 * Handle auto integration of ElasticPress for TEC post types.
	 *
	 * @param WP_Query $query
	 */
	public function auto_integrate( $query ) {

		// Skip our logic if it's already integrated
		if ( true === $query->get( 'ep_integrate', false ) ) {
			return;
		}

		// Check if auto integration is enabled
		$integration       = Tribe__Events__Elasticsearch__Main::get_option( 'enable_ep_integrate', true );
		$admin_integration = Tribe__Events__Elasticsearch__Main::get_option( 'enable_ep_integrate_admin', false );

		if ( true !== $integration ) {
			// Auto integration is not enabled
			return;
		} elseif ( is_admin() && true !== $admin_integration ) {
			// Auto integration for admin is not enabled
			return;
		}

		// Get list of queried post types
		$queried_post_types = (array) $query->get( 'post_type', array() );

		// Get list of TEC post types
		$tec_post_types = $this->whitelist_post_types();

		// Check if TEC post types are in the currently queried post types list
		foreach ( $tec_post_types as $tec_post_type ) {
			if ( in_array( $tec_post_type, $queried_post_types, true ) ) {
				$query->set( 'ep_integrate', true );

				break;
			}
		}

	}

	/**
	 * Whitelist the TEC post types for indexing.
	 *
	 * @param array $post_types List of post types to include
	 *
	 * @return array
	 */
	public function whitelist_post_types( $post_types = array() ) {

		$post_types[ Tribe__Events__Main::POSTTYPE ]            = Tribe__Events__Main::POSTTYPE;
		$post_types[ Tribe__Events__Main::VENUE_POST_TYPE ]     = Tribe__Events__Main::VENUE_POST_TYPE;
		$post_types[ Tribe__Events__Main::ORGANIZER_POST_TYPE ] = Tribe__Events__Main::ORGANIZER_POST_TYPE;

		return $post_types;

	}

	/**
	 * Whitelist the TEC taxonomies for indexing.
	 *
	 * @param array        $taxonomies List of taxonomies to include
	 * @param WP_Post|null $post       Post object
	 *
	 * @return array
	 */
	public function whitelist_taxonomies( $taxonomies = array(), $post = null ) {

		// Only include TEC taxonomies if this is a TEC post type (or null $post)
		if ( null === $post || Tribe__Events__Main::POSTTYPE === $post->post_type ) {
			// Only include if it's not arleady included
			if ( ! in_array( Tribe__Events__Main::TAXONOMY, $taxonomies, true ) ) {
				$taxonomies[] = Tribe__Events__Main::TAXONOMY;
			}
		}

		return $taxonomies;

	}

	/**
	 * Whitelist the TEC protected meta keys for indexing.
	 *
	 * @param boolean|false $is_whitelisted Whether the meta key is whitelisted (false by default)
	 * @param string        $key            Meta key
	 * @param WP_Post       $post           Post object
	 *
	 * @return boolean array
	 */
	public function whitelist_meta_keys( $is_whitelisted, $key, $post ) {

		// Get list of TEC post types
		$tec_post_types = $this->whitelist_post_types();

		// Only include TEC taxonomies if this is a TEC post type (or null $post)
		if ( null === $post || in_array( $post->post_type, $tec_post_types, true ) ) {
			// Only include protected meta keys that start with the TEC post type prefixes
			if ( preg_match( '/^(_Event|_Venue|_Organizer)/i', $key ) ) {
				$is_whitelisted = true;
			}
		}

		return $is_whitelisted;

	}

	/**
	 * Add ElasticPress integration for TEC queries.
	 *
	 * @param WP_Query $query Query object
	 */
	public function add_ep_query_integration( $query ) {

		// Convert our WHERE SQL logic to WP_Query for ElasticPress to use
		$this->add_ep_query_integration_where( $query );

		// Convert our ORDER BY SQL logic to WP_Query for ElasticPress to use
		$this->add_ep_query_integration_orderby( $query );

	}

	/**
	 * Remove TEC wpdb overrides for WP_Query.
	 *
	 * @param WP_Query $query Query object
	 */
	public function remove_tec_wpdb_overrides( $query ) {

		// Skip our logic if EP isn't integrated with this query
		if ( false === $query->get( 'ep_integrate', false ) ) {
			return;
		}

		remove_filter( 'posts_fields', array( 'Tribe__Events__Query', 'posts_fields' ) );
		remove_filter( 'posts_fields', array( 'Tribe__Events__Query', 'multi_type_posts_fields' ) );
		remove_filter( 'posts_join', array( 'Tribe__Events__Query', 'posts_join' ) );
		remove_filter( 'posts_join', array( 'Tribe__Events__Query', 'posts_join_venue_organizer' ) );

		remove_filter( 'posts_where', array( 'Tribe__Events__Query', 'posts_where' ) );

		remove_filter( 'posts_orderby', array( 'Tribe__Events__Query', 'posts_orderby' ) );

	}

	/**
	 * Add ElasticPress 'where' integration for TEC queries.
	 *
	 * @param WP_Query $query Query object
	 */
	public function add_ep_query_integration_where( $query ) {

		$dates = $this->get_tec_dates_from_query( $query );

		$start_date = $dates['start'];
		$end_date   = $dates['end'];

		$meta_query = $query->get( 'meta_query', array() );

		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		if ( '' !== $start_date || ! empty( $meta_query['relation'] ) && 'OR' === strtoupper( $meta_query['relation'] ) ) {
			// EP doesn't do date ranges with date_query well for our purposes (nested)
			// We can't do nested 'relation' since this filtering needs to be 'AND' so it adds to main query
			// The below hook above also handles cases like: '' !== $start_date && '' !== $end_date

			// We handle date ranges in EP API args handler
			add_filter( 'ep_formatted_args', array( $this, 'add_ep_api_integration_where' ), 10, 2 );
		} elseif ( '' !== $end_date ) {
			// Handle using a simple meta_query arg
			$meta_query[] = array(
				'key'     => '_EventStartDate',
				'value'   => $end_date,
				'compare' => '<=', // wp_postmeta.meta_value <= $value
				'type'    => 'DATE',
			);
		}

	}

	/**
	 * Add ElasticPress 'orderby' integration for TEC queries.
	 *
	 * @param WP_Query $query Query object
	 */
	public function add_ep_query_integration_orderby( $query ) {

		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		$orderby = $query->get( 'orderby' );
		$order   = $query->get( 'order' );

		// We store _EventStartDate as post_date, we filter data being sent to Elasticsearch to make adjustments

		if ( 'event_date' === $orderby ) {
			$query->set( 'orderby', 'date' );
		} elseif ( "TIMESTAMP( $wpdb->postmeta.meta_value ) ID" === $orderby ) {
			$query->set( 'orderby', 'date ID' );
		} elseif ( in_array( $orderby, array( 'title', 'menu_order' ), true ) ) {
			$query->set( 'orderby', $orderby . ' date' );
		} elseif ( 'venue' === $orderby ) {
			/**
			 * We need to be able to sort by venue.post_title here
			 * but Elasticsearch can't do that without a custom ES script.
			 *
			 * We could store venue title in Elasticsearch post meta
			 * being indexed and update it any time someone updates/deletes
			 * a venue that matches (by ID).
			 *
			 * There aren't presently any great options for this, they are
			 * all hacks unfortunately.
			 */
		} elseif ( 'organizer' === $orderby ) {
			/**
			 * We need to be able to sort by organizer.post_title here
			 * but Elasticsearch can't do that without a custom ES script.
			 *
			 * We could store organizer title in Elasticsearch post meta
			 * being indexed and update it any time someone updates/deletes
			 * an organizer that matches (by ID).
			 *
			 * There aren't presently any great options for this, they are
			 * all hacks unfortunately.
			 */
		}

	}

	/**
	 * Add Elasticsearch 'where' integration for TEC queries.
	 *
	 * @param array $formatted_args Elasticsearch formatted args
	 * @param array $args           WP_Query args
	 *
	 * @return array
	 */
	public function add_ep_api_integration_where( $formatted_args, $args ) {

		$dates = $this->get_tec_dates_from_query( $args );

		$start_date = $dates['start'];
		$end_date   = $dates['end'];

		if ( '' !== $start_date && '' !== $end_date ) {
			// Handle full date range

			/**
			 * Converted from:
			 *
			 * $wpdb->prepare( "( %s <= meta._EventStartDate.date AND meta._EventStartDate.date <= %s )", $start_date, $end_date )
			 */
			$date_query_args = array(
				array(
					'column'    => 'meta._EventStartDate.date',
					'after'     => $start_date,
					'before'    => $end_date,
					'inclusive' => true,
				),
			);

			$date_query = new EP_WP_Date_Query( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if ( ! empty( $date_filter['and'] ) ) {
				$formatted_args['query']['bool']['should'][] = $date_filter['and'];
			}

			/**
			 * Converted from:
			 *
			 * $wpdb->prepare( "( %s <= meta._EventEndDate.date AND meta._EventStartDate.date <= %s )", $start_date, $end_date )
			 */
			$date_query_args = array(
				array(
					'column'    => 'meta._EventEndDate.date',
					'after'     => $start_date,
					'inclusive' => true,
				),
				array(
					'column'    => 'meta._EventStartDate.date',
					'before'    => $end_date,
					'inclusive' => true,
				),
			);

			$date_query = new EP_WP_Date_Query( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if ( ! empty( $date_filter['and'] ) ) {
				$formatted_args['query']['bool']['should'][] = $date_filter['and'];
			}

			/**
			 * Converted from:
			 *
			 * $wpdb->prepare( "( meta._EventStartDate.date < %s AND %s <= meta._EventEndDate.date )", $start_date, $end_date )
			 */
			$date_query_args = array(
				array(
					'column'    => 'meta._EventStartDate.date',
					'before'     => $start_date,
				),
				array(
					'column'    => 'meta._EventEndDate.date',
					'after'     => $end_date,
					'inclusive' => true,
				),
			);

			$date_query = new EP_WP_Date_Query( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if ( ! empty( $date_filter['and'] ) ) {
				$formatted_args['query']['bool']['should'][] = $date_filter['and'];
			}
		} elseif ( '' !== $start_date ) {
			// Handle partial date range

			/**
			 * Converted from:
			 *
			 * $wpdb->prepare( "%s <= meta._EventStartDate.date", $start_date )
			 */
			$date_query_args = array(
				array(
					'column'    => 'meta._EventStartDate.date',
					'after'     => $start_date,
					'inclusive' => true,
				),
			);

			$date_query = new EP_WP_Date_Query( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if ( ! empty( $date_filter['and'] ) ) {
				$formatted_args['query']['bool']['should'][] = $date_filter['and'];
			}

			/**
			 * Converted from:
			 *
			 * $wpdb->prepare( "( meta._EventStartDate.date <= %s AND %s <= meta._EventEndDate.date )", $start_date, $start_date )
			 */
			$date_query_args = array(
				array(
					'column'    => 'meta._EventStartDate.date',
					'before'    => $start_date,
					'inclusive' => true,
				),
				array(
					'column'    => 'meta._EventEndDate.date',
					'after'     => $start_date,
					'inclusive' => true,
				),
			);

			$date_query = new EP_WP_Date_Query( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if ( ! empty( $date_filter['and'] ) ) {
				$formatted_args['query']['bool']['should'][] = $date_filter['and'];
			}

			if ( $query->is_singular() && $query->get( 'eventDate' ) ) {
				// AND this
				$tomorrow        = date( 'Y-m-d', strtotime( $query->get( 'eventDate' ) . ' +1 day' ) );

				/**
				 * Converted from:
				 *
				 * $tomorrow_clause = $wpdb->prepare( "meta._EventStartDate.date < %s", $tomorrow )
				 */
				$date_query_args = array(
					array(
						'column'    => 'meta._EventStartDate.date',
						'before'    => $tomorrow,
					),
				);

				$date_query = new EP_WP_Date_Query( $args['date_query'] );

				$date_filter = $date_query->get_es_filter();

				if ( ! empty( $date_filter['and'] ) ) {
					$formatted_args['query']['bool']['must'][] = $date_filter['and'];
				}
			}
		} elseif ( '' !== $end_date ) {
			// See Tribe__Events__Elasticsearch__ElasticPress::add_ep_query_integration_where
		}

		return $formatted_args;

	}

	/**
	 * Get the TEC start and end dates from the WP_Query object.
	 *
	 * @param WP_Query|array $query Query object or array arguments
	 *
	 * @return array
	 */
	public function get_tec_dates_from_query( $query ) {

		$start_date = '';
		$end_date   = '';

		// Code below taken from Tribe__Events_Query::posts_where()

		// if it's a true event query then we to setup where conditions
		// but only if we aren't grabbing a specific post
		if (
			(
				is_array( $query )
				&& (
					! empty( $query['start_date'] )
					|| ! empty( $query['end_date'] )
				)
			)
			|| (
				(
					$query->tribe_is_event
					|| $query->tribe_is_event_category
				)
				&& empty( $query->query_vars['name'] )
				&& empty( $query->query_vars['p'] )
			)
		) {
			$start_date = $query->get( 'start_date' );
			$end_date   = $query->get( 'end_date' );
			$use_utc    = Tribe__Events__Timezones::is_mode( 'site' );
			$site_tz    = $use_utc ? Tribe__Events__Timezones::wp_timezone_string() : null;

			// Sitewide timezone mode: convert the start date - if set - to UTC
			if ( $use_utc && ! empty( $start_date ) ) {
				$start_date = Tribe__Events__Timezones::to_utc( $start_date, $site_tz );
			}

			// Sitewide timezone mode: convert the end date - if set - to UTC
			if ( $use_utc && ! empty( $end_date ) ) {
				$end_date = Tribe__Events__Timezones::to_utc( $end_date, $site_tz );
			}
		}

		$dates = array(
			'start' => $start_date,
			'end'   => $end_date,
		);

		return $dates;

	}

	/**
	 * Override ElasticPress post_date used with _EventStartDate.
	 *
	 * @param array $post_args Post arguments sent to Elasticsearch for indexing
	 * @param int   $post_id   Post ID
	 *
	 * @return array
	 */
	public function override_ep_post_date( $post_args, $post_id ) {

		if ( Tribe__Events__Main::POSTTYPE === $post_args['post_type'] ) {
			$start_date = get_post_meta( $post_id, '_EventStartDate', true );

			if ( ! empty( $start_date ) ) {
				$site_tz = $use_utc ? Tribe__Events__Timezones::wp_timezone_string() : null;

				$post_args['post_date']     = $start_date;
				$post_args['post_date_gmt'] = Tribe__Events__Timezones::to_utc( $start_date, $site_tz );
			}
		}

		return $post_args;

	}

	/**
	 * Override custom SQL query used by TEC for month view template.
	 *
	 * @param null|array $events_in_month An array of events in month (default null, run the SQL query like normal)
	 * @param string     $start_date      The start date to filter the queried events by
	 * @param string     $end_date        The end date to filter the queried events by
	 *
	 * @return null|array
	 */
	public function override_tec_events_in_month( $events_in_month, $start_date, $end_date ) {

		$args = array(
			'post_type'      => Tribe__Events__Main::POSTTYPE,
			'fields'         => 'ids',
			'posts_per_page' => 500,
		);

		$event_query = new WP_Query( $args );

		$posts = $event_query->posts;

		$events_in_month = array();

		foreach ( $posts as $post_id ) {
			$event_start_date = get_post_meta( $post_id, '_EventStartDate', true );
			$event_end_date   = get_post_meta( $post_id, '_EventEndDate', true );

			$events_in_month[] = (object) array(
				'ID'             => $post_id,
				'EventStartDate' => $event_start_date,
				'EventEndDate'   => $event_end_date,
			);
		}

		return $events_in_month;

	}

}
