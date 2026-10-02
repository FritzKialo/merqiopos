<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');

        if (!$token) {
            return response()->json(['error' => 'API token required. Pass it as: Authorization: Bearer {token}'], 401);
        }

        $business = Business::where('api_token', hash('sha256', $token))->first();

        if (!$business) {
            return response()->json(['error' => 'Invalid API token.'], 401);
        }

        // 'api_access' is an org-level feature (config/plans.php 'org' =>
        // [...]) — it isn't a key in any store-level plan config at all, so
        // Business::hasFeature() was always returning false here regardless
        // of plan. Every API request has been getting a 403 "requires
        // Enterprise" even from genuine Enterprise-plan organizations —
        // the entire v1 REST API has been unusable for every customer.
        if (!($business->organization?->hasFeature('api_access') ?? $business->hasFeature('api_access'))) {
            return response()->json(['error' => 'API access requires the Enterprise plan.'], 403);
        }

        $request->merge(['_api_business' => $business]);

        return $next($request);
    }
}
