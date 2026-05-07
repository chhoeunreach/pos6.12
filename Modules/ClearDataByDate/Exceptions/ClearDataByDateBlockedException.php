<?php

namespace Modules\ClearDataByDate\Exceptions;

use Exception;

class ClearDataByDateBlockedException extends Exception
{
    protected array $fix_links = [];

    protected array $context = [];

    public function __construct(string $message, array $fix_links = [], array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->fix_links = $fix_links;
        $this->context = $context;
    }

    public function fixLinks(): array
    {
        return $this->fix_links;
    }

    public function context(): array
    {
        return $this->context;
    }
}

