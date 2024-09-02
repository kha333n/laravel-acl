<?php

return [

    /*
     * Resource Prefix
     *
     * This is the prefix for the resource and will be added to each resource string.
     *
     * For example, if the prefix is 'admin', the resource string will be 'admin::users::*'.
     */
    'prefix' => env('LARAVEL_ACL_PREFIX', 'acl'),


    /*
     * Teams
     *
     * Teams are groups of users that can be used to apply policies to multiple users at once.
     *
     * Supported modes: 'session', 'all'. Only applicable if 'enabled' is set to true and via Policy.
     */
    'teams' => [
        'enabled' => env('LARAVEL_ACL_TEAMS_ENABLED', false),
    ],

    /*
     * Classes Array
     *
     * Classes array is used to define the classes that implement the AclInterface.
     * Which are not in the defautl Models directory.
     * All those models and classes which are not in default Models directory should be defined here.
     * Otherwise will not be scanned
     */
    'classes' => [
        // 'App\Models\CustomModel',
    ],

    /*
     * Custom Resources
     *
     * Custom resource are those resources which do not belong to any model or class.
     * Like Controller action, repository method or service method etc.
     *
     * These can be defined here and autherized using policies.
     */

    'custom_resources' => [
//        'resource1' => [
//            'name' => 'resource1',
//            'description' => 'Resource 1',
//            'actions' => [
//                [
//                    'action' => 'action1',
//                    'description' => 'Action 1',
//                    'is_scopeable' => true
//                ],
//                [
//                    'action' => 'action2',
//                    'description' => 'Action 2',
//                    'is_scopeable' => false
//                ]
//            ]
//        ]
    ],
];
