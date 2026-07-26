@extends('backend.support_ticket.index')
@section('title', __('Ticket Categories'))

@section('support_header')
    <div class="st-hero">
        <div class="st-hero__copy">
            <span class="st-hero__icon"><i class="fa-solid fa-tags"></i></span>
            <div>
                <h1 class="st-hero__title">{{ __('Ticket Categories') }}</h1>
                <span class="st-hero__sub">{{ __('Organize incoming support tickets into clear, routable topics.') }}</span>
            </div>
        </div>
        <button type="button" class="st-hero__btn" data-coreui-toggle="modal" data-coreui-target="#new-category-modal">
            <i class="fa-solid fa-plus"></i> {{ __('Add Category') }}
        </button>
    </div>
@endsection

@section('support_content')
    <div class="st-list-card">
        <table class="st-table">
            <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Tickets') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Created At') }}</th>
                <th class="text-end">{{ __('Action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td class="st-cell-subject">
                        <div class="st-subject">
                            <span class="st-avatar"><i class="fa-solid fa-tag"></i></span>
                            <div class="st-subject__body">
                                <span class="st-subject__title">{{ title($category->name) }}</span>
                            </div>
                        </div>
                    </td>
                    <td data-label="{{ __('Tickets') }}">
                        <span class="st-count-chip">
                            <i class="fa-solid fa-ticket"></i> {{ $category->tickets_count }}
                        </span>
                    </td>
                    <td data-label="{{ __('Status') }}">
                        <span class="st-pill {{ $category->status ? 'st-pill--success' : 'st-pill--danger' }}">
                            {{ $category->status ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td data-label="{{ __('Created At') }}">
                        <div class="st-time">
                            <span class="st-time__abs">{{ $category->created_at->format('Y-m-d H:i') }}</span>
                            <span class="st-time__rel">{{ $category->created_at->diffForHumans() }}</span>
                        </div>
                    </td>
                    <td class="st-cell-action" data-label="{{ __('Action') }}">
                        <div class="st-actions">
                            <a href="javascript:void(0)" class="st-rowbtn st-rowbtn--edit edit-modal"
                               data-edit-url="{{ route('admin.support-ticket.category.edit', $category->id) }}">
                                <x-icon name="edit" height="16"/> {{ __('Edit') }}
                            </a>
                            <a href="javascript:void(0)" class="st-rowbtn st-rowbtn--danger delete"
                               data-url="{{ route('admin.support-ticket.category.destroy', $category->id) }}">
                                <x-icon name="delete-3" height="16"/> {{ __('Delete') }}
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr class="st-list-empty">
                    <td colspan="5">
                        <x-admin-not-found
                            :title="__('No support categories found')"
                            :message="__('Add categories to organize incoming support tickets.')"
                            icon="fa-tags"
                        />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include('backend.support_ticket.category.partial._new_category_modal')
    @include('backend.support_ticket.category.partial._edit_category_modal')
@endsection

@section('scripts')
    <script>
        'use strict';
        $(document).ready(function () {
            editFormByModal('edit-category-modal', 'edit-category-data');
        });
    </script>
@endsection
