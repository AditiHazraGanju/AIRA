@extends('layout.master')

@push('plugin-styles')
    <style>
        .lang-tab-pane textarea { min-height: 160px; }
        .preview-audio-box {
            margin-top: 12px;
            padding: 12px 14px;
            border: 1px solid #edf0f5;
            border-radius: 8px;
            background: #f9fafc;
        }
        .preview-audio-box audio { width: 100%; margin-top: 8px; }
        .char-count { font-size: 0.78rem; color: #8a94a6; }
        .saved-audio-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #1a7d3c;
            margin-bottom: 8px;
        }
    </style>
@endpush

@section('content')
    <nav class="page-breadcrumb d-flex justify-content-between align-items-center flex-wrap">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#">HealthTalk AI</a></li>
            <li class="breadcrumb-item active" aria-current="page">Introductory Message</li>
        </ol>
    </nav>
    <div style="height: 30px;"></div>

    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-0">Introductory Message (Start Pitch)</h6>
                    <p class="text-secondary">
                        This is what the AI says out loud when a user presses <strong>Start Pitch</strong>. Write the
                        message per language below - saving it generates the speech audio automatically, so it plays
                        instantly in the app. The spoken text also appears on screen as the transcript.
                    </p>

                    <form id="startPitchForm" method="POST" action="{{ route('healthtalk.start-pitch.update') }}">
                        @csrf
                        @method('PUT')

                        <ul class="nav nav-tabs" id="pitchLangTabs" role="tablist">
                            @foreach ($languages as $code => $label)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                                        id="pitch-tab-{{ $code }}" data-bs-toggle="tab"
                                        data-bs-target="#pitch-pane-{{ $code }}" type="button" role="tab">
                                        {{ $label }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content pt-3" id="pitchLangTabsContent">
                            @foreach ($languages as $code => $label)
                                @php $pitch = $pitches[$code] ?? []; @endphp
                                <div class="tab-pane fade lang-tab-pane {{ $loop->first ? 'show active' : '' }}"
                                    id="pitch-pane-{{ $code }}" role="tabpanel">

                                    @if (!empty($pitch['audio_url']))
                                        <div class="saved-audio-badge">
                                            <i data-lucide="check-circle" class="icon-sm"></i>
                                            Audio generated and saved for {{ $label }}
                                        </div>
                                    @endif

                                    <label class="form-label">{{ $label }} introductory message
                                        <span class="text-danger">*</span></label>
                                    <textarea class="form-control pitch-text" name="pitches[{{ $code }}]"
                                        data-lang="{{ $code }}"
                                        placeholder="e.g. Namaste! I'm Aira, your Saathealth assistant. Let me walk you through how Hospicash can protect your family...">{{ old('pitches.' . $code, $pitch['text'] ?? '') }}</textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span class="char-count" data-count-for="{{ $code }}">0 / 2000</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary preview-btn"
                                            data-lang="{{ $code }}">
                                            <i data-lucide="volume-2" class="icon-sm"></i>
                                            Preview voice
                                        </button>
                                    </div>

                                    <div class="preview-audio-box" data-preview-box="{{ $code }}"
                                        style="display:none;">
                                        <span class="text-secondary" style="font-size:0.85rem;">
                                            Preview (not saved yet):
                                        </span>
                                        <audio controls data-preview-player="{{ $code }}"></audio>
                                    </div>

                                    @if (!empty($pitch['audio_url']))
                                        <div class="preview-audio-box">
                                            <span class="text-secondary" style="font-size:0.85rem;">
                                                Currently saved audio:
                                            </span>
                                            <audio controls src="{{ $pitch['audio_url'] }}"></audio>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">
                            Save & Generate Audio
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('plugin-scripts')
    <script src="{{ asset('build/plugins/jquery/jquery.min.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        $(document).ready(function() {
            'use strict';

            function updateCount($textarea) {
                var lang = $textarea.data('lang');
                var len = $textarea.val().length;
                $('[data-count-for="' + lang + '"]').text(len + ' / 2000');
            }

            $('.pitch-text').each(function() {
                updateCount($(this));
            }).on('input', function() {
                updateCount($(this));
            });

            $(document).on('click', '.preview-btn', function() {
                var lang = $(this).data('lang');
                var text = $('.pitch-text[data-lang="' + lang + '"]').val();
                var $btn = $(this);
                var $box = $('[data-preview-box="' + lang + '"]');
                var $player = $('[data-preview-player="' + lang + '"]');

                if (!text || !text.trim()) {
                    if (typeof window.Toaster !== 'undefined') {
                        window.Toaster.error('Write the message for this language first.', 2000);
                    }
                    return;
                }

                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span> Generating...');

                $.ajax({
                    url: "{{ route('healthtalk.start-pitch.preview') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        text: text,
                        language: lang
                    },
                    success: function(response) {
                        if (response.success && response.audio_url) {
                            $player.attr('src', response.audio_url);
                            $box.show();
                            $player[0].play();
                        } else if (typeof window.Toaster !== 'undefined') {
                            window.Toaster.error(response.message || 'Could not generate a preview.', 2500);
                        }
                    },
                    error: function(xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                            'Could not generate a preview right now.';
                        if (typeof window.Toaster !== 'undefined') {
                            window.Toaster.error(msg, 2500);
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(
                            '<i data-lucide="volume-2" class="icon-sm"></i> Preview voice');
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }
                });
            });
        });
    </script>
@endpush
