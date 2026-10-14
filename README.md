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

By default, the indicator is shown to users with the `edit_posts` capability (contributors, authors, editors and administrators): anyone who can change content. Use the `siratik_environment_indicator_show` filter to change who can see it:

```php
// Administrators only.
add_filter( 'siratik_environment_indicator_show', function ( $show, $environment ) {
	return current_user_can( 'manage_options' );
}, 10, 2 );
```

### Custom environments

WordPress only knows `local`, `development`, `staging` and `production`, and treats any
other `WP_ENVIRONMENT_TYPE` value as `production`. To show another name, such as "QA",
leave `WP_ENVIRONMENT_TYPE` at the type WordPress should behave as and set a custom name
with the constant:

```php
// wp-config.php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
define( 'SIRATIK_ENVIRONMENT_INDICATOR_CUSTOM', 'qa' );
```

Or with the `siratik_environment_indicator_custom` filter, which receives the constant's
value (or `''`) and core's environment type, and wins over the constant:

```php
add_filter( 'siratik_environment_indicator_custom', function ( $custom, $type ) {
	return 'staging' === $type ? 'qa' : $custom;
}, 10, 2 );
```

An empty name shows core's type. Names outside the four types use the `custom` entry: a
gray dot, labeled with the name itself ("client-review" shows as "Client Review"). Give
them a fixed label or color with `set_config()`:

```php
Indicator::instance()->set_config( [
	'custom' => [ 'text' => 'Quality Assurance', 'color' => '#0891b2' ],
] );
```

To hide the indicator, return `false` from `siratik_environment_indicator_show`.

### Is the environment set?

`Indicator::instance()->is_environment_type_set()` is `false` when `WP_ENVIRONMENT_TYPE`
is missing (as a constant and as an environment variable) or holds a value other than the
four types. WordPress then assumes `production`, so the indicator shows "Production".

## Features

- Detects environment via `wp_get_environment_type()` (falls back to the `WP_ENVIRONMENT_TYPE` constant)
- Built-in labels for `local`, `development`, `staging`, and `production`
- Any other environment name through the `SIRATIK_ENVIRONMENT_INDICATOR_CUSTOM` constant or the `siratik_environment_indicator_custom` filter
- Shows environment indicator in admin bar; on phones (782px and narrower) it shows as a dot, and tapping it shows the label
- Customizable colors and labels
- Visible to users who can edit posts by default (customizable via filter)
- Lightweight and efficient

## Reading the environment

```php
Indicator::instance()->get_environment();
```

Returns the custom name when one is set, otherwise `local`, `development`, `staging`
or `production`.

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
