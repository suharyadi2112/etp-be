<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
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
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
      
        return response()->json([
            "status" => "fail",
            "message" => "Unauthorized. You do not have access.",
            "data" => null
        ], 401);
    }

    protected function can($request, Closure $next, ...$permissions)
    {
        try {
            // Check if the user has any of the specified permissions.
            $this->authorizeAny($permissions);
        } catch (AuthorizationException $e) {
            // Customize the response when authorization fails.
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }

    
}
