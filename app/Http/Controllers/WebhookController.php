<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchWebhook;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebhookController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorize(Webhook $webhook): void
    {
        if ($webhook->business_id !== $this->businessId()) {
            abort(403);
        }
    }

    public function index()
    {
        $webhooks = Webhook::forBusiness($this->businessId())
            ->with(['deliveries' => fn ($q) => $q->latest()->limit(1)])
            ->latest()
            ->get();

        $events = WebhookService::EVENTS;
        return view('settings.webhooks.index', compact('webhooks', 'events'));
    }

    public function create()
    {
        $events = WebhookService::EVENTS;
        return view('settings.webhooks.create', compact('events'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'url'    => 'required|url|max:500',
            'secret' => 'nullable|string|max:100',
            'events' => 'required|array|min:1',
            'events.*' => 'in:' . implode(',', WebhookService::EVENTS),
        ]);

        Webhook::create([
            'business_id' => $this->businessId(),
            'url'         => $request->url,
            'secret'      => $request->secret ?: null,
            'events'      => $request->events,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return redirect()->route('settings.webhooks.index')
            ->with('success', 'Webhook created.');
    }

    public function edit(Webhook $webhook)
    {
        $this->authorize($webhook);
        $events = WebhookService::EVENTS;
        return view('settings.webhooks.edit', compact('webhook', 'events'));
    }

    public function update(Request $request, Webhook $webhook)
    {
        $this->authorize($webhook);

        $request->validate([
            'url'    => 'required|url|max:500',
            'secret' => 'nullable|string|max:100',
            'events' => 'required|array|min:1',
            'events.*' => 'in:' . implode(',', WebhookService::EVENTS),
        ]);

        $webhook->update([
            'url'       => $request->url,
            'secret'    => $request->secret ?: null,
            'events'    => $request->events,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('settings.webhooks.index')
            ->with('success', 'Webhook updated.');
    }

    public function destroy(Webhook $webhook)
    {
        $this->authorize($webhook);
        $webhook->delete();
        return redirect()->route('settings.webhooks.index')
            ->with('success', 'Webhook deleted.');
    }

    public function test(Request $request, Webhook $webhook)
    {
        $this->authorize($webhook);

        DispatchWebhook::dispatch($webhook, 'test.ping', [
            'message' => 'This is a test ping from Merqio POS.',
        ]);

        return back()->with('success', 'Test ping queued for delivery.');
    }

    public function deliveries(Webhook $webhook)
    {
        $this->authorize($webhook);
        $deliveries = $webhook->deliveries()->latest()->paginate(30);
        return view('settings.webhooks.deliveries', compact('webhook', 'deliveries'));
    }
}
