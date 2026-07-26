<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\CurrencyType;
use App\Enums\P2P\OfferStatus;
use App\Enums\P2P\OrderSide;
use App\Enums\P2P\OrderStatus;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\P2P\Offer;
use App\Models\P2P\OfferFeedback;
use App\Models\P2P\Order;
use App\Models\P2P\PaymentAccount;
use App\Models\P2P\PaymentMethod;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seeds a realistic P2P marketplace on top of the demo accounts created by
 * DemoAccountSeeder. Every offer follows the live model: a crypto asset
 * (the maker's wallet) priced in a per-offer fiat currency. Each trader gets
 * funded crypto wallets, saved payment accounts, live Buy/Sell ads across
 * several fiats, and a backdated order + rating history so seller reputation
 * renders the way it does in production.
 */
class P2PDemoTradingSeeder extends Seeder
{
    /** Spot prices in USD; the placeholder crypto exchange_rate in the DB is unusable. */
    private const CRYPTO_USD = [
        'USDT' => 1.00,
        'BTC'  => 67000.00,
        'ETH'  => 3500.00,
    ];

    private const FEEDBACK_COMMENTS = [
        'Fast release, smooth trade. Highly recommended.',
        'Paid quickly and confirmed within minutes. Thanks!',
        'Great rate and very responsive. Will trade again.',
        'Trusted trader, released escrow right after payment.',
        'Clear instructions and quick settlement.',
        'Excellent communication throughout the trade.',
        'No issues at all, exactly as advertised.',
        'Reliable and professional. Smooth from start to finish.',
    ];

    /**
     * Every demo account that may hold P2P data. P2P is a normal-user feature
     * (disabled for agents and merchants via feature control), so only the
     * `user`-role accounts are seeded as traders. The merchant/agent accounts
     * are listed only so any stale P2P data they hold is wiped on reseed.
     *
     * @var array<int, string>
     */
    private const DEMO_EMAILS = [
        'ayesha.rahman@digikash.test',
        'imran.hossain@digikash.test',
        'rakib.hasan@digikash.test',
        'sadia.islam@digikash.test',
        'mahin.chowdhury@digikash.test',
        'priya.das@digikash.test',
        // Non-trader demo accounts (reset only, never seeded as P2P traders).
        'nusrat.farhana@digikash.test',
        'tanvir.ahmed@digikash.test',
        'farid.uddin@digikash.test',
        'sabila.akter@digikash.test',
    ];

    public function run(): void
    {
        $traders = $this->resolveTraders();

        if ($traders->count() < 2) {
            $this->command?->warn('P2PDemoTradingSeeder skipped: demo trader accounts (@digikash.test) were not found. Run DemoAccountSeeder first.');

            return;
        }

        $bdt        = $this->ensureBdtCurrency();
        $currencies = Currency::query()->get()->keyBy('code');
        $currencies->put($bdt->code, $bdt);

        // Wipe P2P data across the whole demo set — including merchant/agent
        // accounts — so disabling P2P for those roles leaves no stale offers.
        $resetIds = User::query()->whereIn('email', self::DEMO_EMAILS)->pluck('id')->all();
        $this->resetTraderP2PData($resetIds);

        foreach ($this->traderBook() as $spec) {
            $maker = $traders->get($spec['email']);

            if (! $maker) {
                continue;
            }

            $cryptoWallets = $this->fundCryptoWallets($maker, $spec['wallets'], $currencies);
            $accounts      = $this->ensurePaymentAccounts($maker, $spec['accounts']);

            $sellOffer = null;

            foreach ($spec['offers'] as $offerSpec) {
                $offer = $this->createOffer($maker, $offerSpec, $accounts, $cryptoWallets, $currencies);

                if ($offer && $offer->side === OrderSide::SELL && ! $sellOffer) {
                    $sellOffer = $offer;
                }
            }

            $this->seedReputation($maker, $sellOffer, $spec['reputation'] ?? [], $traders);
        }

        $this->command?->info(sprintf(
            'P2P demo marketplace seeded: %d offers, %d orders, %d ratings across %d traders.',
            Offer::query()->whereIn('user_id', $traders->pluck('id'))->count(),
            Order::query()->whereIn('maker_id', $traders->pluck('id'))->count(),
            OfferFeedback::query()->whereIn('user_id', $traders->pluck('id'))->count(),
            $traders->count(),
        ));
    }

    /**
     * Demo trader accounts keyed by email — only existing `user`-role accounts,
     * since P2P is disabled for merchants and agents.
     *
     * @return Collection<string, User>
     */
    private function resolveTraders(): Collection
    {
        return User::query()
            ->whereIn('email', self::DEMO_EMAILS)
            ->where('role', UserRole::USER->value)
            ->get()
            ->keyBy('email');
    }

    private function ensureBdtCurrency(): Currency
    {
        return Currency::query()->firstOrCreate(
            ['code' => 'BDT'],
            [
                'name'          => 'Bangladeshi Taka',
                'symbol'        => '৳',
                'type'          => CurrencyType::FIAT,
                'exchange_rate' => 120.00,
                'rate_live'     => false,
                'auto_wallet'   => false,
                'default'       => false,
                'status'        => true,
            ],
        );
    }

    /**
     * Remove any previously seeded P2P data for these traders so the seeder is
     * safely re-runnable.
     *
     * @param array<int, int> $userIds
     */
    private function resetTraderP2PData(array $userIds): void
    {
        $offerIds = Offer::query()->withTrashed()->whereIn('user_id', $userIds)->pluck('id')->all();

        if ($offerIds !== []) {
            OfferFeedback::query()->whereIn('offer_id', $offerIds)->delete();
            Order::query()->withTrashed()->whereIn('offer_id', $offerIds)->forceDelete();
            Offer::query()->withTrashed()->whereIn('user_id', $userIds)->forceDelete();
        }
    }

    /**
     * Create (if needed) and fund the maker's crypto wallets.
     *
     * @param  array<string, float>         $balances
     * @param  Collection<string, Currency> $currencies
     * @return array<string, Wallet>
     */
    private function fundCryptoWallets(User $user, array $balances, Collection $currencies): array
    {
        $wallets = [];

        foreach ($balances as $code => $balance) {
            $currency = $currencies->get($code);

            if (! $currency) {
                continue;
            }

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('currency_id', $currency->id)
                ->first();

            if (! $wallet) {
                app(WalletService::class)->createWalletForCurrency($user, (int) $currency->id);

                $wallet = Wallet::query()
                    ->where('user_id', $user->id)
                    ->where('currency_id', $currency->id)
                    ->first();
            }

            if (! $wallet) {
                continue;
            }

            $wallet->update(['balance' => $balance, 'status' => true]);
            $wallets[$code] = $wallet;
        }

        return $wallets;
    }

    /**
     * Ensure the maker has a saved payment account per requested method.
     *
     * @param  array<int, string>            $methodNames
     * @return array<string, PaymentAccount>
     */
    private function ensurePaymentAccounts(User $user, array $methodNames): array
    {
        $accounts = [];

        foreach (array_unique($methodNames) as $methodName) {
            $method = PaymentMethod::query()->where('name', $methodName)->where('status', true)->first();

            if (! $method) {
                continue;
            }

            $account = PaymentAccount::query()->firstOrNew([
                'user_id'           => $user->id,
                'payment_method_id' => $method->id,
            ]);

            if (! $account->exists) {
                $account->label        = $method->name.' · '.$user->first_name;
                $account->field_values = $this->paymentFieldValues($methodName, $user);
                $account->setRelation('paymentMethod', $method);
                $account->syncDerivedAttributes($method);
                $account->save();
            }

            $accounts[$methodName] = $account;
        }

        return $accounts;
    }

    /**
     * Realistic field values keyed to each payment method's own field schema.
     *
     * @return array<string, string>
     */
    private function paymentFieldValues(string $methodName, User $user): array
    {
        $name  = (string) $user->name;
        $phone = (string) ($user->phone ?: '+8801700000000');
        $city  = (string) ($user->city ?: 'Dhaka');
        $pad   = str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);

        return match ($methodName) {
            'bKash' => [
                'account_holder_name' => $name,
                'bkash_number'        => $phone,
                'account_type'        => 'Personal',
                'default_reference'   => 'P2P-'.strtoupper(Str::random(5)),
            ],
            'Nagad' => [
                'account_holder_name' => $name,
                'nagad_number'        => $phone,
                'account_type'        => 'Personal',
                'district'            => $city,
            ],
            'Rocket' => [
                'account_holder_name' => $name,
                'rocket_number'       => preg_replace('/\D/', '', $phone).'5',
                'account_type'        => 'Personal',
                'linked_branch'       => $city,
            ],
            'Bangladesh Bank Transfer' => [
                'account_holder_name' => $name,
                'bank_name'           => 'BRAC Bank',
                'account_number'      => '15'.$pad.'01',
                'branch_name'         => $city.' Main',
                'routing_number'      => '060270534',
                'transfer_type'       => 'BEFTN',
            ],
            'PayPal' => [
                'account_holder_name' => $name,
                'paypal_email'        => $user->email,
                'paypal_type'         => 'Personal',
            ],
            'Wise Account' => [
                'profile_name'    => $name,
                'wise_email'      => $user->email,
                'wise_currency'   => 'USD',
                'wise_profile_id' => 'WISE-'.$pad,
            ],
            'SEPA Bank Transfer' => [
                'account_holder_name' => $name,
                'iban'                => 'DE89'.$pad.'3704004405320130',
                'bank_name'           => 'Wise Europe SA',
                'swift_bic'           => 'TRWIBEB1XXX',
            ],
            default => [
                'account_holder_name' => $name,
                'account_number'      => $pad,
            ],
        };
    }

    /**
     * @param array<string, mixed>          $spec
     * @param array<string, PaymentAccount> $accounts
     * @param array<string, Wallet>         $cryptoWallets
     * @param Collection<string, Currency>  $currencies
     */
    private function createOffer(User $user, array $spec, array $accounts, array $cryptoWallets, Collection $currencies): ?Offer
    {
        $wallet = $cryptoWallets[$spec['asset']] ?? null;
        $quote  = $currencies->get($spec['fiat']);

        if (! $wallet || ! $quote) {
            return null;
        }

        $methodIds = collect($spec['methods'])
            ->map(fn (string $name) => isset($accounts[$name]) ? (int) $accounts[$name]->payment_method_id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($methodIds === []) {
            return null;
        }

        $offer = Offer::create([
            'user_id'                => $user->id,
            'wallet_id'              => $wallet->id,
            'quote_currency_id'      => $quote->id,
            'side'                   => $spec['side'],
            'price'                  => $this->priceFor($spec['asset'], $quote, (float) $spec['spread']),
            'min_amount'             => $spec['min'],
            'max_amount'             => $spec['max'],
            'status'                 => OfferStatus::ACTIVE,
            'payment_window_minutes' => $spec['window'] ?? 45,
            'terms'                  => $spec['terms']  ?? null,
        ]);

        $offer->paymentMethods()->sync($methodIds);

        return $offer->load('paymentMethods');
    }

    private function priceFor(string $assetCode, Currency $quote, float $spread): float
    {
        $usd  = self::CRYPTO_USD[$assetCode] ?? 1.0;
        $rate = (float) ($quote->exchange_rate ?: 1.0);

        return round($usd * $rate * $spread, 2);
    }

    /**
     * Backdated order + rating history so the maker shows a realistic
     * completion rate, trade count, and star rating in the marketplace.
     *
     * @param array{total?: int, rate?: float, rating?: float} $rep
     * @param Collection<string, User>                         $traders
     */
    private function seedReputation(User $maker, ?Offer $sellOffer, array $rep, Collection $traders): void
    {
        $total = (int) ($rep['total'] ?? 0);

        if (! $sellOffer || $total <= 0) {
            return;
        }

        $takerPool = $traders->reject(fn (User $u) => $u->id === $maker->id)->values();

        if ($takerPool->isEmpty()) {
            return;
        }

        $completed     = (int) round($total * (float) ($rep['rate'] ?? 1.0));
        $targetRating  = (float) ($rep['rating'] ?? 5.0);
        $min           = (float) $sellOffer->min_amount;
        $max           = (float) ($sellOffer->max_amount ?: $min * 10);
        $paymentMethod = $sellOffer->paymentMethods->first();

        for ($i = 0; $i < $total; $i++) {
            $taker      = $takerPool->random();
            $amount     = round(mt_rand((int) ($min * 100), (int) ($max * 100)) / 100, 8);
            $created    = now()->subDays(mt_rand(1, 150))->subHours(mt_rand(0, 23));
            $isComplete = $i < $completed;

            $status = $isComplete
                ? OrderStatus::COMPLETED
                : (mt_rand(0, 1) === 1 ? OrderStatus::CANCELLED : OrderStatus::EXPIRED);

            $order = Order::create([
                'offer_id'          => $sellOffer->id,
                'maker_id'          => $maker->id,
                'taker_id'          => $taker->id,
                'wallet_id'         => $sellOffer->wallet_id,
                'payment_method_id' => $paymentMethod?->id,
                'price'             => $sellOffer->price,
                'amount'            => $amount,
                'maker_fee'         => 0,
                'taker_fee'         => 0,
                'total'             => round((float) $sellOffer->price * $amount, 8),
                'status'            => $status,
                'expires_at'        => (clone $created)->addMinutes((int) $sellOffer->payment_window_minutes),
            ]);

            Order::query()->whereKey($order->id)->update([
                'created_at'   => $created,
                'updated_at'   => $created,
                'paid_at'      => $isComplete ? (clone $created)->addMinutes(8) : null,
                'completed_at' => $isComplete ? (clone $created)->addMinutes(22) : null,
                'cancelled_at' => $status === OrderStatus::CANCELLED ? (clone $created)->addMinutes(15) : null,
                'expired_at'   => $status === OrderStatus::EXPIRED ? (clone $created)->addMinutes((int) $sellOffer->payment_window_minutes) : null,
            ]);

            if ($isComplete && mt_rand(1, 100) <= 78) {
                $ratedAt  = (clone $created)->addMinutes(30);
                $feedback = OfferFeedback::create([
                    'offer_id' => $sellOffer->id,
                    'order_id' => $order->id,
                    'user_id'  => $taker->id,
                    'rating'   => $this->weightedRating($targetRating),
                    'comment'  => self::FEEDBACK_COMMENTS[array_rand(self::FEEDBACK_COMMENTS)],
                ]);

                OfferFeedback::query()->whereKey($feedback->id)->update([
                    'created_at' => $ratedAt,
                    'updated_at' => $ratedAt,
                ]);
            }
        }
    }

    private function weightedRating(float $target): int
    {
        $roll = mt_rand(1, 100);

        if ($target >= 4.85) {
            return $roll <= 92 ? 5 : 4;
        }

        if ($target >= 4.6) {
            return $roll <= 70 ? 5 : ($roll <= 95 ? 4 : 3);
        }

        return $roll <= 55 ? 5 : ($roll <= 85 ? 4 : 3);
    }

    /**
     * Per-trader personas: which crypto to fund, which payment accounts to
     * hold, and the live Buy/Sell ads they publish across several fiats.
     *
     * @return array<int, array<string, mixed>>
     */
    private function traderBook(): array
    {
        $bkashNagad = ['bKash', 'Nagad'];

        return [
            // Seasoned BD retail trader — USDT⇄BDT desk.
            [
                'email'    => 'ayesha.rahman@digikash.test',
                'wallets'  => ['USDT' => 60000.0],
                'accounts' => ['bKash', 'Nagad', 'Bangladesh Bank Transfer'],
                'offers'   => [
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 1.020,
                        'min'   => 50, 'max' => 5000, 'window' => 30, 'methods' => $bkashNagad,
                        'terms' => "Send only from your own bKash/Nagad account.\nUse the reference shown in chat.\nMark paid only after the transfer succeeds.",
                    ],
                    [
                        'side'  => OrderSide::BUY, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 0.988,
                        'min'   => 50, 'max' => 8000, 'window' => 45, 'methods' => ['bKash', 'Nagad', 'Bangladesh Bank Transfer'],
                        'terms' => "I pay instantly via bKash/Nagad/Bank.\nShare your receiving number after the order starts.",
                    ],
                ],
                'reputation' => ['total' => 16, 'rate' => 0.94, 'rating' => 4.9],
            ],

            // Mid-volume BD trader — USDT + ETH.
            [
                'email'    => 'imran.hossain@digikash.test',
                'wallets'  => ['USDT' => 25000.0, 'ETH' => 8.0],
                'accounts' => ['bKash', 'Rocket'],
                'offers'   => [
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 1.016,
                        'min'   => 30, 'max' => 3000, 'window' => 45, 'methods' => ['bKash', 'Rocket'],
                        'terms' => "Online 9am-11pm.\nPay within the window or the order auto-cancels.",
                    ],
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'ETH', 'fiat' => 'BDT', 'spread' => 1.022,
                        'min'   => 0.02, 'max' => 2.0, 'window' => 60, 'methods' => ['bKash'],
                        'terms' => 'ETH released after on-chain confirmation of your fiat payment.',
                    ],
                ],
                'reputation' => ['total' => 9, 'rate' => 0.88, 'rating' => 4.6],
            ],

            // High-volume retail trader — multi-fiat (BDT, USD, SAR), USDT + BTC.
            [
                'email'    => 'rakib.hasan@digikash.test',
                'wallets'  => ['USDT' => 150000.0, 'BTC' => 1.5],
                'accounts' => ['bKash', 'Bangladesh Bank Transfer', 'PayPal', 'Wise Account'],
                'offers'   => [
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 1.012,
                        'min'   => 100, 'max' => 20000, 'window' => 30, 'methods' => ['bKash', 'Bangladesh Bank Transfer'],
                        'terms' => "Trusted high-volume desk. Large orders welcome.\nBank transfers settle within working hours.",
                    ],
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'USD', 'spread' => 1.010,
                        'min'   => 50, 'max' => 10000, 'window' => 60, 'methods' => ['PayPal', 'Wise Account'],
                        'terms' => 'PayPal as Friends & Family or Wise only. No notes referencing crypto.',
                    ],
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'SAR', 'spread' => 1.008,
                        'min'   => 50, 'max' => 8000, 'window' => 90, 'methods' => ['Wise Account'],
                        'terms' => 'For KSA-based traders. Wise SAR transfers only.',
                    ],
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'BTC', 'fiat' => 'USD', 'spread' => 1.006,
                        'min'   => 0.002, 'max' => 0.5, 'window' => 120, 'methods' => ['Wise Account', 'PayPal'],
                        'terms' => 'BTC escrow released after full confirmation of the fiat payment.',
                    ],
                ],
                'reputation' => ['total' => 24, 'rate' => 0.99, 'rating' => 5.0],
            ],

            // International trader — USDT + ETH + BTC across USD/EUR/NGN.
            [
                'email'    => 'sadia.islam@digikash.test',
                'wallets'  => ['USDT' => 40000.0, 'ETH' => 12.0, 'BTC' => 0.8],
                'accounts' => ['Bangladesh Bank Transfer', 'Wise Account', 'PayPal'],
                'offers'   => [
                    [
                        'side'  => OrderSide::BUY, 'asset' => 'USDT', 'fiat' => 'USD', 'spread' => 0.990,
                        'min'   => 100, 'max' => 8000, 'window' => 60, 'methods' => ['Wise Account'],
                        'terms' => 'Buying USDT with USD via Wise. Fast payer, online most hours.',
                    ],
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'ETH', 'fiat' => 'EUR', 'spread' => 1.018,
                        'min'   => 0.05, 'max' => 5.0, 'window' => 90, 'methods' => ['Wise Account'],
                        'terms' => 'EUR via Wise multi-currency. ETH released after confirmation.',
                    ],
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'NGN', 'spread' => 1.095,
                        'min'   => 100, 'max' => 5000, 'window' => 60, 'methods' => ['Wise Account'],
                        'terms' => 'Naira settlement for African traders. Bank/Wise transfer only.',
                    ],
                ],
                'reputation' => ['total' => 12, 'rate' => 0.92, 'rating' => 4.7],
            ],

            // Competitive high-volume BDT desk.
            [
                'email'    => 'mahin.chowdhury@digikash.test',
                'wallets'  => ['USDT' => 80000.0],
                'accounts' => ['bKash', 'Nagad', 'Bangladesh Bank Transfer'],
                'offers'   => [
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 1.014,
                        'min'   => 50, 'max' => 15000, 'window' => 30, 'methods' => $bkashNagad,
                        'terms' => "Always-on desk. Instant bKash/Nagad release on payment.\nOnline during business hours.",
                    ],
                    [
                        'side'  => OrderSide::BUY, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 0.992,
                        'min'   => 50, 'max' => 15000, 'window' => 45, 'methods' => ['bKash', 'Nagad', 'Bangladesh Bank Transfer'],
                        'terms' => 'Buying USDT at counter rate. Share receiving wallet after start.',
                    ],
                ],
                'reputation' => ['total' => 18, 'rate' => 0.97, 'rating' => 4.9],
            ],

            // New trader — single ad, no history yet (fresh-trader state).
            [
                'email'    => 'priya.das@digikash.test',
                'wallets'  => ['USDT' => 12000.0],
                'accounts' => ['bKash', 'Rocket'],
                'offers'   => [
                    [
                        'side'  => OrderSide::SELL, 'asset' => 'USDT', 'fiat' => 'BDT', 'spread' => 1.024,
                        'min'   => 20, 'max' => 2000, 'window' => 60, 'methods' => ['bKash', 'Rocket'],
                        'terms' => "New trader building reputation.\nSmall orders welcome, quick response.",
                    ],
                ],
                'reputation' => ['total' => 0, 'rate' => 0.0, 'rating' => 0.0],
            ],
        ];
    }
}
