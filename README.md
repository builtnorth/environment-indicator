# Environment Indicator

Shows the current WordPress environment (local, development, staging, production) in the admin bar.

![Environment Indicator Screenshot](https://i.imgur.com/iWINX5I.png)

**Status: private, pre-1.0.** The public surface is not stable yet and breaking
changes land without deprecation cycles until 1.0.

## Installation

```bash
composer require siratik/environment-indicator
```

## Usage

### Basic Usage

Initialize the package in your theme or plugin:

```php
use Siratik\EnvironmentIndicator\Indicator;

Indicator::instance()->boot();
```

### Custom Configuration

Customize the environment colors and labels. Partial overrides keep other keys:

```php
use Siratik\EnvironmentIndicator\Indicator;

$indicator = Indicator::instance();

$indicator->set_config([
	'development' => [
		'color' => '#00ff00',
		'text' => 'Local Dev',
	],
	'staging' => [
		'color' => '#ffaa00',
		'text' => 'QA Environment',
	],
]);

$indicator->boot();
```

Call `set_config()` before `boot()` if you add a custom environment slug that must match the detected type.

### Visibility

By default, the indicator is only shown to users with the `manage_options` capability. Use the `siratik_environment_indicator_show` filter to change who can see it:

```php
// Show to editors and above.
add_filter( 'siratik_environment_indicator_show', function ( $show, $environment ) {
	return current_user_can( 'edit_posts' );
}, 10, 2 );
```

### Custom environments

WordPress only knows `local`, `development`, `staging` and `production`. Use the `siratik_environment_indicator_environment` filter to show any other name; it receives core's environment type:

```php
// Show "QA" on this server.
add_filter( 'siratik_environment_indicator_environment', function ( $environment ) {
	return 'qa';
} );
```

Names outside the four types use the `custom` entry: a slate dot, labelled with the name itself ("client-review" shows as "Client Review"). Give them a fixed label or colour with `set_config()`:

```php
Indicator::instance()->set_config( [
	'custom' => [ 'text' => 'Quality Assurance', 'color' => '#0891b2' ],
] );
```

Return an empty string from the filter to hide the indicator.

## Features

- Detects environment via `wp_get_environment_type()` (falls back to the `WP_ENVIRONMENT_TYPE` constant)
- Built-in labels for `local`, `development`, `staging`, and `production`
- Any other environment name through the `siratik_environment_indicator_environment` filter
- Shows environment indicator in admin bar
- Customizable colors and labels
- Only visible to users with `manage_options` capability by default (customizable via filter)
- Lightweight and efficient

## Reading the environment

```php
Indicator::instance()->get_environment();
```

Returns `local`, `development`, `staging`, `production`, the name returned by the
`siratik_environment_indicator_environment` filter, or an empty string when the
indicator is hidden.

## Development

```bash
composer test
composer lint
```

## Requirements

- PHP 8.1 or higher
- WordPress 5.5 or higher (for `wp_get_environment_type()`)

## License

GPL-2.0-or-later

## Disclaimer

This software is provided "as is", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose and noninfringement. In no event shall the authors or copyright holders be liable for any claim, damages or other liability, whether in an action of contract, tort or otherwise, arising from, out of or in connection with the software or the use or other dealings in the software.

Use of this library is at your own risk. The authors and contributors of this project are not responsible for any damage to your website or any loss of data that may result from the use of this library.

While we strive to keep this library up-to-date and secure, we make no guarantees about its performance, reliability, or suitability for any particular purpose. Users are advised to thoroughly test the library in a safe environment before deploying it to a live site.

By using this library, you acknowledge that you have read this disclaimer and agree to its terms.
