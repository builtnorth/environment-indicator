# Environment Indicator

Adds a coloured node to the WordPress admin bar showing which environment the site is
running in — local, development, staging or production.

**Status: private, pre-1.0.** The public surface is not stable yet and breaking changes
land without deprecation cycles until 1.0.

## Requirements

- PHP 8.1+
- WordPress 6.4+

## Install

```bash
composer require navas/environment-indicator
```

## Boot

```php
use Navas\EnvironmentIndicator\Indicator;

Indicator::instance()->boot();
```

`boot()` reads the environment from `wp_get_environment_type()`. When it recognises one,
it registers the admin-bar node and its styles; when it does not, it does nothing.

## Who sees it

By default, users with the `manage_options` capability. Change that with the
`environment_indicator_show` filter:

```php
add_filter( 'environment_indicator_show', function ( $show, $environment ) {
	return $environment === 'production' ? false : $show;
}, 10, 2 );
```

## Customising the colours and labels

```php
$indicator = Indicator::instance();

$indicator->set_config( [
	'staging' => [
		'color' => '#b45309',
		'text'  => 'Staging — do not use real data',
	],
] );

$indicator->boot();
```

Call `set_config()` before `boot()`. Environments you do not pass keep their defaults.

## Reading the environment

```php
Indicator::instance()->get_environment(); // 'local' | 'development' | 'staging' | 'production' | ''
```

Returns an empty string when the environment is unrecognised.

## Development

```bash
composer test
composer lint
```

## License

GPL-2.0-or-later
