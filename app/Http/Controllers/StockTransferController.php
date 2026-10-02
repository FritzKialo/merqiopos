<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    private function org()
    {
        return Auth::user()->organization;
    }

    // Org-wide roles (owner / overall manager) operate transfers between any
    // branches; branch managers are limited to their own branch(es).
    private function isOrgWide(): bool
    {
        return Auth::user()->canActAsOwner();
    }

    private function managedIds(): array
    {
        return Auth::user()->managedBusinessIds();
    }

    private function managesBranch(int $businessId): bool
    {
        return $this->isOrgWide() || in_array($businessId, $this->managedIds(), true);
    }

    private function authorizeTransfer(StockTransfer $transfer): void
    {
        if ($transfer->organization_id !== $this->org()->id) {
            abort(403);
        }

        // Branch managers may only touch transfers that involve their branch.
        if (! $this->isOrgWide()
            && ! $this->managesBranch($transfer->from_business_id)
            && ! $this->managesBranch($transfer->to_business_id)) {
            abort(403, 'You can only access transfers involving your branch.');
        }
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $org = $this->org();

        $transfersQuery = StockTransfer::with(['fromBusiness', 'toBusiness', 'requester'])
            ->forOrganization($org->id);

        // Branch managers see only transfers involving a branch they manage.
        if (! $this->isOrgWide()) {
            $managed = $this->managedIds();
            $transfersQuery->where(function ($q) use ($managed) {
                $q->whereIn('from_business_id', $managed)
                  ->orWhereIn('to_business_id', $managed);
            });
        }

        $transfers = $transfersQuery->latest()->paginate(20);

        $stores = Business::where('organization_id', $org->id)->get();

        return view('org.transfers.index', compact('transfers', 'stores'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        $org    = $this->org();
        $stores = Business::where('organization_id', $org->id)->get();

        // Branches this user may use as one side of the transfer.
        $managedIds = $this->isOrgWide() ? $stores->pluck('id')->all() : $this->managedIds();

        // Products are independent per-store records with no shared id across
        // branches, so the create form needs every store's catalogue up front
        // to let the requester explicitly map each source product to its real
        // counterpart at the destination — that mapping is what actually gets
        // used to move stock on receive(), instead of guessing by id.
        $productsByStore = Product::whereIn('business_id', $stores->pluck('id'))
            ->active()
            ->orderBy('name')
            ->get(['id', 'business_id', 'name', 'sku', 'stock_qty'])
            ->groupBy('business_id');

        return view('org.transfers.create', compact('stores', 'managedIds', 'productsByStore'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $org = $this->org();

        $request->validate([
            'from_business_id'  => 'required|integer|exists:businesses,id',
            'to_business_id'    => 'required|integer|exists:businesses,id|different:from_business_id',
            'notes'             => 'nullable|string|max:1000',
            'items'             => 'required|array|min:1',
            'items.*.product_id'          => 'required|integer|exists:products,id',
            // Explicit mapping to the matching product at the destination
            // store — required, not inferred. Products are independent
            // per-store rows with no shared id, so there's no safe way to
            // guess this; the requester must confirm it themselves.
            'items.*.dest_product_id'     => 'required|integer|exists:products,id',
            'items.*.quantity_requested'  => 'required|integer|min:1',
        ]);

        // Ensure both stores belong to the org
        $fromBusiness = Business::where('organization_id', $org->id)->findOrFail($request->from_business_id);
        $toBusiness   = Business::where('organization_id', $org->id)->findOrFail($request->to_business_id);

        // A branch manager must have one side of the transfer be their own branch.
        if (! $this->isOrgWide()
            && ! $this->managesBranch($fromBusiness->id)
            && ! $this->managesBranch($toBusiness->id)) {
            abort(403, 'A branch manager can only transfer stock to or from their own branch.');
        }

        DB::transaction(function () use ($request, $org, $fromBusiness, $toBusiness) {
            $transfer = StockTransfer::create([
                'organization_id'  => $org->id,
                'from_business_id' => $fromBusiness->id,
                'to_business_id'   => $request->to_business_id,
                'requested_by'     => Auth::id(),
                'transfer_number'  => StockTransfer::nextNumber($org->id),
                'status'           => 'pending',
                'notes'            => $request->notes,
                'requested_at'     => now(),
            ]);

            foreach ($request->items as $line) {
                $product     = Product::where('business_id', $fromBusiness->id)->findOrFail($line['product_id']);
                // Verify the chosen destination product genuinely belongs to
                // the destination store — never trust a posted id blindly.
                $destProduct = Product::where('business_id', $toBusiness->id)->findOrFail($line['dest_product_id']);

                StockTransferItem::create([
                    'stock_transfer_id'  => $transfer->id,
                    'product_id'         => $product->id,
                    'dest_product_id'    => $destProduct->id,
                    'product_name'       => $product->name,
                    'quantity_requested' => $line['quantity_requested'],
                    'quantity_dispatched'=> 0,
                    'quantity_received'  => 0,
                    'notes'              => $line['notes'] ?? null,
                ]);
            }
        });

        $created = StockTransfer::where('organization_id', $org->id)->where('requested_by', Auth::id())->latest('id')->first();
        foreach ([$fromBusiness, $toBusiness] as $branch) {
            \App\Models\AppNotification::notifyApprovers(
                $branch, Auth::id(), 'transfer_requested', 'Stock Transfer Requested',
                Auth::user()->name . ' requested a transfer from ' . $fromBusiness->name . ' to ' . $toBusiness->name . ($created ? ' (' . $created->transfer_number . ')' : '') . '.',
                $created ? route('org.transfers.show', $created) : route('org.transfers.index'), 'truck'
            );
        }

        return redirect()->route('org.transfers.index')->with('success', 'Transfer request created.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(StockTransfer $stockTransfer)
    {
        $this->authorizeTransfer($stockTransfer);
        $stockTransfer->load(['fromBusiness', 'toBusiness', 'requester', 'approver', 'items.product', 'items.destProduct']);

        return view('org.transfers.show', compact('stockTransfer'));
    }

    // ── Approve (org owner only) ──────────────────────────────────────────────

    public function approve(StockTransfer $stockTransfer)
    {
        $this->authorizeTransfer($stockTransfer);

        // A branch manager may approve only a transfer involving their branch
        // that they did NOT request — the counterpart authorises it. Owners and
        // overall managers can always approve.
        if (! $this->isOrgWide()) {
            $involvesManaged = $this->managesBranch($stockTransfer->from_business_id)
                            || $this->managesBranch($stockTransfer->to_business_id);
            if (! $involvesManaged || $stockTransfer->requested_by === Auth::id()) {
                abort(403, "Approval must come from the other branch's manager or an owner.");
            }
        }

        if ($stockTransfer->status !== 'pending') {
            return back()->with('error', 'Only pending transfers can be approved.');
        }

        $stockTransfer->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Notify org owner when a branch manager (not org-wide) approves a transfer
        if (! $this->isOrgWide()) {
            $org   = $this->org();
            $owner = $org?->owner;
            if ($owner) {
                $from = $stockTransfer->fromBusiness->name ?? 'Branch';
                $to   = $stockTransfer->toBusiness->name   ?? 'Branch';
                AppNotification::send(
                    $stockTransfer->from_business_id,
                    $owner->id,
                    'transfer_approved',
                    'Stock Transfer Approved by Manager',
                    Auth::user()->name . " approved transfer {$stockTransfer->transfer_number} ({$from} → {$to}). Please review.",
                    route('org.transfers.show', $stockTransfer),
                    'truck'
                );
            }
        }

        \App\Models\AuditLog::record('transfer.approved', $stockTransfer, ['number' => $stockTransfer->transfer_number]);

        return back()->with('success', 'Transfer approved.');
    }

    // ── Dispatch (from-store: decrement source stock) ─────────────────────────

    public function dispatch(Request $request, StockTransfer $stockTransfer)
    {
        $this->authorizeTransfer($stockTransfer);

        // Only the source branch (or an org-wide role) may dispatch.
        if (! $this->managesBranch($stockTransfer->from_business_id)) {
            abort(403, 'Only the sending branch can dispatch this transfer.');
        }

        if ($stockTransfer->status !== 'approved') {
            return back()->with('error', 'Transfer must be approved before dispatching.');
        }

        $request->validate([
            'items'                      => 'required|array',
            'items.*.id'                 => 'required|integer|exists:stock_transfer_items,id',
            'items.*.quantity_dispatched'=> 'required|integer|min:0',
        ]);

        try {
        DB::transaction(function () use ($request, $stockTransfer) {
            foreach ($request->items as $line) {
                $item = StockTransferItem::findOrFail($line['id']);
                if ($item->stock_transfer_id !== $stockTransfer->id) {
                    abort(403);
                }

                $qty = (int) $line['quantity_dispatched'];

                // Nothing capped this: a branch could dispatch more than was
                // requested, or more than it physically had, driving source
                // stock negative and inventing units at the destination.
                if ($qty > (int) $item->quantity_requested) {
                    throw new \RuntimeException("Cannot dispatch {$qty} of \"{$item->product_name}\" — only {$item->quantity_requested} were requested.");
                }
                if ($qty > 0) {
                    $onHand = (int) Product::where('business_id', $stockTransfer->from_business_id)
                        ->where('id', $item->product_id)->lockForUpdate()->value('stock_qty');
                    if ($qty > $onHand) {
                        throw new \RuntimeException("Cannot dispatch {$qty} of \"{$item->product_name}\" — only {$onHand} in stock at the sending branch.");
                    }
                }

                $item->update(['quantity_dispatched' => $qty]);

                if ($qty > 0) {
                    $product = Product::where('business_id', $stockTransfer->from_business_id)->find($item->product_id);
                    if ($product) {
                        $before = $product->stock_qty;
                        $product->decrement('stock_qty', $qty);
                        $product->refresh();

                        StockAdjustment::create([
                            'business_id'     => $stockTransfer->from_business_id,
                            'product_id'      => $product->id,
                            'user_id'         => Auth::id(),
                            'type'            => 'deduction',
                            'quantity_before' => $before,
                            'quantity_change' => $qty,
                            'quantity_after'  => $product->stock_qty,
                            'reason'          => 'Transfer dispatched: ' . $stockTransfer->transfer_number,
                            'reference'       => $stockTransfer->transfer_number,
                        ]);
                    }
                }
            }

            $stockTransfer->update([
                'status'        => 'dispatched',
                'dispatched_at' => now(),
            ]);
        });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Models\AuditLog::record('transfer.dispatched', $stockTransfer, ['number' => $stockTransfer->transfer_number]);

        return back()->with('success', 'Transfer dispatched.');
    }

    // ── Receive (to-store: increment destination stock) ──────────────────────

    public function receive(Request $request, StockTransfer $stockTransfer)
    {
        $this->authorizeTransfer($stockTransfer);

        // Only the destination branch (or an org-wide role) may receive.
        if (! $this->managesBranch($stockTransfer->to_business_id)) {
            abort(403, 'Only the receiving branch can receive this transfer.');
        }

        if ($stockTransfer->status !== 'dispatched') {
            return back()->with('error', 'Transfer must be dispatched before receiving.');
        }

        $request->validate([
            'items'                    => 'required|array',
            'items.*.id'               => 'required|integer|exists:stock_transfer_items,id',
            'items.*.quantity_received'=> 'required|integer|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $stockTransfer) {
            foreach ($request->items as $line) {
                $item = StockTransferItem::findOrFail($line['id']);
                if ($item->stock_transfer_id !== $stockTransfer->id) {
                    abort(403);
                }

                $qty = (int) $line['quantity_received'];
                if ($qty > (int) $item->quantity_dispatched) {
                    throw new \RuntimeException("Cannot receive {$qty} of \"{$item->product_name}\" — only {$item->quantity_dispatched} were dispatched.");
                }
                $item->update(['quantity_received' => $qty]);

                if ($qty > 0) {
                    // Use the explicit destination-store mapping captured at
                    // creation time — NOT the source product_id. Products are
                    // independent per-store rows with no shared id, so
                    // looking up "the same" id at the destination store
                    // silently matched nothing and the stock never arrived
                    // (this was the actual bug: source decremented, nothing
                    // ever incremented, transfer still marked "received").
                    if (! $item->dest_product_id) {
                        // Transfer created before this fix shipped — there is
                        // no reliable destination product to credit. Fail
                        // loudly rather than silently completing with the
                        // stock unaccounted for, same principle as the fix.
                        throw new \RuntimeException(
                            "\"{$item->product_name}\" has no destination-store product mapping (this transfer was created before that requirement existed). " .
                            'Please contact support to reconcile this stock manually — do not assume it arrived.'
                        );
                    }

                    $product = Product::where('business_id', $stockTransfer->to_business_id)
                        ->find($item->dest_product_id);

                    if (! $product) {
                        throw new \RuntimeException(
                            "The mapped destination product for \"{$item->product_name}\" no longer exists at the receiving store — cannot receive this line item."
                        );
                    }

                    $before = $product->stock_qty;
                    $product->increment('stock_qty', $qty);
                    $product->refresh();

                    StockAdjustment::create([
                        'business_id'     => $stockTransfer->to_business_id,
                        'product_id'      => $product->id,
                        'user_id'         => Auth::id(),
                        'type'            => 'return_in',
                        'quantity_before' => $before,
                        'quantity_change' => $qty,
                        'quantity_after'  => $product->stock_qty,
                        'reason'          => 'Transfer received: ' . $stockTransfer->transfer_number,
                        'reference'       => $stockTransfer->transfer_number,
                    ]);
                }
            }

            $stockTransfer->update([
                'status'      => 'received',
                'received_at' => now(),
            ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Models\AuditLog::record('transfer.received', $stockTransfer, ['number' => $stockTransfer->transfer_number]);

        return back()->with('success', 'Transfer received and stock updated.');
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(StockTransfer $stockTransfer)
    {
        $this->authorizeTransfer($stockTransfer);

        if (in_array($stockTransfer->status, ['received', 'cancelled'])) {
            return back()->with('error', 'Transfer cannot be cancelled in its current status.');
        }

        DB::transaction(function () use ($stockTransfer) {
            // A dispatched transfer has already taken stock off the sending
            // branch. Cancelling without putting it back made those units
            // vanish from both branches' books.
            if ($stockTransfer->status === 'dispatched') {
                foreach ($stockTransfer->items as $item) {
                    $qty = (int) $item->quantity_dispatched;
                    if ($qty <= 0) continue;
                    $product = Product::where('business_id', $stockTransfer->from_business_id)->find($item->product_id);
                    if (! $product) continue;
                    $before = $product->stock_qty;
                    $product->increment('stock_qty', $qty);
                    $product->refresh();
                    StockAdjustment::create([
                        'business_id'     => $stockTransfer->from_business_id,
                        'product_id'      => $product->id,
                        'user_id'         => Auth::id(),
                        'type'            => 'addition',
                        'quantity_before' => $before,
                        'quantity_change' => $qty,
                        'quantity_after'  => $product->stock_qty,
                        'reason'          => 'Transfer cancelled after dispatch: ' . $stockTransfer->transfer_number,
                        'reference'       => $stockTransfer->transfer_number,
                    ]);
                }
            }

            $stockTransfer->update(['status' => 'cancelled']);
        });

        \App\Models\AuditLog::record('transfer.cancelled', $stockTransfer, ['number' => $stockTransfer->transfer_number]);

        return back()->with('success', 'Transfer cancelled.');
    }
}
