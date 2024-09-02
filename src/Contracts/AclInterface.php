<?php

namespace Kha333n\LaravelAcl\Contracts;

use JetBrains\PhpStorm\ArrayShape;

interface AclInterface
{
    /**
     * Define the resource unique name and description
     * @return array
     * @example ['name' => 'users', 'description' => 'User resource']
     */
    #[ArrayShape(['name' => "string", 'description' => "string"])]
    public static function getResourceName(): array;

    /**
     * Define the actions for the resource
     * @return array
     * @example [['action' => 'create', 'description' => 'Create user'], ['action' => 'read', 'description' => 'Read user'], ['action' => 'update', 'description' => 'Update user'], ['action' => 'delete', 'description' => 'Delete user']]
     */
    #[ArrayShape([['action' => "string", 'description' => "string", 'is_scopeable' => "bool"]])]
    public static function getActions(): array;
}
