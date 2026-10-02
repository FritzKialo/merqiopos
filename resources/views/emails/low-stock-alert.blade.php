<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Low Stock Alert</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 22px; color: #dc2626; }
        .header p { margin: 8px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .table th { background: #f8fafc; text-align: left; padding: 10px 14px; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        .table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .table tr:last-child td { border-bottom: none; }
        .qty-critical { color: #dc2626; font-weight: 700; }
        .qty-low      { color: #d97706; font-weight: 600; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    {{-- Inline background/color duplicate the .header CSS above — Gmail
    (dark mode, clipped/long-email view) can strip or override a <style>-block
    rule, which would otherwise leave this heading text white on white. --}}
    <div class="header" style="background:#ffffff !important; padding:28px 40px 22px !important; text-align:center !important; border-bottom:1px solid #e2e8f0 !important;">
        <h1 style="margin:0; color:#dc2626 !important;">⚠️ Low Stock Alert</h1>
        <p style="margin:8px 0 0; color:#64748b !important;">{{ $products->count() }} {{ $products->count() === 1 ? 'product' : 'products' }} need restocking at {{ $business->name }}</p>
    </div>
    <div class="body">
        <h2>Hi, {{ $business->owner?->name ?? $business->name }}!</h2>

        <p>
            The following {{ $products->count() == 1 ? 'product is' : 'products are' }}
            at or below the reorder level and may need restocking soon:
        </p>

        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>In Stock</th>
                    <th>Reorder Level</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td style="color:#94a3b8;">{{ $product->sku }}</td>
                    <td class="{{ $product->stock_qty <= 0 ? 'qty-critical' : 'qty-low' }}">
                        {{ $product->stock_qty }}
                        @if($product->unit) {{ $product->unit }} @endif
                        @if($product->stock_qty <= 0)
                            &nbsp;<span style="font-size:11px;background:#fef2f2;color:#dc2626;padding:2px 6px;border-radius:4px;">OUT OF STOCK</span>
                        @endif
                    </td>
                    <td>{{ $product->reorder_level }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Inline style duplicates the .btn rule above — same Gmail
        <style>-stripping risk that leaves button text/background both white. --}}
        <a href="{{ config('app.url') }}/inventory" class="btn" style="display:inline-block !important; background:#4f46e5 !important; color:#ffffff !important; padding:14px 32px !important; border-radius:6px !important; text-decoration:none !important; font-weight:600 !important;">Go to Inventory</a>

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            You will receive this alert once per day while these products remain below their reorder levels.
            Update stock quantities in the inventory module to stop receiving these alerts for individual products.
        </p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} Merqio POS &mdash; Nairobi, Kenya</p>
        <p>This is an automated stock alert. Please do not reply to this email.</p>
    </div>
</div>
</body>
</html>
