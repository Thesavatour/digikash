<?php

namespace App\Http\Controllers\Frontend\P2P;

use App\Constants\CurrencyType;
use App\Enums\P2P\OfferStatus;
use App\Enums\P2P\OrderSide;
use App\Enums\P2P\OrderStatus;
use App\Exceptions\NotifyErrorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\P2P\StoreOfferRequest;
use App\Http\Requests\P2P\UpdateOfferRequest;
use App\Models\Currency;
use App\Models\P2P\Offer;
use App\Models\P2P\OfferFeedback;
use App\Models\P2P\Order as P2POrder;
use App\Models\P2P\PaymentAccount;
use App\Models\P2P\PaymentMethod as P2PPaymentMethod;
use App\Models\P2P\PromotionPackage;
use App\Models\Wallet;
use App\Services\P2P\P2POfferPromotionService;
use App\Services\SubscriptionFeatureService;
use App\Support\P2PPaymentMethodManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function __construct(protected SubscriptionFeatureService $subscriptionFeatures) {}

    // region Marketplace and Trade Ad Screens

    public function index(Request $request): View
    {
        $userCountryCode = P2PPaymentMethodManager::resolveCountryCode(auth()->user());

        $query = Offer::query()
            ->leftJoin('p2p_offer_promotions as promo', 'promo.offer_id', '=', 'p2p_offers.id')
            ->select('p2p_offers.*')
            ->with(['wallet.currency', 'quoteCurrency', 'paymentMethods', 'user.kycSubmission', 'promotion.package'])
            ->withCount([
                'orders as active_orders_count' => function ($q) {
                    $q->whereIn('status', [
                        OrderStatus::PENDING->value,
                        OrderStatus::PAID->value,
                        OrderStatus::DISPUTED->value,
                    ]);
                },
            ])
            ->where('p2p_offers.status', OfferStatus::ACTIVE)
            ->orderByRaw(
                'CASE WHEN promo.status = ? AND promo.ends_at IS NOT NULL AND promo.ends_at > ? THEN 0 ELSE 1 END',
                ['ACTIVE', now()]
            );

        $side          = $request->string('side')->toString();
        $sideEnum      = OrderSide::tryFrom($side);
        $effectiveSide = $sideEnum ?? OrderSide::SELL;
        $query->where('p2p_offers.side', $effectiveSide);

        // The marketplace is a counterparty feed: every row must be tradeable.
        // A user cannot trade against their own ad, so exclude their listings
        // here — they are managed on the "My Trade Ads" screen instead.
        $query->where('p2p_offers.user_id', '!=', (int) auth()->id());

        if ($currency = $request->string('currency')->toString()) {
            $query->whereHas('wallet.currency', function ($q) use ($currency) {
                $q->where('code', $currency);
            });
        }
        if ($fiat = $request->string('fiat')->toString()) {
            $query->whereHas('quoteCurrency', function ($q) use ($fiat) {
                $q->where('code', $fiat);
            });
        }
        if ($pmId = $request->integer('payment_method_id')) {
            $query->whereHas('paymentMethods', function ($q) use ($pmId) {
                $q->where('p2p_payment_methods.id', $pmId);
            });
        }

        if ($country = $request->string('country')->toString()) {
            $query->whereHas('paymentMethods', function ($q) use ($country) {
                $q->where('country', $country);
            });
        }

        $amountMinInput = $request->input('amount_min');
        $amountMaxInput = $request->input('amount_max');

        $amountMin = is_numeric($amountMinInput) ? max(0, (float) $amountMinInput) : null;
        $amountMax = is_numeric($amountMaxInput) ? max(0, (float) $amountMaxInput) : null;

        if ($amountMin !== null && $amountMax !== null && $amountMin > $amountMax) {
            [$amountMin, $amountMax] = [$amountMax, $amountMin];
        }

        if ($amountMin !== null) {
            $query->where(function ($q) use ($amountMin) {
                $q->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $amountMin);
            });
        }

        if ($amountMax !== null) {
            $query->where('min_amount', '<=', $amountMax);
        }

        $sort = $request->string('sort')->toString();
        if (in_array($sort, ['completion', 'trades'], true)) {
            $makerStats = P2POrder::query()
                ->selectRaw('maker_id, COUNT(*) as maker_total_orders, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as maker_completed_orders', [OrderStatus::COMPLETED->value])
                ->groupBy('maker_id');

            $query->leftJoinSub($makerStats, 'maker_stats', 'maker_stats.maker_id', '=', 'p2p_offers.user_id');

            if ($sort === 'trades') {
                $query->orderByRaw('COALESCE(maker_stats.maker_total_orders, 0) DESC');
            } else {
                $query->orderByRaw('CASE WHEN COALESCE(maker_stats.maker_total_orders, 0) = 0 THEN 0 ELSE maker_stats.maker_completed_orders * 1.0 / maker_stats.maker_total_orders END DESC');
            }
        } elseif ($sort === 'price_desc') {
            $query->orderBy('p2p_offers.price', 'desc');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('p2p_offers.price', 'asc');
        } elseif ($effectiveSide === OrderSide::BUY) {
            $query->orderBy('p2p_offers.price', 'desc');
        } else {
            $query->orderBy('p2p_offers.price', 'asc');
        }

        $offers = $query->paginate(15)->withQueryString();

        $userIds = $offers->getCollection()
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $sellerStats = [];
        if ($userIds->isNotEmpty()) {
            $orderAgg = P2POrder::query()
                ->select('maker_id')
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders', [OrderStatus::COMPLETED->value])
                ->whereIn('maker_id', $userIds)
                ->groupBy('maker_id')
                ->get()
                ->keyBy('maker_id');

            $feedbackAgg = DB::table('p2p_offer_feedback as f')
                ->join('p2p_offers as o', 'o.id', '=', 'f.offer_id')
                ->whereIn('o.user_id', $userIds)
                ->select('o.user_id')
                ->selectRaw('AVG(f.rating) as avg_rating')
                ->selectRaw('SUM(CASE WHEN f.rating >= 4 THEN 1 ELSE 0 END) as positive_feedback')
                ->groupBy('o.user_id')
                ->get()
                ->keyBy('user_id');

            foreach ($userIds as $id) {
                $totalOrders      = (int) ($orderAgg[$id]->total_orders ?? 0);
                $completedOrders  = (int) ($orderAgg[$id]->completed_orders ?? 0);
                $completionRate   = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
                $avgRating        = isset($feedbackAgg[$id]) ? (float) $feedbackAgg[$id]->avg_rating : null;
                $positiveFeedback = (int) ($feedbackAgg[$id]->positive_feedback ?? 0);

                $sellerStats[(int) $id] = [
                    'total_orders'      => $totalOrders,
                    'completed_orders'  => $completedOrders,
                    'completion_rate'   => $completionRate,
                    'avg_rating'        => $avgRating,
                    'positive_feedback' => $positiveFeedback,
                ];
            }
        }

        $priceTrends = $this->resolveOfferPriceTrends($offers->getCollection(), $effectiveSide);

        $currencyOptions = Offer::query()
            ->where('p2p_offers.status', OfferStatus::ACTIVE)
            ->join('wallets', 'wallets.id', '=', 'p2p_offers.wallet_id')
            ->join('currencies', 'currencies.id', '=', 'wallets.currency_id')
            ->select('currencies.code')
            ->distinct()
            ->orderBy('currencies.code')
            ->pluck('currencies.code')
            ->filter()
            ->values();

        $fiatOptions = Offer::query()
            ->where('p2p_offers.status', OfferStatus::ACTIVE)
            ->join('currencies as quote_currencies', 'quote_currencies.id', '=', 'p2p_offers.quote_currency_id')
            ->select('quote_currencies.code')
            ->distinct()
            ->orderBy('quote_currencies.code')
            ->pluck('quote_currencies.code')
            ->filter()
            ->values();

        $userPaymentAccounts = PaymentAccount::query()
            ->with('paymentMethod')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->get();
        $userPaymentAccounts = P2PPaymentMethodManager::sortPaymentAccounts($userPaymentAccounts, $userCountryCode);

        $savedMethodIds = $userPaymentAccounts
            ->pluck('payment_method_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $methods = P2PPaymentMethodManager::sortMethods(
            P2PPaymentMethod::query()
                ->where('status', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'logo', 'country', 'sort_order']),
            $userCountryCode,
            $savedMethodIds
        );

        $countryOptions = P2PPaymentMethodManager::countryOptions($methods);

        $subscriptionGates = $this->resolveP2PSubscriptionGates();

        if ($request->ajax() && $request->boolean('rows')) {
            return view('frontend.user.p2p.trade_ads.partials._trade_ad_rows', compact('offers', 'sellerStats', 'priceTrends', 'userPaymentAccounts', 'subscriptionGates'))
                ->with('marketplaceRow', true);
        }

        return view('frontend.user.p2p.trade_ads.marketplace', compact('offers', 'currencyOptions', 'fiatOptions', 'methods', 'countryOptions', 'sellerStats', 'priceTrends', 'userPaymentAccounts', 'subscriptionGates'));
    }

    public function my(Request $request): View
    {
        $userId = (int) auth()->id();

        $search = trim((string) $request->string('search'));
        $status = strtoupper(trim((string) $request->string('status')));
        $sort   = trim((string) $request->string('sort', 'recent'));

        $offersQuery = Offer::query()
            ->with(['wallet.currency', 'quoteCurrency', 'paymentMethods', 'promotion.package'])
            ->withCount([
                'orders as total_orders_count',
                'orders as active_orders_count' => function ($q) {
                    $q->whereIn('status', [
                        OrderStatus::PENDING->value,
                        OrderStatus::PAID->value,
                        OrderStatus::DISPUTED->value,
                    ]);
                },
                'orders as completed_orders_count' => function ($q) {
                    $q->where('status', OrderStatus::COMPLETED->value);
                },
                'orders as disputed_orders_count' => function ($q) {
                    $q->where('status', OrderStatus::DISPUTED->value);
                },
            ])
            ->where('user_id', $userId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('id', (int) $search)
                        ->orWhere('terms', 'like', '%'.$search.'%')
                        ->orWhereHas('wallet.currency', function ($walletQuery) use ($search) {
                            $walletQuery->where('code', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('paymentMethods', function ($paymentMethodQuery) use ($search) {
                            $paymentMethodQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            });

        if ($status !== '') {
            if ($status === 'PROMOTED') {
                $offersQuery->whereHas('promotion', function ($query) {
                    $query->where('status', 'ACTIVE')
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '>', now());
                });
            } elseif (in_array($status, array_map(fn ($case) => $case->value, OfferStatus::cases()), true)) {
                $offersQuery->where('status', $status);
            }
        }

        $offersQuery->orderByRaw(
            'CASE p2p_offers.status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END',
            [OfferStatus::ACTIVE->value, OfferStatus::PAUSED->value]
        );

        match ($sort) {
            'oldest'      => $offersQuery->oldest('updated_at'),
            'price_low'   => $offersQuery->orderBy('price')->latest('updated_at'),
            'price_high'  => $offersQuery->orderByDesc('price')->latest('updated_at'),
            'most_orders' => $offersQuery->orderByDesc('total_orders_count')->latest('updated_at'),
            default       => $offersQuery->latest('updated_at'),
        };

        $offers = $offersQuery->paginate(15)->withQueryString();

        $offerStatusCounts = Offer::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->where('user_id', $userId)
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [(string) $item->getRawOriginal('status') => $item];
            });

        $orderStatusCounts = P2POrder::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->where('maker_id', $userId)
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [(string) $item->getRawOriginal('status') => $item];
            });

        $summary = [
            'total_offers'    => (int) $offerStatusCounts->sum('aggregate'),
            'active_offers'   => (int) ($offerStatusCounts[OfferStatus::ACTIVE->value]->aggregate ?? 0),
            'paused_offers'   => (int) ($offerStatusCounts[OfferStatus::PAUSED->value]->aggregate ?? 0),
            'promoted_offers' => Offer::query()
                ->where('user_id', $userId)
                ->whereHas('promotion', function ($q) {
                    $q->where('status', 'ACTIVE')
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '>', now());
                })
                ->count(),
            'open_orders' => (int) (($orderStatusCounts[OrderStatus::PENDING->value]->aggregate ?? 0)
                + ($orderStatusCounts[OrderStatus::PAID->value]->aggregate ?? 0)
                + ($orderStatusCounts[OrderStatus::DISPUTED->value]->aggregate ?? 0)),
            'completed_orders' => (int) ($orderStatusCounts[OrderStatus::COMPLETED->value]->aggregate ?? 0),
            'disputed_orders'  => (int) ($orderStatusCounts[OrderStatus::DISPUTED->value]->aggregate ?? 0),
        ];

        return view('frontend.user.p2p.trade_ads.my_trade_ads', compact('offers', 'summary', 'search', 'status', 'sort'));
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->guardP2PAdQuota()) {
            return $redirect;
        }

        $userCountryCode = P2PPaymentMethodManager::resolveCountryCode(auth()->user());
        $wallets         = $this->tradableWallets();
        $paymentAccounts = PaymentAccount::query()
            ->with('paymentMethod')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->get();
        $paymentAccounts = P2PPaymentMethodManager::sortPaymentAccounts($paymentAccounts, $userCountryCode);

        $savedMethodIds = $paymentAccounts
            ->pluck('payment_method_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $methods = P2PPaymentMethodManager::sortMethods(
            P2PPaymentMethod::query()
                ->where('status', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            $userCountryCode,
            $savedMethodIds
        );
        $packagesQuery = PromotionPackage::query()->where('status', true);
        if (Schema::hasColumn('p2p_promotion_packages', 'visibility')) {
            $packagesQuery->where('visibility', 'PUBLIC');
        }
        $packages = $packagesQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        ['currencies' => $fiatCurrencies, 'default_id' => $defaultQuoteCurrencyId] = $this->fiatQuoteCurrencies();

        return view('frontend.user.p2p.trade_ads.create_trade_ad', compact('wallets', 'methods', 'packages', 'paymentAccounts', 'userCountryCode', 'fiatCurrencies', 'defaultQuoteCurrencyId'));
    }

    public function edit(Offer $offer): View|RedirectResponse
    {
        $this->authorize('update', $offer);

        $hasOpenOrders = $this->offerHasActiveOrders($offer);

        $userCountryCode = P2PPaymentMethodManager::resolveCountryCode(auth()->user());
        $wallets         = $this->tradableWallets();
        $paymentAccounts = PaymentAccount::query()
            ->with('paymentMethod')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->get();
        $paymentAccounts = P2PPaymentMethodManager::sortPaymentAccounts($paymentAccounts, $userCountryCode);

        $savedMethodIds = $paymentAccounts
            ->pluck('payment_method_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $methods = P2PPaymentMethodManager::sortMethods(
            P2PPaymentMethod::query()
                ->where('status', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            $userCountryCode,
            $savedMethodIds
        );

        $packagesQuery = PromotionPackage::query()->where('status', true);
        if (Schema::hasColumn('p2p_promotion_packages', 'visibility')) {
            $packagesQuery->where('visibility', 'PUBLIC');
        }
        $packages = $packagesQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        ['currencies' => $fiatCurrencies, 'default_id' => $defaultQuoteCurrencyId] = $this->fiatQuoteCurrencies();

        $offer->loadMissing(['wallet.currency', 'quoteCurrency', 'paymentMethods', 'promotion.package']);
        $offer->loadCount([
            'orders as total_orders_count',
            'orders as active_orders_count' => function ($q) {
                $q->whereIn('status', [
                    OrderStatus::PENDING->value,
                    OrderStatus::PAID->value,
                    OrderStatus::DISPUTED->value,
                ]);
            },
            'orders as completed_orders_count' => function ($q) {
                $q->where('status', OrderStatus::COMPLETED->value);
            },
            'orders as disputed_orders_count' => function ($q) {
                $q->where('status', OrderStatus::DISPUTED->value);
            },
        ]);

        $totalOrders        = (int) ($offer->total_orders_count ?? 0);
        $openOrders         = (int) ($offer->active_orders_count ?? 0);
        $completedOrders    = (int) ($offer->completed_orders_count ?? 0);
        $disputedOrders     = (int) ($offer->disputed_orders_count ?? 0);
        $completionRate     = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
        $canManageLifecycle = $offer->status !== OfferStatus::DISABLED
            && $openOrders === 0;
        $canEditTradeAd = ! $hasOpenOrders
            && $offer->status !== OfferStatus::DISABLED;

        return view('frontend.user.p2p.trade_ads.create_trade_ad', compact(
            'wallets',
            'methods',
            'packages',
            'paymentAccounts',
            'userCountryCode',
            'fiatCurrencies',
            'defaultQuoteCurrencyId',
            'offer',
            'totalOrders',
            'openOrders',
            'completedOrders',
            'disputedOrders',
            'completionRate',
            'canManageLifecycle',
            'canEditTradeAd'
        ));
    }

    // endregion

    // region Trade Ad Submission and Lifecycle Actions

    public function store(StoreOfferRequest $request, P2POfferPromotionService $promotionService): RedirectResponse
    {
        if ($redirect = $this->guardP2PAdQuota()) {
            return $redirect;
        }

        $validated = $request->validated();

        $side             = OrderSide::from($validated['side']);
        $paymentMethodIds = collect($validated['payment_method_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($paymentMethodIds->isEmpty()) {
            notifyEvs('error', $side === OrderSide::SELL
                ? __('Sell trade ads must include at least one accepted payment method.')
                : __('Buy trade ads must include at least one preferred payment method.')
            );

            return back()->withInput();
        }

        $savedMethodIds = PaymentAccount::query()
            ->where('user_id', auth()->id())
            ->pluck('payment_method_id')
            ->map(fn ($id) => (int) $id);

        $missingMethodIds = $paymentMethodIds->diff($savedMethodIds)->values();

        if ($missingMethodIds->isNotEmpty()) {
            notifyEvs('error', $side === OrderSide::SELL
                ? __('Add your saved payment account details for each selected payment method before publishing a sell ad.')
                : __('Add your saved payment account details for each preferred payment method before publishing a buy ad.')
            );

            return back()->withInput();
        }

        $wallet = $this->resolveTradableWallet((int) $validated['wallet_id']);

        if (! $wallet) {
            notifyEvs('error', __('P2P trade ads support crypto wallets only. Choose an active crypto wallet to continue.'));

            return back()->withInput();
        }

        $offer = Offer::create([
            'user_id'                => auth()->id(),
            'wallet_id'              => $wallet->id,
            'quote_currency_id'      => (int) $validated['quote_currency_id'],
            'side'                   => $side,
            'price'                  => (float) $validated['price'],
            'min_amount'             => (float) $validated['min_amount'],
            'max_amount'             => isset($validated['max_amount']) ? (float) $validated['max_amount'] : null,
            'status'                 => OfferStatus::ACTIVE,
            'payment_window_minutes' => (int) ($validated['payment_window_minutes'] ?? (int) setting('p2p_order_expiry_minutes', 45)),
            'terms'                  => $validated['terms'] ?? null,
        ]);

        $offer->paymentMethods()->sync($paymentMethodIds->all());

        $shouldPromote = (bool) ($validated['promote_now'] ?? false);

        if ($shouldPromote) {
            $packageId         = (int) ($validated['promotion_package_id'] ?? 0);
            $promotionWalletId = (int) ($validated['promotion_wallet_id'] ?? 0);

            $package = PromotionPackage::query()
                ->where('id', $packageId)
                ->where('status', true)
                ->when(Schema::hasColumn('p2p_promotion_packages', 'visibility'), function ($q) {
                    $q->where('visibility', 'PUBLIC');
                })
                ->firstOrFail();

            $promotionWallet = Wallet::query()
                ->where('id', $promotionWalletId)
                ->where('user_id', auth()->id())
                ->where('status', true)
                ->with('currency')
                ->firstOrFail();

            try {
                $promotionService->purchase($offer, $package, $promotionWallet, (int) auth()->id());
            } catch (NotifyErrorException $e) {
                notifyEvs('error', $e->getMessage());

                return redirect()
                    ->route('user.p2p.offers.promotion.show', $offer)
                    ->withInput([
                        'package_id' => $packageId,
                        'wallet_id'  => $promotionWalletId,
                    ]);
            }

            notifyEvs('success', __('Trade ad created and promoted successfully'));

            return redirect()->route('user.p2p.offers.my');
        }

        notifyEvs('success', __('Trade ad created successfully'));

        return redirect()->route('user.p2p.offers.my');
    }

    public function update(UpdateOfferRequest $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);

        if ($offer->status === OfferStatus::DISABLED) {
            notifyEvs('error', __('This trade ad cannot be updated.'));

            return back();
        }

        if ($this->offerHasActiveOrders($offer)) {
            notifyEvs('error', __('This trade ad has active orders and cannot be edited right now.'));

            return redirect()->route('user.p2p.offers.my');
        }

        $validated        = $request->validated();
        $side             = OrderSide::from($validated['side']);
        $paymentMethodIds = collect($validated['payment_method_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($paymentMethodIds->isEmpty()) {
            notifyEvs('error', $side === OrderSide::SELL
                ? __('Sell trade ads must include at least one accepted payment method.')
                : __('Buy trade ads must include at least one preferred payment method.')
            );

            return back()->withInput();
        }

        $savedMethodIds = PaymentAccount::query()
            ->where('user_id', auth()->id())
            ->pluck('payment_method_id')
            ->map(fn ($id) => (int) $id);

        $missingMethodIds = $paymentMethodIds->diff($savedMethodIds)->values();

        if ($missingMethodIds->isNotEmpty()) {
            notifyEvs('error', $side === OrderSide::SELL
                ? __('Add your saved payment account details for each selected payment method before updating a sell ad.')
                : __('Add your saved payment account details for each preferred payment method before updating a buy ad.')
            );

            return back()->withInput();
        }

        $wallet = $this->resolveTradableWallet((int) $validated['wallet_id']);

        if (! $wallet) {
            notifyEvs('error', __('P2P trade ads support crypto wallets only. Choose an active crypto wallet to continue.'));

            return back()->withInput();
        }

        $offer->update([
            'wallet_id'              => $wallet->id,
            'quote_currency_id'      => (int) $validated['quote_currency_id'],
            'side'                   => $side,
            'price'                  => (float) $validated['price'],
            'min_amount'             => (float) $validated['min_amount'],
            'max_amount'             => isset($validated['max_amount']) && $validated['max_amount'] !== null ? (float) $validated['max_amount'] : null,
            'payment_window_minutes' => (int) ($validated['payment_window_minutes'] ?? (int) setting('p2p_order_expiry_minutes', 45)),
            'terms'                  => $validated['terms'] ?? null,
        ]);

        $offer->paymentMethods()->sync($paymentMethodIds->all());

        notifyEvs('success', __('Trade ad updated successfully'));

        return redirect()->route('user.p2p.offers.my');
    }

    public function show(Offer $offer): View
    {
        $offer->load(['wallet.currency', 'quoteCurrency', 'paymentMethods', 'user.kycSubmission', 'promotion.package'])
            ->loadCount([
                'orders as total_orders_count',
                'orders as open_orders_count' => function ($q) {
                    $q->whereIn('status', [
                        OrderStatus::PENDING->value,
                        OrderStatus::PAID->value,
                        OrderStatus::DISPUTED->value,
                    ]);
                },
                'orders as completed_orders_count' => function ($q) {
                    $q->where('status', OrderStatus::COMPLETED->value);
                },
                'orders as disputed_orders_count' => function ($q) {
                    $q->where('status', OrderStatus::DISPUTED->value);
                },
            ]);

        $userPaymentAccounts = PaymentAccount::query()
            ->with('paymentMethod')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->get();
        $userPaymentAccounts = P2PPaymentMethodManager::sortPaymentAccounts(
            $userPaymentAccounts,
            P2PPaymentMethodManager::resolveCountryCode(auth()->user())
        );

        $this->authorize('view', $offer);

        $isOwner            = (int) $offer->user_id === (int) auth()->id();
        $totalOrders        = (int) ($offer->total_orders_count ?? 0);
        $openOrders         = (int) ($offer->open_orders_count ?? 0);
        $completedOrders    = (int) ($offer->completed_orders_count ?? 0);
        $disputedOrders     = (int) ($offer->disputed_orders_count ?? 0);
        $completionRate     = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
        $canManageLifecycle = $isOwner
            && $offer->status !== OfferStatus::DISABLED
            && $openOrders === 0;

        $feedbacks = OfferFeedback::query()
            ->with('user')
            ->where('offer_id', $offer->id)
            ->latest()
            ->limit(10)
            ->get();
        $positiveCnt = OfferFeedback::where('offer_id', $offer->id)->where('rating', '>=', 4)->count();
        $negativeCnt = OfferFeedback::where('offer_id', $offer->id)->where('rating', '<=', 2)->count();

        return view('frontend.user.p2p.trade_ads.trade_ad_details', compact(
            'offer',
            'completionRate',
            'totalOrders',
            'openOrders',
            'completedOrders',
            'disputedOrders',
            'feedbacks',
            'positiveCnt',
            'negativeCnt',
            'userPaymentAccounts',
            'isOwner',
            'canManageLifecycle'
        ));
    }

    public function toggle(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);

        if ($offer->status === OfferStatus::DISABLED) {
            notifyEvs('error', __('This trade ad cannot be updated.'));

            return back();
        }

        $hasOpenOrders = $this->offerHasActiveOrders($offer);

        if ($hasOpenOrders) {
            notifyEvs('error', __('This trade ad has active orders and cannot be updated right now.'));

            return back();
        }

        $offer->update([
            'status' => $offer->status === OfferStatus::ACTIVE ? OfferStatus::PAUSED : OfferStatus::ACTIVE,
        ]);

        notifyEvs('success', __('Trade ad status updated.'));

        return back();
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        $this->authorize('delete', $offer);

        $hasOpenOrders = P2POrder::query()
            ->where('offer_id', $offer->id)
            ->whereIn('status', ['PENDING', 'PAID', 'DISPUTED'])
            ->exists();

        if ($hasOpenOrders) {
            notifyEvs('error', __('This trade ad has active orders and cannot be deleted right now.'));

            return back();
        }

        $offer->delete();

        notifyEvs('success', __('Trade ad deleted.'));

        return back();
    }

    /**
     * Build a per-offer price-competitiveness signal comparing each offer's
     * price to the live market average for the same asset + fiat on the same
     * side. This is a real, computed indicator — not a fabricated trend: a
     * positive advantage means the price is better for the viewer than the
     * current market average (cheaper when buying, higher when selling).
     *
     * @param  Collection<int, Offer>                                            $offers
     * @return array<int, array{pct: float, direction: string, favorable: bool}>
     */
    private function resolveOfferPriceTrends(Collection $offers, OrderSide $side): array
    {
        if ($offers->isEmpty()) {
            return [];
        }

        $benchmarks = Offer::query()
            ->join('wallets', 'wallets.id', '=', 'p2p_offers.wallet_id')
            ->where('p2p_offers.status', OfferStatus::ACTIVE)
            ->where('p2p_offers.side', $side)
            ->groupBy('wallets.currency_id', 'p2p_offers.quote_currency_id')
            ->selectRaw('wallets.currency_id as base_currency_id, p2p_offers.quote_currency_id as quote_currency_id, AVG(p2p_offers.price) as avg_price, COUNT(*) as offers_count')
            ->get()
            ->keyBy(fn ($row) => $row->base_currency_id.':'.$row->quote_currency_id);

        $viewerBuys = $side === OrderSide::SELL;
        $trends     = [];

        foreach ($offers as $offer) {
            $key       = ($offer->wallet?->currency_id).':'.$offer->quote_currency_id;
            $benchmark = $benchmarks[$key] ?? null;

            if ($benchmark === null || (int) $benchmark->offers_count < 2) {
                continue;
            }

            $average = (float) $benchmark->avg_price;
            $price   = (float) $offer->price;

            if ($average <= 0) {
                continue;
            }

            $advantage = $viewerBuys
                ? (($average - $price) / $average) * 100
                : (($price - $average) / $average) * 100;

            $trends[(int) $offer->id] = [
                'pct'       => round(abs($advantage), 2),
                'direction' => $advantage > 0.05 ? 'up' : ($advantage < -0.05 ? 'down' : 'flat'),
                'favorable' => $advantage > 0.05,
            ];
        }

        return $trends;
    }

    /**
     * The user's active crypto wallets eligible to back a P2P trade ad. Fiat
     * wallets are excluded: a P2P offer trades a crypto asset for a fiat price.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Wallet>
     */
    private function tradableWallets(): \Illuminate\Database\Eloquent\Collection
    {
        return Wallet::query()
            ->where('user_id', auth()->id())
            ->active()
            ->whereHas('currency', fn ($q) => $q->where('type', CurrencyType::CRYPTO))
            ->with('currency')
            ->get();
    }

    /**
     * Resolve a wallet the authenticated user may publish an offer against,
     * enforcing ownership, active status, and a crypto currency.
     */
    private function resolveTradableWallet(int $walletId): ?Wallet
    {
        return Wallet::query()
            ->with('currency')
            ->where('id', $walletId)
            ->where('user_id', auth()->id())
            ->where('status', true)
            ->whereHas('currency', fn ($q) => $q->where('type', CurrencyType::CRYPTO))
            ->first();
    }

    /**
     * Active fiat currencies selectable as the offer price (quote) currency,
     * plus the default selection for the create form.
     *
     * @return array{currencies: Collection<int, Currency>, default_id: int|null}
     */
    private function fiatQuoteCurrencies(): array
    {
        $currencies = Currency::query()
            ->where('type', CurrencyType::FIAT)
            ->where('status', true)
            ->orderByDesc('default')
            ->orderBy('code')
            ->get(['id', 'code', 'symbol', 'name', 'default']);

        $defaultId = $currencies->firstWhere('default', true)?->id ?? $currencies->first()?->id;

        return ['currencies' => $currencies, 'default_id' => $defaultId !== null ? (int) $defaultId : null];
    }

    private function offerHasActiveOrders(Offer $offer): bool
    {
        return P2POrder::query()
            ->where('offer_id', $offer->id)
            ->whereIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::PAID->value,
                OrderStatus::DISPUTED->value,
            ])
            ->exists();
    }

    /**
     * Compute the user's subscription-plan gates for the P2P marketplace UI.
     * The view uses these flags to swap real Buy/Sell/Create flows for the
     * upgrade modal when the user has no quota on their plan.
     *
     * @return array{can_trade: bool, can_create_ad: bool, trade_limit: ?int, ad_limit: ?int}
     */
    private function resolveP2PSubscriptionGates(): array
    {
        $user       = auth()->user();
        $canTrade   = $this->subscriptionFeatures->canUse($user, 'p2p_trading');
        $tradeLimit = $this->subscriptionFeatures->getLimit($user, 'p2p_trade_limit');
        $adLimit    = $this->subscriptionFeatures->getLimit($user, 'p2p_ad_limit');

        return [
            'can_trade'     => $canTrade && ($tradeLimit === null || $tradeLimit > 0),
            'can_create_ad' => $canTrade && ($adLimit === null || $adLimit > 0),
            'trade_limit'   => $tradeLimit,
            'ad_limit'      => $adLimit,
        ];
    }

    /**
     * Enforce the subscription-plan p2p_ad_limit quota for create / store actions.
     * Returns a redirect when the user has no quota or has hit their cap, null
     * when they are allowed to proceed.
     */
    private function guardP2PAdQuota(): ?RedirectResponse
    {
        $user  = auth()->user();
        $limit = $this->subscriptionFeatures->getLimit($user, 'p2p_ad_limit');

        if (! $this->subscriptionFeatures->canUse($user, 'p2p_trading')) {
            notifyEvs('warning', __('Creating P2P trade ads requires Pro or Business access.'));

            return redirect()->route('user.subscription.plans');
        }

        if ($limit === null) {
            return null;
        }

        if ($limit === 0) {
            notifyEvs('warning', __('Creating P2P trade ads requires Pro or Business access.'));

            return redirect()->route('user.subscription.plans');
        }

        $activeAdsCount = Offer::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OfferStatus::ACTIVE->value, OfferStatus::PAUSED->value])
            ->count();

        if ($activeAdsCount >= $limit) {
            notifyEvs('warning', __('You have reached your plan\'s P2P ad limit. Upgrade your plan or remove an existing ad to create a new one.'));

            return redirect()->route('user.subscription.plans');
        }

        return null;
    }
}
