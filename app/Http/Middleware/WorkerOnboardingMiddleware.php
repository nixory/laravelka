<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkerOnboardingMiddleware
{
    /**
     * Redirect workers who haven't completed onboarding
     * to the appropriate onboarding step page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'worker') {
            return $next($request);
        }

        $worker = $user->workerProfile;

        // No worker profile yet — let them through (registration just happened)
        if (!$worker) {
            return $next($request);
        }

        // Already completed — let through
        if ($worker->isOnboardingComplete()) {
            return $next($request);
        }

        // Determine target path based on status
        $targetPath = match ($worker->onboarding_status) {
            'step1' => '/worker/onboarding-step-1',
            'pending_approval' => '/worker/onboarding-pending',
            'step2' => '/worker/onboarding-step-2',
            default => '/worker/onboarding-step-1',
        };

        // Don't redirect if already on the target page (avoid loops)
        $currentPath = '/' . ltrim($request->path(), '/');
        if ($currentPath === $targetPath) {
            return $next($request);
        }

        // Allow access to logout
        if (str_contains($currentPath, '/logout')) {
            return $next($request);
        }

        return redirect($targetPath);
    }
}
