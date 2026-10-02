{{--
    Shared branded header for customer-facing store emails (invoice, quote,
    portal invite/reset). Expects:
      $business    — the Business the email is from
      $subtitle    — optional line under the logo/name (e.g. "Invoice")
      $showLogo    — optional bool gate (defaults to true — pass the
                     document-specific setting, e.g. $business->show_logo_on_invoice,
                     when the caller has one; quote/portal have no such
                     setting, so they just show the logo whenever one is set)
      $icon        — optional emoji shown inline before the name
                     (defaults to a generic building — pass a document-
                     specific one, e.g. 🧾 for an invoice, 📝 for a quote)

    Renders the business's actual uploaded logo when available (Settings →
    Branding), falling back to a plain text name — previously every one of
    these emails showed only text, so two different shops' invoices/invites
    looked pixel-identical apart from the name string.

    No colored background block — a prior version put white text on a solid
    color div, which some email clients' dark-mode processing can strip,
    leaving white-on-white. Plain colored text on the ordinary white body
    is the safer, more standard choice that survives light/dark rendering
    either way.
--}}
<div class="header" style="background:#ffffff !important; padding:28px 40px 22px !important; text-align:center !important; border-bottom:1px solid #e2e8f0 !important;">
    @if(($showLogo ?? true) && $business->logo)
        <img src="{{ asset('storage/' . $business->logo) }}" alt="{{ $business->name }}" style="max-height:56px; max-width:220px; margin-bottom:4px;">
    @else
        <h1 style="margin:0; color:#4338ca !important; font-size:24px;">{{ $icon ?? '🏢' }} {{ $business->name }}</h1>
    @endif
    @isset($subtitle)
        <p style="margin:6px 0 0; color:#64748b !important; font-size:14px;">{{ $subtitle }}</p>
    @endisset
</div>
