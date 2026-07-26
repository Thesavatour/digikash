@extends('backend.support_ticket.index')
@section('title', $title)

@section('support_header')
    <div class="st-hero">
        <div class="st-hero__copy">
            <span class="st-hero__icon"><i class="fa-solid fa-comments"></i></span>
            <div>
                <h1 class="st-hero__title">@yield('title')</h1>
                <span class="st-hero__sub">{{ __('Review the conversation and reply to the customer.') }}</span>
            </div>
        </div>
        <a href="{{ route('admin.support-ticket.new') }}" class="st-hero__count">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to queue') }}
        </a>
    </div>
@endsection

@section('support_content')
    <div class="st-ticket">
        {{-- Header: ticket id + status control --}}
        <div class="st-ticket__head">
            <div class="st-ticket__id">
                <span class="st-pill st-pill--{{ $ticket->priority->badgeColor() }}">
                    <i class="fa-solid fa-bolt"></i> {{ $ticket->priority->label() }}
                </span>
                <h5>{{ __('Ticket ID: :id', ['id' => strtoupper($ticket->uuid)]) }}</h5>
            </div>

            <form action="{{ route('admin.support-ticket.status-update', $ticket->id) }}" method="POST" class="st-ticket__status-form">
                @csrf
                @method('PUT')
                <label for="ticket_status" class="st-ticket__status-label">{{ __('Status') }}</label>
                <select name="ticket_status" id="ticket_status" class="form-select form-select-sm st-ticket__status-select" onchange="this.form.submit()">
                    @foreach(\App\Enums\TicketStatus::options() as $status)
                        <option value="{{ $status['value'] }}" @selected($status['value'] == $ticket->status->value)>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Original request --}}
        <div class="st-ticket__intro">
            <h2 class="st-ticket__title">{{ $ticket->title }}</h2>
            <p class="st-ticket__message">{!! nl2br(e($ticket->message)) !!}</p>
            @if($ticket->attachment)
                <a href="{{ route('file.download', ['filePath' => $ticket->attachment]) }}" class="st-ticket__attachment">
                    <i class="fa-solid fa-paperclip"></i> {{ __('Download Attachment') }}
                </a>
            @endif
        </div>

        {{-- Conversation thread --}}
        @if($ticket->messages->count() > 0)
            <div class="st-thread" id="chat-box">
                @foreach($ticket->messages as $message)
                    <div class="st-msg {{ $message->admin_id ? 'st-msg--admin' : 'st-msg--user' }}">
                        <img src="{{ asset($message->admin_id ? auth()->user()->avatar_alt : $ticket->user->avatar_alt) }}"
                             class="st-msg__avatar" alt="{{ $message->admin_id ? __('Admin') : title($ticket->user->name) }}"
                             width="34" height="34" loading="lazy">
                        <div class="st-bubble">
                            <p class="st-bubble__text">{!! nl2br(e($message->message)) !!}</p>
                            @if($message->attachment)
                                <a href="{{ route('file.download', ['filePath' => $message->attachment]) }}" class="st-bubble__attachment">
                                    <i class="fa-solid fa-paperclip"></i> {{ __('Download Attachment') }}
                                </a>
                            @endif
                            <span class="st-bubble__meta">
                                <span><i class="fa-regular fa-user"></i> {{ $message->admin_id ? __('Admin') : title($ticket->user->name) }}</span>
                                <span><i class="fa-regular fa-clock"></i> {{ $message->created_at->format('g:i A') }}</span>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Reply composer --}}
        <div class="st-composer">
            <form action="{{ route('admin.support-ticket.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <textarea name="message" class="form-control" rows="3" placeholder="{{ __('Type your message here...') }}" required></textarea>

                <div id="attachment-preview" class="st-file-preview d-none">
                    <div class="st-file-preview__name">
                        <i class="fa-solid fa-file"></i>
                        <span id="file-name"></span>
                    </div>
                    <button type="button" class="btn btn-link text-danger btn-sm px-2" onclick="removeFile()">
                        {{ __('Remove') }}
                    </button>
                </div>

                <div class="st-composer__actions">
                    <input type="file" id="file-input" name="attachment" class="d-none" onchange="showFileName(this)">
                    <label for="file-input" class="st-btn st-btn--attach">
                        <i class="fa-solid fa-paperclip"></i> {{ __('Attach') }}
                    </label>
                    <button type="submit" class="st-btn st-btn--send">
                        <i class="fa-solid fa-paper-plane"></i> {{ __('Send Now') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('backend.support_ticket.partials._script')
@endpush
