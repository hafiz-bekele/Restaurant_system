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
    protected function paginated(AbstractPaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'message' => 'OK',
            'errors' => null,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => method_exists($paginator, 'total') ? $paginator->total() : null,
                'last_page' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null,
            ],
            'links' => [
                'first' => method_exists($paginator, 'url') ? $paginator->url(1) : null,
                'last' => method_exists($paginator, 'url') && method_exists($paginator, 'lastPage')
                    ? $paginator->url($paginator->lastPage()) : null,
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
