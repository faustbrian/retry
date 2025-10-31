[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

A comprehensive retry library for PHP 8.4+ featuring multiple backoff strategies, jitter support, and a fluent interface for building resilient applications. Designed for handling transient failures in network requests, database operations, and external service calls.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/retry
```

## Quick Start

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;
use function Cline\Retry\retry;

// Functional style with exponential backoff
$result = retry(5, ExponentialBackoff::milliseconds(100))(
    fn() => $apiClient->fetchData()
);

// OOP style with fluent interface
$result = Retry::times(5)
    ->withBackoff(ExponentialBackoff::seconds(1))
    ->withMaxDelay(30_000_000) // 30 seconds
    ->execute(fn() => $database->query());
```

## Documentation

- **[Backoff Strategies](cookbook/backoff-strategies.md)** - All available backoff algorithms and when to use them
- **[Retry Patterns](cookbook/retry-patterns.md)** - Common retry patterns for APIs, databases, and more
- **[Functional API](cookbook/functional-api.md)** - Using the `retry()` function with closures
- **[OOP API](cookbook/oop-api.md)** - Fluent interface with conditional retries and max delays
- **[Examples](cookbook/examples.md)** - Real-world usage examples and patterns

## Features

- **8 Backoff Strategies**: Constant, Linear, Exponential, Exponential+Jitter, Fibonacci, Decorrelated Jitter, Polynomial, and Max Delay Decorator
- **Functional & OOP APIs**: Choose the style that fits your codebase
- **Conditional Retries**: Retry only on specific exceptions or conditions
- **Max Delay Caps**: Prevent exponential backoff from growing too large
- **Immutable**: All configuration methods return new instances
- **Type Safe**: Full PHP 8.4 type coverage with readonly properties
- **Well Tested**: 244 tests with 2,539 assertions covering all edge cases

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/retry/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/retry.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/retry.svg

[link-tests]: https://github.com/faustbrian/retry/actions
[link-packagist]: https://packagist.org/packages/cline/retry
[link-downloads]: https://packagist.org/packages/cline/retry
[link-security]: https://github.com/faustbrian/retry/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
