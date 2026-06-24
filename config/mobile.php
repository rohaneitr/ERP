<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Open Registration
    |--------------------------------------------------------------------------
    |
    | When true, users who register via the mobile app are created immediately
    | with a trial subscription and can log in right away.
    |
    | When false (default), registration creates a pending request that an
    | administrator must approve from /admin/mobile-users.
    |
    */
    'open_registration' => env('MOBILE_OPEN_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Trial Period (days)
    |--------------------------------------------------------------------------
    |
    | How many days a newly self-registered account stays in trial mode
    | before requiring a paid subscription.
    |
    */
    'trial_days' => env('MOBILE_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Require Subscription
    |--------------------------------------------------------------------------
    |
    | When true, the EnsureActiveSubscription middleware will block access
    | for users without a valid subscription.
    |
    */
    'require_subscription' => env('MOBILE_REQUIRE_SUBSCRIPTION', true),
];
