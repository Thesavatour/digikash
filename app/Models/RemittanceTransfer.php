<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\RemittanceStatus;
use Carbon\Carbon;
use Database\Factories\RemittanceTransferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RemittanceTransfer extends Model
{
    /** @use HasFactory<RemittanceTransferFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'user_id',
        'quote_id',
        'beneficiary_id',
        'corridor_id',
        'transaction_id',
        'source_currency',
        'destination_currency',
        'send_amount',
        'fee_amount',
        'total_paid',
        'exchange_rate',
        'receive_amount',
        'payout_method',
        'payout_gateway',
        'gateway_reference',
        'status',
        'status_history',
        'compliance_result',
        'gateway_payload',
        'purpose',
        'source_of_funds',
        'failure_reason',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payout_method'     => PayoutMethod::class,
            'status'            => RemittanceStatus::class,
            'status_history'    => 'array',
            'compliance_result' => 'array',
            'gateway_payload'   => 'array',
            'send_amount'       => 'float',
            'fee_amount'        => 'float',
            'total_paid'        => 'float',
            'exchange_rate'     => 'float',
            'receive_amount'    => 'float',
            'submitted_at'      => 'datetime',
            'completed_at'      => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $transfer): void {
            if (! $transfer->reference) {
                do {
                    $transfer->reference = 'RMT'.Str::upper(Str::random(12));
                } while (self::where('reference', $transfer->reference)->exists());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(RemittanceQuote::class, 'quote_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(RemittanceCorridor::class, 'corridor_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recordStatus(RemittanceStatus $status, ?string $note = null): void
    {
        $history   = $this->status_history ?? [];
        $history[] = [
            'status'    => $status->value,
            'note'      => $note,
            'timestamp' => now()->toIso8601String(),
        ];

        $updates = ['status' => $status, 'status_history' => $history];

        if ($status === RemittanceStatus::Submitted && ! $this->submitted_at) {
            $updates['submitted_at'] = now();
        }

        if ($status === RemittanceStatus::Completed && ! $this->completed_at) {
            $updates['completed_at'] = now();
        }

        if ($status === RemittanceStatus::Failed && $note) {
            $updates['failure_reason'] = $note;
        }

        $this->update($updates);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus(Builder $query, RemittanceStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof RemittanceStatus ? $status->value : $status);
    }

    public function getCreatedAtTimeAttribute(): string
    {
        return Carbon::parse($this->attributes['created_at'])->format('M d Y h:i A');
    }
}
