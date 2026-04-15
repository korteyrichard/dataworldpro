<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        // Allow all authenticated users to access agent routes (shop features)
        if ($role === 'agent') {
            return $next($request);
        }
        
        // Standard role check for other routes (admin, etc.)
        if ($request->user()->role !== $role) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Access denied'], 403);
            }
            
            return redirect()->route('dashboard')->with('error', 'Access denied.');
        }

        return $next($request);
    }
}