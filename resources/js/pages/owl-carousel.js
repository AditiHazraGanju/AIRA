// npm package: owl.carousel
// github link: https://github.com/OwlCarousel2/OwlCarousel2

'use strict';

(function () {

  const owlBasic = $('.owl-basic');
  if (owlBasic.length) {
    owlBasic.owlCarousel({
      loop:true,
      margin:10,
      rtl: checkRTL(),
      nav:false,
      responsive:{
        0:{
          items:2
        },
        600:{
          items:3
        },
        1000:{
          items:4
        }
      }
    });
  }

  const owlAutoPlay = $('.owl-auto-play');
  if (owlAutoPlay.length) {
    owlAutoPlay.owlCarousel({
      items:4,
      loop:true,
      margin:10,
      rtl: checkRTL(),
      autoplay:true,
      autoplayTimeout:1000,
      autoplayHoverPause:true,
      responsive:{
        0:{
          items:2
        },
        600:{
          items:3
        },
        1000:{
          items:4
        }
    }
    });
  }

  const owlFadeout = $('.owl-fadeout');
  if (owlFadeout.length) {
    owlFadeout.owlCarousel({
      animateOut: 'fadeOut',
      rtl: checkRTL(),
      items:1,
      margin:30,
      stagePadding:30,
      smartSpeed:450
    });
  }

  const owlAnimateCss = $('.owl-animate-css');
  if (owlAnimateCss.length) {
    owlAnimateCss.owlCarousel({
      animateOut: 'animate__animated animate__slideOutDown',
      animateIn: 'animate__animated animate__flipInX',
      items:1,
      rtl: checkRTL(),
      margin:30,
      stagePadding:30,
      smartSpeed:450
    });
  }

  const owlMouseWheel = $('.owl-mouse-wheel');
  if (owlMouseWheel.length) {
    owlMouseWheel.owlCarousel({
      loop:true,
      nav:false,
      margin:10,
      rtl: checkRTL(),
      responsive:{
        0:{
          items:2
        },
        600:{
          items:3
        },            
        960:{
          items:3
        },
        1200:{
          items:4
        }
      }
    });
    
    owlMouseWheel.on('mousewheel', '.owl-stage', function (e) {
      if (e.deltaY>0) {
        owlMouseWheel.trigger('next.owl');
      } else {
        owlMouseWheel.trigger('prev.owl');
      }
      e.preventDefault();
    });
  }

  // Check if the document is in RTL direction.
  function checkRTL() {
    if (getComputedStyle(document.documentElement).direction === 'rtl' || document.documentElement.getAttribute('dir') === 'rtl') {
      return true;
    }
    return false;
  }

})();