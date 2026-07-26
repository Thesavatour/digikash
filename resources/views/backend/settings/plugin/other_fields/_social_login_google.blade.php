<div class="col-md-12 mb-3">
    <p class="text-muted small mb-0">
        {{ __('Leave Redirect blank to use') }}
        <code>{{ rtrim((string) config('app.url'), '/').'/user/auth/google/callback' }}</code>
    </p>
    <p class="text-muted small mb-0 mt-2">
        {{ __('For local demo login without Google Cloud, set Client ID to') }}
        <code>demo-google</code>
        {{ __('and any Client Secret, then enable the plugin.') }}
    </p>
</div>
