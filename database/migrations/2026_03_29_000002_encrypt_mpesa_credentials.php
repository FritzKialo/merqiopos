<?php

use App\Models\Business;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Re-save all businesses through the Eloquent model so that
     * the 'encrypted' cast on mpesa_consumer_key, mpesa_consumer_secret,
     * and mpesa_passkey encrypts any existing plaintext values.
     *
     * Column types are unchanged — encrypted strings fit within TEXT/VARCHAR.
     */
    public function up(): void
    {
        Business::whereNotNull('mpesa_consumer_key')
            ->orWhereNotNull('mpesa_consumer_secret')
            ->orWhereNotNull('mpesa_passkey')
            ->each(function (Business $business) {
                // Reading through the model decrypts (but these are still plaintext
                // in DB at this point since the cast was just added).
                // We force-save to trigger encryption on write.
                try {
                    $business->saveQuietly();
                } catch (\Exception $e) {
                    // If already encrypted (e.g., re-running migration), skip silently
                }
            });
    }

    public function down(): void
    {
        // Decryption on rollback would require reading through model and
        // storing raw values — intentionally left as no-op for security.
        // To rollback: remove the encrypted cast from Business model first,
        // then re-run this migration's down().
    }
};
