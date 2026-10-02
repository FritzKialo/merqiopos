@extends('layouts.app')
@section('title', 'My Payslips')

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Payslips</h1>
            <p class="page-subtitle">Your payslips from each pay period.</p>
        </div>
    </div>

    <div class="table-section">
        @if($payslips->isEmpty())
            <div class="empty-state">
                <h3>No payslips yet.</h3>
                <p>Your payslips will appear here once payroll has been run for a period that includes you.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Gross Pay</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payslips as $item)
                        <tr>
                            <td data-label="Period">{{ $item->period?->name ?? '—' }}</td>
                            <td data-label="Gross Pay">KSh {{ number_format($item->gross_pay, 2) }}</td>
                            <td data-label="Deductions">KSh {{ number_format($item->total_deductions, 2) }}</td>
                            <td data-label="Net Pay"><strong>KSh {{ number_format($item->net_pay, 2) }}</strong></td>
                            <td data-label="Status">
                                @if($item->isPaid())
                                    <span class="badge badge-success">Paid</span>
                                @else
                                    <span class="badge badge-warning">{{ ucfirst($item->status) }}</span>
                                @endif
                            </td>
                            <td data-label="">
                                @if($item->period)
                                <a href="{{ route('payroll.payslip', [$item->period, $item]) }}" class="btn btn--secondary btn--sm">View</a>
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
@endsection
