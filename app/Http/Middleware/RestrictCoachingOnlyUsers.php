<?php

namespace App\Http\Middleware;

use App\Support\Auth\CoachingOnlyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCoachingOnlyUsers
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if ($account === null || ! CoachingOnlyAccess::appliesTo($account)) {
            return $next($request);
        }

        if ($request->routeIs('coaching.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('coaching.dashboard');
    }
}
