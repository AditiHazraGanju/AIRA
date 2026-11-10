@extends('layout.master')

@push('plugin-styles')
    <link href="{{ asset('build/plugins/datatables.net-bs5/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('build/plugins/flatpickr/flatpickr.min.css') }}" rel="stylesheet" />
    <style>
        .ht-kpi-value { font-size: 1.6rem; }
        .ht-chart { position: relative; height: 260px; }
        .ht-stars { color: #f5a524; letter-spacing: 1px; white-space: nowrap; }
        /* Muted in both light and dark themes so 2 stars never reads as 5. */
        .ht-stars .off { color: var(--bs-secondary-color); opacity: .3; }
        .ht-title-cell { max-width: 320px; white-space: normal; }
        .ht-text-cell { max-width: 380px; white-space: normal; }
        .ht-transcript { max-height: 55vh; overflow-y: auto; }
        .ht-msg { max-width: 85%; border-radius: 14px; padding: 8px 12px; margin-bottom: 8px; white-space: pre-wrap; }
        .ht-msg.user { align-self: flex-end; background: #010b65; color: #fff; border-bottom-right-radius: 4px; }
        .ht-msg.assistant { align-self: flex-start; background: #eef0ff; color: #212529; border-bottom-left-radius: 4px; }
        .ht-msg .ht-meta { font-size: .72rem; opacity: .7; margin-top: 2px; }
        .ht-lookup { font-size: .8rem; margin: 2px 0 10px; padding: 4px 10px; border-left: 3px solid #6571ff; color: var(--bs-secondary-color); }
        .ht-lookup.miss { border-left-color: #ff3366; background: rgba(255, 51, 102, .1); }
        .ht-unanswered td { vertical-align: middle; }
    </style>
@endpush

@section('content')
    <nav class="page-breadcrumb d-flex justify-content-between align-items-center flex-wrap">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#">HealthTalk AI</a></li>
            <li class="breadcrumb-item active" aria-current="page">Conversation Reports</li>
        </ol>

        <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-md-0">
            <div class="btn-group btn-group-sm" role="group" id="htPresetGroup" aria-label="Date range presets">
                <button type="button" class="btn btn-outline-primary" data-days="7">Last 7 days</button>
                <button type="button" class="btn btn-outline-primary active" data-days="30">Last 30 days</button>
                <button type="button" class="btn btn-outline-primary" data-days="90">Last 90 days</button>
            </div>
            <div class="input-group input-group-sm" style="width: 260px;">
                <span class="input-group-text"><i data-lucide="calendar" style="width: 14px; height: 14px;"></i></span>
                <input type="text" id="htDateRange" class="form-control" placeholder="YYYY-MM-DD to YYYY-MM-DD">
            </div>
            <button type="button" class="btn btn-sm btn-primary btn-icon-text" id="htApply">
                <i class="btn-icon-prepend" data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> Apply
            </button>
        </div>
    </nav>

    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-1">Conversation Reports</h4>
            <p class="text-secondary mb-0">How people use Aira, how they rate her, which answers they marked wrong, and what the
                Content Library is missing.</p>
        </div>
    </div>

    <div class="alert alert-danger d-none mb-3" id="htError" role="alert"></div>

    {{-- KPI cards --}}
    <div class="row">
        @php
            $kpis = [
                ['id' => 'kpiConversations', 'title' => 'Conversations', 'icon' => 'messages-square', 'color' => 'text-primary', 'sub' => 'kpiConversationsSub'],
                ['id' => 'kpiUsers', 'title' => 'Users', 'icon' => 'users', 'color' => 'text-info', 'sub' => 'kpiUsersSub'],
                ['id' => 'kpiRating', 'title' => 'Average Rating', 'icon' => 'star', 'color' => 'text-warning', 'sub' => 'kpiRatingSub'],
                ['id' => 'kpiLength', 'title' => 'Avg. Conversation', 'icon' => 'timer', 'color' => 'text-success', 'sub' => 'kpiLengthSub'],
                ['id' => 'kpiDisliked', 'title' => 'Disliked Answers', 'icon' => 'thumbs-down', 'color' => 'text-danger', 'sub' => 'kpiDislikedSub'],
                ['id' => 'kpiUnanswered', 'title' => 'Searches With No Answer', 'icon' => 'search-x', 'color' => 'text-danger', 'sub' => 'kpiUnansweredSub'],
            ];
        @endphp
        @foreach ($kpis as $kpi)
            <div class="col-12 col-sm-6 col-xl-2 mb-3 stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <h6 class="card-title mb-0">{{ $kpi['title'] }}</h6>
                            <i data-lucide="{{ $kpi['icon'] }}" class="{{ $kpi['color'] }}" style="width: 18px; height: 18px;"></i>
                        </div>
                        <h3 class="mb-0 fw-bold ht-kpi-value" id="{{ $kpi['id'] }}">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                        </h3>
                        <p class="text-secondary small mt-1 mb-0" id="{{ $kpi['sub'] }}">&nbsp;</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="row">
        <div class="col-12 col-xl-8 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Conversations per Day</h6>
                    <div class="ht-chart"><canvas id="chartDaily"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Ratings</h6>
                    <div class="ht-chart"><canvas id="chartRatings"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-3 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Languages</h6>
                    <div class="ht-chart"><canvas id="chartLanguages"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Top Topics</h6>
                    <div class="ht-chart"><canvas id="chartTopics"></canvas></div>
                    <p class="text-secondary small mb-0 mt-2">Topics are written in each conversation's language.</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-1">What Aira Couldn't Find</h6>
                    <p class="text-secondary small">Content Library searches that returned nothing: candidates for new content.</p>
                    <div class="table-responsive">
                        <table class="table table-sm ht-unanswered mb-0">
                            <thead>
                                <tr><th>Search</th><th class="text-end">Times</th><th class="text-end">Last asked</th></tr>
                            </thead>
                            <tbody id="htUnanswered">
                                <tr><td colspan="3" class="text-secondary">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tables --}}
    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#htTabSessions" type="button" role="tab">Conversations</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#htTabFeedback" type="button" role="tab">Disliked Answers</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="htTabSessions" role="tabpanel">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <select class="form-select form-select-sm w-auto" id="htFilterLanguage">
                                    <option value="">All languages</option>
                                    <option value="ENG">English</option>
                                    <option value="HIN">Hindi</option>
                                    <option value="MAR">Marathi</option>
                                    <option value="BEN">Bengali</option>
                                </select>
                                <select class="form-select form-select-sm w-auto" id="htFilterRating">
                                    <option value="">All ratings</option>
                                    <option value="5">★★★★★ (5)</option>
                                    <option value="4">★★★★ (4)</option>
                                    <option value="3">★★★ (3)</option>
                                    <option value="2">★★ (2)</option>
                                    <option value="1">★ (1)</option>
                                    <option value="unrated">Not rated</option>
                                </select>
                                <div class="form-check form-check-inline align-self-center ms-1">
                                    <input class="form-check-input" type="checkbox" id="htFilterDisliked">
                                    <label class="form-check-label" for="htFilterDisliked">Only with disliked answers</label>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="htSessionsTable" class="table w-100">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Started</th>
                                            <th>User</th>
                                            <th>Language</th>
                                            <th>Title</th>
                                            <th>Questions</th>
                                            <th>Rating</th>
                                            <th>Disliked</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="htTabFeedback" role="tabpanel">
                            <p class="text-secondary small">Answers users marked "edit response" in their Session History.</p>
                            <div class="table-responsive">
                                <table id="htFeedbackTable" class="table w-100">
                                    <thead>
                                        <tr>
                                            <th>Marked on</th>
                                            <th>Question</th>
                                            <th>Aira's answer</th>
                                            <th>Language</th>
                                            <th>User</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Conversation viewer --}}
    <div class="modal fade" id="htSessionModal" tabindex="-1" aria-labelledby="htSessionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="htSessionModalTitle">Conversation</h5>
                        <div class="small text-secondary" id="htSessionMeta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="htSessionBody">
                    <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
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
    <script src="{{ asset('build/plugins/chartjs/chart.umd.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        $(function() {
            'use strict';

            const ROUTES = {
                overview: "{{ route('healthtalk.reports.overview') }}",
                sessions: "{{ route('healthtalk.reports.sessions') }}",
                session: "{{ route('healthtalk.reports.session', ['uuid' => '__ID__']) }}",
                feedback: "{{ route('healthtalk.reports.feedback') }}",
            };
            const LANGUAGES = { ENG: 'English', HIN: 'Hindi', MAR: 'Marathi', BEN: 'Bengali' };
            const COLORS = ['#6571ff', '#05a34a', '#fbbc06', '#ff3366', '#66d1d1', '#7987a1', '#9c27b0', '#ff9800', '#3f51b5', '#009688'];

            // Everything shown here (transcripts, titles, names) is user-provided: always escape.
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const fmtNum = (v) => new Intl.NumberFormat('en-IN').format(v ?? 0);
            const fmtDateTime = (iso) => iso ? new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
            const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) : '';
            const fmtDuration = (s) => s == null ? '—' : (s < 60 ? `${s}s` : `${Math.floor(s / 60)}m ${s % 60}s`);
            const stars = (n) => n ? `<span class="ht-stars">${'★'.repeat(n)}<span class="off">${'★'.repeat(5 - n)}</span></span>` : '<span class="text-secondary">—</span>';
            const userCell = (id, name) => `${esc(name || 'Unknown')} <span class="text-secondary small">#${esc(id)}</span>`;

            function showError(message) {
                $('#htError').text(message).toggleClass('d-none', !message);
            }

            // ── Date range ─────────────────────────────────────────────────
            const ymd = (d) => flatpickr.formatDate(d, 'Y-m-d');
            function lastDays(days) {
                const end = new Date();
                const start = new Date();
                start.setDate(end.getDate() - (days - 1));
                return [start, end];
            }
            let range = lastDays(30);
            const picker = flatpickr('#htDateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                defaultDate: range,
                onChange: (dates) => {
                    if (dates.length === 2) {
                        range = dates;
                        $('#htPresetGroup .btn').removeClass('active');
                    }
                },
            });
            const dateRangeParam = () => `${ymd(range[0])} to ${ymd(range[1])}`;

            $('#htPresetGroup .btn').on('click', function() {
                $('#htPresetGroup .btn').removeClass('active');
                $(this).addClass('active');
                range = lastDays(Number($(this).data('days')));
                picker.setDate(range, false);
                refreshAll();
            });
            $('#htApply').on('click', refreshAll);

            // ── Overview ───────────────────────────────────────────────────
            const charts = {};
            function drawChart(key, canvasId, config) {
                if (charts[key]) charts[key].destroy();
                charts[key] = new Chart(document.getElementById(canvasId), config);
            }
            const noLegend = { plugins: { legend: { display: false } } };

            function loadOverview() {
                $.getJSON(ROUTES.overview, { date_range: dateRangeParam() })
                    .done((res) => {
                        if (!res.success) return showError(res.message || 'Failed to load the overview.');
                        showError('');
                        renderOverview(res.data);
                    })
                    .fail((xhr) => showError(xhr.responseJSON?.message || 'Failed to load the overview.'));
            }

            function renderOverview(d) {
                const t = d.totals;
                $('#kpiConversations').text(fmtNum(t.conversations));
                $('#kpiConversationsSub').text(`${fmtNum(t.lookups)} Content Library searches`);
                $('#kpiUsers').text(fmtNum(t.users));
                $('#kpiUsersSub').text(t.users ? `${(t.conversations / t.users).toFixed(1)} conversations each` : ' ');
                $('#kpiRating').html(t.avg_rating != null ? `${t.avg_rating.toFixed(2)} <span class="ht-stars" style="font-size:1rem">★</span>` : '—');
                $('#kpiRatingSub').text(`${fmtNum(t.rated)} of ${fmtNum(t.conversations)} rated`);
                $('#kpiLength').text(t.avg_turns != null ? t.avg_turns : '—');
                $('#kpiLengthSub').text(`questions, about ${fmtDuration(t.avg_duration_seconds)} each`);
                $('#kpiDisliked').text(fmtNum(t.rejected_answers));
                $('#kpiDislikedSub').text(`${fmtNum(t.approved_answers)} approved`);
                $('#kpiUnanswered').text(fmtNum(t.lookups_no_result));
                $('#kpiUnansweredSub').text(t.lookups ? `${Math.round(100 * t.lookups_no_result / t.lookups)}% of searches` : 'no searches yet');

                drawChart('daily', 'chartDaily', {
                    type: 'line',
                    data: {
                        labels: d.daily.map((p) => fmtDate(p.date)),
                        datasets: [{ data: d.daily.map((p) => p.count), borderColor: COLORS[0], backgroundColor: 'rgba(101,113,255,.12)', fill: true, tension: .3, pointRadius: 2 }],
                    },
                    options: { ...noLegend, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });

                const ratingLabels = ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'];
                drawChart('ratings', 'chartRatings', {
                    type: 'bar',
                    data: { labels: ratingLabels, datasets: [{ data: [1, 2, 3, 4, 5].map((r) => d.rating_distribution[r] || 0), backgroundColor: ['#ff3366', '#ff9800', '#fbbc06', '#66d1d1', '#05a34a'] }] },
                    options: { ...noLegend, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });

                const langKeys = Object.keys(d.languages);
                drawChart('languages', 'chartLanguages', {
                    type: 'doughnut',
                    data: { labels: langKeys.map((k) => LANGUAGES[k] || k), datasets: [{ data: langKeys.map((k) => d.languages[k]), backgroundColor: COLORS }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
                });

                const topicKeys = Object.keys(d.top_tags);
                drawChart('topics', 'chartTopics', {
                    type: 'bar',
                    data: { labels: topicKeys, datasets: [{ data: topicKeys.map((k) => d.top_tags[k]), backgroundColor: COLORS[0] }] },
                    options: { ...noLegend, indexAxis: 'y', maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
                });

                $('#htUnanswered').html(d.unanswered.length
                    ? d.unanswered.map((u) => `<tr><td>${esc(u.query)}</td><td class="text-end">${fmtNum(u.times)}</td><td class="text-end text-secondary small">${esc(fmtDateTime(u.last_asked))}</td></tr>`).join('')
                    : '<tr><td colspan="3" class="text-secondary">Every search found something in this period.</td></tr>');
            }

            // ── Conversations table ────────────────────────────────────────
            const sessionsTable = $('#htSessionsTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[1, 'desc']],
                pageLength: 25,
                ajax: {
                    url: ROUTES.sessions,
                    type: 'POST',
                    data: (d) => {
                        d._token = "{{ csrf_token() }}";
                        d.date_range = dateRangeParam();
                        d.language = $('#htFilterLanguage').val();
                        d.rating = $('#htFilterRating').val();
                        d.disliked_only = $('#htFilterDisliked').is(':checked') ? 1 : 0;
                    },
                    dataSrc: (json) => {
                        showError(json.error || '');
                        return json.data || [];
                    },
                },
                columns: [
                    { data: null, orderable: false, render: (r) => `<a href="#" class="ht-view" data-id="${esc(r.id)}" title="View conversation"><i class="icon-lg" data-lucide="eye"></i></a>` },
                    { data: 'started_at', render: (v) => esc(fmtDateTime(v)) },
                    { data: 'user_name', render: (v, t, r) => userCell(r.user_id, v) },
                    { data: 'language', render: (v) => esc(LANGUAGES[v] || v) },
                    { data: 'title', className: 'ht-title-cell', render: (v, t, r) => v ? esc(v) : `<span class="text-secondary">${r.summary_status === 'pending' ? 'Summarising…' : 'Untitled'}</span>` },
                    { data: 'turns' },
                    { data: 'rating', render: (v) => stars(v) },
                    { data: 'rejected_answers', render: (v) => v ? `<span class="badge bg-danger">${esc(v)}</span>` : '<span class="text-secondary">0</span>' },
                ],
                drawCallback: () => { try { lucide.createIcons(); } catch (e) {} },
            });
            $('#htFilterLanguage, #htFilterRating, #htFilterDisliked').on('change', () => sessionsTable.ajax.reload());

            // ── Disliked answers table ─────────────────────────────────────
            const feedbackTable = $('#htFeedbackTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[0, 'desc']],
                pageLength: 25,
                ajax: {
                    url: ROUTES.feedback,
                    type: 'POST',
                    data: (d) => {
                        d._token = "{{ csrf_token() }}";
                        d.date_range = dateRangeParam();
                    },
                    dataSrc: (json) => {
                        showError(json.error || '');
                        return json.data || [];
                    },
                },
                columns: [
                    { data: 'feedback_at', render: (v) => esc(fmtDateTime(v)) },
                    { data: 'question', className: 'ht-text-cell', render: (v) => esc(v) },
                    { data: 'answer', className: 'ht-text-cell', orderable: false, render: (v) => esc(v) },
                    { data: 'language', render: (v) => esc(LANGUAGES[v] || v) },
                    { data: 'user_name', orderable: false, render: (v, t, r) => userCell(r.user_id, v) },
                    { data: null, orderable: false, render: (r) => `<a href="#" class="ht-view" data-id="${esc(r.session_id)}">View conversation</a>` },
                ],
            });

            // DataTables can't size columns inside a hidden tab; fix on show.
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', () => $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust());

            // ── Conversation viewer ────────────────────────────────────────
            const modal = new bootstrap.Modal(document.getElementById('htSessionModal'));

            $(document).on('click', '.ht-view', function(e) {
                e.preventDefault();
                openSession($(this).data('id'));
            });

            function openSession(id) {
                $('#htSessionModalTitle').text('Conversation');
                $('#htSessionMeta').empty();
                $('#htSessionBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
                modal.show();

                $.getJSON(ROUTES.session.replace('__ID__', encodeURIComponent(id)))
                    .done((res) => res.success ? renderSession(res.data.session) : $('#htSessionBody').html(`<p class="text-danger">${esc(res.message)}</p>`))
                    .fail((xhr) => $('#htSessionBody').html(`<p class="text-danger">${esc(xhr.responseJSON?.message || 'Failed to load the conversation.')}</p>`));
            }

            function renderSession(s) {
                $('#htSessionModalTitle').text(s.title || 'Untitled conversation');
                $('#htSessionMeta').html([
                    userCell(s.user_id, s.user_name),
                    esc(fmtDateTime(s.started_at)),
                    esc(LANGUAGES[s.language] || s.language),
                    `${esc(s.turns)} question${s.turns === 1 ? '' : 's'}`,
                    stars(s.rating),
                    s.end_reason ? `ended: ${esc(s.end_reason)}` : esc(s.status),
                ].join(' &middot; '));

                const summary = s.summary
                    ? `<div class="bg-body-tertiary border rounded p-3 mb-3"><h6 class="mb-1">Summary</h6><p class="mb-2">${esc(s.summary)}</p>${(s.tags || []).map((t) => `<span class="badge bg-primary-subtle text-primary me-1">${esc(t)}</span>`).join('')}</div>`
                    : `<div class="alert alert-secondary py-2">Summary ${esc(s.summary_status)}${s.summary_error ? ': ' + esc(s.summary_error) : ''}</div>`;

                const transcript = s.messages.map((m) => {
                    if (m.role === 'tool') {
                        const n = m.metadata?.results ?? 0;
                        const from = (m.metadata?.titles || []).length ? ` from ${m.metadata.titles.map(esc).join(', ')}` : '';
                        return `<div class="ht-lookup ${n ? '' : 'miss'}">🔎 Looked up “${esc(m.content)}”: ${n ? `${n} result${n === 1 ? '' : 's'}${from}` : '<strong>nothing found</strong>'}</div>`;
                    }
                    const meta = m.role === 'user'
                        ? (m.input_mode === 'text' ? 'typed' : 'spoken')
                        : (m.interrupted ? 'cut off by the user' : 'Aira');
                    return `<div class="ht-msg ${m.role}">${esc(m.content)}<div class="ht-meta">${esc(meta)} · ${esc(fmtDateTime(m.at))}</div></div>`;
                }).join('');

                const feedbackBadge = (f) => f === 'rejected' ? '<span class="badge bg-danger">Disliked</span>' : f === 'approved' ? '<span class="badge bg-success">Approved</span>' : '';
                const qa = s.qa.length
                    ? `<h6 class="mt-4">Questions &amp; Answers (as shown to the user)</h6>` + s.qa.map((q, i) =>
                        `<div class="border-bottom py-2"><div class="fw-semibold">Q${i + 1}: ${esc(q.question)} ${feedbackBadge(q.feedback)}</div><div class="text-secondary">${esc(q.answer)}</div></div>`).join('')
                    : '';

                $('#htSessionBody').html(summary + `<h6>Transcript</h6><div class="ht-transcript d-flex flex-column">${transcript || '<p class="text-secondary">No messages.</p>'}</div>` + qa);
            }

            function refreshAll() {
                loadOverview();
                sessionsTable.ajax.reload();
                feedbackTable.ajax.reload();
            }

            loadOverview();
        });
    </script>
@endpush
