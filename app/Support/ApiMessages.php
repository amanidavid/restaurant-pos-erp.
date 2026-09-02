<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ApiMessages
{
    public const VALIDATION_FAILED = 'Please check the highlighted details and try again.';
    public const AUTHENTICATION_REQUIRED = 'Please sign in to continue.';
    public const ACCESS_DENIED = 'You do not have access to this action.';
    public const RESOURCE_NOT_FOUND = 'We could not find what you requested.';
    public const METHOD_NOT_ALLOWED = 'This request method is not allowed for that endpoint.';
    public const SERVER_ERROR = 'Something went wrong. Please try again shortly.';
    public const TOO_MANY_REQUESTS = 'Too many requests. Please wait a moment and try again.';
    public const INVALID_TENANT_HEADER = 'A restaurant workspace is required for this request.';
    public const TENANT_NOT_FOUND = 'We could not find the selected restaurant workspace.';
    public const INVALID_SESSION = 'Your session has expired. Please sign in again.';
    public const TENANT_CONTEXT_UNAVAILABLE = 'Restaurant workspace is not ready for this request.';

    public static function listRetrieved(string $resource): string
    {
        return self::label($resource).' loaded successfully.';
    }

    public static function detailsRetrieved(string $resource): string
    {
        return self::singularLabel($resource).' details loaded successfully.';
    }

    public static function created(string $resource): string
    {
        return self::singularLabel($resource).' saved successfully.';
    }

    public static function updated(string $resource): string
    {
        return self::singularLabel($resource).' updated successfully.';
    }

    public static function deleted(string $resource): string
    {
        return self::singularLabel($resource).' deleted successfully.';
    }

    public static function queued(string $resource): string
    {
        return self::singularLabel($resource).' has been queued successfully.';
    }

    public static function failed(string $resource): string
    {
        return 'We could not complete the '.Str::lower(self::singularLabel($resource)).'.';
    }

    public static function duplicate(string $resource): string
    {
        return 'A '.Str::lower(self::singularLabel($resource)).' with the same details already exists.';
    }

    public static function invalidReference(string $resource): string
    {
        return self::singularLabel($resource).' could not be found.';
    }

    private static function label(string $value): string
    {
        return Str::ucfirst(str_replace('_', ' ', trim($value)));
    }

    private static function singularLabel(string $value): string
    {
        return Str::ucfirst(Str::singular(str_replace('_', ' ', trim($value))));
    }
}
