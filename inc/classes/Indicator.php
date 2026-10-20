<?php

declare(strict_types=1);

namespace Siratik\EnvironmentIndicator;

defined('ABSPATH') || exit;

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
 * environment and, when it recognizes one, registers the admin-bar node and
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
	 * Environment configurations.
	 *
	 * Colors come from WordPress's admin notice palette, graded by how careful
	 * to be: green for local and development (safe to break), amber staging,
	 * red production.
	 *
	 * @var array
	 */
	private array $config = [
		'local' => [
			'color' => '#4ab866',
			'text' => 'Local'
		],
		'development' => [
			'color' => '#4ab866',
			'text' => 'Development'
		],
		'staging' => [
			'color' => '#f0b849',
			'text' => 'Staging'
		],
		'production' => [
			'color' => '#cc1818',
			'text' => 'Production'
		],
		// A custom environment name (the SIRATIK_ENVIRONMENT_INDICATOR_CUSTOM
		// constant or the siratik_environment_indicator_custom filter). An
		// empty text shows the name itself.
		'custom' => [
			'color' => '#949494',
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
	 * knows local, development, staging and production, so a custom name can be
	 * shown in its place without changing how WordPress itself behaves.
	 */
	private function detect_environment(): string
	{
		$type = '';
		if (\function_exists('wp_get_environment_type')) {
			$type = (string) \wp_get_environment_type();
		} elseif (\defined('WP_ENVIRONMENT_TYPE')) {
			$type = (string) \WP_ENVIRONMENT_TYPE;
		}

		$custom = \defined('SIRATIK_ENVIRONMENT_INDICATOR_CUSTOM') ? (string) \SIRATIK_ENVIRONMENT_INDICATOR_CUSTOM : '';

		/**
		 * Filters the custom environment name the indicator shows.
		 *
		 * Return any name (e.g. 'qa') to show it in place of WordPress's type;
		 * names other than local, development, staging and production use the
		 * `custom` color. Return '' to show the type. Defaults to the
		 * SIRATIK_ENVIRONMENT_INDICATOR_CUSTOM constant. To hide the indicator,
		 * use the siratik_environment_indicator_show filter.
		 *
		 * @param string $custom Custom name, or '' for none.
		 * @param string $type   Environment type from WordPress core.
		 */
		$custom = apply_filters('siratik_environment_indicator_custom', $custom, $type);
		// Spaces become hyphens, so 'Client Review' is labeled "Client Review".
		$custom = \is_string($custom)
			? strtolower((string) preg_replace(['/\s+/', '/[^A-Za-z0-9_-]/'], ['-', ''], trim($custom)))
			: '';

		return $custom !== '' ? $custom : $type;
	}

	/**
	 * Whether WP_ENVIRONMENT_TYPE names one of WordPress's four types.
	 *
	 * False when neither the constant nor the environment variable is set, or
	 * when it holds another value: WordPress then assumes `production`.
	 */
	public function is_environment_type_set(): bool
	{
		$raw = false;
		if (\defined('WP_ENVIRONMENT_TYPE') && \WP_ENVIRONMENT_TYPE) {
			$raw = \WP_ENVIRONMENT_TYPE;
		} elseif (\function_exists('getenv')) {
			$raw = getenv('WP_ENVIRONMENT_TYPE');
		}

		return \in_array($raw, ['local', 'development', 'staging', 'production'], true);
	}

	/**
	 * Color and label for the current environment. Unknown names use the
	 * `custom` entry, labeled with the name itself unless a label is set.
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
		 * @param bool   $show        Whether to show the indicator. Default checks `edit_posts` (contributors and up).
		 * @param string $environment Current environment slug.
		 */
		return (bool) apply_filters(
			'siratik_environment_indicator_show',
			current_user_can('edit_posts'),
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
		$text   = esc_html($config['text']);

		// The label sits in .ab-label: on phones core's admin-bar CSS hides it
		// visually (screen readers still read it) and only the dot shows.
		$admin_bar->add_node([
			'id'    => 'environment-indicator',
			'title' => sprintf('<span class="env-dot" aria-hidden="true"></span> <span class="ab-label">%s</span>', $text),
			'parent' => 'top-secondary',
			'meta'  => [
				'class' => 'environment-indicator'
			]
		]);

		// Phones only (hidden on desktop by add_styles()): tapping the dot opens
		// core's standard dropdown with the label the bar has no room for.
		$admin_bar->add_node([
			'id'     => 'environment-indicator-label',
			'title'  => $text,
			'parent' => 'environment-indicator',
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

			#wpadminbar #wp-admin-bar-environment-indicator .env-dot {
				display: inline-block;
				width: 6px;
				height: 6px;
				border-radius: 50%;
				border: 1px solid #ffffff;
				background: <?php echo esc_attr($config['color']); ?>;
				margin-right: 2px;
				margin-top: -3px;
				vertical-align: middle;
			}

			/* Desktop: the label is in the bar, so the badge is static and has no dropdown. */
			@media screen and (min-width: 783px) {
				#wpadminbar #wp-admin-bar-environment-indicator {
					pointer-events: none;
					cursor: default;
				}

				#wpadminbar #wp-admin-bar-environment-indicator .ab-sub-wrapper {
					display: none;
				}
			}

			/* Phones: core hides plugin items below 782px; keep this one as a larger dot. */
			@media screen and (max-width: 782px) {
				#wpadminbar li#wp-admin-bar-environment-indicator {
					display: block;
				}

				#wpadminbar #wp-admin-bar-environment-indicator > .ab-item {
					padding: 0 14px;
				}

				#wpadminbar #wp-admin-bar-environment-indicator .env-dot {
					width: 12px;
					height: 12px;
					margin: 0;
				}

				/* The badge sits near the right edge; open the dropdown leftward so it stays on screen. */
				#wpadminbar #wp-admin-bar-environment-indicator .ab-sub-wrapper {
					right: 0;
					left: auto;
				}
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
	 *
	 * A color that isn't a hex value (#abc or #aabbcc) is ignored: it is
	 * printed into a <style> block, where escaping alone can't stop CSS
	 * injection. A non-string text is ignored too.
	 */
	public function set_config(array $config): void
	{
		foreach ($config as $env => $settings) {
			if (!\is_array($settings)) {
				continue;
			}
			if (isset($settings['color']) && !(\is_string($settings['color']) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $settings['color']))) {
				unset($settings['color']);
			}
			if (isset($settings['text']) && !\is_string($settings['text'])) {
				unset($settings['text']);
			}
			$this->config[$env] = \array_merge(
				$this->config[$env] ?? [],
				$settings
			);
		}
	}
}
