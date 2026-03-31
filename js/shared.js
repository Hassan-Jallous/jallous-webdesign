/* ─── Theme Toggle ─── */
(function() {
  var html = document.documentElement;
  var toggle = document.getElementById('themeToggle');
  var stored = localStorage.getItem('theme');

  if (stored) {
    html.setAttribute('data-theme', stored);
  } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    html.setAttribute('data-theme', 'dark');
  }

  if (toggle) {
    toggle.addEventListener('click', function() {
      var current = html.getAttribute('data-theme');
      var next = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
    });
  }
})();

/* ─── Nav scroll effect ─── */
(function() {
  var nav = document.getElementById('nav');
  if (!nav) return;
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
})();

/* ─── Mobile menu ─── */
(function() {
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
})();

/* ─── Intersection Observer for scroll reveals ─── */
(function() {
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReducedMotion) {
    document.querySelectorAll('.reveal').forEach(function(el) {
      el.classList.add('visible');
    });
    return;
  }

  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.05, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('.reveal').forEach(function(el) {
    observer.observe(el);
  });

  /* Capacity bar fill animation */
  var capacityFills = document.querySelectorAll('.capacity-fill');
  if (capacityFills.length > 0) {
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
})();
