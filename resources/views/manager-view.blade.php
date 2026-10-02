<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $business->name }} — Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f4f5f7;
            color: #1a1a2e;
            padding: 1rem;
        }
        .wrap { max-width: 900px; margin: 0 auto; }

        .header { margin-bottom: 1.25rem; }
        .header h1 { font-size: 1.35rem; color: #0f766e; }
        .header p { color: #6b7280; font-size: .85rem; margin-top: .2rem; }
        .readonly-badge {
            display: inline-block; background: #ecfdf5; color: #065f46;
            font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            padding: 2px 8px; border-radius: 10px; margin-top: .4rem;
        }

        .tabs { display: flex; gap: .5rem; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; overflow-x: auto; }
        .tab-btn {
            background: none; border: none; padding: .6rem .9rem; font-size: .85rem; font-weight: 600;
            color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap;
        }
        .tab-btn.active { color: #0f766e; border-bottom-color: #0f766e; }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
        .kpi-card { background: #fff; border-radius: 10px; padding: .9rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .kpi-card .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
        .kpi-card .value { font-size: 1.4rem; font-weight: 700; color: #1a1a2e; margin-top: .2rem; }
        .kpi-card.warn .value { color: #b45309; }

        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,.04); overflow: hidden; margin-bottom: 1rem; }
        .card-header { padding: .8rem 1rem; border-bottom: 1px solid #f0f1f3; font-weight: 700; font-size: .9rem; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        th { text-align: left; padding: .6rem .8rem; background: #f9fafb; color: #6b7280; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
        td { padding: .6rem .8rem; border-top: 1px solid #f0f1f3; white-space: nowrap; }
        tr.low-stock td { background: #fffbeb; }
        .badge-low { background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; }
        .empty { padding: 1.5rem; text-align: center; color: #9ca3af; font-size: .85rem; }

        .footer-note { text-align: center; color: #9ca3af; font-size: .75rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="header">
        <h1>{{ $business->name }}</h1>
        <p>Manager Dashboard &middot; as of {{ now()->format('d M Y, H:i') }}</p>
        <span class="readonly-badge">View only</span>
    </div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="overview" onclick="showTab('overview')">Overview</button>
        <button class="tab-btn" data-tab="sales" onclick="showTab('sales')">Sales</button>
        <button class="tab-btn" data-tab="products" onclick="showTab('products')">Products</button>
    </div>

    {{-- ── Overview ── --}}
    <div class="tab-panel active" id="tab-overview">
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="label">Today's Sales</div>
                <div class="value">KSh {{ number_format($todayTotal, 0) }}</div>
            </div>
            <div class="kpi-card">
                <div class="label">Transactions Today</div>
                <div class="value">{{ $todayCount }}</div>
            </div>
            <div class="kpi-card">
                <div class="label">Total Products</div>
                <div class="value">{{ $products->count() }}</div>
            </div>
            <div class="kpi-card {{ $lowStock->count() > 0 ? 'warn' : '' }}">
                <div class="label">Low Stock</div>
                <div class="value">{{ $lowStock->count() }}</div>
            </div>
        </div>

        @if($lowStock->count() > 0)
        <div class="card">
            <div class="card-header">Needs Reordering</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Stock</th><th>Reorder Level</th></tr></thead>
                    <tbody>
                        @foreach($lowStock->take(10) as $p)
                        <tr class="low-stock">
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->stock_qty }} {{ $p->unit }}</td>
                            <td>{{ $p->reorder_level }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Sales ── --}}
    <div class="tab-panel" id="tab-sales">
        <div class="card">
            <div class="card-header">Recent Sales</div>
            @if($recentSales->isEmpty())
                <div class="empty">No completed sales yet.</div>
            @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Time</th><th>Invoice</th><th>Customer</th><th>Served By</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach($recentSales as $sale)
                        <tr>
                            <td>{{ $sale->created_at->format('d M, H:i') }}</td>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $sale->customer->name ?? 'Walk-in' }}</td>
                            <td>{{ $sale->user->name ?? '—' }}</td>
                            <td>KSh {{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Products ── --}}
    <div class="tab-panel" id="tab-products">
        <div class="card">
            <div class="card-header">Products ({{ $products->count() }})</div>
            @if($products->isEmpty())
                <div class="empty">No active products.</div>
            @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Product</th><th>SKU</th><th>Stock</th><th>Selling Price</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                        <tr class="{{ $p->reorder_level > 0 && $p->stock_qty <= $p->reorder_level ? 'low-stock' : '' }}">
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->sku }}</td>
                            <td>{{ $p->stock_qty }} {{ $p->unit }}</td>
                            <td>KSh {{ number_format($p->selling_price, 2) }}</td>
                            <td>
                                @if($p->reorder_level > 0 && $p->stock_qty <= $p->reorder_level)
                                    <span class="badge-low">Low</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <p class="footer-note">Merqio POS &middot; Read-only dashboard link &middot; this page cannot make any changes to your data</p>
</div>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + name));
}
</script>
</body>
</html>
