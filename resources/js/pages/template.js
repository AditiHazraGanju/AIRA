'use strict';

(function () {

  // Root css-variable value
  const getCssVariableValue = function(variableName) {
    let hex = getComputedStyle(document.documentElement).getPropertyValue(variableName);
    if ( hex && hex.length > 0 ) {
      hex = hex.trim();
    }
    return hex;
  }

  // Global variables
  window.config = {
    colors: {
      primary        : getCssVariableValue('--bs-primary'),
      secondary      : getCssVariableValue('--bs-secondary'),
      success        : getCssVariableValue('--bs-success'),
      info           : getCssVariableValue('--bs-info'),
      warning        : getCssVariableValue('--bs-warning'),
      danger         : getCssVariableValue('--bs-danger'),
      light          : getCssVariableValue('--bs-light'),
      dark           : getCssVariableValue('--bs-dark'),
      gridBorder     : "rgba(77, 138, 240, .15)",
    },
    fontFamily       : "'Lora', serif !important"
  }



  const body = document.body;
  const sidebar = document.querySelector('.sidebar');
  const sidebarBody = document.querySelector('.sidebar .sidebar-body');


  // Initializing bootstrap tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })



  // Initializing bootstrap popover
  const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl)
  })



  // Applying perfect-scrollbar 
  if (document.querySelector('.sidebar .sidebar-body')) {
    const sidebarBodyScroll = new PerfectScrollbar('.sidebar-body');
  }



  // Sidebar toggle to sidebar-folded
  const sidebarTogglers = document.querySelectorAll('.sidebar-toggler');
  // there are two sidebar togglers. 
  // 1: on sidebar - for min-width 992px (laptop, desktop) 
  // 2: on navbar - for max-width 991px (mobile phone, tablet)
  if (sidebarTogglers.length) {

    sidebarTogglers.forEach( toggler => {

      toggler.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector('.sidebar .sidebar-toggler').classList.toggle('active');
        if (window.matchMedia('(min-width: 992px)').matches) {
          body.classList.toggle('sidebar-folded');
        } else if (window.matchMedia('(max-width: 991px)').matches) {
          body.classList.toggle('sidebar-open');
        }
      });

    });

    // To avoid layout issues, remove body and toggler classes on window resize.
    window.addEventListener('resize', function(event) {
      body.classList.remove('sidebar-folded', 'sidebar-open');
      document.querySelector('.sidebar .sidebar-toggler').classList.remove('active');
    }, true);

  }



  //  sidebar-folded on min-width:992px and max-width: 1199px (in lg only not in xl)
  // Warning!!! this results apex chart width issue
  // 
  // const desktopMedium = window.matchMedia('(min-width:992px) and (max-width: 1199px)');
  // function iconSidebar() {
  //   if (desktopMedium.matches) {
  //     body.classList.add('sidebar-folded');
  //   } else {
  //     body.classList.remove('sidebar-folded');
  //   }
  // }
  // window.addEventListener('resize', iconSidebar)
  // iconSidebar();



  // Open & fold sidebar-folded on mouse enter and leave
  if (sidebarBody) {
    sidebarBody.addEventListener('mouseenter', function () {
      if (body.classList.contains('sidebar-folded')) {
        body.classList.add('open-sidebar-folded');
      }
    });

    sidebarBody.addEventListener('mouseleave', function () {
      if (body.classList.contains('sidebar-folded')) {
        body.classList.remove('open-sidebar-folded');
      }
    });
  }



  // Close sidebar on click outside in phone/tablet
  const mainWrapper = document.querySelector('.main-wrapper');
  if (sidebar) {
    document.addEventListener('touchstart', function(e) {
      if (e.target === mainWrapper && body.classList.contains('sidebar-open')) {
        body.classList.remove('sidebar-open');
        document.querySelector('.sidebar .sidebar-toggler').classList.remove('active');
      }
    });
  }



  // Prevent body scrolling while sidebar scroll
  // 
  // if (sidebarBody) {
  //   sidebarBody.addEventListener('mouseover', function () {
  //     body.classList.add('overflow-hidden');
  //   });
  //   sidebarBody.addEventListener('mouseout', function () {
  //     body.classList.remove('overflow-hidden');
  //   });
  // }




  // Setup clipboard.js plugin (https://github.com/zenorocha/clipboard.js)
  const clipboardButtons = document.querySelectorAll('.btn-clipboard');

  if (clipboardButtons.length) {

    clipboardButtons.forEach( btn => {
      btn.addEventListener('mouseover', function() {
        this.innerText = 'Copy to clipboard';
      });
      btn.addEventListener('mouseout', function() {
        this.innerText = 'Copy';
      });
    });

    const clipboard = new ClipboardJS('.btn-clipboard');

    clipboard.on('success', function(e) {
      e.trigger.innerHTML = 'Copied';
      setTimeout(function() {
        e.trigger.innerHTML = 'Copy';
        e.clearSelection();
      },800)
    });
  }



  // Enable lucide icons with SVG markup
  lucide.createIcons();

})();