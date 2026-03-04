<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Enumeration of available backoff strategy types.
 *
 * Defines the supported backoff strategies for retry operations. Each strategy
 * provides different delay calculation patterns optimized for specific failure
 * scenarios, from aggressive exponential growth to more conservative linear
 * increases or randomized jitter patterns.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum BackoffStrategyType: string
{
    /** Exponential growth: delay multiplies by a factor each attempt (e.g., 1s, 2s, 4s, 8s) */
    case Exponential = 'exponential';

    /** Exponential with random jitter: adds randomization to exponential delays to prevent thundering herd */
    case ExponentialJitter = 'exponential_jitter';

    /** Decorrelated jitter: AWS-recommended strategy using randomized delays with upper bound */
    case DecorrelatedJitter = 'decorrelated_jitter';

    /** Linear growth: delay increases by constant amount each attempt (e.g., 1s, 2s, 3s, 4s) */
    case Linear = 'linear';

    /** Constant delay: same fixed delay between all retry attempts */
    case Constant = 'constant';

    /** Fibonacci sequence: delays follow Fibonacci pattern (e.g., 1s, 1s, 2s, 3s, 5s, 8s) */
    case Fibonacci = 'fibonacci';

    /** Polynomial growth: delay grows by exponential power with configurable degree */
    case Polynomial = 'polynomial';

    /** No delay: immediate retry without waiting between attempts */
    case None = 'none';
}
