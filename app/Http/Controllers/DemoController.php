<?php

namespace App\Http\Controllers;

use App\Support\DemoStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** "Try the demo": a private, throw-away sample store for every visitor. */
class DemoController extends Controller
{
    public function start(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('menu');
        }
        if (DemoStore::activeCount() >= DemoStore::MAX_ACTIVE) {
            return back()->with('error', 'The demo is very busy right now. Please try again in a few minutes, or start your free trial.');
        }

        $userId = DemoStore::create();
        // Tells the developer log to skip this visit (demo traffic is noise there).
        $request->attributes->set('is_demo', true);

        $request->session()->regenerate();
        Auth::guard('web')->loginUsingId($userId);
        $request->session()->put('demo_started_at', time());

        return redirect()->route('menu')->with('success', 'Welcome to the demo! This is a sample shop with a month of sales. Look around and try anything.');
    }

    // Leaving the demo removes the sandbox straight away and sends the visitor to sign up.
    public function exit(Request $request)
    {
        $user = Auth::user();
        $orgId = $user?->organization_id;
        abort_unless($orgId && DB::table('organizations')->where('id', $orgId)->where('is_demo', true)->exists(), 403);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        DemoStore::purge($orgId);
        Cache::forget('demo-org:' . $orgId);

        return redirect()->route('register');
    }
}
