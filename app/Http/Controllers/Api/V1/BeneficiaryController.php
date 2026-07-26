<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Beneficiary\StoreBeneficiaryRequest;
use App\Http\Requests\Beneficiary\UpdateBeneficiaryRequest;
use App\Http\Resources\BeneficiaryResource;
use App\Models\Beneficiary;
use App\Services\Remittance\BeneficiaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BeneficiaryController extends Controller
{
    public function __construct(protected BeneficiaryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $beneficiaries = $this->service->listForUser($request->user(), $request->string('country')->toString() ?: null);

        return BeneficiaryResource::collection($beneficiaries)->response();
    }

    public function store(StoreBeneficiaryRequest $request): JsonResponse
    {
        try {
            $beneficiary = $this->service->create($request->user(), $request->validated());

            return response()->json(['data' => new BeneficiaryResource($beneficiary)], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== $request->user()->id) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        return response()->json(['data' => new BeneficiaryResource($beneficiary)]);
    }

    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): JsonResponse
    {
        // Belt-and-suspenders: Form Request already authorizes ownership,
        // but if the route binding ever changes, this keeps the API safe.
        if ($beneficiary->user_id !== $request->user()->id) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        try {
            $beneficiary = $this->service->update($beneficiary, $request->validated());

            return response()->json(['data' => new BeneficiaryResource($beneficiary)]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== $request->user()->id) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        $this->service->delete($beneficiary);

        return response()->json(['message' => __('Beneficiary deleted.')]);
    }
}
