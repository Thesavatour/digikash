@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'admin-feature-header']) }}>
    <div class="admin-feature-header__main">
        @if($icon)
            <span class="admin-feature-header__icon" aria-hidden="true">
                <x-icon name="{{ $icon }}" height="22" width="22"/>
            </span>
        @endif
        <div class="admin-feature-header__copy">
            <h1 class="admin-feature-header__title">{{ $title }}</h1>
            @if($subtitle)
                <p class="admin-feature-header__sub">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="admin-feature-header__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
