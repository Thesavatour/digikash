<?php

declare(strict_types=1);

namespace App\Http\Requests\P2P;

use App\Models\P2P\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return auth()->check()
            && $order instanceof Order
            && $this->user()->can('chat', $order);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('Type a message before sending.'),
            'body.max'      => __('Messages are limited to 1,000 characters.'),
        ];
    }
}
