<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    // Return JSON for all API exceptions
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {

            // Validation errors → 422
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation failed.',
                    'errors'  => $exception->errors(),
                ], 422);
            }

            // Unauthenticated → 401
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthenticated. Please login first.',
                ], 401);
            }

            // Generic server error → 500
            return response()->json([
                'status'  => 'error',
                'message' => 'Server error: ' . $exception->getMessage(),
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
