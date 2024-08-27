<?php

namespace Kha333n\LaravelAcl\Exceptions;

use Exception;

class InvalidPolicyException extends Exception
{
    /**
     * Construct the exception.
     *
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
