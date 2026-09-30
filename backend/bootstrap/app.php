<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Resources\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
        ]);

        // Do not trim passwords: leading or trailing spaces may be intentional.
        $middleware->trimStrings(except: [
            'password',
            'password_confirmation',
            'current_password',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool =>
                $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (
            Throwable $exception,
            Request $request
        ) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($exception instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return $exception->getResponse();
            }

            $status = 500;
            $message = 'An unexpected error occurred. Please try again.';
            $errors = [];
            $headers = [];

            if ($exception instanceof ValidationException) {
                $status = 422;
                $message = 'Please correct the highlighted fields.';
                $errors = $exception->errors();
            } elseif ($exception instanceof AuthenticationException) {
                $status = 401;
                $message = 'Authentication is required.';
            } elseif ($exception instanceof AuthorizationException) {
                $status = $exception->hasStatus()
                    ? $exception->status()
                    : 403;

                $message = $status === 404
                    ? 'The requested resource was not found.'
                    : 'You do not have permission to perform this action.';
            } elseif (
                $exception instanceof ModelNotFoundException
                || $exception instanceof NotFoundHttpException
            ) {
                $status = 404;
                $message = 'The requested resource was not found.';
            } elseif ($exception instanceof ThrottleRequestsException) {
                $status = 429;
                $message = 'Too many requests. Please try again shortly.';
                $headers = $exception->getHeaders();
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $headers = $exception->getHeaders();

                $message = match ($status) {
                    400 => 'The request could not be processed.',
                    401 => 'Authentication is required.',
                    403 => 'You do not have permission to perform this action.',
                    404 => 'The requested resource was not found.',
                    405 => 'This HTTP method is not allowed.',
                    409 => 'The request conflicts with the current resource state.',
                    413 => 'The uploaded request is too large.',
                    419 => 'Your session has expired.',
                    422 => 'The submitted data is invalid.',
                    429 => 'Too many requests. Please try again shortly.',
                    503 => 'The service is temporarily unavailable.',
                    default => 'The request could not be completed.',
                };
            }

            // Laravel still reports reportable exceptions through its logger.
            // API responses intentionally omit traces and database details.
            return ApiResponse::failure($message, $errors)
                ->response()
                ->setStatusCode($status)
                ->withHeaders($headers);
        });
    })
    ->create();
