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

## Documentation

- **[Getting Started](https://docs.cline.sh/retry/getting-started/)** - Installation and basic usage
- **[Functional API](https://docs.cline.sh/retry/functional-api/)** - Using the `retry()` function
- **[OOP API](https://docs.cline.sh/retry/oop-api/)** - Fluent interface with conditional retries
- **[Backoff Strategies](https://docs.cline.sh/retry/backoff-strategies/)** - All 8 backoff algorithms
- **[Configuration](https://docs.cline.sh/retry/configuration/)** - Laravel config options
- **[Examples](https://docs.cline.sh/retry/examples/)** - Real-world usage patterns

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
