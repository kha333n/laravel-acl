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
    public function getResourceName(): array;

    /**
     * Define the actions for the resource
     * @return array
     * @example ['create', 'read', 'update', 'delete']
     */
    public function getActions(): array;
}
