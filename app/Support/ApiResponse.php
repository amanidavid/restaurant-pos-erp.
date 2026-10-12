<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ApiResponse
{
    public static function resource(
        JsonResource|AnonymousResourceCollection $resource,
        string $message,
        int $status = 200
    ): JsonResponse {
        return $resource
            ->additional(array_merge($resource->additional, [
                'success' => true,
                'message' => $message,
                'errors' => null,
            ]))
            ->response()
            ->setStatusCode($status);
    }

    public static function success(string $message, ?array $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    public static function error(
        string $message,
        ?array $errors = null,
        int $status = 422,
        ?string $code = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    public static function validation(array $errors, ?string $message = null): JsonResponse
    {
        return self::error($message ?? self::validationMessage($errors), $errors, 422);
    }

    public static function unauthorized(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? ApiMessages::AUTHENTICATION_REQUIRED, $errors, 401);
    }

    public static function forbidden(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? ApiMessages::ACCESS_DENIED, $errors, 403);
    }

    public static function badRequest(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? self::validationMessage($errors ?? []), $errors, 400);
    }

    private static function validationMessage(array $errors): string
    {
        foreach (Arr::flatten($errors) as $error) {
            if (is_string($error) && trim($error) !== '') {
                return trim($error);
            }
        }

        return ApiMessages::VALIDATION_FAILED;
    }

    public static function notFound(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? ApiMessages::RESOURCE_NOT_FOUND, $errors, 404);
    }

    public static function methodNotAllowed(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? ApiMessages::METHOD_NOT_ALLOWED, $errors, 405);
    }

    public static function tooManyRequests(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? ApiMessages::TOO_MANY_REQUESTS, $errors, 429);
    }

    public static function serverError(?array $errors = null, ?string $message = null): JsonResponse
    {
        return self::error($message ?? ApiMessages::SERVER_ERROR, $errors, 500);
    }
}
