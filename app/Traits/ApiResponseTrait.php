<?php

namespace App\Traits;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;

trait ApiResponseTrait
{
    /**
     * Build the standard API response structure.
     *
     * @param  mixed  $data
     */
    protected function apiResponse(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data ?? (object) [],
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Success Response.
     */
    protected function successResponse(string $message, $data = null, int $statusCode = 200): JsonResponse
    {
        if ($data instanceof ResourceCollection &&
            ($data->resource instanceof CursorPaginator ||
             $data->resource instanceof Paginator)) {
            $paginated = $data->response()->getData(true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $paginated['data'] ?? [],
                'meta' => $paginated['meta'] ?? [],
                'links' => $paginated['links'] ?? [],
                'next_cursor' => $paginated['meta']['next_cursor'] ?? null,
                'prev_cursor' => $paginated['meta']['prev_cursor'] ?? null,
                'errors' => [],
            ], $statusCode);
        }

        if ($data instanceof CursorPaginator) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data->items(),
                'meta' => [
                    'path' => $data->path(),
                    'per_page' => $data->perPage(),
                    'next_cursor' => $data->nextCursor()?->encode(),
                    'prev_cursor' => $data->previousCursor()?->encode(),
                ],
                'links' => [
                    'prev' => $data->previousPageUrl(),
                    'next' => $data->nextPageUrl(),
                ],
                'next_cursor' => $data->nextCursor()?->encode(),
                'prev_cursor' => $data->previousCursor()?->encode(),
                'errors' => [],
            ], $statusCode);
        }

        return $this->apiResponse(true, $message, $data, [], $statusCode);
    }

    /**
     * Error Response.
     */
    protected function errorResponse(string $message, array $errors = [], int $statusCode = 400): JsonResponse
    {
        return $this->apiResponse(false, $message, null, $errors, $statusCode);
    }

    /**
     * Validation Error Response: يعرض أول رسالة تحقق فعلية بدل رسالة عامة.
     */
    protected function validationErrorResponse(ValidationException $e): JsonResponse
    {
        $message = collect($e->errors())->flatten()->first()
            ?? 'عذراً، حدث خطأ في المدخلات المزودة.';

        return $this->errorResponse($message, $e->errors(), 422);
    }

    /**
     * Unauthorized Error Response (401).
     */
    protected function unauthorizedResponse(string $message = 'المستخدم غير مصادق عليه أو انتهت صلاحية الجلسة.'): JsonResponse
    {
        return $this->errorResponse($message, [], 401);
    }

    /**
     * Authentication Error Response (401).
     */
    protected function authenticationErrorResponse(AuthenticationException $e): JsonResponse
    {
        return $this->unauthorizedResponse($e->getMessage() ?: 'المستخدم غير مصادق عليه أو انتهت صلاحية الجلسة.');
    }
}
