<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class TenantProvisioningException extends RuntimeException
{
    public function __construct(string $message = 'Tenant provisioning failed.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
