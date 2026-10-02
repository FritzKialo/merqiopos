<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrustSafaricom
{
    // Safaricom Daraja production + sandbox IP ranges (updated June 2024)
    private const ALLOWED = [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.138',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.74',
        '196.201.212.69',
        // Sandbox
        '196.201.214.200',
    ];

    public function handle(Request $request, Closure $next)
    {
        // In local/sandbox development, skip IP check
        if (config('app.env') !== 'production') {
            return $next($request);
        }

        $ip = $request->ip();

        if (!in_array($ip, self::ALLOWED, true)) {
            // Bumped from warning() — production's LOG_LEVEL only records
            // error and above (see ReceiptService.php's note on the same
            // issue). This one matters more than most: if Safaricom ever
            // rotates their IP ranges and this hardcoded ALLOWED list goes
            // stale, every real M-Pesa callback starts getting rejected here
            // and every payment confirmation silently stops working
            // app-wide — with nothing, previously, to show it happened.
            Log::error('M-Pesa callback blocked: unknown IP', ['ip' => $ip]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
        }

        return $next($request);
    }
}
