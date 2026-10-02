<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * A separate OAuth flow from Google sign-in (GoogleAuthController) — this
 * one asks for the Sheets scope specifically, with offline access so a
 * background job can keep syncing without the owner's browser open. Scoped
 * to the owner's own currently-active business, same as every other
 * per-business integration (M-Pesa, Pesapal) in Settings.
 */
class GoogleSheetsController extends Controller
{
    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public function index()
    {
        $business = Auth::user()->currentBusiness();

        return view('settings.google-sheets', ['business' => $business]);
    }

    public function connect()
    {
        return Socialite::driver('google')
            ->scopes([self::SCOPE])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirectUrl(route('settings.google-sheets.callback'))
            ->redirect();
    }

    public function callback(GoogleSheetsService $sheets)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('settings.google-sheets.callback'))
                ->user();
        } catch (\Exception) {
            return redirect()->route('settings.google-sheets.index')
                ->with('error', 'Google connection was cancelled or failed. Please try again.');
        }

        if (! $googleUser->refreshToken) {
            // Happens if the account was connected before without the
            // consent-prompt forcing a fresh refresh token — Google only
            // issues one the first time an app is authorized.
            return redirect()->route('settings.google-sheets.index')
                ->with('error', 'Google did not grant offline access. Disconnect any prior Merqio POS access in your Google Account permissions, then try connecting again.');
        }

        $business = Auth::user()->currentBusiness();

        $business->update([
            'google_sheets_access_token'     => $googleUser->token,
            'google_sheets_refresh_token'    => $googleUser->refreshToken,
            'google_sheets_token_expires_at' => now()->addSeconds($googleUser->expiresIn ?? 3600),
            'google_sheets_connected_by'     => Auth::id(),
            'google_sheets_connected_at'     => now(),
        ]);

        $spreadsheetId = $sheets->createSpreadsheet($business);

        if (! $spreadsheetId) {
            $business->update([
                'google_sheets_access_token'  => null,
                'google_sheets_refresh_token' => null,
            ]);
            return redirect()->route('settings.google-sheets.index')
                ->with('error', 'Connected to Google, but creating the spreadsheet failed. Please try again.');
        }

        $business->update(['google_sheets_spreadsheet_id' => $spreadsheetId]);

        \App\Jobs\SyncGoogleSheetEvent::dispatch($business->id, 'sheets.initial_sync', []);

        \App\Models\AuditLog::record('google_sheets.connected', $business);

        return redirect()->route('settings.google-sheets.index')
            ->with('success', 'Google Sheets connected. Your existing data is being imported now (give it a minute), and new sales/expenses/changes will keep it updated automatically.');
    }

    public function disconnect()
    {
        $business = Auth::user()->currentBusiness();

        $business->update([
            'google_sheets_access_token'     => null,
            'google_sheets_refresh_token'    => null,
            'google_sheets_token_expires_at' => null,
            'google_sheets_spreadsheet_id'   => null,
            'google_sheets_connected_by'     => null,
            'google_sheets_connected_at'     => null,
        ]);

        \App\Models\AuditLog::record('google_sheets.disconnected', $business);

        return redirect()->route('settings.google-sheets.index')
            ->with('success', 'Google Sheets disconnected. The existing spreadsheet in your Google Drive is untouched — it just stops receiving updates.');
    }

    public function syncNow(GoogleSheetsService $sheets)
    {
        $business = Auth::user()->currentBusiness();

        if (! $business->hasGoogleSheetsConnected()) {
            return redirect()->route('settings.google-sheets.index')->with('error', 'Connect Google Sheets first.');
        }

        \App\Jobs\SyncGoogleSheetEvent::dispatch($business->id, 'sheets.initial_sync', []);

        return redirect()->route('settings.google-sheets.index')
            ->with('success', 'Full sync started — give it a moment to refresh all four tabs.');
    }
}
