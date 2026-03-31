/* ─── Theme Toggle (runs immediately, before DOM ready) ─── */
(function() {
  var html = document.documentElement;
  var stored = localStorage.getItem('theme');

  if (stored) {
    html.setAttribute('data-theme', stored);
  } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    html.setAttribute('data-theme', 'dark');
  }
})();

/* ─── Everything else waits for DOM ─── */
document.addEventListener('DOMContentLoaded', function() {

  /* ─── Theme Toggle Click ─── */
  var html = document.documentElement;
  var toggle = document.getElementById('themeToggle');
  if (toggle) {
    toggle.addEventListener('click', function() {
      var current = html.getAttribute('data-theme');
      var next = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
    });
  }

  /* ─── Nav scroll effect ─── */
  var nav = document.getElementById('nav');
  if (nav) {
    var ticking = false;
    window.addEventListener('scroll', function() {
      if (!ticking) {
        requestAnimationFrame(function() {
          nav.classList.toggle('scrolled', window.scrollY > 40);
          ticking = false;
        });
        ticking = true;
      }
    });
  }

  /* ─── Mobile menu ─── */
  var navHamburger = document.getElementById('navHamburger');
  var navLinks = document.getElementById('navLinks');

  if (navHamburger && navLinks) {
    navHamburger.addEventListener('click', function() {
      navHamburger.classList.toggle('active');
      navLinks.classList.toggle('open');
    });
  }

  window.closeMenu = function() {
    if (navHamburger && navLinks) {
      navHamburger.classList.remove('active');
      navLinks.classList.remove('open');
    }
  };

  /* ─── Scroll Reveal Animations ─── */
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReducedMotion) {
    document.querySelectorAll('.reveal').forEach(function(el) {
      el.classList.add('visible');
    });
  } else {
    /* Make hero elements visible immediately — no scroll needed */
    document.querySelectorAll('.hero .reveal, .projekt-hero .reveal').forEach(function(el) {
      el.classList.add('visible');
    });

    /* IntersectionObserver for the rest */
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.01, rootMargin: '0px 0px 0px 0px' });

      document.querySelectorAll('.reveal:not(.visible)').forEach(function(el) {
        observer.observe(el);
      });
    } else {
      /* Fallback: no IntersectionObserver (old browsers) — show all */
      document.querySelectorAll('.reveal').forEach(function(el) {
        el.classList.add('visible');
      });
    }

    /* Safety net: force all visible after 3s no matter what */
    setTimeout(function() {
      document.querySelectorAll('.reveal:not(.visible)').forEach(function(el) {
        el.classList.add('visible');
      });
    }, 3000);
  }

  /* ─── Capacity bar fill animation ─── */
  var capacityFills = document.querySelectorAll('.capacity-fill');
  if (capacityFills.length > 0 && 'IntersectionObserver' in window) {
    var capObserver = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('animated');
          capObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    capacityFills.forEach(function(el) {
      capObserver.observe(el);
    });
  }

});
