@php
    $portal = in_array(($portal ?? 'user'), ['user', 'merchant', 'agent'], true)
        ? ($portal ?? 'user')
        : 'user';
    $googleEnabled = (bool) config('services.google.status');
    $appleEnabled = (bool) config('services.apple.status');
@endphp

@if($googleEnabled || $appleEnabled)
    <div class="auth-social">
        <div class="auth-social__divider" role="separator">
            <span>{{ __('Or go with') }}</span>
        </div>

        <div class="auth-social__actions">
            @if($googleEnabled)
                <a href="{{ route('user.auth.redirect', ['provider' => 'google', 'portal' => $portal]) }}"
                   class="auth-social__btn auth-social__btn--google"
                   title="{{ __('Continue with Google') }}"
                   aria-label="{{ __('Continue with Google') }}">
                    <svg viewBox="0 0 48 48" width="22" height="22" aria-hidden="true">
                        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.5-.4-3.5z"/>
                        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 16.1 4 9.2 8.5 6.3 14.7z"/>
                        <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.3 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.1 39.5 16 44 24 44z"/>
                        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.5l.1.1 6.2 5.2C39.2 37.3 44 32 44 24c0-1.3-.1-2.5-.4-3.5z"/>
                    </svg>
                </a>
            @endif

            @if($appleEnabled)
                <a href="{{ route('user.auth.redirect', ['provider' => 'apple', 'portal' => $portal]) }}"
                   class="auth-social__btn auth-social__btn--apple"
                   title="{{ __('Continue with Apple') }}"
                   aria-label="{{ __('Continue with Apple') }}">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="currentColor">
                        <path d="M16.4 12.7c0-2.1 1.7-3.1 1.8-3.2-1-1.4-2.5-1.6-3.1-1.6-1.3-.1-2.5.8-3.2.8-.7 0-1.7-.8-2.8-.7-1.4.1-2.8.9-3.5 2.2-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.6 2.2 2.7 2.1 1.1 0 1.5-.7 2.8-.7s1.6.7 2.8.7c1.2 0 1.9-1 2.6-2 .8-1.2 1.1-2.3 1.1-2.4-.1 0-2.2-.8-2.3-3.6zM14.5 6.3c.6-.7 1-1.7.9-2.7-.9.1-1.9.6-2.5 1.3-.6.6-1.1 1.7-1 2.6 1 .1 1.9-.5 2.6-1.2z"/>
                    </svg>
                </a>
            @endif
        </div>
    </div>
@endif
