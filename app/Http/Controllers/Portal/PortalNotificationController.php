<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification;
use Illuminate\Http\Request;

class PortalNotificationController extends Controller
{
    public function markRead(Request $request, $id)
    {
        CustomerNotification::forCustomer()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request)
    {
        CustomerNotification::forCustomer()->unread()->update(['read_at' => now()]);

        return back()->with('success', 'All marked as read.');
    }

    public function destroy(Request $request, $id)
    {
        CustomerNotification::forCustomer()->where('id', $id)->delete();

        return back();
    }
}
