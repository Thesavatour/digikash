@extends('backend.layouts.app')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('backend/css/transaction-admin.css?v=' . config('app.version') . '-' . filemtime(public_path('backend/css/transaction-admin.css'))) }}">
@endpush

@section('content')
    @php($depositMenu = getAdminMenuByCode('deposit-management'))

    <div class="txn-mgmt">
        {{-- Deposit header (dynamic per page) --}}
        <div class="txn-mgmt__head">
            @yield('deposit_header')
        </div>

        <div class="txn-mgmt__panel card border-0">
            @if($depositMenu && isset($depositMenu['sub_menus']))
                <div class="txn-tabs-wrap">
                    <ul class="nav nav-pills txn-tabs" role="tablist">
                        @foreach($depositMenu['sub_menus'] as $menu)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ isActive($menu['route'], $menu['params'] ?? []) }}"
                                   href="{{ route($menu['route'], $menu['params'] ?? []) }}"
                                   @if(isActive($menu['route'], $menu['params'] ?? [])) aria-current="page" @endif>
                                    <x-icon name="{{ $menu['icon'] }}" height="18" width="18"/>
                                    {{ title($menu['label']) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="txn-mgmt__body">
                @yield('deposit_content')
            </div>
        </div>
    </div>
@endsection
