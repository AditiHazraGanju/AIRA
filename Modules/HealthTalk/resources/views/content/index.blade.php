@extends('layout.master')

@push('plugin-styles')
    <link href="{{ asset('build/plugins/datatables.net-bs5/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('build/plugins/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .table .dropdown { position: relative !important; }
        .purpose-badge {
            display: inline-block;
            font-size: 0.72rem;
            padding: 0.2rem 0.55rem;
            border-radius: 1rem;
            background: #eef1ff;
            color: #4a4fd8;
            margin: 0 4px 4px 0;
        }
        .content-type-badge.pdf { background: #fdeee6; color: #c0562a; }
        .content-type-badge.text { background: #e7f6ec; color: #1a7d3c; }
        .content-type-badge {
            font-size: 0.72rem;
            padding: 0.25rem 0.6rem;
            border-radius: 1rem;
            font-weight: 600;
        }
        .file-current-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            padding: 6px 10px;
            border-radius: 6px;
            background: #f4f6fb;
            margin-bottom: 8px;
        }
        /* Select2 Tag Layout Fixes (All Modes) */
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            padding: 2px 8px !important;
            margin-top: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            padding: 0 6px 0 0 !important;
            margin: 0 !important;
            text-decoration: none !important;
            position: static !important;
            display: flex !important;
            align-items: center !important;
            line-height: 1 !important;
            border-right: 1px solid #aaa !important;
        }
        .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: #e6f2ff !important;
            color: #0d6efd !important;
            font-weight: 500 !important;
        }

        /* Select2 Dark Mode Support */
        [data-bs-theme="dark"] .select2-container--default .select2-selection--multiple,
        [data-bs-theme="dark"] .select2-container--default .select2-selection--single {
            background-color: #0c1427 !important;
            border-color: #1b263b !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #1b263b !important;
            border-color: #2b3956 !important;
            color: #e2e8f0 !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #e2e8f0 !important;
            border-right-color: #4a5568 !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #ffffff !important;
            background: transparent !important;
        }
        [data-bs-theme="dark"] .select2-dropdown {
            background-color: #0c1427 !important;
            border-color: #1b263b !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-results__option {
            color: #e2e8f0 !important;
            background-color: transparent !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: #1a2a40 !important;
            color: #66b2ff !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #2b3956 !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .select2-search--dropdown .select2-search__field {
            background-color: #0c1427 !important;
            border-color: #1b263b !important;
            color: #e2e8f0 !important;
        }
        [data-bs-theme="dark"] .select2-container--default .select2-results__group {
            color: #a0aec0 !important;
            background-color: #0c1427 !important;
        }
    </style>
@endpush

@section('content')
    <nav class="page-breadcrumb d-flex justify-content-between align-items-center flex-wrap">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#">HealthTalk AI</a></li>
            <li class="breadcrumb-item active" aria-current="page">Content Library</li>
        </ol>
    </nav>
    <div style="height: 30px;"></div>

    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="card-title mb-0">Content Library</h6>
                            <p class="text-secondary mb-0">
                                Everything the AI is allowed to answer from. Add text or upload a PDF, and tag it with
                                a Purpose so the right content reaches the right conversation.
                            </p>
                        </div>
                            <button type="button" class="btn btn-primary btn-icon-text" data-bs-toggle="modal"
                                data-bs-target="#addContentModal">
                                <i class="btn-icon-prepend" data-lucide="plus"></i>
                                Add Content
                            </button>
                    </div>
                    <div class="table-responsive">
                        <table id="contentLibraryTable" class="table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>AI Search</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($contents as $content)
                                    <tr>
                                        <td>
                                                <a href="#" class="edit-content me-2"
                                                    data-id="{{ $content['id'] ?? '' }}"
                                                    data-title="{{ $content['title'] ?? '' }}"
                                                    data-content-type="{{ $content['content_type'] ?? 'text' }}"
                                                    data-body="{{ $content['body'] ?? '' }}"
                                                    data-purposes="{{ json_encode($content['purposes'] ?? []) }}"
                                                    data-status="{{ !empty($content['is_active']) ? '1' : '0' }}"
                                                    data-file-name="{{ $content['file_name'] ?? '' }}"
                                                    data-file-url="{{ $content['file_url'] ?? '' }}"
                                                    data-bs-toggle="modal" data-bs-target="#editContentModal"
                                                    title="Edit">
                                                    <i class="icon-lg" data-lucide="edit"></i>
                                                </a>
                                                <a href="#" class="delete-content"
                                                    data-id="{{ $content['id'] ?? '' }}"
                                                    data-title="{{ $content['title'] ?? '' }}" title="Delete">
                                                    <i class="icon-lg text-danger" data-lucide="trash-2"></i>
                                                </a>
                                        </td>
                                        <td>{{ $content['title'] ?? '' }}</td>
                                        <td>
                                            <span
                                                class="content-type-badge {{ ($content['content_type'] ?? 'text') === 'pdf' ? 'pdf' : 'text' }}">
                                                {{ ($content['content_type'] ?? 'text') === 'pdf' ? 'PDF Document' : 'Text' }}
                                            </span>
                                        </td>
                                        <td>
                                            @foreach ($content['purposes'] ?? [] as $purpose)
                                                <span class="purpose-badge">{{ $purpose }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" class="form-check-input status-toggle"
                                                        id="statusToggle{{ $loop->index }}"
                                                        data-id="{{ $content['id'] ?? '' }}"
                                                        {{ !empty($content['is_active']) ? 'checked' : '' }}>
                                                    <label class="form-check-label"
                                                        for="statusToggle{{ $loop->index }}">
                                                        {{ !empty($content['is_active']) ? 'Active' : 'Inactive' }}
                                                    </label>
                                                </div>
                                        </td>
                                        <td>
                                            {{-- Whether Aira can find this content yet (indexed by the API after each save). --}}
                                            @switch($content['index_status'] ?? 'pending')
                                                @case('indexed')
                                                    <span class="badge bg-success" title="Aira can use this content">Ready</span>
                                                @break

                                                @case('failed')
                                                    <span class="badge bg-danger"
                                                        title="{{ $content['index_error'] ?? 'Indexing failed' }}">Failed</span>
                                                    <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit($content['index_error'] ?? '', 80) }}</div>
                                                @break

                                                @default
                                                    <span class="badge bg-warning text-dark"
                                                        title="Being prepared for Aira; refresh in a minute">Indexing…</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            {{ !empty($content['created_at']) ? \Carbon\Carbon::parse($content['created_at'])->format('d M Y H:i:s') : '' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            No content added yet. Click "Add Content" to build the AI's knowledge base.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reusable field partial is inlined twice below (Add / Edit) to keep this a single file,
         matching the rest of the codebase's per-module view convention. --}}

    <!-- Add Content Modal -->
    <div class="modal fade" id="addContentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addContentForm" method="POST" action="{{ route('healthtalk.content.store') }}"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" placeholder="e.g. Hospicash claim process"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Content Type <span class="text-danger">*</span></label>
                            <div class="btn-group content-type-switch" role="group">
                                <input type="radio" class="btn-check" name="content_type" id="add_type_text"
                                    value="text" checked>
                                <label class="btn btn-outline-primary" for="add_type_text">Text</label>

                                <input type="radio" class="btn-check" name="content_type" id="add_type_pdf"
                                    value="pdf">
                                <label class="btn btn-outline-primary" for="add_type_pdf">PDF Document</label>
                            </div>
                        </div>

                        <div class="mb-3 content-body-field">
                            <label class="form-label">Content Text <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="body" rows="6"
                                placeholder="Type or paste the approved content here..."></textarea>
                        </div>

                        <div class="mb-3 content-file-field" style="display:none;">
                            <label class="form-label">PDF Document <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="file" accept="application/pdf">
                            <small class="text-secondary">PDF only, up to 20 MB.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Purpose / Tags <span class="text-danger">*</span></label>
                            <select class="form-select purpose-select" name="purposes[]" multiple="multiple"
                                data-placeholder="Select or type to add a Purpose tag">
                                @foreach ($purposeGroups as $group => $subCategories)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($subCategories as $sub)
                                            <option value="{{ $group }}: {{ $sub }}">{{ $group }}: {{ $sub }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="text-secondary">
                                Pick from the list, or type your own and press enter to add a new tag /
                                sub-category.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="addStatusSwitch"
                                    name="is_active" value="1" checked>
                                <label class="form-check-label" for="addStatusSwitch">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="addContentForm" class="btn btn-primary">Add Content</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Content Modal -->
    <div class="modal fade" id="editContentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editContentForm" method="POST" action="" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        {{-- Laravel form method spoofing (actual POST, interpreted as PUT by router) --}}
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Content Type <span class="text-danger">*</span></label>
                            <div class="btn-group content-type-switch" role="group">
                                <input type="radio" class="btn-check" name="content_type" id="edit_type_text"
                                    value="text">
                                <label class="btn btn-outline-primary" for="edit_type_text">Text</label>

                                <input type="radio" class="btn-check" name="content_type" id="edit_type_pdf"
                                    value="pdf">
                                <label class="btn btn-outline-primary" for="edit_type_pdf">PDF Document</label>
                            </div>
                        </div>

                        <div class="mb-3 content-body-field">
                            <label class="form-label">Content Text <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="body" id="edit_body" rows="6"></textarea>
                        </div>

                        <div class="mb-3 content-file-field" style="display:none;">
                            <label class="form-label">PDF Document</label>
                            <div id="edit_current_file"></div>
                            <input type="file" class="form-control" name="file" accept="application/pdf">
                            <small class="text-secondary">Leave empty to keep the current document. Upload a new
                                file to replace it.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Purpose / Tags <span class="text-danger">*</span></label>
                            <select class="form-select purpose-select-edit" name="purposes[]" multiple="multiple"
                                data-placeholder="Select or type to add a Purpose tag">
                                @foreach ($purposeGroups as $group => $subCategories)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($subCategories as $sub)
                                            <option value="{{ $group }}: {{ $sub }}">{{ $group }}: {{ $sub }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editStatusSwitch"
                                    name="is_active" value="1">
                                <label class="form-check-label" for="editStatusSwitch">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="editContentForm" class="btn btn-primary">Update Content</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('plugin-scripts')
    <script src="{{ asset('build/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('build/plugins/datatables.net/dataTables.min.js') }}"></script>
    <script src="{{ asset('build/plugins/datatables.net-bs5/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('build/plugins/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush

@push('custom-scripts')
    <script>
        $(document).ready(function() {
            'use strict';

            var table = $('#contentLibraryTable').DataTable({
                "responsive": true,
                "autoWidth": false,
                "pageLength": 10,
                "lengthMenu": [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                "columnDefs": [{
                    "orderable": false,
                    "targets": [0, 3]
                }],
                "drawCallback": function() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });

            function initPurposeSelect2(selector, modalId) {
                $(selector).select2({
                    dropdownParent: $('#' + modalId),
                    width: '100%',
                    tags: true,
                    tokenSeparators: [','],
                    placeholder: $(selector).data('placeholder') || 'Select or add a Purpose tag'
                });
            }

            initPurposeSelect2('.purpose-select', 'addContentModal');
            initPurposeSelect2('.purpose-select-edit', 'editContentModal');

            // Toggle Text vs PDF fields
            function bindContentTypeToggle(scopeId) {
                var $scope = $('#' + scopeId);
                var $radios = $scope.find('input[name="content_type"]');
                var $bodyField = $scope.find('.content-body-field');
                var $fileField = $scope.find('.content-file-field');
                var $bodyInput = $bodyField.find('textarea');
                var $fileInput = $fileField.find('input[type="file"]');

                function apply() {
                    var type = $scope.find('input[name="content_type"]:checked').val();
                    if (type === 'pdf') {
                        $bodyField.hide();
                        $fileField.show();
                        $bodyInput.prop('required', false);
                        $fileInput.prop('required', scopeId === 'addContentForm');
                    } else {
                        $bodyField.show();
                        $fileField.hide();
                        $bodyInput.prop('required', true);
                        $fileInput.prop('required', false);
                    }
                }

                $radios.on('change', apply);
                apply();
            }

            bindContentTypeToggle('addContentForm');
            bindContentTypeToggle('editContentForm');

            // Reset Add modal when it's opened, so leftover state from a previous add doesn't linger
            $('#addContentModal').on('show.bs.modal', function() {
                $('#addContentForm')[0].reset();
                $('.purpose-select').val(null).trigger('change');
                $('#addContentForm input[name="content_type"][value="text"]').prop('checked', true).trigger(
                    'change');
            });

            // Populate Edit modal
            $(document).on('click', '.edit-content', function(e) {
                e.preventDefault();
                var $el = $(this);
                var id = $el.data('id');

                $('#edit_title').val($el.data('title'));
                $('#edit_body').val($el.data('body'));

                var contentType = $el.data('content-type') || 'text';
                $('#editContentForm input[name="content_type"][value="' + contentType + '"]')
                    .prop('checked', true).trigger('change');

                var fileName = $el.data('file-name');
                var fileUrl = $el.data('file-url');
                if (fileName) {
                    $('#edit_current_file').html(
                        '<div class="file-current-link">' +
                        '<i data-lucide="file-text" class="icon-sm"></i>' +
                        (fileUrl ? '<a href="' + fileUrl + '" target="_blank">' + fileName + '</a>' :
                            fileName) +
                        '</div>'
                    );
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } else {
                    $('#edit_current_file').html('');
                }

                var purposes = [];
                try {
                    purposes = JSON.parse($el.attr('data-purposes') || '[]');
                } catch (err) {
                    purposes = [];
                }

                var $purposeSelect = $('.purpose-select-edit');
                
                // Remove selected attribute from all options to avoid lingering state
                $purposeSelect.find('option').prop('selected', false).removeAttr('selected');

                purposes.forEach(function(p) {
                    var $existingOption = $purposeSelect.find('option[value="' + p.replace(/"/g, '\\"') + '"]');
                    if ($existingOption.length === 0) {
                        $purposeSelect.append(new Option(p, p, true, true));
                    } else {
                        $existingOption.prop('selected', true).attr('selected', 'selected');
                    }
                });
                $purposeSelect.val(purposes).trigger('change');

                var status = $el.data('status');
                $('#editStatusSwitch').prop('checked', status == '1');
                $('#editStatusSwitch').next('label').text(status == '1' ? 'Active' : 'Inactive');

                var actionUrl = "{{ route('healthtalk.content.update', ':id') }}".replace(':id', id);
                $('#editContentForm').attr('action', actionUrl);
            });

            // Status toggle (list view switch)
            $(document).on('change', '.status-toggle', function() {
                var checkbox = $(this);
                var id = checkbox.data('id');
                var isChecked = checkbox.is(':checked');
                var label = checkbox.next('label');

                if (checkbox.data('processing')) return;
                checkbox.data('processing', true).prop('disabled', true);
                label.text(isChecked ? 'Active' : 'Inactive');

                $.ajax({
                    url: "{{ route('healthtalk.content.toggle-status', ':id') }}".replace(':id', id),
                    type: 'PATCH',
                    data: {
                        _token: '{{ csrf_token() }}',
                        status: isChecked ? 1 : 0
                    },
                    success: function() {
                        if (typeof window.Toaster !== 'undefined') {
                            window.Toaster.success('Status updated successfully!', 2000);
                        }
                    },
                    error: function() {
                        checkbox.prop('checked', !isChecked);
                        label.text(!isChecked ? 'Active' : 'Inactive');
                        if (typeof window.Toaster !== 'undefined') {
                            window.Toaster.error('Failed to update status. Please try again.', 2000);
                        }
                    },
                    complete: function() {
                        checkbox.data('processing', false).prop('disabled', false);
                    }
                });
            });

            // Delete with confirmation
            $(document).on('click', '.delete-content', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var title = $(this).data('title');

                Swal.fire({
                    title: 'Are you sure?',
                    html: `You are about to delete:<br><strong>"${title}"</strong><br><br>This action cannot be undone!`,
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
                            url: "{{ route('healthtalk.content.destroy', ':id') }}".replace(':id', id),
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function() {
                                if (typeof window.Toaster !== 'undefined') {
                                    window.Toaster.success(`"${title}" deleted successfully!`, 2000);
                                }
                                table.row($(`.delete-content[data-id="${id}"]`).closest('tr')).remove()
                                    .draw();
                            },
                            error: function() {
                                if (typeof window.Toaster !== 'undefined') {
                                    window.Toaster.error('Failed to delete content. Please try again.',
                                        2000);
                                }
                            }
                        });
                    }
                });
            });

            $('#addStatusSwitch, #editStatusSwitch').on('change', function() {
                $(this).next('label').text(this.checked ? 'Active' : 'Inactive');
            });
        });
    </script>
@endpush
