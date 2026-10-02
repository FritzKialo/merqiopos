<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// In-app support: a chat bubble for tenants, an inbox for the platform admin.
//
//  support_conversations  one continuous thread per person (user) in a store
//  support_messages       tenant messages, staff replies, and staff-only notes
//  support_canned_replies saved answers the admin can insert with one click
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 12)->default('open');          // open | answered | resolved
            $table->string('role', 30)->nullable();
            $table->string('first_page', 255)->nullable();           // where they were when they first wrote
            $table->string('last_page', 255)->nullable();
            $table->string('last_message_by', 10)->nullable();       // tenant | staff
            $table->string('last_message_preview', 160)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_admin')->default(0);     // tenant messages staff has not read
            $table->unsignedInteger('unread_tenant')->default(0);    // staff replies the tenant has not read
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_message_at']);
            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('sender_type', 10);                       // tenant | staff | note
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->text('body');
            $table->string('page', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 150)->nullable();
            $table->string('attachment_mime', 60)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('support_canned_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->text('body');
            $table->timestamps();
        });

        $now = now();
        DB::table('support_canned_replies')->insert([
            ['title' => 'Thanks — looking into it', 'body' => "Thanks for letting us know. We're looking into this now and will get back to you shortly.", 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Need a screenshot', 'body' => "Could you please send a screenshot of the screen where this happens? Use the picture button next to the message box.", 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Try refreshing / re-login', 'body' => "Please refresh the page (or sign out and back in) and try again. Let us know if it still doesn't work.", 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Fixed', 'body' => "This is now fixed. Please try again and tell us if anything still looks wrong.", 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Anything else?', 'body' => "Is there anything else we can help you with?", 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('support_canned_replies');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
