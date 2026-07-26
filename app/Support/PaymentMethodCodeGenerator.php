<?php

namespace App\Support;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodCodeGenerator
{
    /**
     * @param class-string<Model> $methodModel
     */
    public static function forGatewayCurrency(PaymentGateway $gateway, string $currency, string $methodModel, ?int $ignoreId = null): string
    {
        $baseCode = $gateway->code.'-'.strtolower(trim($currency));

        if (! self::exists($methodModel, $baseCode, $ignoreId)) {
            return $baseCode;
        }

        $suffix = 2;

        do {
            $methodCode = "{$baseCode}-{$suffix}";
            $suffix++;
        } while (self::exists($methodModel, $methodCode, $ignoreId));

        return $methodCode;
    }

    /**
     * @param class-string<Model> $methodModel
     */
    private static function exists(string $methodModel, string $methodCode, ?int $ignoreId): bool
    {
        $query = $methodModel::query()->where('method_code', $methodCode);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
