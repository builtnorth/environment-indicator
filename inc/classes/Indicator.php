<?php

namespace Siratik\EnvironmentIndicator;

/**
 * Indicator: single entry point for the package.
 *
 * Named for what it is rather than for its role. `App` is the name every
 * consuming plugin and theme uses for its own root class, so importing this
 * package's entry point forced an alias at the call site — Kitaris core's
 * `Dependencies` carried `use Siratik\EnvironmentIndicator\App as
 * EnvironmentIndicator;` — aliasing it alongside two other packages' `App`
 * classes, all three in the same file.
 *
 * Call `Indicator::instance()->boot()` once. `boot()` detects the
 * environment and, when it recognises one, registers the admin-bar node and
 * its styles.
 */
class Indicator
{
	/**
	 * Holds the single instance of this class.
	 *
	 * @var Indicator|null
	 */
	protected static $instance = null;

	/**
	 * @var string The current environment
	 */
	private string $environment = '';

	/**
	 * @var array Environment configurations
	 */
	private array $config = [
		'local' => [
			'color' => '#7c3aed',
			'text' => 'Local'
		],
		'development' => [
			'color' => '#3858e9',
			'text' => 'Development'
		],
		'staging' => [
			'color' => '#db7800',
			'text' => 'Staging'
		],
		'production' => [
			'color' => '#0cb034',
			'text' => 'Production'
		],
		// Any environment name outside WordPress's four types (see the
		// siratik_environment_indicator_environment filter). An empty text
		// shows the environment name itself.
		'custom' => [
			'color' => '#64748b',
			'text' => ''
		]
	];

	/**
	 * Get the single instance of this class.
	 *
	 * @return Indicator
	 */
	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct()
	{
		// Constructor does nothing - initialization happens in boot()
	}


	/**
	 * Boot the environment indicator.
	 * This method should be called after getting the instance.
	 */
	public function boot(): void
	{
		$this->environment = $this->detect_environment();

		if ($this->environment !== '') {
			add_action('admin_bar_menu', [$this, 'add_environment_menu'], 100);
			add_action('admin_head', [$this, 'add_styles']);
			add_action('wp_head', [$this, 'add_styles']);
		}
	}

	/**
	 * Detect the current environment.
	 *
	 * Starts from WordPress core's `wp_get_environment_type()` (constant, getenv,
	 * then default), falling back to the `WP_ENVIRONMENT_TYPE` constant when the
	 * function is unavailable (e.g. very early bootstrap in tests). Core only
	 * knows local, development, staging and production, so the result can be
	 * replaced with any name through a filter.
	 */
	private function detect_environment(): string
	{
		$env = '';
		if (\function_exists('wp_get_environment_type')) {
			$env = (string) \wp_get_environment_type();
		} elseif (\defined('WP_ENVIRONMENT_TYPE')) {
			$env = (string) \WP_ENVIRONMENT_TYPE;
		}

		/**
		 * Filters the environment the indicator shows.
		 *
		 * Return any name to show an environment WordPress has no type for
		 * (e.g. 'qa'); names other than local, development, staging and
		 * production use the `custom` colour. Return '' to hide the indicator.
		 *
		 * @param string $env Environment type from WordPress core.
		 */
		$env = apply_filters('siratik_environment_indicator_environment', $env);

		return \is_string($env) ? strtolower((string) preg_replace('/[^A-Za-z0-9_-]/', '', $env)) : '';
	}

	/**
	 * Colour and label for the current environment. Unknown names use the
	 * `custom` entry, labelled with the name itself unless a label is set.
	 *
	 * @return array{color: string, text: string}
	 */
	private function display(): array
	{
		if ($this->environment !== 'custom' && isset($this->config[$this->environment])) {
			return $this->config[$this->environment];
		}

		$custom = $this->config['custom'];
		if (($custom['text'] ?? '') === '') {
			$custom['text'] = ucwords(str_replace(['-', '_'], ' ', $this->environment));
		}

		return $custom;
	}

	/**
	 * Whether the environment indicator should be shown to the current user.
	 */
	private function should_show_indicator(): bool
	{
		if (!is_admin_bar_showing()) {
			return false;
		}

		/**
		 * Filters whether the environment indicator is visible to the current user.
		 *
		 * @param bool   $show        Whether to show the indicator. Default checks `manage_options`.
		 * @param string $environment Current environment slug.
		 */
		return (bool) apply_filters(
			'siratik_environment_indicator_show',
			current_user_can('manage_options'),
			$this->environment
		);
	}

	/**
	 * Add the environment indicator to the admin bar
	 */
	public function add_environment_menu($admin_bar): void
	{
		if (!$this->should_show_indicator()) {
			return;
		}

		$config = $this->display();

		$admin_bar->add_node([
			'id'    => 'environment-indicator',
			'title' => sprintf('<span class="env-dot"></span> %s', esc_html($config['text'])),
			'parent' => 'top-secondary',
			'meta'  => [
				'class' => 'environment-indicator'
			]
		]);
	}

	/**
	 * Add required CSS styles
	 */
	public function add_styles(): void
	{
		if (!$this->should_show_indicator()) {
			return;
		}

		$config = $this->display();

?>
		<style>
			#wpadminbar #wp-admin-bar-environment-indicator {
				pointer-events: none;
				cursor: default;
			}

			#wpadminbar #wp-admin-bar-environment-indicator .ab-item {
				padding: 0 15px;
			}

			#wpadminbar #wp-admin-bar-environment-indicator .env-dot {
				display: inline-block;
				width: 6px;
				height: 6px;
				border-radius: 50%;
				border: 1px solid #ffffff;
				background: <?php echo esc_attr($config['color']); ?>;
				margin-right: 2px;
				margin-top: -2px;
				vertical-align: middle;
			}
		</style>
<?php
	}

	/**
	 * Get the current environment
	 */
	public function get_environment(): string
	{
		return $this->environment;
	}

	/**
	 * Set custom environment configuration.
	 *
	 * Per-environment arrays are merged recursively so a partial override
	 * (e.g. only `color`) keeps existing `text` / defaults.
	 */
	public function set_config(array $config): void
	{
		foreach ($config as $env => $settings) {
			if (!\is_array($settings)) {
				continue;
			}
			$this->config[$env] = \array_merge(
				$this->config[$env] ?? [],
				$settings
			);
		}
	}
}
