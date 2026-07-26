<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Remittance\ConfirmTransferRequest;
use App\Http\Requests\Remittance\QuoteRequest;
use App\Http\Resources\RemittanceQuoteResource;
use App\Http\Resources\RemittanceTransferResource;
use App\Models\Beneficiary;
use App\Models\RemittanceCorridor;
use App\Models\RemittanceQuote;
use App\Models\RemittanceTransfer;
use App\Services\Remittance\PayoutRouter;
use App\Services\Remittance\QuoteService;
use App\Services\Remittance\RemittanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RemittanceController extends Controller
{
    public function corridors(): JsonResponse
    {
        $corridors = RemittanceCorridor::active()->get()->map(fn ($corridor) => [
            'id'                     => $corridor->id,
            'name'                   => $corridor->name,
            'source_country'         => $corridor->source_country,
            'source_currency'        => $corridor->source_currency,
            'destination_country'    => $corridor->destination_country,
            'destination_currency'   => $corridor->destination_currency,
            'allowed_payout_methods' => $corridor->allowed_payout_methods,
            'fixed_fee'              => (float) $corridor->fixed_fee,
            'percent_fee'            => (float) $corridor->percent_fee,
            'fx_spread_percent'      => (float) $corridor->fx_spread_percent,
            'min_amount'             => (float) $corridor->min_amount,
            'max_amount'             => (float) $corridor->max_amount,
        ]);

        return response()->json(['data' => $corridors]);
    }

    public function quote(QuoteRequest $request, QuoteService $service, PayoutRouter $router): JsonResponse
    {
        try {
            $corridor = $router->findActiveById((int) $request->corridor_id);

            $quote = $service->quote(
                user: $request->user(),
                corridor: $corridor,
                sendAmount: (float) $request->send_amount,
                payoutMethod: PayoutMethod::from($request->payout_method),
                beneficiaryId: $request->filled('beneficiary_id') ? (int) $request->beneficiary_id : null,
            );

            return response()->json(['data' => new RemittanceQuoteResource($quote)]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function send(ConfirmTransferRequest $request, RemittanceService $remittance): JsonResponse
    {
        try {
            $quote       = RemittanceQuote::where('quote_id', $request->quote_id)->firstOrFail();
            $beneficiary = Beneficiary::findOrFail($request->beneficiary_id);

            $transfer = $remittance->send(
                user: $request->user(),
                quote: $quote,
                beneficiary: $beneficiary,
                context: [
                    'wallet_id'       => (int) $request->wallet_id,
                    'purpose'         => $request->purpose,
                    'source_of_funds' => $request->source_of_funds,
                ],
            );

            return response()->json(['data' => new RemittanceTransferResource($transfer->load('beneficiary'))], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function track(Request $request, string $reference): JsonResponse
    {
        $transfer = RemittanceTransfer::with(['beneficiary'])
            ->forUser($request->user()->id)
            ->where('reference', $reference)
            ->first();

        if (! $transfer) {
            return response()->json(['message' => __('Transfer not found.')], 404);
        }

        return response()->json(['data' => new RemittanceTransferResource($transfer)]);
    }

    public function history(Request $request): JsonResponse
    {
        $transfers = RemittanceTransfer::with(['beneficiary'])
            ->forUser($request->user()->id)
            ->latest()
            ->paginate((int) min(100, max(5, $request->integer('per_page', 20))));

        return RemittanceTransferResource::collection($transfers)->response();
    }
}
