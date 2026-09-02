<?php

namespace App\Exceptions\Tenant\Auth;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DeviceConflictException extends ConflictHttpException
{
    public const ERROR_CODE = 'DEVICE_CONFLICT';

    public function __construct()
    {
        parent::__construct('This account is already active on another device. Please logout the other device first.');
    }
}
