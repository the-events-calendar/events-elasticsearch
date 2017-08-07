<?php
/**
 * Handle ElasticPress integration with hooks and indexing.
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
	 * Constructor method.
	 */
	public function __construct() {

		$this->hook();

	}

	/**
	 * Add hooks for ElasticPress integration.
	 */
	public function hook() {

		// Handle auto integration of ElasticPress for TEC post types
		add_action( 'pre_get_posts', array( $this, 'auto_integrate' ) );

		// Whitelist post types
		add_filter( 'ep_indexable_post_types', array( $this, 'whitelist_post_types' ), 10, 1 );

		// Whitelist taxonomies
		add_filter( 'ep_sync_taxonomies', array( $this, 'whitelist_taxonomies' ), 10, 2 );

		// Whitelist meta keys
		add_filter( 'ep_prepare_meta_whitelist_key', array( $this, 'whitelist_meta_keys' ), 10, 3 );

	}

	/**
	 * Handle auto integration of ElasticPress for TEC post types.
	 *
	 * @todo Add option for auto integration for all queries, default on
	 * @todo Add option for auto integration for admin queries, default off
	 *
	 * @param WP_Query $query
	 */
	public function auto_integrate( $query ) {

		// Skip our logic if it's already integrated
		if ( true === $query->get( 'ep_integrate', false ) ) {
			return;
		}

		/**
		 * Enable admin integration for TEC post types.
		 *
		 * @param boolean  $admin_integration Whether to enable admin integration for TEC
		 * @param WP_Query $query             Current WP_Query object
		 */
		$admin_integration = apply_filters( 'tribe_event_elasticpress_admin', false, $query );

		if ( is_admin() && true !== $admin_integration ) {
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

		$post_types[ Tribe__Events__Main::POSTTYPE ]      = Tribe__Events__Main::POSTTYPE;
		$post_types[ Tribe__Events__Venue::POSTTYPE ]     = Tribe__Events__Venue::POSTTYPE;
		$post_types[ Tribe__Events__Organizer::POSTTYPE ] = Tribe__Events__Organizer::POSTTYPE;

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

}
