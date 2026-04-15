<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DomainRestriction
{
    public function handle(Request $request, Closure $next)
    {
        // Get the host from multiple sources to ensure we catch it
        $currentHost = $request->getHost() ?: 
                      $request->server('HTTP_HOST') ?: 
                      $request->server('SERVER_NAME') ?: 
                      $_SERVER['HTTP_HOST'] ?? 
                      $_SERVER['SERVER_NAME'] ?? '';
        
        // Remove port if present
        $currentHost = explode(':', $currentHost)[0];
        
        // Block alldatagh.com for non-shop routes
        if ($currentHost === 'alldatagh.com' || $currentHost === 'www.alldatagh.com') {
            $path = $request->path();
            
            // Only allow shop routes and guest payment routes
            if (!str_starts_with($path, 'shop/') && !str_starts_with($path, 'guest/payment/')) {
                // Return the store-only view with proper message
                return response(view('store-domain-only'), 403);
            }
        }
        
        return $next($request);
    }
}