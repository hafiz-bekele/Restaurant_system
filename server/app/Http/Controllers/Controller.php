<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;

abstract class Controller
{
    /**
     * Return a successful JSON envelope response.
     */
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = ['data' => $data, 'message' => $message, 'errors' => null];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return an error JSON envelope response.
     */
    protected function error(
        string $message = 'An error occurred',
        int $status = 400,
        mixed $errors = null
    ): JsonResponse {
        return response()->json([
            'data' => null,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a paginated JSON envelope response.
     */
    protected function paginated(mixed $paginator): JsonResponse
    {
        $isCursor = $paginator instanceof \Illuminate\Pagination\AbstractCursorPaginator;
        $currentPage = method_exists($paginator, 'currentPage') ? $paginator->currentPage() : null;
        $total = method_exists($paginator, 'total') ? $paginator->total() : null;
        $lastPage = method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null;

        return response()->json([
            'data' => $paginator->items(),
            'message' => 'OK',
            'errors' => null,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $paginator->perPage(),
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => method_exists($paginator, 'hasMorePages') ? $paginator->hasMorePages() : null,
            ],
            'links' => [
                'first' => !$isCursor && method_exists($paginator, 'url') ? $paginator->url(1) : null,
                'last' => !$isCursor && method_exists($paginator, 'url') && $lastPage !== null
                    ? $paginator->url($lastPage) : null,
                'prev' => method_exists($paginator, 'previousPageUrl') ? $paginator->previousPageUrl() : null,
                'next' => method_exists($paginator, 'nextPageUrl') ? $paginator->nextPageUrl() : null,
            ],
        ]);
    }
}
