<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectCanonicalAuthHost
{
    /**
     * Send login and dashboard requests on a legacy host to the canonical app URL.
     *
     * The path and query string are preserved. Dashboard sub-paths are included.
     * This middleware is on the web group, so /api is never redirected. The
     * path must also be exactly "login", or "dashboard" and anything beneath it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hostShouldRedirect($request) || ! $this->isLoginOrDashboard($request)) {
            return $next($request);
        }

        $target = $this->targetUrl($request);

        if ($target === null) {
            return $next($request);
        }

        return Inertia::location($target);
    }

    private function hostShouldRedirect(Request $request): bool
    {
        $hosts = array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host)),
            (array) config('canonical.redirect_hosts', []),
        );

        return in_array(strtolower($request->getHost()), $hosts, true);
    }

    private function isLoginOrDashboard(Request $request): bool
    {
        $path = strtolower(trim($request->getPathInfo(), '/'));

        if ($path === 'login') {
            return true;
        }

        return $path === 'dashboard' || str_starts_with($path, 'dashboard/');
    }

    private function targetUrl(Request $request): ?string
    {
        $base = $this->canonicalBaseUrl();

        if ($base === null) {
            return null;
        }

        $canonicalHost = strtolower((string) parse_url($base, PHP_URL_HOST));

        if ($canonicalHost === '' || $canonicalHost === strtolower($request->getHost())) {
            return null;
        }

        $path = $request->getPathInfo();

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            $path = '/'.ltrim($request->path(), '/');
        }

        $target = $base.$path;
        $queryString = $request->server->get('QUERY_STRING');

        if (is_string($queryString) && $queryString !== '') {
            $target .= '?'.$queryString;
        }

        return $target;
    }

    private function canonicalBaseUrl(): ?string
    {
        $configured = trim((string) config('canonical.url'));

        if ($configured === '') {
            return null;
        }

        if (! str_contains($configured, '://')) {
            $configured = 'https://'.$configured;
        }

        $host = parse_url($configured, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $scheme = parse_url($configured, PHP_URL_SCHEME);
        $scheme = is_string($scheme) && $scheme !== '' ? $scheme : 'https';
        $port = parse_url($configured, PHP_URL_PORT);
        $portSuffix = is_int($port) ? ':'.$port : '';

        return strtolower($scheme).'://'.strtolower($host).$portSuffix;
    }
}
