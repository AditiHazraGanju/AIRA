<style>
    /* .nav-item{
        color: #010B65 !important;
    } */
    [data-bs-theme="dark"] .logo-light { display: none !important; }
    [data-bs-theme="dark"] .logo-dark { display: block !important; }
    [data-bs-theme="light"] .logo-dark,
    :root:not([data-bs-theme="dark"]) .logo-dark { display: none !important; }

    /* Prevent long menu titles (e.g. Sales & Product Performance) from being cropped */
    .sidebar .sidebar-body .nav {
        padding: 25px 15px 50px 25px !important;
    }
    .sidebar .sidebar-body .nav .nav-item .nav-link {
        height: auto !important;
        min-height: 32px !important;
        padding: 4px 0 !important;
        white-space: normal !important;
        align-items: flex-start !important;
    }
    .sidebar .sidebar-body .nav .nav-item .nav-link .link-icon {
        top: 6px !important;
    }
    .sidebar .sidebar-body .nav .nav-item .nav-link .link-title {
        line-height: 1.35 !important;
        word-break: normal !important;
    }
    .sidebar .sidebar-body .nav .nav-item.active .nav-link::before {
        top: 6px !important;
    }
    .sidebar-folded:not(.open-sidebar-folded) .sidebar .sidebar-body .nav .nav-item .nav-link {
        height: 32px !important;
        align-items: center !important;
    }
    .sidebar-folded:not(.open-sidebar-folded) .sidebar .sidebar-body .nav .nav-item .nav-link .link-icon {
        top: 8px !important;
    }
</style>

<nav class="sidebar">
    <div class="sidebar-header">
        <img class="sidebar-brand logo-light" style="width: 150px; height: auto;" src="{{ url('assets/images/sh-light.png') }}" alt="logo">
        <img class="sidebar-brand logo-dark" style="width: 150px; height: auto;" src="{{ url('assets/images/sh-dark.png') }}" alt="logo">
        <div class="sidebar-toggler not-active">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="sidebar-body">
        <ul class="nav" id="sidebarNav">
            <li class="nav-item nav-category">HealthTalk AI</li>
            <li class="nav-item {{ active_class(['healthtalk/content*']) }}">
                <a href="{{ route('healthtalk.content.index') }}" class="nav-link">
                    <i class="link-icon" data-lucide="library"></i>
                    <span class="link-title">Content Library</span>
                </a>
            </li>
            <li class="nav-item {{ active_class(['healthtalk/guardrails*']) }}">
                <a href="{{ route('healthtalk.guardrails.index') }}" class="nav-link">
                    <i class="link-icon" data-lucide="shield-check"></i>
                    <span class="link-title">Guardrails</span>
                </a>
            </li>
            <li class="nav-item {{ active_class(['healthtalk/start-pitch*']) }}">
                <a href="{{ route('healthtalk.start-pitch.index') }}" class="nav-link">
                    <i class="link-icon" data-lucide="message-square"></i>
                    <span class="link-title">Introductory Message</span>
                </a>
            </li>
            <li class="nav-item {{ active_class(['healthtalk/reports*']) }}">
                <a href="{{ route('healthtalk.reports.index') }}" class="nav-link">
                    <i class="link-icon" data-lucide="file-text"></i>
                    <span class="link-title">Conversation Reports</span>
                </a>
            </li>

            <li class="nav-item nav-category">Users</li>
            <li class="nav-item {{ active_class(['sh-users*']) }}">
                <a href="{{ route('sh-users.index') }}" class="nav-link">
                    <i class="link-icon" data-lucide="user-round-search"></i>
                    <span class="link-title">SH Users</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
