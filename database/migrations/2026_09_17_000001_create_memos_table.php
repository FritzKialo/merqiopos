<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A memo's actual delivery is one AppNotification row per recipient
    // (so it shows up in the bell dropdown everyone already has) — this
    // table is just the sent record itself: who sent what, to whom, and
    // how many people it reached, so Settings > Memos can show a history
    // instead of announcements vanishing the moment they're sent.
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // The business the memo was composed from — kept even for an
            // org-wide memo (scope='org') as "sent from" context, not as
            // the delivery scope itself.
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope'); // store | org | role
            $table->string('target_role')->nullable(); // only set when scope = role
            $table->string('title');
            $table->text('message');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
