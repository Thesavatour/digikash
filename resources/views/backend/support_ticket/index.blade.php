@extends('backend.layouts.app')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('backend/css/support-ticket.css?v=' . config('app.version') . '-' . filemtime(public_path('backend/css/support-ticket.css'))) }}">
@endpush

@section('content')
    @php($supportMenu = getAdminMenuByCode('support-ticket'))

    <div class="st-shell">
        {{-- Support header (dynamic per page) --}}
        @yield('support_header')

        {{-- Queue navigation --}}
        @if($supportMenu && isset($supportMenu['sub_menus']))
            <div class="st-tabs-wrap">
                <ul class="st-tabs" role="tablist">
                    @foreach($supportMenu['sub_menus'] as $menu)
                        <li class="st-tabs__item" role="presentation">
                            <a class="st-tabs__link {{ isActive($menu['route'], $menu['params'] ?? []) }}"
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

        {{-- Dynamic content --}}
        <div>
            @yield('support_content')
        </div>
    </div>
@endsection
