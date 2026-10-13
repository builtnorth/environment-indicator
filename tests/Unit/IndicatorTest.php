<?php
/**
 * Tests for the Indicator class
 *
 * @package Siratik\EnvironmentIndicator\Tests\Unit
 */

namespace Siratik\EnvironmentIndicator\Tests\Unit;

use Siratik\EnvironmentIndicator\Indicator;
use Siratik\EnvironmentIndicator\Tests\TestCase;
use WP_Mock;
use Mockery;

/**
 * Test the Indicator class
 */
class IndicatorTest extends TestCase {

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset the singleton instance before each test
		$reflection = new \ReflectionClass( Indicator::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Stub wp_get_environment_type for the current test.
	 *
	 * @param string $env Environment slug to return.
	 */
	private function mock_environment_type( string $env ): void {
		WP_Mock::userFunction( 'wp_get_environment_type' )->andReturn( $env );
	}

	/**
	 * Stub the common "show indicator" gate as allowed.
	 *
	 * @param string $environment Environment slug passed to the filter.
	 */
	private function mock_show_allowed( string $environment = 'development' ): void {
		WP_Mock::userFunction( 'is_admin_bar_showing' )->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );
		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_environment_indicator_show', true, $environment )
			->andReturnUsing(
				static function ( $tag, $value ) {
					return $value;
				}
			);
	}

	/**
	 * Test singleton instance
	 */
	public function test_singleton_instance() {
		$instance1 = Indicator::instance();
		$instance2 = Indicator::instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Indicator::class, $instance1 );
	}

	/**
	 * Test boot method with environment
	 */
	public function test_boot_with_environment() {
		$this->mock_environment_type( 'development' );

		WP_Mock::expectActionAdded( 'admin_bar_menu', [ Indicator::instance(), 'add_environment_menu' ], 100 );
		WP_Mock::expectActionAdded( 'admin_head', [ Indicator::instance(), 'add_styles' ] );
		WP_Mock::expectActionAdded( 'wp_head', [ Indicator::instance(), 'add_styles' ] );

		$app = Indicator::instance();
		$app->boot();

		$this->assertEquals( 'development', $app->get_environment() );
		$this->assertConditionsMet();
	}

	/**
	 * Test boot with local environment registers hooks
	 */
	public function test_boot_with_local_environment() {
		$this->mock_environment_type( 'local' );

		WP_Mock::expectActionAdded( 'admin_bar_menu', [ Indicator::instance(), 'add_environment_menu' ], 100 );
		WP_Mock::expectActionAdded( 'admin_head', [ Indicator::instance(), 'add_styles' ] );
		WP_Mock::expectActionAdded( 'wp_head', [ Indicator::instance(), 'add_styles' ] );

		$app = Indicator::instance();
		$app->boot();

		$this->assertEquals( 'local', $app->get_environment() );
		$this->assertConditionsMet();
	}

	/**
	 * A name outside WordPress's four types is shown as a custom environment.
	 */
	public function test_boot_with_unknown_environment_shows_it_as_custom() {
		$this->mock_environment_type( 'qa' );

		WP_Mock::expectActionAdded( 'admin_bar_menu', [ Indicator::instance(), 'add_environment_menu' ], 100 );
		WP_Mock::expectActionAdded( 'admin_head', [ Indicator::instance(), 'add_styles' ] );
		WP_Mock::expectActionAdded( 'wp_head', [ Indicator::instance(), 'add_styles' ] );

		$app = Indicator::instance();
		$app->boot();

		$this->assertSame( 'qa', $app->get_environment() );
		$this->assertConditionsMet();
	}

	/**
	 * The environment filter replaces core's type with any name.
	 */
	public function test_environment_filter_replaces_the_core_type() {
		$this->mock_environment_type( 'production' );
		WP_Mock::onFilter( 'siratik_environment_indicator_environment' )
			->with( 'production' )
			->reply( 'Client-Review' );

		$app = Indicator::instance();
		$app->boot();

		$this->assertSame( 'client-review', $app->get_environment() );
	}

	/**
	 * Returning an empty name from the filter hides the indicator.
	 */
	public function test_environment_filter_can_hide_the_indicator() {
		$this->mock_environment_type( 'staging' );
		WP_Mock::onFilter( 'siratik_environment_indicator_environment' )
			->with( 'staging' )
			->reply( '' );

		WP_Mock::expectActionNotAdded( 'admin_bar_menu', [ Indicator::instance(), 'add_environment_menu' ] );

		$app = Indicator::instance();
		$app->boot();

		$this->assertSame( '', $app->get_environment() );
		$this->assertConditionsMet();
	}

	/**
	 * A custom environment is labelled with its name by default.
	 */
	public function test_custom_environment_is_labelled_with_its_name() {
		$this->mock_environment_type( 'client_review' );
		$this->mock_show_allowed( 'client_review' );

		WP_Mock::userFunction( 'esc_html' )
			->with( 'Client Review' )
			->andReturn( 'Client Review' );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( static function ( $args ) {
				return strpos( $args['title'], 'Client Review' ) !== false;
			} ) );

		$app = Indicator::instance();
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * A configured custom label replaces the name.
	 */
	public function test_custom_label_from_config_replaces_the_name() {
		$this->mock_environment_type( 'qa' );
		$this->mock_show_allowed( 'qa' );

		WP_Mock::userFunction( 'esc_html' )
			->with( 'Quality Assurance' )
			->andReturn( 'Quality Assurance' );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( static function ( $args ) {
				return strpos( $args['title'], 'Quality Assurance' ) !== false;
			} ) );

		$app = Indicator::instance();
		$app->set_config( [ 'custom' => [ 'text' => 'Quality Assurance' ] ] );
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test add environment menu
	 */
	public function test_add_environment_menu() {
		$this->mock_environment_type( 'development' );
		$this->mock_show_allowed( 'development' );

		WP_Mock::userFunction( 'esc_html' )
			->with( 'Development' )
			->andReturn( 'Development' );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( function( $args ) {
				return $args['id'] === 'environment-indicator' &&
					   strpos( $args['title'], 'Development' ) !== false &&
					   $args['parent'] === 'top-secondary';
			} ) );

		$app = Indicator::instance();
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test environment detection
	 */
	public function test_get_environment() {
		$this->mock_environment_type( 'development' );

		$app = Indicator::instance();
		$app->boot();

		$this->assertEquals( 'development', $app->get_environment() );
	}

	/**
	 * Test set_config merges partial overrides and affects menu title
	 */
	public function test_set_config() {
		$this->mock_environment_type( 'development' );
		$this->mock_show_allowed( 'development' );

		WP_Mock::userFunction( 'esc_html' )
			->with( 'Development' )
			->andReturn( 'Development' );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( static function ( $args ) {
				return strpos( $args['title'], 'Development' ) !== false
					&& strpos( $args['title'], 'Custom' ) === false;
			} ) );

		$app = Indicator::instance();
		$app->set_config(
			[
				'development' => [
					'color' => '#ff0000',
				],
			]
		);
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test set_config can replace the label
	 */
	public function test_set_config_replaces_text() {
		$this->mock_environment_type( 'development' );
		$this->mock_show_allowed( 'development' );

		WP_Mock::userFunction( 'esc_html' )
			->with( 'Custom Dev' )
			->andReturn( 'Custom Dev' );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( static function ( $args ) {
				return strpos( $args['title'], 'Custom Dev' ) !== false;
			} ) );

		$app = Indicator::instance();
		$app->set_config(
			[
				'development' => [
					'text' => 'Custom Dev',
				],
			]
		);
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test add_environment_menu doesn't add node when user lacks capability
	 */
	public function test_add_environment_menu_no_capability() {
		$this->mock_environment_type( 'development' );

		WP_Mock::userFunction( 'is_admin_bar_showing' )->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( false );
		WP_Mock::userFunction( 'apply_filters' )
			->andReturnUsing(
				static function ( $tag, $value ) {
					return $value;
				}
			);

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldNotReceive( 'add_node' );

		$app = Indicator::instance();
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test filter can deny visibility even when capability would allow
	 */
	public function test_add_environment_menu_filter_denies() {
		$this->mock_environment_type( 'development' );

		WP_Mock::userFunction( 'is_admin_bar_showing' )->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );
		WP_Mock::onFilter( 'siratik_environment_indicator_show' )
			->with( true, 'development' )
			->reply( false );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldNotReceive( 'add_node' );

		$app = Indicator::instance();
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test indicator is hidden when admin bar is not showing
	 */
	public function test_add_environment_menu_admin_bar_hidden() {
		$this->mock_environment_type( 'development' );

		WP_Mock::userFunction( 'is_admin_bar_showing' )->andReturn( false );

		$admin_bar = Mockery::mock( 'WP_Admin_Bar' );
		$admin_bar->shouldNotReceive( 'add_node' );

		$app = Indicator::instance();
		$app->boot();
		$app->add_environment_menu( $admin_bar );

		$this->assertConditionsMet();
	}

	/**
	 * Test add_styles outputs CSS
	 */
	public function test_add_styles() {
		$this->mock_environment_type( 'development' );
		$this->mock_show_allowed( 'development' );

		WP_Mock::userFunction( 'esc_attr' )
			->with( '#3858e9' )
			->andReturn( '#3858e9' );

		$app = Indicator::instance();
		$app->boot();

		ob_start();
		$app->add_styles();
		$output = ob_get_clean();

		$this->assertStringContainsString( '#3858e9', $output );
		$this->assertStringContainsString( '#wp-admin-bar-environment-indicator', $output );
		$this->assertConditionsMet();
	}
}
