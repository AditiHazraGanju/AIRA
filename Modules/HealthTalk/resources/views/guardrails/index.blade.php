@extends('layout.master')

@push('plugin-styles')
    <link href="{{ asset('build/plugins/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" />
    <style>
        .guardrail-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #edf0f5;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .guardrail-row .rule-text { flex: 1; }
        .guardrail-actions a { margin-left: 10px; }
        .lang-tab-pane textarea { min-height: 110px; }
    </style>
@endpush

@section('content')
    <nav class="page-breadcrumb d-flex justify-content-between align-items-center flex-wrap">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#">HealthTalk AI</a></li>
            <li class="breadcrumb-item active" aria-current="page">Guardrails</li>
        </ol>
    </nav>
    <div style="height: 30px;"></div>

    <div class="row">
        <div class="col-lg-7 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="card-title mb-0">Guardrail Rules</h6>
                            <p class="text-secondary mb-0">
                                State what the AI must never talk about. Each rule is enforced on every
                                conversation, in every language.
                            </p>
                        </div>
                            <button type="button" class="btn btn-primary btn-icon-text" data-bs-toggle="modal"
                                data-bs-target="#addGuardrailModal">
                                <i class="btn-icon-prepend" data-lucide="plus"></i>
                                Add Guardrail
                            </button>
                    </div>

                    <div id="guardrailsList">
                        @forelse ($rules as $rule)
                            <div class="guardrail-row" data-id="{{ $rule['id'] ?? '' }}">
                                <div class="rule-text">
                                    <span
                                        class="badge bg-{{ !empty($rule['is_active']) ? 'success' : 'secondary' }} mb-1">
                                        {{ !empty($rule['is_active']) ? 'Active' : 'Inactive' }}
                                    </span>
                                    <div>{{ $rule['rule_text'] ?? '' }}</div>
                                </div>
                                <div class="guardrail-actions text-nowrap">
                                        <a href="#" class="edit-guardrail" data-id="{{ $rule['id'] ?? '' }}"
                                            data-rule-text="{{ $rule['rule_text'] ?? '' }}"
                                            data-status="{{ !empty($rule['is_active']) ? '1' : '0' }}"
                                            data-bs-toggle="modal" data-bs-target="#editGuardrailModal"
                                            title="Edit">
                                            <i class="icon-lg" data-lucide="edit"></i>
                                        </a>
                                        <a href="#" class="delete-guardrail" data-id="{{ $rule['id'] ?? '' }}"
                                            title="Delete">
                                            <i class="icon-lg text-danger" data-lucide="trash-2"></i>
                                        </a>
                                </div>
                            </div>
                        @empty
                            <p id="noGuardrailsMessage" class="text-secondary mb-0">
                                No guardrails added yet. The AI currently has no topic restrictions beyond its
                                approved content.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-0">Default Guardrail Message</h6>
                    <p class="text-secondary">
                        What the AI says when a guardrail is hit - one message per language, so the refusal is
                        understandable no matter which language the conversation is in.
                    </p>

                    <form id="guardrailSettingsForm" method="POST"
                        action="{{ route('healthtalk.guardrails.settings.update') }}">
                        @csrf
                        @method('PUT')

                        <ul class="nav nav-tabs" id="guardrailLangTabs" role="tablist">
                            @foreach ($languages as $code => $label)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                                        id="guardrail-tab-{{ $code }}" data-bs-toggle="tab"
                                        data-bs-target="#guardrail-pane-{{ $code }}" type="button" role="tab">
                                        {{ $label }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="tab-content pt-3" id="guardrailLangTabsContent">
                            @foreach ($languages as $code => $label)
                                <div class="tab-pane fade lang-tab-pane {{ $loop->first ? 'show active' : '' }}"
                                    id="guardrail-pane-{{ $code }}" role="tabpanel">
                                    <label class="form-label">{{ $label }} message
                                        <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="default_messages[{{ $code }}]"
                                        placeholder="e.g. I'm not able to help with that. Let me connect you with a Saathealth team member.">{{ $defaultMessages[$code] ?? '' }}</textarea>
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">Save Default Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Guardrail Modal -->
    <div class="modal fade" id="addGuardrailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Guardrail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addGuardrailForm" method="POST" action="{{ route('healthtalk.guardrails.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">What can't the AI talk about? <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" name="rule_text" rows="4"
                                placeholder="e.g. Do not discuss competitor insurance products or give personalised medical advice."
                                required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="addGuardrailForm" class="btn btn-primary">Add Guardrail</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Guardrail Modal -->
    <div class="modal fade" id="editGuardrailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Guardrail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editGuardrailForm" method="POST" action="">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">What can't the AI talk about? <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" name="rule_text" id="edit_rule_text" rows="4"
                                required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editGuardrailStatusSwitch"
                                    name="is_active" value="1">
                                <label class="form-check-label" for="editGuardrailStatusSwitch">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="editGuardrailForm" class="btn btn-primary">Update Guardrail</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('plugin-scripts')
    <script src="{{ asset('build/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('build/plugins/sweetalert2/sweetalert2.min.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        $(document).ready(function() {
            'use strict';

            $(document).on('click', '.edit-guardrail', function(e) {
                e.preventDefault();
                var $el = $(this);
                var id = $el.data('id');

                $('#edit_rule_text').val($el.data('rule-text'));

                var status = $el.data('status');
                $('#editGuardrailStatusSwitch').prop('checked', status == '1');
                $('#editGuardrailStatusSwitch').next('label').text(status == '1' ? 'Active' : 'Inactive');

                var actionUrl = "{{ route('healthtalk.guardrails.update', ':id') }}".replace(':id', id);
                $('#editGuardrailForm').attr('action', actionUrl);
            });

            $('#editGuardrailStatusSwitch').on('change', function() {
                $(this).next('label').text(this.checked ? 'Active' : 'Inactive');
            });

            $(document).on('click', '.delete-guardrail', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.guardrail-row');
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This guardrail will stop being enforced. This action cannot be undone!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    focusCancel: true,
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('healthtalk.guardrails.destroy', ':id') }}".replace(':id', id),
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function() {
                                if (typeof window.Toaster !== 'undefined') {
                                    window.Toaster.success('Guardrail deleted successfully!', 2000);
                                }
                                $row.remove();
                                if ($('#guardrailsList .guardrail-row').length === 0) {
                                    $('#guardrailsList').append(
                                        '<p class="text-secondary mb-0">No guardrails added yet.</p>'
                                    );
                                }
                            },
                            error: function() {
                                if (typeof window.Toaster !== 'undefined') {
                                    window.Toaster.error(
                                        'Failed to delete guardrail. Please try again.', 2000);
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
