<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\P2P;

use App\Http\Controllers\Controller;
use App\Http\Requests\P2P\StoreOrderMessageRequest;
use App\Models\P2P\Order;
use App\Models\P2P\OrderMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderChatController extends Controller
{
    /**
     * Incremental message feed for the order chat. Pass ?after={id} to fetch
     * only newer messages; fetching also marks the counterpart's messages as
     * read for the viewing participant.
     */
    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $afterId = max(0, (int) $request->query('after', 0));

        $messages = OrderMessage::query()
            ->with('sender:id,first_name,last_name,username')
            ->where('order_id', $order->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(200)
            ->get();

        OrderMessage::query()
            ->where('order_id', $order->id)
            ->where('sender_id', '!=', (int) auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn (OrderMessage $message) => $this->presentMessage($message))->values(),
            'last_id'  => (int) ($messages->last()->id ?? $afterId),
            'can_chat' => Gate::allows('chat', $order),
        ]);
    }

    public function store(StoreOrderMessageRequest $request, Order $order): JsonResponse
    {
        $message = OrderMessage::create([
            'order_id'  => $order->id,
            'sender_id' => (int) auth()->id(),
            'body'      => trim((string) $request->validated('body')),
        ]);

        $message->setRelation('sender', $request->user());

        return response()->json([
            'message'  => $this->presentMessage($message),
            'can_chat' => true,
        ], 201);
    }

    /**
     * @return array{id: int, body: string, mine: bool, sender_name: string, sent_at: string, sent_at_human: string}
     */
    private function presentMessage(OrderMessage $message): array
    {
        return [
            'id'            => (int) $message->id,
            'body'          => (string) $message->body,
            'mine'          => (int) $message->sender_id === (int) auth()->id(),
            'sender_name'   => (string) ($message->sender?->name ?? __('User')),
            'sent_at'       => (string) $message->created_at?->toIso8601String(),
            'sent_at_human' => (string) $message->created_at?->format('M d, H:i'),
        ];
    }
}
