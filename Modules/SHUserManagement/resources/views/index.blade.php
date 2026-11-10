@extends('layout.master')

@push('plugin-styles')
    <link href="{{ asset('build/plugins/datatables.net-bs5/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('build/plugins/flatpickr/flatpickr.min.css') }}" rel="stylesheet" />

    <style>
        .date-filter-group {
            min-width: 200px;
            max-width: 250px;
        }

        @media (max-width: 992px) {
            .date-filter-group {
                min-width: 180px;
                max-width: 210px;
            }
        }

        .btn-responsive {
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .btn-responsive .btn-text {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    <nav class="page-breadcrumb d-flex justify-content-between align-items-center flex-wrap">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#">User Management</a></li>
            <li class="breadcrumb-item active" aria-current="page">SH Users</li>
        </ol>

        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <!-- Filter Count -->
            <div class="filter-count" id="filterCount"
                style="background: #f8f9fa; color: #495057; padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; border: 1px solid #dee2e6; white-space: nowrap; height: 38px; display: flex; align-items: center; min-width: 120px;">
                <span id="currentCount">0&nbsp;</span>of&nbsp;<span
                    id="totalCount">0&nbsp;</span>users
            </div>

            <div class="input-group date-filter-group" id="shUsersDateFilter">
                <span class="input-group-text input-group-addon bg-transparent border-primary" data-toggle>
                    <i data-lucide="calendar" class="text-primary"></i>
                </span>
                <input type="text" class="form-control bg-transparent border-primary" placeholder="Select joining date"
                    data-input id="shUsersDateRange">
            </div>

            <button type="button" class="btn btn-outline-secondary" id="clearFilters">
                <i data-lucide="x" class="icon-sm"></i>
            </button>

            <button type="button" class="btn btn-primary btn-icon-text btn-responsive" id="exportShUsersCsv"
                style="background-color: #188038 !important; border: 1px solid #188038;">
                <i class="btn-icon-prepend" data-lucide="download"></i>
                <span class="btn-text">Export CSV</span>
            </button>
        </div>
    </nav>
    <div style="height: 30px;"></div>

    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">SH Users</h6>
                    <p class="text-secondary mb-3">All Saathealth users registered on the platform. Search by name, email,
                        SH ID, pincode, age or full 10-digit mobile number.</p>
                    <div class="table-responsive">
                        <table id="shUsersTable" class="table">
                            <thead>
                                <tr>
                                    <th>SH ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile Number</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Pincode</th>
                                    <th>Joining Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('plugin-scripts')
    <script src="{{ asset('build/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('build/plugins/datatables.net/dataTables.min.js') }}"></script>
    <script src="{{ asset('build/plugins/datatables.net-bs5/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('build/plugins/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('build/plugins/moment/moment.min.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        $(function() {
            function escapeHtml(value) {
                return $('<div>').text(value).html();
            }

            function textRender(data) {
                return (data === null || data === undefined || data === '') ? 'N/A' : escapeHtml(data);
            }

            function formatGender(data) {
                if (!data) return '';
                data = String(data);
                return data.charAt(0).toUpperCase() + data.slice(1).toLowerCase();
            }

            function formatJoiningDate(data) {
                return data ? moment(data).format('DD MMM YYYY HH:mm') : '';
            }

            // Initialize DataTable (server-side: users table has 1L+ rows)
            var table = $('#shUsersTable').DataTable({
                "processing": true,
                "serverSide": true,
                "searchDelay": 500,
                "ajax": {
                    "url": "{{ route('sh-users.datatable') }}",
                    "type": "POST",
                    "data": function(d) {
                        d._token = "{{ csrf_token() }}";
                        d.date_range = $('#shUsersDateRange').val();
                    },
                    "error": function() {
                        if (typeof window.Toaster !== 'undefined') window.Toaster.error('Failed to load SH users', 3000);
                        $('#shUsersTable_processing').hide();
                    }
                },
                "columns": [
                    { "data": "id" },
                    { "data": "name", "render": textRender },
                    { "data": "email", "render": textRender },
                    { "data": "mobile", "orderable": false, "render": textRender },
                    { "data": "age", "render": textRender },
                    {
                        "data": "gender",
                        "render": function(data) {
                            return textRender(formatGender(data));
                        }
                    },
                    { "data": "pincode", "render": textRender },
                    {
                        "data": "joining_date",
                        "render": function(data) {
                            return data ? formatJoiningDate(data) : 'N/A';
                        }
                    }
                ],
                "order": [[7, "desc"]],
                "responsive": false,
                "scrollX": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 10,
                "lengthMenu": [10, 25, 50, 100],
                "language": {
                    "emptyTable": "No SH users found.",
                    "zeroRecords": "No matching SH users found."
                },
                "dom": 'lfrtip',
                "drawCallback": function() {
                    updateFilterCount();
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });

            // Date range picker with filtering
            const dateRangePicker = flatpickr("#shUsersDateRange", {
                mode: "range",
                dateFormat: "d-m-Y",
                onChange: function(selectedDates) {
                    // Wait for both ends of the range (or a cleared input) before reloading
                    if (selectedDates.length !== 1) {
                        table.draw();
                    }
                }
            });

            // Clear all filters
            $('#clearFilters').on('click', function() {
                $('#shUsersDateRange').val('');
                if (dateRangePicker) {
                    dateRangePicker.clear();
                }
                table.search('').draw();
            });

            function updateFilterCount() {
                if (table && table.page) {
                    var info = table.page.info();
                    $('#currentCount').text(info.recordsDisplay + ' ');
                    $('#totalCount').text(info.recordsTotal + ' ');
                }
            }

            function csvCell(value) {
                return '"' + String(value ?? '').replace(/"/g, '""').replace(/\r?\n/g, ' ') + '"';
            }

            // CSV Export: fetches the current filter from the server in chunks,
            // since a single request for 1L+ rows would time out.
            var EXPORT_CHUNK_SIZE = 5000;

            $('#exportShUsersCsv').on('click', function() {
                var btn = $(this);
                var originalText = btn.find('.btn-text').text();
                btn.prop('disabled', true).find('.btn-text').text('Exporting...');

                var today = new Date();
                var nowtime = ("0" + today.getHours()).slice(-2) + '-' + ("0" + today.getMinutes()).slice(-2) + '-' + ("0" + today.getSeconds()).slice(-2);
                var dateStr = ("0" + today.getDate()).slice(-2) + '-' + ("0" + (today.getMonth() + 1)).slice(-2) + '-' + today.getFullYear();
                var range = $('#shUsersDateRange').val().replace(/\s+/g, '');
                var filename = 'saathi_sh_users_' + (range ? range.replace('to', '-') + '_' : '') +
                    dateStr + '_' + nowtime + '.csv';

                var headers = [];
                $('#shUsersTable thead th').each(function() {
                    headers.push(csvCell($(this).text().trim()));
                });
                var csv = [headers.join(',')];

                // Reuse the current search, order and date filter
                var baseParams = $.extend(true, {}, table.ajax.params());
                var totalRows = null;

                function fetchChunk(start) {
                    var params = $.extend(true, {}, baseParams, {
                        start: start,
                        length: EXPORT_CHUNK_SIZE
                    });

                    $.ajax({
                        url: "{{ route('sh-users.datatable') }}",
                        type: "POST",
                        data: params,
                        success: function(response) {
                            if (response.error) {
                                exportFailed();
                                return;
                            }

                            totalRows = response.recordsFiltered || 0;
                            var rows = response.data || [];

                            rows.forEach(function(row) {
                                csv.push([
                                    csvCell(row.id),
                                    csvCell(row.name),
                                    csvCell(row.email),
                                    csvCell(row.mobile),
                                    csvCell(row.age),
                                    csvCell(formatGender(row.gender)),
                                    csvCell(row.pincode),
                                    csvCell(formatJoiningDate(row.joining_date))
                                ].join(','));
                            });

                            var fetched = start + rows.length;
                            if (rows.length > 0 && fetched < totalRows) {
                                btn.find('.btn-text').text('Exporting ' + Math.floor(fetched * 100 / totalRows) + '%');
                                fetchChunk(fetched);
                            } else {
                                downloadCsv();
                            }
                        },
                        error: exportFailed
                    });
                }

                function downloadCsv() {
                    var blob = new Blob([csv.join('\n')], {
                        type: 'text/csv;charset=utf-8;'
                    });
                    var link = document.createElement('a');
                    if (link.download !== undefined) {
                        var url = URL.createObjectURL(blob);
                        link.setAttribute('href', url);
                        link.setAttribute('download', filename);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(url);
                    }
                    resetButton();
                }

                function exportFailed() {
                    if (typeof window.Toaster !== 'undefined') window.Toaster.error('Failed to export data');
                    else alert('Failed to export data');
                    resetButton();
                }

                function resetButton() {
                    btn.prop('disabled', false).find('.btn-text').text(originalText);
                }

                fetchChunk(0);
            });
        });
    </script>
@endpush
