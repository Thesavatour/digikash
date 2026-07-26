@extends('backend.layouts.app')
@section('title', __('Remittance Transfers'))

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1"><i class="fa-solid fa-paper-plane text-primary"></i> {{ __('International Transfers') }}</h4>
                <p class="text-muted small mb-0">{{ __('Monitor every outbound remittance, approve manual transfers, or trigger refunds.') }}</p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control"
                               placeholder="{{ __('Search by reference, gateway, sender email…') }}"
                               value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                @if ($transfers->isEmpty())
                    <x-admin-not-found
                        :title="__('No transfers match')"
                        :message="__('Adjust filters or wait for users to send their first international transfer.')"
                        icon="fa-paper-plane"
                    />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Sender') }}</th>
                                    <th>{{ __('Recipient') }}</th>
                                    <th>{{ __('Sent') }}</th>
                                    <th>{{ __('Receives') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Created') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transfers as $transfer)
                                    <tr>
                                        <td>
                                            <code>{{ $transfer->reference }}</code>
                                            <div class="text-muted small">{{ $transfer->payout_gateway }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $transfer->user->name }}</strong>
                                            <div class="text-muted small">{{ $transfer->user->email }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $transfer->beneficiary->full_name }}</strong>
                                            <div class="text-muted small">{{ $transfer->beneficiary->country_code }}</div>
                                        </td>
                                        <td>{{ number_format($transfer->total_paid, 2) }} {{ $transfer->source_currency }}</td>
                                        <td>{{ number_format($transfer->receive_amount, 2) }} {{ $transfer->destination_currency }}</td>
                                        <td><span class="badge bg-{{ $transfer->status->badgeColor() }}">{{ $transfer->status->label() }}</span></td>
                                        <td class="small text-muted">{{ $transfer->created_at_time }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.remittance.transfers.show', $transfer) }}"
                                               class="btn btn-sm btn-light-primary">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if ($transfers->hasPages())
                <div class="card-footer">{{ $transfers->links() }}</div>
            @endif
        </div>
    </div>
@endsection
