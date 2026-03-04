<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry;

use Cline\Retry\Strategy\BackoffStrategy;
use Cline\Retry\Strategy\BackoffStrategyType;
use Cline\Retry\Strategy\ConstantBackoff;
use Cline\Retry\Strategy\DecorrelatedJitterBackoff;
use Cline\Retry\Strategy\ExponentialBackoff;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use Cline\Retry\Strategy\FibonacciBackoff;
use Cline\Retry\Strategy\LinearBackoff;
use Cline\Retry\Strategy\PolynomialBackoff;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Laravel service provider for the retry package.
 *
 * Registers retry services in the Laravel container, including the main Retry
 * instance configured from the retry config file and a BackoffStrategy binding
 * for dependency injection. Supports all available backoff strategies with
 * sensible defaults.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RetryServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package registration.
     *
     * @param Package $package Package configuration instance
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('retry')
            ->hasConfigFile();
    }

    /**
     * Register package services in the container.
     *
     * Binds a singleton Retry instance configured from config/retry.php and a
     * BackoffStrategy binding for injecting the default strategy. The Retry
     * singleton uses the configured max attempts, default strategy, and max delay.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton('retry', function (array $app): Retry {
            /** @var array{default_strategy: string, max_attempts: int, max_delay_microseconds: int, strategies: array<string, array<string, mixed>>} $config */
            $config = $app['config']['retry'];

            $strategy = $this->createStrategy(
                $config['default_strategy'],
                $config['strategies'][$config['default_strategy']] ?? [],
            );

            return new Retry(
                maxAttempts: $config['max_attempts'],
                strategy: $strategy,
                maxDelayMicroseconds: $config['max_delay_microseconds'],
            );
        });

        $this->app->bind(function (array $app): BackoffStrategy {
            /** @var array{default_strategy: string, strategies: array<string, array<string, mixed>>} $config */
            $config = $app['config']['retry'];

            return $this->createStrategy(
                $config['default_strategy'],
                $config['strategies'][$config['default_strategy']] ?? [],
            );
        });
    }

    /**
     * Create a backoff strategy instance from configuration.
     *
     * Factory method that instantiates the appropriate BackoffStrategy implementation
     * based on the strategy type string and configuration array. Provides sensible
     * defaults for all strategy parameters when not specified in config.
     *
     * @param  string               $type   Strategy type identifier matching BackoffStrategyType enum values
     * @param  array<string, mixed> $config Strategy-specific configuration parameters with keys
     *                                      like base_microseconds, multiplier, max_microseconds,
     *                                      delay_microseconds, or degree depending on strategy type
     * @return null|BackoffStrategy Configured strategy instance, or null for 'none' type
     */
    private function createStrategy(string $type, array $config): ?BackoffStrategy
    {
        $strategyType = BackoffStrategyType::from($type);

        return match ($strategyType) {
            BackoffStrategyType::Exponential => new ExponentialBackoff(
                baseMicroseconds: $config['base_microseconds'] ?? 1_000_000,
                multiplier: $config['multiplier'] ?? 2.0,
            ),
            BackoffStrategyType::ExponentialJitter => new ExponentialJitterBackoff(
                baseMicroseconds: $config['base_microseconds'] ?? 1_000_000,
                multiplier: $config['multiplier'] ?? 2.0,
            ),
            BackoffStrategyType::DecorrelatedJitter => new DecorrelatedJitterBackoff(
                baseMicroseconds: $config['base_microseconds'] ?? 1_000_000,
                maxMicroseconds: $config['max_microseconds'] ?? 60_000_000,
            ),
            BackoffStrategyType::Linear => new LinearBackoff(
                baseMicroseconds: $config['base_microseconds'] ?? 1_000_000,
            ),
            BackoffStrategyType::Constant => new ConstantBackoff(
                delayMicroseconds: $config['delay_microseconds'] ?? 1_000_000,
            ),
            BackoffStrategyType::Fibonacci => new FibonacciBackoff(
                baseMicroseconds: $config['base_microseconds'] ?? 1_000_000,
            ),
            BackoffStrategyType::Polynomial => new PolynomialBackoff(
                baseMicroseconds: $config['base_microseconds'] ?? 1_000_000,
                degree: $config['degree'] ?? 2,
            ),
            BackoffStrategyType::None => null,
        };
    }
}
