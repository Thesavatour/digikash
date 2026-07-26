@extends('frontend.layouts.user.index')
@section('title', __('Add Recipient'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/remittance.css?v=' . filemtime(public_path('frontend/css/remittance.css'))) }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="single-form-card">
                @include('frontend.user.remittance.partials._page_header', [
                    'rmtPageTitle'    => __('Add Recipient'),
                    'rmtPageSubtitle' => __('Save the details once and reuse them on every transfer.'),
                    'rmtPageIcon'     => 'fas fa-user-plus',
                    'rmtCurrentPage'  => 'beneficiaries-create',
                ])
            </div>
        </div>
    </div>

    <div class="dk-rmt">
        @include('frontend.user.remittance.beneficiaries._form', [
            'action'        => route('user.beneficiaries.store'),
            'method'        => 'POST',
            'beneficiary'   => null,
            'payoutMethods' => $payoutMethods,
        ])
    </div>
@endsection
