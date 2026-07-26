<?php

namespace App\Http\Controllers\Frontend\Auth\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\TransactionUpdated;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFeature;
use App\Services\AgentService;
use App\Services\WalletService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'apple'];

    private const PORTALS = ['user', 'merchant', 'agent'];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        $portal = $this->resolvePortal($request->query('portal'));
        $request->session()->put('social_auth_portal', $portal);

        if (! $this->providerConfigured($provider)) {
            notifyEvs('error', __(':provider sign-in is not configured yet. Enable it and add credentials in Admin → Settings → Plugins.', [
                'provider' => ucfirst($provider),
            ]));

            return redirect()->route($this->loginRoute($portal));
        }

        if ($this->isDemoProvider($provider)) {
            return $this->completeDemoLogin($provider, $portal);
        }

        $driver = Socialite::driver($provider)->scopes($this->scopes($provider));

        if ($provider === 'apple') {
            // Apple requires form_post for web Sign in with Apple.
            $driver->with(['response_mode' => 'form_post']);
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->assertProvider($provider);
        $portal = $this->resolvePortal($request->session()->pull('social_auth_portal', 'user'));

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', __('Could not sign in with :provider. Please try again.', [
                'provider' => ucfirst($provider),
            ]));

            return redirect()->route($this->loginRoute($portal));
        }

        $email = strtolower(trim((string) ($socialUser->getEmail() ?? '')));
        $providerId = (string) $socialUser->getId();

        if ($providerId === '') {
            notifyEvs('error', __(':provider did not return a valid account id.', [
                'provider' => ucfirst($provider),
            ]));

            return redirect()->route($this->loginRoute($portal));
        }

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (! $user && $email !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            if ($mismatch = $this->portalRoleMismatch($user, $portal)) {
                return $mismatch;
            }

            $user->forceFill([
                'provider'          => $provider,
                'provider_id'       => $providerId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            if ($email === '') {
                notifyEvs('error', __(':provider did not share an email address. Enable email permission and try again.', [
                    'provider' => ucfirst($provider),
                ]));

                return redirect()->route($this->loginRoute($portal));
            }

            [$firstName, $lastName] = $this->splitName((string) ($socialUser->getName() ?? ''));
            $user = $this->createPortalUser(
                portal: $portal,
                provider: $provider,
                providerId: $providerId,
                email: $email,
                firstName: $firstName ?: 'User',
                lastName: $lastName ?: 'Account',
                nickname: $socialUser->getNickname(),
                avatar: $socialUser->getAvatar(),
            );
        }

        return $this->finishLogin($user, $provider, $portal);
    }

    private function completeDemoLogin(string $provider, string $portal): RedirectResponse
    {
        $providerId = 'demo-'.$provider.'-'.$portal;
        $email = $provider.'.'.$portal.'.demo@digikash.test';

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user) {
            $user = $this->createPortalUser(
                portal: $portal,
                provider: $provider,
                providerId: $providerId,
                email: $email,
                firstName: $provider === 'apple' ? 'Apple' : 'Google',
                lastName: ucfirst($portal).' Demo',
                nickname: $provider.'_'.$portal.'_demo',
                avatar: null,
            );
        } else {
            if ($mismatch = $this->portalRoleMismatch($user, $portal)) {
                return $mismatch;
            }

            $user->forceFill([
                'provider'          => $provider,
                'provider_id'       => $providerId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        return $this->finishLogin($user, $provider, $portal, demo: true);
    }

    private function createPortalUser(
        string $portal,
        string $provider,
        string $providerId,
        string $email,
        string $firstName,
        string $lastName,
        ?string $nickname,
        mixed $avatar,
    ): User {
        $role = $this->roleForPortal($portal);

        $attributes = [
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'username'          => $this->uniqueUsername($email, $nickname),
            'email'             => $email,
            'provider'          => $provider,
            'provider_id'       => $providerId,
            'role'              => $role,
            'password'          => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
            'status'            => UserStatus::ACTIVE,
            'avatar'            => $avatar,
        ];

        if ($role === UserRole::MERCHANT) {
            $attributes['business_name'] = $firstName.' '.$lastName;
        }

        $user = User::create($attributes);

        event(new Registered($user));
        event(new TransactionUpdated($user));
        UserFeature::syncWithConfigForUser($user->id);

        if ($role === UserRole::AGENT) {
            app(AgentService::class)->createDefaultForUser($user);
        }

        return $user;
    }

    private function finishLogin(User $user, string $provider, string $portal, bool $demo = false): RedirectResponse
    {
        if (! $this->userIsActive($user)) {
            notifyEvs('error', __('Your account is not active. Contact support.'));

            return redirect()->route($this->loginRoute($portal));
        }

        Auth::login($user, true);
        request()->session()->regenerate();
        app(WalletService::class)->createDefaultWalletForUser($user);

        $message = $demo
            ? __('Signed in with :provider (demo).', ['provider' => ucfirst($provider)])
            : __('Signed in with :provider.', ['provider' => ucfirst($provider)]);

        notifyEvs('success', $message);

        return redirect()->route('user.dashboard');
    }

    private function portalRoleMismatch(User $user, string $portal): ?RedirectResponse
    {
        $expected = $this->roleForPortal($portal);

        if ($user->role === $expected) {
            return null;
        }

        $message = match ($user->role) {
            UserRole::MERCHANT => __('Please use merchant login for merchant accounts.'),
            UserRole::AGENT    => __('Please use agent login for agent accounts.'),
            default            => __('Please use user login for user accounts.'),
        };

        notifyEvs('error', $message);

        return redirect()->route(match ($user->role) {
            UserRole::MERCHANT => 'merchant.login',
            UserRole::AGENT    => 'agent.login',
            default            => 'user.login',
        });
    }

    private function roleForPortal(string $portal): UserRole
    {
        return match ($portal) {
            'merchant' => UserRole::MERCHANT,
            'agent'    => UserRole::AGENT,
            default    => UserRole::USER,
        };
    }

    private function loginRoute(string $portal): string
    {
        return match ($portal) {
            'merchant' => 'merchant.login',
            'agent'    => 'agent.login',
            default    => 'user.login',
        };
    }

    private function resolvePortal(mixed $portal): string
    {
        $portal = is_string($portal) ? strtolower(trim($portal)) : 'user';

        return in_array($portal, self::PORTALS, true) ? $portal : 'user';
    }

    private function assertProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }

    private function userIsActive(User $user): bool
    {
        $status = $user->status;

        if ($status instanceof UserStatus) {
            return $status === UserStatus::ACTIVE;
        }

        return (int) $status === UserStatus::ACTIVE->value;
    }

    private function providerConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        if (! is_array($config) || empty($config['status'])) {
            return false;
        }

        if ($this->isDemoProvider($provider)) {
            return true;
        }

        if ($provider === 'google') {
            return filled($config['client_id'] ?? null) && filled($config['client_secret'] ?? null);
        }

        return filled($config['client_id'] ?? null)
            && filled($config['team_id'] ?? null)
            && filled($config['key_id'] ?? null)
            && filled($config['private_key'] ?? null);
    }

    private function isDemoProvider(string $provider): bool
    {
        $clientId = strtolower(trim((string) config("services.{$provider}.client_id")));

        return $clientId !== '' && str_starts_with($clientId, 'demo');
    }

    /**
     * @return list<string>
     */
    private function scopes(string $provider): array
    {
        return match ($provider) {
            'google' => ['openid', 'profile', 'email'],
            'apple'  => ['name', 'email'],
            default  => [],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['', ''];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function uniqueUsername(string $email, ?string $nickname = null): string
    {
        $base = Str::slug((string) ($nickname ?: Str::before($email, '@')), '_');
        $base = Str::lower($base !== '' ? $base : 'user');
        $base = Str::limit($base, 40, '');

        $candidate = $base;
        $i = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }
}
