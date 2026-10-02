<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * The support chat bubble (tenant side, SupportChatController) and the platform
 * admin's inbox (Admin\SupportController) — one continuous conversation per
 * (business, user), with staff-only notes never visible to the tenant.
 */
class SupportChatTest extends TestCase
{
    use CreatesOrganization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create([
            'is_super_admin' => true, 'two_factor_enabled' => true, 'two_factor_confirmed_at' => now(),
        ]);
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin)->withSession(['2fa_verified' => true, '2fa_last_verified' => time()]);
    }

    // ── Sending ──────────────────────────────────────────────────────────────

    /** @test */
    public function a_tenant_sending_a_first_message_creates_an_open_conversation(): void
    {
        [$owner] = $this->scaffoldOrg();

        $this->actingAs($owner)->postJson(route('support.send'), [
            'body' => 'I cannot find where to print a bill.',
            'page' => '/sales/create?search=secret-token',
        ])->assertOk();

        $conversation = SupportConversation::where('user_id', $owner->id)->first();
        $this->assertNotNull($conversation);
        $this->assertEquals('open', $conversation->status);
        $this->assertEquals(1, $conversation->unread_admin);
        // Only the path is kept — never the query string (it could carry a search term or token).
        $this->assertEquals('/sales/create', $conversation->last_page);
    }

    /** @test */
    public function an_empty_message_with_no_attachment_is_rejected(): void
    {
        [$owner] = $this->scaffoldOrg();

        $this->actingAs($owner)->postJson(route('support.send'), ['body' => ''])
            ->assertStatus(422);
    }

    /** @test */
    public function a_message_over_2000_characters_is_rejected(): void
    {
        [$owner] = $this->scaffoldOrg();

        $this->actingAs($owner)->postJson(route('support.send'), ['body' => str_repeat('x', 2001)])
            ->assertStatus(422);
    }

    /** @test */
    public function a_screenshot_can_be_attached_and_is_stored_privately(): void
    {
        [$owner] = $this->scaffoldOrg();

        // ->create() with an explicit mime type, not ->image() (needs the GD extension,
        // which isn't installed here) — the 'image' validation rule only inspects the
        // declared mime type on a faked upload either way.
        $this->actingAs($owner)->postJson(route('support.send'), [
            'body' => 'see attached', 'image' => UploadedFile::fake()->create('shot.jpg', 10, 'image/jpeg'),
        ])->assertOk();

        $message = SupportMessage::where('sender_type', 'tenant')->latest('id')->first();
        $this->assertNotNull($message->attachment_path);
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    /** @test */
    public function a_file_whose_real_type_is_not_an_image_is_rejected_despite_the_png_extension(): void
    {
        [$owner] = $this->scaffoldOrg();

        $fake = UploadedFile::fake()->create('evil.png', 10, 'text/plain');

        $this->actingAs($owner)->postJson(route('support.send'), ['body' => 'x', 'image' => $fake])
            ->assertStatus(422);
    }

    /** @test */
    public function a_super_admin_cannot_use_the_tenant_chat(): void
    {
        $this->asAdmin()->getJson(route('support.thread'))->assertForbidden();
    }

    // ── Attachment access control ────────────────────────────────────────────

    /** @test */
    public function only_the_conversations_own_user_can_view_its_screenshot(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->actingAs($owner)->postJson(route('support.send'), [
            'body' => 'see attached', 'image' => UploadedFile::fake()->create('shot.jpg', 10, 'image/jpeg'),
        ]);
        $message = SupportMessage::latest('id')->first();

        $this->actingAs($owner)->get(route('support.attachment', $message))->assertOk();

        // A different user in the SAME business must not see it.
        $colleague = User::factory()->cashier()->create(['organization_id' => $owner->organization_id]);
        $business->users()->attach($colleague->id, ['role' => 'cashier']);
        $this->actingAs($colleague)->get(route('support.attachment', $message))->assertNotFound();

        // A user from a completely different business must not see it either.
        [$stranger] = $this->scaffoldOrg();
        $this->actingAs($stranger)->get(route('support.attachment', $message))->assertNotFound();
    }

    // ── Admin inbox ──────────────────────────────────────────────────────────

    /** @test */
    public function a_non_admin_cannot_reach_the_support_inbox(): void
    {
        [$owner] = $this->scaffoldOrg();
        $this->actingAs($owner)->get(route('admin.support.index'))->assertForbidden();
    }

    /** @test */
    public function admin_reply_reaches_the_tenant_and_marks_the_conversation_answered(): void
    {
        [$owner] = $this->scaffoldOrg();
        $this->actingAs($owner)->postJson(route('support.send'), ['body' => 'hello, help please']);
        $conversation = SupportConversation::where('user_id', $owner->id)->first();

        $this->asAdmin()->post(route('admin.support.reply', $conversation), ['body' => 'We are on it.'])
            ->assertRedirect();

        $conversation->refresh();
        $this->assertEquals('answered', $conversation->status);
        $this->assertEquals(1, $conversation->unread_tenant);
        $this->assertTrue(AppNotification::where('user_id', $owner->id)->where('type', 'support_reply')->exists());

        $thread = $this->actingAs($owner)->getJson(route('support.thread', ['mark' => 1]))->json();
        $this->assertContains('We are on it.', array_column($thread['messages'], 'body'));
        $this->assertEquals(0, SupportConversation::find($conversation->id)->unread_tenant);
    }

    /** @test */
    public function a_staff_only_note_never_appears_in_the_tenants_thread(): void
    {
        [$owner] = $this->scaffoldOrg();
        $this->actingAs($owner)->postJson(route('support.send'), ['body' => 'hello']);
        $conversation = SupportConversation::where('user_id', $owner->id)->first();

        $this->asAdmin()->post(route('admin.support.note', $conversation), ['body' => 'internal: check their payroll setup']);

        $thread = $this->actingAs($owner)->getJson(route('support.thread'))->json();
        $bodies = array_column($thread['messages'], 'body');
        $this->assertNotContains('internal: check their payroll setup', $bodies);
    }

    /** @test */
    public function polling_after_the_last_seen_id_returns_nothing_new(): void
    {
        [$owner] = $this->scaffoldOrg();
        $this->actingAs($owner)->postJson(route('support.send'), ['body' => 'hello']);
        $lastId = SupportMessage::latest('id')->first()->id;

        $thread = $this->actingAs($owner)->getJson(route('support.thread', ['after' => $lastId]))->json();

        $this->assertCount(0, $thread['messages']);
    }

    /** @test */
    public function resolving_then_the_tenant_writing_again_reopens_the_conversation(): void
    {
        [$owner] = $this->scaffoldOrg();
        $this->actingAs($owner)->postJson(route('support.send'), ['body' => 'hello']);
        $conversation = SupportConversation::where('user_id', $owner->id)->first();

        $this->asAdmin()->post(route('admin.support.status', $conversation), ['status' => 'resolved']);
        $this->assertEquals('resolved', $conversation->fresh()->status);

        $this->actingAs($owner)->postJson(route('support.send'), ['body' => 'one more thing']);
        $this->assertEquals('open', $conversation->fresh()->status);
    }
}
