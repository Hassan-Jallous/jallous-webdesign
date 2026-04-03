/* ═══════════════════════════════════════════════════════════════
   COOKIE CONSENT — Fullscreen overlay popup
   Big accept button, small reject text.
   Calls window._startTracking() (from tracking.js) on accept.
   ═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var STORAGE_KEY = 'cookie_consent';

  function getConsent() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function setConsent(value) {
    try { localStorage.setItem(STORAGE_KEY, value); } catch (e) {}
  }

  function removeOverlay() {
    var overlay = document.getElementById('cookieOverlay');
    if (overlay) {
      overlay.style.opacity = '0';
      overlay.style.transition = 'opacity 0.3s ease';
      setTimeout(function () { overlay.remove(); }, 350);
    }
  }

  function showOverlay() {
    if (document.getElementById('cookieOverlay')) return;

    var overlay = document.createElement('div');
    overlay.id = 'cookieOverlay';
    overlay.className = 'cookie-overlay';

    overlay.innerHTML =
      '<div class="cookie-box">' +
        '<span class="cookie-icon">\uD83C\uDF6A</span>' +
        '<div class="cookie-title">Diese Website verwendet Cookies</div>' +
        '<div class="cookie-desc">Wir nutzen Cookies, um dir die beste Erfahrung zu bieten und unsere Website zu verbessern. <a href="/datenschutz.html">Datenschutz</a></div>' +
        '<button class="cookie-reject" id="cookieReject">Ohne Cookies fortfahren</button>' +
        '<button class="cookie-accept" id="cookieAccept">Akzeptieren & weiter</button>' +
      '</div>';

    document.body.appendChild(overlay);

    document.getElementById('cookieAccept').addEventListener('click', function () {
      setConsent('accepted');
      if (typeof window._startTracking === 'function') window._startTracking();
      removeOverlay();
    });

    document.getElementById('cookieReject').addEventListener('click', function () {
      setConsent('rejected');
      removeOverlay();
    });
  }

  window.resetCookieConsent = function () {
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
    showOverlay();
  };

  // Init — skip overlay on legal pages (must be readable before consent)
  var path = location.pathname;
  var isLegalPage = path.indexOf('datenschutz') !== -1 || path.indexOf('impressum') !== -1 || path.indexOf('agb') !== -1;

  var consent = getConsent();
  if (!consent && !isLegalPage) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', showOverlay);
    } else {
      showOverlay();
    }
  }

})();
