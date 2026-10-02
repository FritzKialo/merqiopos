<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>P9 Tax Deduction Card — {{ $year }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
        color: #111;
        background: #fff;
        padding: 20px 28px;
    }
    .header-band {
        background: #1e3a5f;
        color: #fff;
        padding: 14px 18px;
        border-radius: 4px 4px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .header-band h1 { font-size: 15pt; font-weight: 700; margin-bottom: 2px; }
    .header-band .sub { font-size: 8pt; opacity: .75; }
    .header-band .year-badge {
        background: rgba(255,255,255,.15);
        border: 1px solid rgba(255,255,255,.35);
        border-radius: 4px;
        padding: 4px 14px;
        font-size: 14pt;
        font-weight: 700;
        white-space: nowrap;
    }
    .party-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        border: 1px solid #ccc;
        border-top: none;
    }
    .party-box {
        padding: 10px 14px;
        border-right: 1px solid #ccc;
    }
    .party-box:last-child { border-right: none; }
    .party-box h3 {
        font-size: 7.5pt;
        font-weight: 700;
        color: #666;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 6px;
        padding-bottom: 4px;
        border-bottom: 1px solid #e5e7eb;
    }
    .party-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        gap: 8px;
    }
    .party-row .lbl { color: #555; font-size: 8.5pt; }
    .party-row .val { font-weight: 600; font-size: 8.5pt; text-align: right; }
    table.deductions {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
    }
    table.deductions thead tr {
        background: #1e3a5f;
        color: #fff;
    }
    table.deductions th {
        padding: 7px 8px;
        font-size: 8pt;
        font-weight: 700;
        text-align: right;
        border: 1px solid #1e3a5f;
    }
    table.deductions th:first-child { text-align: center; }
    table.deductions td {
        padding: 5px 8px;
        font-size: 8.5pt;
        text-align: right;
        border: 1px solid #d1d5db;
    }
    table.deductions td:first-child { text-align: center; font-weight: 600; }
    table.deductions tbody tr:nth-child(even) { background: #f8f9fa; }
    table.deductions tbody tr:hover { background: #eff6ff; }
    table.deductions tfoot tr {
        background: #1e3a5f;
        color: #fff;
        font-weight: 700;
    }
    table.deductions tfoot td {
        padding: 7px 8px;
        font-size: 9pt;
        border: 1px solid #1e3a5f;
        color: #fff;
    }
    .totals-band {
        margin-top: 14px;
        background: #f0f4ff;
        border: 1px solid #c7d3f0;
        border-radius: 4px;
        padding: 10px 14px;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
    }
    .totals-band .t-item { text-align: center; }
    .totals-band .t-lbl { font-size: 7pt; color: #555; text-transform: uppercase; letter-spacing: .04em; }
    .totals-band .t-val { font-size: 10pt; font-weight: 700; color: #1e3a5f; margin-top: 2px; }
    .statutory-note {
        margin-top: 12px;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 8pt;
        color: #555;
        line-height: 1.5;
    }
    .footer {
        margin-top: 18px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .sig-box {
        border-top: 1px solid #333;
        padding-top: 4px;
        font-size: 8pt;
        color: #555;
    }
    .generated {
        margin-top: 14px;
        font-size: 7.5pt;
        color: #aaa;
        text-align: right;
    }
    @php $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; @endphp
</style>
</head>
<body>

{{-- Header --}}
<div class="header-band">
    <div>
        <h1>P9 Tax Deduction Card</h1>
        <div class="sub">
            Kenya Revenue Authority — Employee Annual Return<br>
            Income Tax (PAYE) &amp; NSSF Summary
        </div>
    </div>
    <div class="year-badge">{{ $year }}</div>
</div>

{{-- Employer / Employee grid --}}
<div class="party-grid">
    <div class="party-box">
        <h3>Employer Details</h3>
        <div class="party-row">
            <span class="lbl">Business Name</span>
            <span class="val">{{ $business->name }}</span>
        </div>
        @if($employerPin)
        <div class="party-row">
            <span class="lbl">KRA PIN (Employer)</span>
            <span class="val">{{ $employerPin }}</span>
        </div>
        @endif
        @if($business->address)
        <div class="party-row">
            <span class="lbl">Address</span>
            <span class="val">{{ $business->address }}{{ $business->city ? ', '.$business->city : '' }}</span>
        </div>
        @endif
    </div>
    <div class="party-box">
        <h3>Employee Details</h3>
        <div class="party-row">
            <span class="lbl">Full Name</span>
            <span class="val">{{ $user->name }}</span>
        </div>
        @if($profile?->kra_pin)
        <div class="party-row">
            <span class="lbl">KRA PIN (Employee)</span>
            <span class="val">{{ $profile->kra_pin }}</span>
        </div>
        @endif
        @if($profile?->id_number)
        <div class="party-row">
            <span class="lbl">National ID</span>
            <span class="val">{{ $profile->id_number }}</span>
        </div>
        @endif
        @if($profile?->nssf_no)
        <div class="party-row">
            <span class="lbl">NSSF No.</span>
            <span class="val">{{ $profile->nssf_no }}</span>
        </div>
        @endif
        @if($profile?->job_title)
        <div class="party-row">
            <span class="lbl">Designation</span>
            <span class="val">{{ $profile->job_title }}</span>
        </div>
        @endif
    </div>
</div>

{{-- Monthly breakdown table --}}
<table class="deductions">
    <thead>
        <tr>
            <th>Month</th>
            <th>Gross Pay (A)</th>
            <th>NSSF Deducted (B)</th>
            <th>Taxable Pay (C = A−B)</th>
            <th>PAYE Tax (D)</th>
            <th>Net Pay (A−B−D)</th>
        </tr>
    </thead>
    <tbody>
        @for($m = 1; $m <= 12; $m++)
        @php $row = $months[$m] ?? null; @endphp
        <tr>
            <td>{{ $monthNames[$m - 1] }}</td>
            @if($row)
            <td>{{ number_format($row['gross'], 2) }}</td>
            <td>{{ number_format($row['nssf_ee'], 2) }}</td>
            <td>{{ number_format($row['taxable'], 2) }}</td>
            <td>{{ number_format($row['paye'], 2) }}</td>
            <td>{{ number_format($row['net'], 2) }}</td>
            @else
            <td style="color:#aaa;">—</td>
            <td style="color:#aaa;">—</td>
            <td style="color:#aaa;">—</td>
            <td style="color:#aaa;">—</td>
            <td style="color:#aaa;">—</td>
            @endif
        </tr>
        @endfor
    </tbody>
    <tfoot>
        <tr>
            <td>TOTALS</td>
            <td>{{ number_format($totals['gross'], 2) }}</td>
            <td>{{ number_format($totals['nssf_ee'], 2) }}</td>
            <td>{{ number_format($totals['taxable'], 2) }}</td>
            <td>{{ number_format($totals['paye'], 2) }}</td>
            <td>{{ number_format($totals['net'], 2) }}</td>
        </tr>
    </tfoot>
</table>

{{-- Annual totals highlight --}}
<div class="totals-band">
    <div class="t-item">
        <div class="t-lbl">Annual Gross</div>
        <div class="t-val">KSh {{ number_format($totals['gross'], 0) }}</div>
    </div>
    <div class="t-item">
        <div class="t-lbl">NSSF (Employee)</div>
        <div class="t-val">KSh {{ number_format($totals['nssf_ee'], 0) }}</div>
    </div>
    <div class="t-item">
        <div class="t-lbl">Taxable Income</div>
        <div class="t-val">KSh {{ number_format($totals['taxable'], 0) }}</div>
    </div>
    <div class="t-item">
        <div class="t-lbl">Annual PAYE</div>
        <div class="t-val">KSh {{ number_format($totals['paye'], 0) }}</div>
    </div>
    <div class="t-item">
        <div class="t-lbl">Annual Net Pay</div>
        <div class="t-val">KSh {{ number_format($totals['net'], 0) }}</div>
    </div>
</div>

{{-- Statutory note --}}
<div class="statutory-note">
    <strong>Notes:</strong>
    Taxable Pay = Gross Pay less NSSF employee contributions (NSSF is PAYE-exempt per Section 31 of the Income Tax Act Cap 470).
    PAYE calculated using KRA 2024/25 progressive bands: 10% (first KSh 24,000), 25% (next KSh 8,333), 30% (next KSh 467,667), 32.5% (next KSh 300,000), 35% (above KSh 800,000).
    Monthly personal relief of KSh 2,400 applied.
    SHIF contributions (2.75%) are not PAYE-exempt and not shown on this certificate.
</div>

{{-- Signature blocks --}}
<div class="footer">
    <div>
        <div style="margin-bottom:32px; font-size:8.5pt; color:#555;">
            I certify that the above figures are correct and complete.
        </div>
        <div class="sig-box">
            Authorised Signatory — {{ $business->name }}
        </div>
        <div style="margin-top:6px; font-size:8pt; color:#555;">Designation &amp; Date</div>
    </div>
    <div>
        <div style="margin-bottom:32px; font-size:8.5pt; color:#555;">
            Received by employee:
        </div>
        <div class="sig-box">
            {{ $user->name }}
        </div>
        <div style="margin-top:6px; font-size:8pt; color:#555;">Employee Signature &amp; Date</div>
    </div>
</div>

<div class="generated">
    Generated by Merqio POS on {{ now()->format('d M Y H:i') }} &middot;
    Tax Year {{ $year }}
</div>

</body>
</html>
