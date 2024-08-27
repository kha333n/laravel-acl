<?php

return [

    /*
     * Resource Prefix
     *
     * This is the prefix for the resource and will be added to each resource string.
     *
     * For example, if the prefix is 'admin', the resource string will be 'admin::users::*'.
     */
    'prefix'  => env('LARAVEL_ACL_PREFIX', 'acl'),


    /*
     * Teams
     *
     * Teams are groups of users that can be used to apply policies to multiple users at once.
     *
     * Supported modes: 'session', 'all'. Only applicable if 'enabled' is set to true and via Policy.
     */
    'teams' => [
        'enabled' => env('LARAVEL_ACL_TEAMS_ENABLED', false),
    ]
];
