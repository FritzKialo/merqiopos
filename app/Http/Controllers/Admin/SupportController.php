<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportChatController;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\ErrorLog;
use App\Models\SupportCannedReply;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The platform admin's support inbox. */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'open');

        $q = SupportConversation::with(['business:id,name', 'user:id,name,email'])->orderByDesc('last_message_at');
        if (in_array($filter, ['open', 'answered', 'resolved'], true)) $q->where('status', $filter);
        if ($filter === 'unread') $q->where('unread_admin', '>', 0);
        if ($request->filled('q')) {
            $s = '%' . $request->q . '%';
            $q->where(function ($w) use ($s) {
                $w->where('last_message_preview', 'like', $s)
                  ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $s))
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $s)->orWhere('email', 'like', $s));
            });
        }

        $conversations = $q->paginate(25)->withQueryString();
        $counts = [
            'open'     => SupportConversation::where('status', 'open')->count(),
            'answered' => SupportConversation::where('status', 'answered')->count(),
            'resolved' => SupportConversation::where('status', 'resolved')->count(),
            'unread'   => SupportConversation::where('unread_admin', '>', 0)->count(),
        ];

        return view('admin.support.index', compact('conversations', 'counts', 'filter'));
    }

    public function show(SupportConversation $conversation)
    {
        $conversation->load(['business.organization.owner', 'user']);
        if ($conversation->unread_admin > 0) {
            $conversation->update(['unread_admin' => 0]);
        }

        $messages = $conversation->messages()->with('sender:id,name')->orderBy('id')->get();
        $canned   = SupportCannedReply::orderBy('title')->get();

        // What has been going wrong for this store lately — the quickest way to answer "my sale failed".
        $problems = ActivityLog::failures()->where('business_id', $conversation->business_id)->where('created_at', '>=', now()->subDays(3))
            ->latest('id')->limit(6)->get(['created_at', 'route_name', 'path', 'outcome', 'message']);
        $errors = ErrorLog::where('business_id', $conversation->business_id)->where('last_seen_at', '>=', now()->subDays(3))
            ->orderByDesc('last_seen_at')->limit(4)->get(['fingerprint', 'exception', 'message', 'count', 'last_seen_at']);

        return view('admin.support.show', compact('conversation', 'messages', 'canned', 'problems', 'errors'));
    }

    public function reply(Request $request, SupportConversation $conversation)
    {
        $data = $request->validate([
            'body'  => 'nullable|string|max:4000',
            'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('image')) {
            return back()->with('error', 'Write a reply or attach a screenshot.');
        }

        $attachment = [];
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $attachment = [
                'attachment_path' => $file->storeAs('support/' . $conversation->id, Str::random(32) . '.' . strtolower($file->getClientOriginalExtension() ?: 'png'), 'local'),
                'attachment_name' => Str::limit(preg_replace('/[^\w\.\- ]+/u', '', $file->getClientOriginalName()) ?: 'image', 140, ''),
                'attachment_mime' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }

        DB::transaction(function () use ($conversation, $body, $attachment) {
            $m = SupportMessage::create([
                'conversation_id' => $conversation->id, 'sender_type' => 'staff', 'sender_user_id' => Auth::id(),
                'body' => $body !== '' ? $body : '(image)', 'created_at' => now(),
            ] + $attachment);

            $conversation->update([
                'status' => 'answered', 'last_message_by' => 'staff', 'last_message_at' => now(),
                'last_message_preview' => Str::limit($m->body, 150, '…'),
                'unread_tenant' => $conversation->unread_tenant + 1, 'unread_admin' => 0, 'resolved_at' => null,
            ]);
        });

        // A bell notification as well, so a reply is seen even when the chat bubble is closed.
        if ($conversation->business_id) {
            AppNotification::notifyUser($conversation->business_id, $conversation->user_id, 'support_reply', 'Support replied',
                Str::limit($body !== '' ? $body : 'Support sent you an image.', 120, '…'), null, 'chat');
        }

        return back()->with('success', 'Reply sent.');
    }

    // A note only staff can see (never shown in the tenant's chat).
    public function note(Request $request, SupportConversation $conversation)
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);
        SupportMessage::create([
            'conversation_id' => $conversation->id, 'sender_type' => 'note', 'sender_user_id' => Auth::id(),
            'body' => trim($data['body']), 'created_at' => now(),
        ]);

        return back()->with('success', 'Note saved (only staff can see it).');
    }

    public function status(Request $request, SupportConversation $conversation)
    {
        $to = $request->validate(['status' => 'required|in:open,resolved'])['status'];
        $conversation->update(['status' => $to, 'resolved_at' => $to === 'resolved' ? now() : null]);

        return back()->with('success', $to === 'resolved' ? 'Marked resolved.' : 'Reopened.');
    }

    public function attachment(SupportMessage $message)
    {
        abort_unless($message->attachment_path, 404);

        return SupportChatController::stream($message);
    }

    /** Polled by the admin layout: the count for the nav badge and tab title. */
    public function unread()
    {
        return response()->json([
            'unread'  => (int) SupportConversation::where('unread_admin', '>', 0)->count(),
            // max() returns the raw database string, not a date object.
            'latest'  => ($latest = SupportConversation::where('unread_admin', '>', 0)->max('last_message_at')) ? strtotime($latest) : null,
        ]);
    }

    public function storeCanned(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:80', 'body' => 'required|string|max:2000']);
        SupportCannedReply::create($data);

        return back()->with('success', 'Saved reply added.');
    }

    public function destroyCanned(SupportCannedReply $canned)
    {
        $canned->delete();

        return back()->with('success', 'Saved reply removed.');
    }
}
