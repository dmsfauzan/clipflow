<?php

namespace App\Exceptions;

use App\Enums\UploadErrorCategory;

class PublishingException extends ClipflowException
{
    public function __construct(
        string $message,
        public readonly ?UploadErrorCategory $errorCategory = null,
        public readonly bool $uncertain = false,
        public readonly ?int $httpStatus = null,
        public readonly ?string $reconciliationNote = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCategory?->value, $previous);
    }
}
