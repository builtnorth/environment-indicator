# Siratik Environment Indicator

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

GPL-3.0-or-later — see `LICENSE`.

## Disclaimer

This software is provided "as is", without warranty of any kind, express or
implied, including the warranties of merchantability and fitness for a
particular purpose. To the extent permitted by applicable law, Built North LLC
(Kitaris) and its contributors are not liable for any direct, indirect,
incidental, or consequential damages arising from its use or inability to use
it, including loss of data, revenue, or site availability.

The software does not guarantee that your site is secure, ranks in search
results, or complies with privacy or other laws; that remains the site owner's
responsibility. AI-generated content can be inaccurate and should be reviewed
before publishing. Third-party services the software connects to are governed
by their own terms.

Keep a current backup and test changes on a staging site before applying them
to a live site. See sections 15 and 16 of the GNU General Public License,
version 3, for the full terms.
