<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class HomeController extends Controller {

    // The colorful tile "home menu" launcher — same idea as a physical POS
    // terminal's home screen (Register / Inventory / Reports / ... as
    // individual tiles), gated to exactly what the sidebar itself would
    // show for this user. Staff never reach this (see
    // User::postLoginRoute()) — every top-level sidebar section explicitly
    // excludes staff except Dashboard, so this page would just show them
    // one tile on an otherwise empty screen; they go straight to their
    // staff portal dashboard instead, same as before this feature existed.
    // Super admins never reach this either (redirected to the admin panel
    // at login), so no branch for that here.
    public function index() {
        $user     = Auth::user();
        $business = $user->currentBusiness();
        $org      = $business?->organization;

        $tiles = collect([
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'color' => 'slate',
                'icon'  => 'grid',
                'show'  => true,
            ],
            [
                // The till itself (routes/web.php's 'sales' group) — Sales
                // History lives one tap further in, on the till's own nav
                // dock, same as every other secondary link there.
                'label' => 'New Sale',
                'route' => 'sales.create',
                'color' => 'teal',
                'icon'  => 'cart',
                'show'  => $user->hasAnyRole('owner', 'manager', 'cashier'),
            ],
            [
                'label' => 'Inventory',
                'route' => 'inventory.index',
                'color' => 'indigo',
                'icon'  => 'package',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier'),
            ],
            [
                'label' => 'Customers',
                'route' => 'customers.index',
                'color' => 'violet',
                'icon'  => 'users',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier')
                    && $business?->hasFeature('customers'),
            ],
            [
                'label' => 'Services',
                'route' => 'services.index',
                'color' => 'amber',
                'icon'  => 'tool',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier'),
            ],
            [
                'label' => 'Expenses',
                'route' => 'expenses.index',
                'color' => 'rose',
                'icon'  => 'receipt',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager')
                    && $business?->hasFeature('expenses'),
            ],
            [
                'label' => 'Reports',
                'route' => 'reports.index',
                'color' => 'blue',
                'icon'  => 'bar-chart',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager'),
            ],
            [
                'label' => 'Suppliers',
                'route' => 'suppliers.index',
                'color' => 'orange',
                'icon'  => 'truck',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager')
                    && $business?->hasFeature('suppliers'),
            ],
            [
                'label' => 'Discounts',
                'route' => 'discounts.index',
                'color' => 'pink',
                'icon'  => 'tag',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager'),
            ],
            [
                'label' => 'Staff',
                'route' => 'staff.index',
                'color' => 'emerald',
                'icon'  => 'staff',
                'show'  => $user->hasAnyRole('owner', 'overall_manager', 'manager')
                    && $org?->hasFeature('payroll'),
            ],
            [
                'label' => $user->canActAsOwner() ? 'Settings' : 'Team',
                'route' => $user->canActAsOwner() ? 'settings.business' : 'settings.team',
                'color' => 'gray',
                'icon'  => 'settings',
                'show'  => $user->canActAsOwner() || $user->hasRole('manager'),
            ],
        ])->filter(fn ($t) => $t['show'])->values();

        return view('home.index', compact('tiles'));
    }
}
