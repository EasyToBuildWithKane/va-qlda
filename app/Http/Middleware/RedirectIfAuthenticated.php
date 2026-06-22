<?php

namespace App\Http\Middleware;

use App\Support\Auth\PortalDestination;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $account = Auth::guard($guard)->user();

                return redirect($account ? PortalDestination::homePath($account, 'portal') : route('congnghe', [], false));
            }
        }

        return $next($request);
    }
}
