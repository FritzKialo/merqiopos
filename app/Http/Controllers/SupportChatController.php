<?php

namespace App\Http\Controllers;

use App\Mail\SupportMessageAlert;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The tenant's side of support: the chat bubble talks to these endpoints.
 * One continuous conversation per person per store. Outside the 'subscribed'
 * middleware on purpose: a store whose plan has lapsed must still be able to
 * ask for help.
 */
class SupportChatController extends Controller
{
    private function context(): array
    {
        $user = Auth::user();
        abort_if($user->isSuperAdmin(), 403, 'Support chat is for stores.');
        $business = $user->currentBusiness();
        abort_if(! $business, 403);

        return [$user, $business];
    }

    private function conversation(User $user, $business, bool $create = false): ?SupportConversation
    {
        $c = SupportConversation::where('business_id', $business->id)->where('user_id', $user->id)->first();
        if (! $c && $create) {
            $c = SupportConversation::create([
                'organization_id' => $business->organization_id,
                'business_id'     => $business->id,
                'user_id'         => $user->id,
                'role'            => $user->role,
                'status'          => 'open',
            ]);
        }

        return $c;
    }

    /** Messages, newest last. ?after=ID returns only newer ones; ?mark=1 marks staff replies as read. */
    public function thread(Request $request)
    {
        [$user, $business] = $this->context();
        $c = $this->conversation($user, $business);
        if (! $c) {
            return response()->json(['messages' => [], 'unread' => 0, 'status' => null]);
        }

        $q = $c->messages()->whereIn('sender_type', ['tenant', 'staff'])->orderBy('id');
        if ($request->filled('after')) {
            $q->where('id', '>', (int) $request->after);
        } else {
            $q->latest('id')->limit(100);
        }
        $messages = $request->filled('after') ? $q->get() : $q->get()->sortBy('id')->values();

        if ($request->boolean('mark') && $c->unread_tenant > 0) {
            $c->update(['unread_tenant' => 0]);
        }

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->present($m))->values(),
            'unread'   => (int) $c->fresh()->unread_tenant,
            'status'   => $c->status,
        ]);
    }

    public function unread()
    {
        [$user, $business] = $this->context();

        return response()->json(['unread' => (int) SupportConversation::where('business_id', $business->id)->where('user_id', $user->id)->value('unread_tenant')]);
    }

    public function send(Request $request)
    {
        [$user, $business] = $this->context();

        $data = $request->validate([
            'body'  => 'nullable|string|max:2000',
            'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:3072',
            'page'  => 'nullable|string|max:255',
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('image')) {
            return response()->json(['error' => 'Type a message or attach a screenshot.'], 422);
        }

        $conversation = $this->conversation($user, $business, true);
        // Only the path: never a query string, which could carry a search or a token.
        $page = isset($data['page']) ? Str::limit(parse_url($data['page'], PHP_URL_PATH) ?: '', 250, '') : null;

        $attachment = [];
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext  = strtolower($file->getClientOriginalExtension() ?: 'png');
            $path = $file->storeAs('support/' . $conversation->id, Str::random(32) . '.' . $ext, 'local');
            $attachment = [
                'attachment_path' => $path,
                'attachment_name' => Str::limit(preg_replace('/[^\w\.\- ]+/u', '', $file->getClientOriginalName()) ?: 'screenshot', 140, ''),
                'attachment_mime' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }

        $message = DB::transaction(function () use ($conversation, $user, $body, $page, $attachment) {
            $m = SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => 'tenant',
                'sender_user_id'  => $user->id,
                'body'            => $body !== '' ? $body : '(screenshot)',
                'page'            => $page,
                'created_at'      => now(),
            ] + $attachment);

            $conversation->update([
                'status'               => 'open',           // a new message always puts it back on the admin's list
                'resolved_at'          => null,
                'role'                 => $user->role,
                'first_page'           => $conversation->first_page ?: $page,
                'last_page'            => $page ?: $conversation->last_page,
                'last_message_by'      => 'tenant',
                'last_message_preview' => Str::limit($m->body, 150, '…'),
                'last_message_at'      => now(),
                'unread_admin'         => $conversation->unread_admin + 1,
            ]);

            return $m;
        });

        $this->alertAdmin($conversation->fresh(['business', 'user']), $message->body);

        return response()->json(['message' => $this->present($message)]);
    }

    /** Streams a screenshot — only to the person whose conversation it belongs to. */
    public function attachment(SupportMessage $message)
    {
        $conversation = $message->conversation;
        abort_unless($conversation && $conversation->user_id === Auth::id() && $message->sender_type !== 'note' && $message->attachment_path, 404);

        return $this->stream($message);
    }

    public static function stream(SupportMessage $message)
    {
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type'           => $message->attachment_mime ?: 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────

    private function present(SupportMessage $m): array
    {
        return [
            'id'    => $m->id,
            'mine'  => $m->sender_type === 'tenant',
            'body'  => $m->body,
            'image' => $m->attachment_path ? route('support.attachment', $m->id) : null,
            'time'  => $m->created_at?->format('d M, H:i'),
        ];
    }

    /** Email the platform admin — but never let a mail problem stop a tenant sending. */
    private function alertAdmin(SupportConversation $conversation, string $preview): void
    {
        try {
            $key = 'support-alert:' . $conversation->id;
            // Cache::add is atomic: only the first message in the window sends an email.
            if (! Cache::add($key, 1, now()->addMinutes((int) config('support.alert_cooldown_minutes', 10)))) {
                return;
            }
            $to = config('support.admin_emails') ?: User::where('is_super_admin', true)->whereNotNull('email')->pluck('email')->all();
            if ($to) {
                Mail::to($to)->queue(new SupportMessageAlert($conversation, Str::limit($preview, 400, '…')));
            }
        } catch (\Throwable $e) {
            Log::error('Support alert email failed: ' . $e->getMessage());
        }
    }
}
