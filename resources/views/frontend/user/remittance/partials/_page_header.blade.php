{{--
    Shared header for every Remittance + Beneficiary page on the user side.
    Mirrors the standard x-user-feature-header pattern used by Send Money,
    Withdraw, Exchange, etc. so the surface stays consistent across the app.

    Required props:
      $rmtPageTitle    string  e.g. __('New Transfer')
      $rmtPageSubtitle string  e.g. __('Get a live quote ...')
      $rmtPageIcon     string  FontAwesome class
      $rmtCurrentPage  string  one of: create | review | show | index | beneficiaries
--}}
@php
    $rmtPageTitle    = $rmtPageTitle    ?? __('Remittance');
    $rmtPageSubtitle = $rmtPageSubtitle ?? __('Send money abroad with live rates.');
    $rmtPageIcon     = $rmtPageIcon     ?? 'fas fa-globe';
    $rmtCurrentPage  = $rmtCurrentPage  ?? '';
@endphp

<x-user-feature-header
    :title="$rmtPageTitle"
    :subtitle="$rmtPageSubtitle"
    :icon="$rmtPageIcon"
>
    @if ($rmtCurrentPage !== 'create')
        <a class="btn btn-light-primary btn-sm" href="{{ route('user.remittance.create') }}">
            <i class="fas fa-paper-plane"></i> {{ __('New Transfer') }}
        </a>
    @endif
    @if ($rmtCurrentPage !== 'index')
        <a class="btn btn-light-success btn-sm" href="{{ route('user.remittance.index') }}">
            <i class="fas fa-clock-rotate-left"></i> {{ __('History') }}
        </a>
    @endif
    @if (! str_contains($rmtCurrentPage, 'beneficiar'))
        <a class="btn btn-light-info btn-sm" href="{{ route('user.beneficiaries.index') }}">
            <i class="fas fa-users"></i> {{ __('Recipients') }}
        </a>
    @endif
</x-user-feature-header>
