<!DOCTYPE html>
<!--
Template Name: NobleUI - Laravel Admin Dashboard Template
Author: NobleUI
Website: https://nobleui.com
Contact: nobleui.team@gmail.com
Purchase: https://1.envato.market/nobleui_laravel
License: You must have a valid license to legally use the template for your project.
-->
<html>
<head>
  <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
  <meta name="description" content="Responsive Laravel Admin Dashboard Template based on Bootstrap 5">
	<meta name="author" content="NobleUI">
	<meta name="keywords" content="nobleui, bootstrap, bootstrap 5, bootstrap5, admin, dashboard, template, responsive, css, sass, html, laravel, theme, front-end, ui kit, web">

  <title>{{ config('app.name') }}</title>

  <!-- color-modes:js -->
  @vite(['resources/js/pages/color-modes.js'])
  <script>
    (function() {
      const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
  
  <!-- End fonts -->
  
  <!-- CSRF Token -->
  <meta name="_token" content="{{ csrf_token() }}">
  
  <link rel="shortcut icon" href="{{ asset('/favicon.ico') }}">

  <!-- Splash Screen -->
  <link href="{{ asset('splash-screen.css') }}" rel="stylesheet" />

  <!-- plugin css -->
  <link href="{{ asset('build/plugins/perfect-scrollbar/perfect-scrollbar.css') }}" rel="stylesheet" />

  @stack('plugin-styles')

  <!-- CSS for LTR layout-->
  @vite(['resources/sass/app.scss', 'resources/css/custom.css'])

  <!-- CSS for RTL layout-->
  <!-- @vite(['resources/rtl-css/app-rtl.css', 'resources/rtl-css/custom-rtl.css']) -->

  @stack('style')

  <!-- Toaster Styles -->
  <style>
    .toaster-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 350px;
    }
    .toast-item {
      margin-bottom: 10px;
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      justify-content: space-between;
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.3s ease-in-out;
    }
    .toast-item.show {
      opacity: 1;
      transform: translateX(0);
    }
    .toast-success {
      background-color: #d4edda;
      border-left: 4px solid #28a745;
      color: #155724;
    }
    .toast-error {
      background-color: #f8d7da;
      border-left: 4px solid #dc3545;
      color: #721c24;
    }
    .toast-warning {
      background-color: #fff3cd;
      border-left: 4px solid #ffc107;
      color: #856404;
    }
    .toast-info {
      background-color: #d1ecf1;
      border-left: 4px solid #6571ff;
      color: #0c5460;
    }
    .toast-close {
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      opacity: 0.7;
      margin-left: 10px;
    }
    .toast-close:hover {
      opacity: 1;
    }
  </style>
</head>
<body data-base-url="{{url('/')}}">

  <script>
    // Create splash screen container
    var splash = document.createElement("div");
    splash.innerHTML = `
      <div class="splash-screen">
        <div class="logo"></div>
        <div class="spinner"></div>
      </div>`;
    
    // Insert splash screen as the first child of the body
    document.body.insertBefore(splash, document.body.firstChild);

    // Add 'loaded' class to body once DOM is fully loaded
    document.addEventListener("DOMContentLoaded", function () {
      document.body.classList.add("loaded");
    });
  </script>

  <div class="main-wrapper" id="app">
    <!-- Global Toaster Container -->
    <div class="toaster-container" id="toaster-container"></div>
    @include('layout.partials.sidebar')
    <div class="page-wrapper">
      @include('layout.partials.header')
      <!-- AJAX Navigation Progress Bar -->
      <div id="ajax-nav-progress" style="position:fixed;top:0;left:0;width:0%;height:3px;background:linear-gradient(90deg,#6571ff,#a78bfa);z-index:9999;transition:width 0.25s ease,opacity 0.4s ease;opacity:0;pointer-events:none;"></div>
      <div class="page-content container-xxl" id="page-content">
        @yield('content')
      </div>
      @include('layout.partials.footer')
    </div>
  </div>

    <!-- base js -->
    @vite(['resources/js/app.js'])
    <script src="{{ asset('build/plugins/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('build/plugins/lucide/lucide.min.js') }}"></script>
    <script src="{{ asset('build/plugins/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <!-- end base js -->

    <!-- plugin js -->
    @stack('plugin-scripts')
    <!-- end plugin js -->

    <!-- common js -->
    @vite(['resources/js/pages/template.js'])
    <!-- end common js -->

    <!-- Toaster JavaScript -->
    <script>
      window.Toaster = {
        show: function(message, type = 'info', duration = 5000) {
          const container = document.getElementById('toaster-container');
          const toastId = 'toast-' + Date.now();
          const toast = document.createElement('div');
          toast.id = toastId;
          toast.className = `toast-item toast-${type}`;
          toast.innerHTML = `
            <span>${message}</span>
            <button class="toast-close" onclick="Toaster.hide('${toastId}')">&times;</button>
          `;
          container.appendChild(toast);
          setTimeout(() => {
            toast.classList.add('show');
          }, 10);
          if (duration > 0) {
            setTimeout(() => {
              this.hide(toastId);
            }, duration);
          }
          return toastId;
        },
        hide: function(toastId) {
          const toast = document.getElementById(toastId);
          if (toast) {
            toast.classList.remove('show');
            setTimeout(() => {
              if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
              }
            }, 300);
          }
        },
        success: function(message, duration = 1500) {
          return this.show(message, 'success', duration);
        },
        error: function(message, duration = 3000) {
          return this.show(message, 'error', duration);
        },
        warning: function(message, duration = 2000) {
          return this.show(message, 'warning', duration);
        },
        info: function(message, duration = 2000) {
          return this.show(message, 'info', duration);
        }
      };

      // Handle toaster from session - Global handler for all pages
      document.addEventListener('DOMContentLoaded', function() {
        // Single toaster - consume immediately
        @if(session()->has('toaster'))
          @php
            $toasterData = session()->pull('toaster'); // pull() removes the data from session
          @endphp
          const toasterData = @json($toasterData);
          Toaster.show(toasterData.message, toasterData.type, toasterData.duration || 2000);
        @endif

        // Multiple toasters - consume immediately
        @if(session()->has('toaster_multiple'))
          @php
            $toasterMultiple = session()->pull('toaster_multiple'); // pull() removes the data from session
          @endphp
          const toasterMultiple = @json($toasterMultiple);
          toasterMultiple.forEach(function(toast, index) {
            setTimeout(function() {
              Toaster.show(toast.message, toast.type, toast.duration || 2000);
            }, index * 500); // Stagger multiple toasts
          });
        @endif
      });
    </script>

    @stack('custom-scripts')

    {{-- =====================================================================
         AJAX SPA Navigation — v3
         Fixes from v2:
         1. Inject <style> blocks from fetched <head> (not just <link> tags)
            → Fixes broken header/filter layout on pages using plugin-styles
              with inline <style> blocks (CSV Upload, Coming Soon, Training, etc.)
         2. Patch DOMContentLoaded inside eval'd scripts
            → Coming Soon's plugin-scripts uses addEventListener('DOMContentLoaded')
              which never fires after AJAX swap since the event already happened.
              We rewrite it to call the callback immediately.
         3. Scripts collected from full doc.body (not just #page-content)
            → plugin-scripts and custom-scripts stacks render outside
              #page-content in the layout body.
    ====================================================================== --}}
    <script>
    (function () {
      'use strict';

      /* ── progress bar ───────────────────────────────────────────────── */
      var progress = document.getElementById('ajax-nav-progress');
      var _progressTimer;

      function showProgress() {
        clearTimeout(_progressTimer);
        progress.style.width = '0%';
        progress.style.opacity = '1';
        requestAnimationFrame(function () {
          progress.style.transition = 'width 0.8s ease, opacity 0.4s ease';
          progress.style.width = '80%';
        });
      }

      function finishProgress() {
        progress.style.transition = 'width 0.15s ease, opacity 0.4s ease';
        progress.style.width = '100%';
        _progressTimer = setTimeout(function () {
          progress.style.opacity = '0';
          setTimeout(function () { progress.style.width = '0%'; }, 400);
        }, 150);
      }

      /* ── inject missing CSS from fetched <head> ─────────────────────── */
      // Handles BOTH <link rel="stylesheet"> AND inline <style> blocks,
      // since many pages use @@push('plugin-styles') with <style>...</style>.
      function injectMissingStyles(doc) {
        // 1. <link rel="stylesheet"> tags
        doc.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
          var href = link.getAttribute('href');
          if (!href) return;
          if (!document.querySelector('link[href="' + href + '"]')) {
            var newLink = document.createElement('link');
            newLink.rel = 'stylesheet';
            newLink.href = href;
            document.head.appendChild(newLink);
          }
        });

        // 2. Inline <style> blocks — tag with a data attribute to avoid re-injecting
        //    on subsequent navigations to the same page.
        doc.querySelectorAll('style').forEach(function (style) {
          var css = style.textContent.trim();
          if (!css) return;
          // Signature = length + first 120 chars + last 40 chars.
          // Using ONLY the first N chars caused false collisions between pages
          // that share the same opening CSS rules (e.g. .sticky-column) but
          // have different total content (Training vs Completed Orders styles).
          var sig = css.length + '|' + css.substring(0, 120) + '|' + css.slice(-40);
          // Encode to a safe attribute value
          var sigKey = btoa(encodeURIComponent(sig).substring(0, 255)).replace(/[^a-zA-Z0-9]/g, '');
          if (!document.querySelector('style[data-ajax-sig="' + sigKey + '"]')) {
            var newStyle = document.createElement('style');
            newStyle.setAttribute('data-ajax-sig', sigKey);
            newStyle.textContent = css;
            document.head.appendChild(newStyle);
          }
        });
      }

      /* ── load <script src> files one-by-one, call cb when done ─────── */
      function loadSrcsSequentially(srcs, cb) {
        if (!srcs.length) { cb(); return; }
        var src = srcs.shift();
        var s = document.createElement('script');
        s.src = src;
        s.onload = s.onerror = function () { loadSrcsSequentially(srcs, cb); };
        document.body.appendChild(s);
      }

      /* ── run an inline script string in global scope ────────────────── */
      // IMPORTANT: Patch DOMContentLoaded listeners — since DOMContentLoaded
      // already fired on the initial page load, any addEventListener call for
      // it inside AJAX-fetched scripts will NEVER fire. We replace them with
      // an immediate callback invocation.
      function execInline(code) {
        var patched = code.replace(
          /document\s*\.\s*addEventListener\s*\(\s*['"]DOMContentLoaded['"]\s*,\s*/g,
          '(window.__ajaxDomReady = function(fn){ fn(); }, window.__ajaxDomReady)('
        );
        try {
          (0, eval)(patched);
        } catch (e) {
          console.warn('[AJAXNav] inline script error:', e, '\nSource:', code.substring(0, 200));
        }
      }

      /* ── fingerprints of layout scripts that must NOT re-run ────────── */
      var SKIP_PATTERNS = [
        'ajax-nav-progress',
        'window.Toaster = {',
        'splash-screen',
        'color-modes',
        'data-bs-theme',
      ];
      function isLayoutScript(text) {
        return SKIP_PATTERNS.some(function (p) { return text.indexOf(p) !== -1; });
      }

      /* ── re-init global UI after every content swap ─────────────────── */
      function reinitGlobals() {
        if (typeof lucide !== 'undefined') {
          try { lucide.createIcons(); } catch (e) {}
        }
      }

      /* ── update sidebar active state ────────────────────────────────── */
      function updateSidebarActive(url) {
        var pathname = (new URL(url, window.location.origin).pathname.replace(/\/$/, '')) || '/';
        document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
          var li = link.closest('.nav-item');
          link.classList.remove('active');
          if (li) li.classList.remove('active');
          try {
            var href = link.getAttribute('href');
            // Skip hash-only hrefs (collapse toggles like "#analyticsMenu"): they resolve
            // to the current page's path, which would mark every collapse menu active.
            // A parent menu is activated via its matching sub-link below instead.
            if (!href || href.charAt(0) === '#' || href.startsWith('javascript')) return;
            var linkPath = (new URL(link.href, window.location.origin).pathname.replace(/\/$/, '')) || '/';
            if (linkPath === pathname) {
              link.classList.add('active');
              if (li) li.classList.add('active');
              var parentCollapse = link.closest('.collapse');
              if (parentCollapse) {
                var parentNavItem = parentCollapse.closest('.nav-item');
                if (parentNavItem) parentNavItem.classList.add('active');
              }
            }
          } catch (e) {}
        });
      }

      /* ── core fetch + swap ──────────────────────────────────────────── */
      function navigateTo(url, pushState) {
        showProgress();

        fetch(url, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-AJAX-Navigation': 'true',
            'Accept': 'text/html'
          },
          credentials: 'same-origin'
        })
        .then(function (response) {
          if (!response.ok) { window.location.href = url; return null; }
          return response.text();
        })
        .then(function (html) {
          if (!html) return;

          var parser = new DOMParser();
          var doc = parser.parseFromString(html, 'text/html');

          // ── 1. Inject missing styles (<link> + inline <style>) from <head>
          injectMissingStyles(doc);

          // ── 2. Clean up accumulated DataTable ext.search functions and instances.
          if (typeof jQuery !== 'undefined' && jQuery.fn.dataTable) {
            jQuery.fn.dataTable.ext.search = jQuery.fn.dataTable.ext.search.filter(function (fn) {
              return !fn.__ajaxNavAdded;
            });
            
            // DESTROY all existing DataTables on the current page before DOM swap!
            // This prevents "Cannot reinitialise DataTable" errors and ensures clean slate
            // for pages that do not manually call destroy().
            try {
              jQuery.fn.dataTable.tables({ api: true }).destroy();
            } catch (e) {
              console.warn('[AJAXNav] Error destroying DataTables:', e);
            }
          }

          // ── 3. Collect scripts from the full fetched <body>
          //       plugin-scripts and custom-scripts stacks render
          //       in <body> OUTSIDE #page-content — must scan the whole body.
          var newSrcs = [];
          var inlineScripts = [];

          doc.body.querySelectorAll('script').forEach(function (script) {
            var src = script.getAttribute('src');
            if (src) {
              if (!document.querySelector('script[src="' + src + '"]')) {
                newSrcs.push(src);
              }
            } else {
              var code = script.textContent.trim();
              if (!code || isLayoutScript(code)) return;
              inlineScripts.push(code);
            }
          });

          // ── 3. Swap #page-content
          var newContent = doc.getElementById('page-content');
          if (!newContent) { window.location.href = url; return; }

          var currentContent = document.getElementById('page-content');
          currentContent.innerHTML = newContent.innerHTML;

          // ── 4. Load new src scripts sequentially, then run inline scripts.
          //       Sequential loading guarantees jQuery → DataTables order is
          //       preserved before the DataTable({...}) init code runs.
          loadSrcsSequentially(newSrcs.slice(), function () {
            // Patch $.fn.dataTable.ext.search.push so we can track which
            // functions were added by this page's AJAX-run scripts.
            if (typeof jQuery !== 'undefined' && jQuery.fn.dataTable) {
              var _origPush = jQuery.fn.dataTable.ext.search.push.bind(jQuery.fn.dataTable.ext.search);
              jQuery.fn.dataTable.ext.search.push = function (fn) {
                if (typeof fn === 'function') fn.__ajaxNavAdded = true;
                return _origPush(fn);
              };
            }
            inlineScripts.forEach(execInline);
            reinitGlobals();
          });

          // ── 5. Update URL + sidebar
          if (pushState !== false) {
            history.pushState({ ajaxNav: true, url: url }, '', url);
          }
          updateSidebarActive(url);
          window.scrollTo(0, 0);
          finishProgress();
        })
        .catch(function () {
          window.location.href = url;
        });
      }

      /* ── intercept sidebar nav-link clicks ──────────────────────────── */
      document.addEventListener('click', function (e) {
        var link = e.target.closest('.sidebar .nav-link');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript')) return;
        if (link.getAttribute('target') === '_blank') return;
        if (link.hasAttribute('data-bs-toggle')) return;
        try {
          var target = new URL(href, window.location.origin);
          if (target.origin !== window.location.origin) return;
        } catch (e) { return; }
        e.preventDefault();
        navigateTo(href);
      });

      /* ── handle browser back / forward ─────────────────────────────── */
      window.addEventListener('popstate', function (e) {
        if (e.state && e.state.ajaxNav) {
          navigateTo(e.state.url, false);
        } else {
          window.location.reload();
        }
      });

      history.replaceState({ ajaxNav: true, url: window.location.href }, '', window.location.href);

    })();
    </script>
</body>
</html>