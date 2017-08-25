<?php

namespace Tribe\Events\Elasticsearch;

use Tribe\Events\Elasticsearch\Tests\Test_Helper;

class ElasticPressTest extends \Tribe\Events\Elasticsearch\Tests\Integration\IntegrationTest_Abstract {

	/**
	 * Test ElasticPress connection exists.
	 *
	 * @test
	 */
	public function test_ep_connection() {

		$this->assertTrue( ep_elasticsearch_can_connect() );
		$this->assertTrue( ep_index_exists() );

	}

	/**
	 * Test ElasticPress WP_Query.
	 *
	 * @test
	 */
	public function test_ep_wp_query() {

		$args = array(
			'post_type'      => 'tribe_events',
			'posts_per_page' => 1,
			'ep_integrate'   => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->have_posts() );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 2511, $query->found_posts );

	}

	/**
	 * Test ElasticPress WP_Query with Geo.
	 *
	 * @test
	 */
	public function test_ep_wp_query_with_geo() {

		$args = array(
			'post_type'        => 'tribe_events',
			'posts_per_page'   => 1,
			'tribe_geoloc'     => true,
			'tribe_geoloc_lat' => '32.8182954',
			'tribe_geoloc_lng' => '-96.892848',
			'start_date'       => '2016-01-01',
			'eventDisplay'     => 'custom',
			'ep_integrate'     => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->have_posts() );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 253, $query->found_posts );

	}

}
