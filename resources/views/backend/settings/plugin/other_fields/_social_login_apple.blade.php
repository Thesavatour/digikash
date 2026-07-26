<div class="col-md-12 mb-3">
    <label class="form-label" for="apple_private_key_hint">{{ __('Private Key Tip') }}</label>
    <p class="text-muted small mb-0">
        {{ __('Paste the absolute path to your AuthKey_XXXXX.p8 file, or paste the full .p8 private key contents into the Private Key field above.') }}
    </p>
    <p class="text-muted small mb-0 mt-2">
        {{ __('Leave Redirect blank to use') }}
        <code>{{ rtrim((string) config('app.url'), '/').'/user/auth/apple/callback' }}</code>
    </p>
    <p class="text-muted small mb-0 mt-2">
        {{ __('For local demo login without Apple Developer, set Client ID to') }}
        <code>demo-apple</code>
        {{ __('and fill the other fields with any placeholder values, then enable the plugin.') }}
    </p>
</div>
