<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Google Sheets API directly over HTTP rather than pulling in
 * the full google/apiclient package — the whole integration only needs
 * three endpoints (create, values.update, values.append), not the hundreds
 * of classes that package ships for every other Google API.
 *
 * One spreadsheet per business, created on first connect, with one tab
 * per data type. Sales and Expenses are append-only logs (new row per
 * event — simplest and safest for something transactional); Inventory and
 * Customers are overwritten in full on every sync (a live snapshot is more
 * useful than a log for things that change in place, like a stock count
 * or a customer's balance).
 */
class GoogleSheetsService
{
    public const TABS = ['Sales', 'Inventory', 'Customers', 'Expenses'];

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SHEETS_API = 'https://sheets.googleapis.com/v4/spreadsheets';

    /**
     * Exchanges the refresh token for a fresh access token if the stored
     * one has expired (or is within a minute of expiring, to avoid a race
     * against the request in flight). Google access tokens live about an
     * hour; the refresh token itself doesn't expire under normal use.
     */
    public function freshAccessToken(Business $business): ?string
    {
        if ($business->google_sheets_access_token
            && $business->google_sheets_token_expires_at
            && $business->google_sheets_token_expires_at->subMinute()->isFuture()) {
            return $business->google_sheets_access_token;
        }

        if (! $business->google_sheets_refresh_token) {
            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $business->google_sheets_refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::error('GoogleSheetsService: token refresh failed', [
                'business_id' => $business->id,
                'response'    => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        $business->update([
            'google_sheets_access_token'     => $data['access_token'],
            'google_sheets_token_expires_at' => now()->addSeconds($data['expires_in'] - 30),
        ]);

        return $data['access_token'];
    }

    /**
     * Creates the workbook with one tab per data type, titled and headed.
     * Returns the new spreadsheet ID, or null if the request failed (token
     * invalid/revoked, Sheets API error, etc.) — the caller decides how to
     * surface that rather than this throwing mid-OAuth-callback.
     */
    public function createSpreadsheet(Business $business): ?string
    {
        $token = $this->freshAccessToken($business);
        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)->post(self::SHEETS_API, [
            'properties' => ['title' => $business->name . ' — Merqio POS Export'],
            'sheets' => array_map(fn ($tab) => ['properties' => ['title' => $tab]], self::TABS),
        ]);

        if (! $response->successful()) {
            Log::error('GoogleSheetsService: spreadsheet creation failed', [
                'business_id' => $business->id,
                'response'    => $response->body(),
            ]);
            return null;
        }

        $spreadsheetId = $response->json('spreadsheetId');

        $this->overwriteTab($business, 'Sales', ['Date', 'Invoice #', 'Customer', 'Total', 'Payment Method', 'Status'], [], $spreadsheetId);
        $this->overwriteTab($business, 'Inventory', ['Product', 'SKU', 'Stock', 'Reorder Level', 'Buying Price', 'Selling Price'], [], $spreadsheetId);
        $this->overwriteTab($business, 'Customers', ['Name', 'Phone', 'Email', 'Balance', 'Credit Limit'], [], $spreadsheetId);
        $this->overwriteTab($business, 'Expenses', ['Date', 'Category', 'Title', 'Amount', 'Payment Method'], [], $spreadsheetId);

        return $spreadsheetId;
    }

    /** Appends one row to the end of a tab — used for Sales and Expenses. */
    public function appendRow(Business $business, string $tab, array $row): bool
    {
        $token = $this->freshAccessToken($business);
        if (! $token || ! $business->google_sheets_spreadsheet_id) {
            return false;
        }

        $range = rawurlencode($tab) . '!A:Z';
        $url = self::SHEETS_API . "/{$business->google_sheets_spreadsheet_id}/values/{$range}:append"
            . '?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

        $response = Http::withToken($token)->post($url, ['values' => [$row]]);

        if (! $response->successful()) {
            Log::warning('GoogleSheetsService: append failed', [
                'business_id' => $business->id,
                'tab'         => $tab,
                'response'    => $response->body(),
            ]);
        }

        return $response->successful();
    }

    /**
     * Replaces a tab's entire contents with a fresh header + rows — used
     * for Inventory and Customers, where "current state" matters more
     * than history. Clears first so a shrinking dataset (e.g. a deleted
     * customer) doesn't leave stale rows behind under a shorter new set.
     */
    public function overwriteTab(Business $business, string $tab, array $header, array $rows, ?string $spreadsheetIdOverride = null): bool
    {
        $token = $this->freshAccessToken($business);
        $spreadsheetId = $spreadsheetIdOverride ?? $business->google_sheets_spreadsheet_id;
        if (! $token || ! $spreadsheetId) {
            return false;
        }

        $range = rawurlencode($tab) . '!A1:Z10000';
        Http::withToken($token)->post(self::SHEETS_API . "/{$spreadsheetId}/values/{$range}:clear");

        $updateRange = rawurlencode($tab) . '!A1';
        $response = Http::withToken($token)->put(
            self::SHEETS_API . "/{$spreadsheetId}/values/{$updateRange}?valueInputOption=USER_ENTERED",
            ['values' => array_merge([$header], $rows)]
        );

        if (! $response->successful()) {
            Log::warning('GoogleSheetsService: tab overwrite failed', [
                'business_id' => $business->id,
                'tab'         => $tab,
                'response'    => $response->body(),
            ]);
        }

        return $response->successful();
    }
}
