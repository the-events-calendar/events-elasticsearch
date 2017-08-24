<?php

namespace Tribe\Events\Elasticsearch\Tests\Integration;

use Tribe\Events\Elasticsearch\Tests\Test_Helper;

abstract class IntegrationTest_Abstract extends \Codeception\TestCase\WPTestCase {

	/**
	 * Code to run before tests.
	 */
	public function setUp() {

		parent::setUp();

		Test_Helper::before_test();

	}

	/**
	 * Code to run after tests.
	 */
	public function tearDown() {

		Test_Helper::after_test();

		parent::tearDown();

	}

}
