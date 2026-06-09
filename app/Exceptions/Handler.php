<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Handle database query exceptions with user-friendly error page
        $this->renderable(function (\Illuminate\Database\QueryException $e, $request) {
            // For API or AJAX requests, return JSON error
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'A database error occurred. Please try again later.',
                    'error' => 'Database Error'
                ], 500);
            }

            // For web requests, show the user-friendly error page
            return response()->view('errors.500', [], 500);
        });

        // Handle general database exceptions
        $this->renderable(function (\PDOException $e, $request) {
            // For API or AJAX requests, return JSON error
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'A database error occurred. Please try again later.',
                    'error' => 'Database Error'
                ], 500);
            }

            // For web requests, show the user-friendly error page
            return response()->view('errors.500', [], 500);
        });

        // Handle general exceptions with user-friendly error page
        $this->renderable(function (\Exception $e, $request) {
            // Skip validation exceptions, HTTP exceptions, and authentication exceptions
            if ($this->isValidationException($e) ||
                $this->isHttpException($e) ||
                $this->isAuthenticationException($e)) {
                return null;
            }

            // For API or AJAX requests, return JSON error
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'An unexpected error occurred. Please try again later.',
                    'error' => 'Server Error'
                ], 500);
            }

            // For web requests, show the user-friendly error page
            return response()->view('errors.500', [], 500);
        });
    }

    /**
     * Check if the exception is a validation exception
     */
    protected function isValidationException(\Throwable $e): bool
    {
        return $e instanceof \Illuminate\Validation\ValidationException;
    }

    /**
     * Check if the exception is an HTTP exception
     */
    protected function isHttpException(\Throwable $e): bool
    {
        return $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException;
    }

    /**
     * Check if the exception is an authentication exception
     */
    protected function isAuthenticationException(\Throwable $e): bool
    {
        return $e instanceof \Illuminate\Auth\AuthenticationException ||
               $e instanceof \Illuminate\Auth\Access\AuthorizationException;
    }
}
