<?php
/**
 * Base test case for WP Environment Indicator
 *
 * @package Navas\EnvironmentIndicator\Tests
 */

namespace Navas\EnvironmentIndicator\Tests;

use WP_Mock;
use WP_Mock\Tools\TestCase as BaseTestCase;

/**
 * Base test case class
 */
abstract class TestCase extends BaseTestCase {

	/**
	 * Set up before each test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	/**
	 * Tear down after each test
	 *
	 * @return void
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}
}