<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // A dedicated /notifications page was overkill for what this app needs
    // — see the topbar bell dropdown in layouts/app.blade.php, which now
    // renders the recent list directly and is the only surface for this
    // feature. This route/name stays (nothing else in the app links to it
    // by name, but an old bookmark or browser history entry might) purely
    // so a stray hit doesn't 404 — it just sends the visitor to the
    // dashboard instead of rendering a page that no longer exists.
    public function index()
    {
        return redirect()->route('dashboard');
    }

    public function markRead(Request $request, $id)
    {
        AppNotification::forUser()->where('id', $id)->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back();
    }

    public function readAll(Request $request)
    {
        AppNotification::forUser()->unread()->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'All marked as read.');
    }

    public function destroy(Request $request, $id)
    {
        AppNotification::forUser()->where('id', $id)->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back();
    }
}
