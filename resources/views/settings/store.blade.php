@extends('layouts.app')
@section('title', 'Online Store Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your business and account</p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Online Store</h2>
            </div>
            <div class="settings-card-body">
                @if(session('success'))
                    <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('settings.store.update') }}">
                    @csrf @method('PUT')

                    <div class="form-group">
                        <label class="form-label">Store URL Slug *</label>
                        <div style="display:flex; align-items:center; gap: var(--space-2);">
                            <span style="color: var(--color-text-muted); font-size:0.9rem;">{{ config('app.url') }}/shop/</span>
                            <input type="text" name="store_slug" class="form-control" value="{{ old('store_slug', $business->store_slug) }}" required placeholder="my-store" pattern="[a-z0-9\-]+" style="flex:1;">
                        </div>
                        @error('store_slug')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Store Description</label>
                        <textarea name="store_description" class="form-control" rows="3">{{ old('store_description', $business->store_description) }}</textarea>
                    </div>

                    <div class="form-group" style="display:flex; align-items:center; gap: var(--space-3);">
                        <input type="hidden" name="store_public" value="0">
                        <input type="checkbox" name="store_public" value="1" id="store_public" {{ $business->store_public ? 'checked' : '' }} style="width:18px; height:18px; cursor:pointer;">
                        <label for="store_public" style="cursor:pointer; font-size:0.95rem;">Enable public store (allow anyone to browse and order)</label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Store Settings</button>
                        @if($business->store_slug && $business->store_public)
                        <a href="{{ route('shop.index', $business->store_slug) }}" target="_blank" class="btn btn-secondary">View Live Store</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if($business->store_slug && $qrSvg)
        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <h3 style="margin:0 0 6px;">Share your store — QR code</h3>
                <p style="color:var(--color-text-muted);font-size:.9rem;margin:0 0 16px;">
                    Customers scan this with their phone camera and land straight on your online store. Put it on your door, counter, menus, flyers and delivery bags.
                </p>
                @unless($business->store_public)
                <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:10px 12px;font-size:.88rem;margin-bottom:16px;">
                    Your store is not public yet, so scanners would see a "store closed" page. Tick <strong>Enable public store</strong> above and save first.
                </div>
                @endunless
                <div style="display:flex;gap:28px;flex-wrap:wrap;align-items:center;">
                    {{-- The generated SVG carries a fixed pixel width/height, so it must be told to fill its box --}}
                    <style>#shopQrBox svg { width: 100%; height: 100%; display: block; }</style>
                    <div id="shopQrBox" style="width:230px;height:230px;background:#fff;padding:12px;border-radius:10px;border:1px solid var(--color-border);flex-shrink:0;overflow:hidden;box-sizing:border-box;">{!! $qrSvg !!}</div>
                    <div style="flex:1;min-width:240px;">
                        <div style="font-size:.8rem;color:var(--color-text-muted);">Your store link</div>
                        <div style="font-weight:700;word-break:break-all;margin-bottom:12px;" id="shopLinkText">{{ $shopUrl }}</div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                            <button type="button" class="btn btn-primary btn-sm" id="dlPng">Download PNG</button>
                            <a class="btn btn-secondary btn-sm" href="{{ route('settings.store.qr', ['download' => 1]) }}">Download SVG</a>
                            <button type="button" class="btn btn-secondary btn-sm" id="copyLink">Copy link</button>
                        </div>
                        <div style="font-size:.85rem;margin-bottom:6px;">Print a ready-made sign:</div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                            <a class="btn btn-secondary btn-sm" target="_blank" href="{{ route('settings.store.poster', ['size' => 'a4']) }}">A4 poster</a>
                            <a class="btn btn-secondary btn-sm" target="_blank" href="{{ route('settings.store.poster', ['size' => 'a5']) }}">A5 sign</a>
                            <a class="btn btn-secondary btn-sm" target="_blank" href="{{ route('settings.store.poster', ['size' => 'sticker']) }}">Sticker (10 cm)</a>
                        </div>
                        <div style="font-size:.85rem;color:var(--color-text-muted);">
                            Visits from scanning this code: <strong style="color:var(--color-text);">{{ number_format($business->shop_qr_scans) }}</strong>
                            <span style="display:block;font-size:.75rem;">Counts every time the code is opened, including repeat scans by the same person.</span>
                        </div>
                    </div>
                </div>
                <p style="font-size:.8rem;color:var(--color-text-muted);margin:16px 0 0;">
                    Tip: to print this QR on every receipt, tick "Print the shop QR code on receipts" in Settings → Receipt &amp; Invoice Branding. If you change your store address above, print the new QR — old codes will stop working.
                </p>
            </div>
        </div>
        <script>
        (function () {
            var slug = @json($business->store_slug);
            document.getElementById('dlPng').addEventListener('click', function () {
                var svg = document.querySelector('#shopQrBox svg');
                var xml = new XMLSerializer().serializeToString(svg);
                var img = new Image();
                img.onload = function () {
                    var c = document.createElement('canvas'); c.width = c.height = 1024;
                    var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, 1024, 1024);
                    x.drawImage(img, 32, 32, 960, 960);
                    c.toBlob(function (b) {
                        var a = document.createElement('a'); a.href = URL.createObjectURL(b);
                        a.download = 'shop-qr-' + slug + '.png'; document.body.appendChild(a); a.click(); a.remove();
                    });
                };
                img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
            });
            document.getElementById('copyLink').addEventListener('click', function () {
                var b = this, t = document.getElementById('shopLinkText').textContent.trim();
                (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy link'; }, 1500); }, function () { window.prompt('Copy this link:', t); });
            });
        })();
        </script>
        @endif

    </div>
</div>
</div>{{-- end .page --}}
@endsection
