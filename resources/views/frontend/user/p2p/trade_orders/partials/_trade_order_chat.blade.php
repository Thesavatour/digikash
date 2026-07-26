@php
	$chatCounterpart = $actorId === (int) $order->maker_id ? $order->taker : $order->maker;
	$chatCounterpartName = $chatCounterpart?->name ?? __('Counterparty');
	$chatCounterpartInitials = strtoupper(substr((string) ($chatCounterpart?->first_name ?? ''), 0, 1).substr((string) ($chatCounterpart?->last_name ?? ''), 0, 1));
	$chatCounterpartInitials = $chatCounterpartInitials !== '' ? $chatCounterpartInitials : strtoupper(substr($chatCounterpartName, 0, 1));
	$chatIsOpen = in_array($order->status->value, ['PENDING', 'PAID', 'DISPUTED'], true);
@endphp
<div class="p2p-trade-panel p2p-chat"
     id="p2pOrderChat"
     data-index-url="{{ route('user.p2p.orders.messages.index', $order) }}"
     data-store-url="{{ route('user.p2p.orders.messages.store', $order) }}"
     data-can-chat="{{ $chatIsOpen ? '1' : '0' }}">
	<div class="p2p-trade-panel__head">
		<span class="p2p-trade-panel__icon"><i class="fas fa-comments"></i></span>
		<div class="p2p-chat__head-copy">
			<h6 class="p2p-trade-panel__title">@lang('Trade Chat')</h6>
			<span class="p2p-trade-panel__hint">
                <span class="p2p-chat__peer-avatar" aria-hidden="true">{{ $chatCounterpartInitials }}</span>
                {{ $chatCounterpartName }}
            </span>
		</div>
	</div>
	<div class="p2p-trade-panel__body p2p-chat__body">
		<div class="p2p-chat__messages" id="p2pChatMessages" aria-live="polite" aria-label="@lang('Trade chat messages')">
			<div class="p2p-chat__loading" id="p2pChatLoading">
				<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> @lang('Loading messages...')
			</div>
			<div class="p2p-chat__empty d-none" id="p2pChatEmpty">
				<i class="far fa-comment-dots" aria-hidden="true"></i>
				<span>@lang('No messages yet. Coordinate the payment with your counterparty here.')</span>
			</div>
		</div>
		
		<div class="p2p-chat__closed {{ $chatIsOpen ? 'd-none' : '' }}" id="p2pChatClosed">
			<i class="fas fa-lock" aria-hidden="true"></i>
			@lang('Chat is closed for this order. The transcript stays available for your records.')
		</div>
		
		<form id="p2pChatForm" class="p2p-chat__composer {{ $chatIsOpen ? '' : 'd-none' }}" autocomplete="off">
			<label class="visually-hidden" for="p2pChatInput">@lang('Write a message')</label>
			<textarea id="p2pChatInput"
			          class="form-control p2p-chat__input"
			          rows="1"
			          maxlength="1000"
			          placeholder="@lang('Write a message...')"
			          required></textarea>
			<button type="submit" class="p2p-chat__send" id="p2pChatSend" aria-label="@lang('Send message')">
				<i class="fas fa-paper-plane" aria-hidden="true"></i>
			</button>
		</form>
		<div class="p2p-chat__error d-none" id="p2pChatError" role="alert"></div>
		<div class="p2p-chat__note">
			<i class="fas fa-shield-halved" aria-hidden="true"></i>
			@lang('Keep the conversation in this chat — it is part of the dispute evidence.')
		</div>
	</div>
</div>
