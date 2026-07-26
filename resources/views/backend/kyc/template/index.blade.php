@php
    use App\Enums\UserRole;
@endphp
@extends('backend.layouts.app')
@section('title', __('KYC Templates'))

@section('content')
    @php
        $templatesCollection = $templates instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($templates->items())
            : collect($templates);
        $totalTemplates    = $templatesCollection->count();
        $activeTemplates   = $templatesCollection->filter(fn ($t): bool => (bool) $t->status)->count();
        $inactiveTemplates = $totalTemplates - $activeTemplates;
        $rolesCovered      = $templatesCollection
            ->flatMap(fn ($t) => is_array($t->applicable_to) ? $t->applicable_to : [])
            ->unique()
            ->count();
    @endphp

    <div class="admin-users-page admin-kyc-page">
        {{-- Hero --}}
        <section class="admin-users-hero">
            <div class="admin-users-hero__copy">
                <h1 class="admin-users-hero__title">{{ __('KYC Templates') }}</h1>
                <p class="admin-users-hero__subtitle">{{ __('Design the verification forms users complete — choose fields, target roles, and toggle availability.') }}</p>
            </div>
            @can('kyc-template-manage')
                <button type="button"
                        class="admin-users-hero__action"
                        data-coreui-toggle="modal"
                        data-coreui-target="#createTemplateModal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>{{ __('Add New Template') }}</span>
                </button>
            @endcan
        </section>

        {{-- KPI strip --}}
        <div class="admin-users-stats" aria-label="{{ __('Templates summary') }}">
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--primary">
                    <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ number_format($totalTemplates) }}</span>
                    <span class="admin-users-stat__label">{{ __('Total templates') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--success">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $activeTemplates }}</span>
                    <span class="admin-users-stat__label">{{ __('Active') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--warning">
                    <i class="fa-solid fa-circle-pause" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $inactiveTemplates }}</span>
                    <span class="admin-users-stat__label">{{ __('Inactive') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--info">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $rolesCovered }}</span>
                    <span class="admin-users-stat__label">{{ __('Roles covered') }}</span>
                </div>
            </div>
        </div>

        {{-- Panel: table --}}
        <section class="admin-users-panel">
            <div class="admin-users-table-wrap">
                @if($totalTemplates > 0)
                    <table class="table admin-users-table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Template') }}</th>
                                <th>{{ __('Applicable To') }}</th>
                                <th>{{ __('Status') }}</th>
                                @can('kyc-template-manage')
                                    <th class="text-end">{{ __('Actions') }}</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $template)
                                <tr class="admin-users-row align-middle">
                                    {{-- Template --}}
                                    <td>
                                        <div class="admin-users-doc">
                                            <span class="admin-users-doc__label">{{ $template->title }}</span>
                                            @if(filled($template->description))
                                                <span class="admin-users-doc__hint">{{ $template->description }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Applicable to --}}
                                    <td>
                                        <div class="admin-users-doc__badges">
                                            @if(is_array($template->applicable_to) && count($template->applicable_to))
                                                @foreach($template->applicable_to as $role)
                                                    @php($roleEnum = UserRole::tryFrom($role))
                                                    @if($roleEnum)
                                                        <span class="admin-user-role-badge admin-user-role-badge--{{ $roleEnum->value }}">
                                                            {{ $roleEnum->title() }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            @else
                                                <span class="text-body-secondary small">{{ __('—') }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        <span class="admin-users-pill {{ $template->status ? 'admin-users-pill--success' : '' }}">
                                            <i class="fa-solid {{ $template->status ? 'fa-circle-check' : 'fa-circle-pause' }}" aria-hidden="true"></i>
                                            {{ $template->status ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>

                                    @can('kyc-template-manage')
                                        <td class="text-end">
                                            <div class="admin-users-actions">
                                                <button type="button"
                                                        class="admin-users-action edit-modal"
                                                        data-edit-url="{{ route('admin.kyc.template.edit', $template->id) }}">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                    <span>{{ __('Edit') }}</span>
                                                </button>
                                                <button type="button"
                                                        class="admin-users-action admin-users-action--danger delete"
                                                        data-url="{{ route('admin.kyc.template.destroy', $template->id) }}">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                    <span>{{ __('Delete') }}</span>
                                                </button>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="admin-users-empty">
                        <x-admin-not-found
                            :title="__('No KYC templates found')"
                            :message="__('Create your first template to start collecting user verification details.')"
                            icon="fa-id-card"
                        />
                    </div>
                @endif
            </div>
        </section>
    </div>

    @can('kyc-template-manage')
        @include('backend.kyc.template.partials._create_template_modal')
        @include('backend.kyc.template.partials._edit_template_modal')
    @endcan
@endsection
@push('scripts')
    @can('kyc-template-manage')
        @include('backend.kyc.template.partials._script')
    @endcan
@endpush
