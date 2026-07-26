@extends('backend.layouts.app')
@section('title', $transfer->reference)

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1"><i class="fa-solid fa-paper-plane text-primary"></i> {{ $transfer->reference }}</h4>
                <p class="text-muted small mb-0">{{ __('Sent on :date', ['date' => $transfer->created_at_time]) }}</p>
            </div>
            <span class="badge bg-{{ $transfer->status->badgeColor() }} fs-6">{{ $transfer->status->label() }}</span>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Money Movement') }}</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">{{ __('Sender paid') }}</div>
                                <strong>{{ number_format($transfer->total_paid, 2) }} {{ $transfer->source_currency }}</strong>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">{{ __('Recipient receives') }}</div>
                                <strong class="text-success">{{ number_format($transfer->receive_amount, 2) }} {{ $transfer->destination_currency }}</strong>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">{{ __('Send amount') }}</div>
                                {{ number_format($transfer->send_amount, 2) }} {{ $transfer->source_currency }}
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">{{ __('Fee') }}</div>
                                {{ number_format($transfer->fee_amount, 2) }} {{ $transfer->source_currency }}
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">{{ __('Exchange rate') }}</div>
                                {{ number_format($transfer->exchange_rate, 4) }}
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">{{ __('Payout method') }}</div>
                                {{ $transfer->payout_method->label() }}
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">{{ __('Payout gateway') }}</div>
                                <code>{{ $transfer->payout_gateway }}</code>
                                @if ($transfer->gateway_reference)
                                    <div class="text-muted small">{{ $transfer->gateway_reference }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Status timeline') }}</strong></div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            @foreach (($transfer->status_history ?? []) as $entry)
                                <li class="d-flex gap-2 mb-3">
                                    <i class="fa-solid fa-circle-check text-primary mt-1"></i>
                                    <div>
                                        <strong>{{ \App\Enums\RemittanceStatus::from($entry['status'])->label() }}</strong>
                                        @if (! empty($entry['note']))
                                            <div class="text-muted small">{{ $entry['note'] }}</div>
                                        @endif
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($entry['timestamp'])->format('M d, Y h:i A') }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if ($transfer->compliance_result)
                    <div class="card mb-3">
                        <div class="card-header"><strong>{{ __('Compliance screening') }}</strong></div>
                        <div class="card-body">
                            <p class="mb-1">
                                <strong>{{ __('Outcome:') }}</strong>
                                @if ($transfer->compliance_result['passed'] ?? false)
                                    <span class="badge bg-success">{{ __('Passed') }}</span>
                                @else
                                    <span class="badge bg-danger">{{ __('Flagged') }}</span>
                                @endif
                            </p>
                            <p class="mb-1"><strong>{{ __('Risk score:') }}</strong> {{ $transfer->compliance_result['risk_score'] ?? '—' }}</p>
                            @if (! empty($transfer->compliance_result['reason']))
                                <p class="mb-0 text-muted small">{{ $transfer->compliance_result['reason'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Sender') }}</strong></div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ $transfer->user->name }}</strong></p>
                        <p class="text-muted small mb-0">{{ $transfer->user->email }}</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Recipient') }}</strong></div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ $transfer->beneficiary->full_name }}</strong></p>
                        <p class="text-muted small mb-1">{{ $transfer->beneficiary->country_code }} • {{ $transfer->beneficiary->masked_account }}</p>
                        @if ($transfer->beneficiary->relationship)
                            <p class="text-muted small mb-0">{{ $transfer->beneficiary->relationship }}</p>
                        @endif
                    </div>
                </div>

                @can('remittance-transfer-manage')
                    @if (! in_array($transfer->status, [
                        \App\Enums\RemittanceStatus::Completed,
                        \App\Enums\RemittanceStatus::Refunded,
                        \App\Enums\RemittanceStatus::Canceled,
                    ], true))
                        <div class="card">
                            <div class="card-header"><strong>{{ __('Admin actions') }}</strong></div>
                            <div class="card-body">
                                @if ($transfer->status !== \App\Enums\RemittanceStatus::Completed)
                                    <form action="{{ route('admin.remittance.transfers.approve', $transfer) }}"
                                          method="POST" class="mb-2"
                                          onsubmit="return confirm('{{ __('Mark this transfer as completed?') }}');">
                                        @csrf
                                        <button class="btn btn-success w-100" type="submit">
                                            <i class="fa-solid fa-check"></i> {{ __('Mark Completed') }}
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('admin.remittance.transfers.fail', $transfer) }}"
                                      method="POST" class="mb-2">
                                    @csrf
                                    <input type="text" name="reason" class="form-control mb-2"
                                           placeholder="{{ __('Failure reason (optional)') }}">
                                    <button class="btn btn-warning w-100" type="submit"
                                            onclick="return confirm('{{ __('Mark this transfer as failed?') }}');">
                                        <i class="fa-solid fa-xmark"></i> {{ __('Mark Failed') }}
                                    </button>
                                </form>

                                @if ($transfer->status !== \App\Enums\RemittanceStatus::Refunded)
                                    <form action="{{ route('admin.remittance.transfers.refund', $transfer) }}"
                                          method="POST"
                                          onsubmit="return confirm('{{ __('Refund this transfer to the sender wallet?') }}');">
                                        @csrf
                                        <input type="text" name="reason" class="form-control mb-2"
                                               placeholder="{{ __('Refund reason (optional)') }}">
                                        <button class="btn btn-danger w-100" type="submit">
                                            <i class="fa-solid fa-rotate-left"></i> {{ __('Refund to Sender') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    </div>
@endsection
