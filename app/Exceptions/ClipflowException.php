<?php

namespace App\Exceptions;

use Exception;

abstract class ClipflowException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $category = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
