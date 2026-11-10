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
    
    <div class="page-wrapper full-page">
      <div class="page-content container-xxl d-flex align-items-center justify-content-center">
        @yield('content')
      </div>
    </div>
  </div>

    <!-- base js -->
    @vite(['resources/js/app.js'])
    <script src="{{ asset('build/plugins/lucide/lucide.min.js') }}"></script>
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
          
          // Trigger show animation
          setTimeout(() => {
            toast.classList.add('show');
          }, 10);
          
          // Auto hide after duration
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
        
        success: function(message, duration = 5000) {
          return this.show(message, 'success', duration);
        },
        
        error: function(message, duration = 5000) {
          return this.show(message, 'error', duration);
        },
        
        warning: function(message, duration = 5000) {
          return this.show(message, 'warning', duration);
        },
        
        info: function(message, duration = 5000) {
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
</body>
</html>