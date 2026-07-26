@extends('frontend.layouts.user.index')
@section('title', __('Edit Recipient'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/remittance.css?v=' . filemtime(public_path('frontend/css/remittance.css'))) }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="single-form-card">
                @include('frontend.user.remittance.partials._page_header', [
                    'rmtPageTitle'    => __('Edit Recipient'),
                    'rmtPageSubtitle' => $beneficiary->full_name . ' · ' . $beneficiary->country_code . ' · ' . $beneficiary->payout_method->label(),
                    'rmtPageIcon'     => 'fas fa-user-pen',
                    'rmtCurrentPage'  => 'beneficiaries-edit',
                ])
            </div>
        </div>
    </div>

    <div class="dk-rmt">
        @include('frontend.user.remittance.beneficiaries._form', [
            'action'        => route('user.beneficiaries.update', $beneficiary),
            'method'        => 'PUT',
            'beneficiary'   => $beneficiary,
            'payoutMethods' => $payoutMethods,
        ])
    </div>
@endsection
