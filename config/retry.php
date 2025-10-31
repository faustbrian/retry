<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Retry Attempts
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum number of retry attempts that will be
    | made before giving up and allowing the operation to fail. Setting this
    | value too high may result in excessive delays, while setting it too
    | low may cause premature failures on transient errors.
    |
    */

    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Maximum Delay
    |--------------------------------------------------------------------------
    |
    | This value controls the maximum delay (in microseconds) between retry
    | attempts. If a backoff strategy calculates a delay greater than this
    | value, it will be capped to this maximum. Set this to null to allow
    | unlimited delays. The default is 60 seconds (60,000,000 microseconds).
    |
    */

    'max_delay_microseconds' => 60_000_000, // 60 seconds

    /*
    |--------------------------------------------------------------------------
    | Default Backoff Strategy
    |--------------------------------------------------------------------------
    |
    | This option controls the default backoff strategy that will be used for
    | retry operations. The strategy determines how delays between retries
    | are calculated. Each strategy has its own configuration which can be
    | set in the "strategies" array below.
    |
    | Supported strategies: "exponential", "exponential_jitter",
    |                       "decorrelated_jitter", "linear", "constant",
    |                       "fibonacci", "polynomial", "none"
    |
    */

    'default_strategy' => 'exponential',

    /*
    |--------------------------------------------------------------------------
    | Backoff Strategy Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each backoff strategy used by
    | your application. Each strategy has its own parameters that control
    | how delays are calculated. Only the configuration for the selected
    | default strategy above will be used during retry operations.
    |
    */

    'strategies' => [

        /*
        | Exponential Backoff
        |
        | Calculates delay as: base * (multiplier ^ attempt)
        | Example: 1s, 2s, 4s, 8s, 16s...
        |
        */

        'exponential' => [
            'base_microseconds' => 1_000_000, // 1 second
            'multiplier' => 2.0,
        ],

        /*
        | Exponential Backoff with Jitter
        |
        | Similar to exponential backoff but adds randomness to prevent
        | thundering herd problems when multiple operations retry
        | simultaneously. Particularly useful in distributed systems.
        |
        */

        'exponential_jitter' => [
            'base_microseconds' => 1_000_000, // 1 second
            'multiplier' => 2.0,
        ],

        /*
        | Decorrelated Jitter Backoff
        |
        | AWS recommended strategy that uses the previous delay to calculate
        | the next delay with randomness. Provides better distribution than
        | standard jitter and helps avoid synchronized retry attempts.
        |
        */

        'decorrelated_jitter' => [
            'base_microseconds' => 1_000_000, // 1 second
            'max_microseconds' => 60_000_000, // 60 seconds
        ],

        /*
        | Linear Backoff
        |
        | Calculates delay as: base * attempt
        | Example: 1s, 2s, 3s, 4s, 5s...
        | Suitable for operations that benefit from gradual delay increases.
        |
        */

        'linear' => [
            'base_microseconds' => 1_000_000, // 1 second
        ],

        /*
        | Constant Backoff
        |
        | Uses the same delay between all retry attempts. Best suited for
        | operations where you want predictable, uniform retry intervals
        | regardless of how many attempts have been made.
        |
        */

        'constant' => [
            'delay_microseconds' => 1_000_000, // 1 second
        ],

        /*
        | Fibonacci Backoff
        |
        | Delays follow the Fibonacci sequence (1, 1, 2, 3, 5, 8, 13...).
        | Provides a balance between exponential and linear growth, giving
        | quick initial retries with gradually increasing delays.
        |
        */

        'fibonacci' => [
            'base_microseconds' => 1_000_000, // 1 second
        ],

        /*
        | Polynomial Backoff
        |
        | Calculates delay as: base * (attempt ^ degree)
        | Allows fine-tuned control over delay growth rate. A degree of 2
        | gives quadratic growth, while higher degrees increase faster.
        |
        */

        'polynomial' => [
            'base_microseconds' => 1_000_000, // 1 second
            'degree' => 2,
        ],

    ],

];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'                _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
