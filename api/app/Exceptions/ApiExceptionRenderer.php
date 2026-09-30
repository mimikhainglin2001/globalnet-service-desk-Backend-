<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every API error as:
 * { "message": string, "code": string, "errors"?: { field: [messages] } }
 */
class ApiExceptionRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        419 => 'session_expired',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(422, $e->getMessage(), $e->errors()),
            $e instanceof AuthenticationException => $this->respond(401, 'Unauthenticated.'),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => $this->respond(
                500,
                config('app.debug') ? $e->getMessage() : 'Server error.',
            ),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        $message = match (true) {
            $e->getPrevious() instanceof ModelNotFoundException => 'Resource not found.',
            $e->getMessage() !== '' => $e->getMessage(),
            default => JsonResponse::$statusTexts[$status] ?? 'Error',
        };

        return $this->respond($status, $message, headers: $e->getHeaders());
    }

    private function respond(int $status, string $message, ?array $errors = null, array $headers = []): JsonResponse
    {
        $body = [
            'message' => $message,
            'code' => self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'error'),
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status, $headers);
    }
}
