@extends('backend.support_ticket.index')
@section('title', $title)

@section('support_header')
    <div class="st-hero">
        <div class="st-hero__copy">
            <span class="st-hero__icon"><i class="fa-solid fa-headset"></i></span>
            <div>
                <h1 class="st-hero__title">@yield('title')</h1>
                <span class="st-hero__sub">{{ __('Respond to customers and keep every conversation moving.') }}</span>
            </div>
        </div>
        <span class="st-hero__count">
            <i class="fa-solid fa-ticket"></i>
            <strong>{{ $tickets->total() }}</strong> {{ trans_choice('ticket|tickets', $tickets->total()) }}
        </span>
    </div>
@endsection

@section('support_content')
    <div class="st-list-card">
        <table class="st-table">
            <thead>
            <tr>
                <th>{{ __('Title') . ' | ' . __('User') }}</th>
                <th>{{ __('Ticket ID') . ' | ' . __('Priority') }}</th>
                <th>{{ __('Opening Time') }}</th>
                <th>{{ __('Category') }}</th>
                <th>{{ __('Status') }}</th>
                @can('support-ticket-manage')
                    <th class="text-end">{{ __('Action') }}</th>
                @endcan
            </tr>
            </thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td class="st-cell-subject">
                        <div class="st-subject">
                            <span class="st-avatar">{{ strtoupper(mb_substr($ticket->user->name ?? '?', 0, 1)) }}</span>
                            <div class="st-subject__body">
                                <a href="{{ route('admin.support-ticket.show', $ticket->id) }}" class="st-subject__title">
                                    {{ $ticket->title }}
                                </a>
                                <span class="st-subject__user">
                                    <i class="fa-regular fa-user"></i> {{ title($ticket->user->name ?? __('Unknown')) }}
                                </span>
                            </div>
                        </div>
                    </td>
                    <td data-label="{{ __('Ticket ID') }}">
                        <div class="d-inline-flex flex-column align-items-end align-items-md-start gap-1">
                            <span class="st-uuid"><i class="fa-solid fa-hashtag"></i>{{ strtoupper($ticket->uuid) }}</span>
                            <span class="st-pill st-pill--{{ $ticket->priority->badgeColor() }}">
                                <i class="fa-solid fa-bolt"></i> {{ $ticket->priority->label() }}
                            </span>
                        </div>
                    </td>
                    <td data-label="{{ __('Opening Time') }}">
                        <div class="st-time">
                            <span class="st-time__abs">{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
                            <span class="st-time__rel">{{ $ticket->created_at->diffForHumans() }}</span>
                        </div>
                    </td>
                    <td data-label="{{ __('Category') }}">
                        <span class="st-chip {{ $ticket->category ? '' : 'st-chip--muted' }}">
                            <i class="fa-solid fa-tag"></i> {{ $ticket->category->name ?? __('Uncategorized') }}
                        </span>
                    </td>
                    <td data-label="{{ __('Status') }}">
                        <div class="st-status-cell">
                            <span class="st-pill st-pill--{{ $ticket->status->badgeColor() }}">{{ $ticket->status->label() }}</span>
                            @if($ticket->is_resolved)
                                <span class="st-pill st-pill--success"><i class="fa-solid fa-check"></i> {{ __('Resolved') }}</span>
                            @endif
                        </div>
                    </td>
                    @can('support-ticket-manage')
                        <td class="st-cell-action" data-label="{{ __('Action') }}">
                            <a href="{{ route('admin.support-ticket.show', $ticket->id) }}" class="st-action">
                                <x-icon name="chat" height="18"/> {{ __('Support Now') }}
                            </a>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr class="st-list-empty">
                    <td colspan="{{ auth()->user()?->can('support-ticket-manage') ? 6 : 5 }}">
                        <x-admin-not-found
                            :title="__('No support tickets found')"
                            :message="__('Support tickets matching this queue will appear here.')"
                            icon="fa-headset"
                        />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if($tickets->hasPages())
            <div class="st-pagination">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
@endsection
