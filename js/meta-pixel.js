/* ═══════════════════════════════════════════════════════════════
   META PIXEL + CONVERSIONS API — Jallous Webdesign
   Pixel ID: 2690088668021853

   Script is always loaded via <script> tag.
   Tracking only activates after cookie consent.
   cookie-consent.js calls window._startTracking() on accept.
   ═══════════════════════════════════════════════════════════════ */

(function() {
  'use strict';

  /* ─── HELPERS ─── */
  var CAPI_ENDPOINT = '/api/track.php';
  var pageName = document.title;
  var pageType = (function() {
    var path = location.pathname;
    if (path === '/' || path.endsWith('index.html')) return 'homepage';
    if (path.indexOf('/projekte/') !== -1) return 'project';
    if (path.indexOf('impressum') !== -1 || path.indexOf('datenschutz') !== -1 || path.indexOf('agb') !== -1) return 'legal';
    return 'other';
  })();

  var tabVisible = true;
  var trackingActive = false;

  document.addEventListener('visibilitychange', function() {
    tabVisible = !document.hidden;
  });

  function genEventId() {
    return Date.now().toString(36) + '-' + Math.random().toString(36).substr(2, 9);
  }

  function getFbc() {
    var m = document.cookie.match(/(^|;)\s*_fbc=([^;]+)/);
    return m ? m[2] : null;
  }
  function getFbp() {
    var m = document.cookie.match(/(^|;)\s*_fbp=([^;]+)/);
    return m ? m[2] : null;
  }

  // Guarded: only sends if tracking is active
  function sendCAPI(eventName, customData, userData) {
    if (!trackingActive) return;
    var eventId = genEventId();

    var pixelParams = Object.assign({}, customData || {});
    pixelParams.eventID = eventId;
    if (eventName === 'PageView' || eventName === 'ViewContent' || eventName === 'Lead' || eventName === 'Contact') {
      fbq('track', eventName, pixelParams, { eventID: eventId });
    } else {
      fbq('trackCustom', eventName, pixelParams, { eventID: eventId });
    }

    var payload = {
      events: [{
        event_name: eventName,
        event_id: eventId,
        source_url: location.href,
        fbc: getFbc(),
        fbp: getFbp(),
        custom_data: customData || {},
        user_data: userData || {}
      }]
    };

    if (navigator.sendBeacon) {
      navigator.sendBeacon(CAPI_ENDPOINT, JSON.stringify(payload));
    } else {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', CAPI_ENDPOINT, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.send(JSON.stringify(payload));
    }
    return eventId;
  }

  function firePixelOnly(eventName, params) {
    if (!trackingActive) return;
    fbq('trackCustom', eventName, params);
  }


  /* ─── SECTION TRACKING STATE ─── */
  var sections = [
    { id: 'nav',        name: 'Navigation' },
    { id: 'system',     name: 'So arbeite ich' },
    { id: 'ergebnisse', name: 'Ergebnisse' },
    { id: 'ueber-mich', name: 'Ueber mich' },
    { id: 'faq',        name: 'FAQ' },
    { id: 'bewerbung',  name: 'Bewerbungsformular' }
  ];
  var sectionSeen = {};
  var sectionTimers = {};
  var sectionSeconds = {};
  sections.forEach(function(s) { sectionTimers[s.id] = null; sectionSeconds[s.id] = 0; });

  /* ─── TIME TRACKING (always runs, sends only if active) ─── */
  var pageStartTime = Date.now();
  var activeSeconds = 0;
  var maxScroll = 0;
  var scrollMilestones = {};
  setInterval(function() { if (tabVisible) activeSeconds++; }, 1000);

  /* ─── FORM STATE ─── */
  var stepNames = { 1:'Name', 2:'Branche', 3:'Website', 4:'Problem', 5:'Umsatz & Kunden', 6:'Kontaktdaten' };
  var stepsReached = {};
  var formStarted = false;

  /* ─── EXIT DATA ─── */
  var exitSent = false;
  function sendExitData() {
    if (exitSent || !trackingActive) return;
    exitSent = true;

    // Engagement score
    var score = 0;
    score += Math.min(30, Math.floor(activeSeconds / 5));
    score += Math.floor(maxScroll / 4);
    var viewed = Object.keys(sectionSeen).length;
    score += Math.round((viewed / Math.max(sections.length, 1)) * 25);
    if (pageType === 'homepage') {
      var fs = Object.keys(stepsReached).filter(function(k) { return k !== 'started'; }).length;
      score += Math.round((fs / 6) * 20);
    } else if (pageType === 'project') { score += 10; }
    score = Math.min(100, score);
    var level = score >= 70 ? 'high' : (score >= 40 ? 'medium' : 'low');

    sendCAPI('EngagementScore', {
      score: score, level: level, seconds: activeSeconds,
      max_scroll: maxScroll, sections_viewed: viewed,
      page: pageName, page_type: pageType
    });

    sendCAPI('TimeOnPage', {
      seconds: activeSeconds,
      seconds_total: Math.round((Date.now() - pageStartTime) / 1000),
      page: pageName, page_type: pageType, max_scroll_percent: maxScroll
    });

    sections.forEach(function(s) {
      if (sectionTimers[s.id]) { clearInterval(sectionTimers[s.id]); sectionTimers[s.id] = null; }
      if (sectionSeconds[s.id] > 0) {
        firePixelOnly('SectionTime', {
          section_name: s.name, section_id: s.id,
          seconds_visible: sectionSeconds[s.id], page: pageName
        });
      }
    });
  }

  document.addEventListener('visibilitychange', function() { if (document.hidden) sendExitData(); });
  window.addEventListener('pagehide', sendExitData);


  /* ═══════════════════════════════════════════════════════════
     MAIN INIT — called by cookie-consent.js after consent
     or immediately if consent already exists
     ═══════════════════════════════════════════════════════════ */
  function initTracking() {
    if (trackingActive) return;
    trackingActive = true;

    // Load Meta Pixel SDK
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){
    n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;
    s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
    (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');

    fbq('init', '2690088668021853');

    // UTM tracking
    var utmParams = {};
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function(p) {
      var m = location.search.match(new RegExp('[?&]' + p + '=([^&]+)'));
      if (m) utmParams[p] = decodeURIComponent(m[1]);
    });
    if (Object.keys(utmParams).length > 0) {
      sessionStorage.setItem('jw_utm', JSON.stringify(utmParams));
    } else {
      var stored = sessionStorage.getItem('jw_utm');
      if (stored) try { utmParams = JSON.parse(stored); } catch(e) {}
    }
    if (Object.keys(utmParams).length > 0) {
      var _origSend = sendCAPI;
      sendCAPI = function(en, cd, ud) {
        return _origSend(en, Object.assign({}, cd || {}, utmParams), ud);
      };
    }

    // PageView
    sendCAPI('PageView', { page: pageName, page_type: pageType });

    // ViewContent (project pages)
    if (pageType === 'project') {
      var h1 = document.querySelector('h1');
      sendCAPI('ViewContent', {
        content_name: h1 ? h1.textContent.trim() : pageName,
        content_type: 'project_case_study', content_category: 'portfolio'
      });
    }

    // CTA clicks
    document.addEventListener('click', function(e) {
      var link = e.target.closest('a[href*="#bewerbung"], .nav-cta, .nav-cta-mobile, .footer-cta, .btn-primary');
      if (link) {
        sendCAPI('Contact', { content_name: link.textContent.trim(), content_category: 'cta_click', page: pageName });
      }
    });

    // Form funnel hooks
    if (pageType === 'homepage') {
      var formSection = document.getElementById('bewerbung');
      if (formSection) {
        var fo = new IntersectionObserver(function(entries) {
          if (entries[0].isIntersecting && !formStarted) {
            formStarted = true;
            sendCAPI('FormVisible', { page: pageName });
          }
        }, { threshold: 0.3 });
        fo.observe(formSection);
      }

      var origNext = window.nextStep;
      if (typeof origNext === 'function') {
        window.nextStep = function(step) {
          var done = step - 1;
          if (!stepsReached[done]) {
            stepsReached[done] = true;
            sendCAPI('FormStep', {
              step_number: done, step_name: stepNames[done] || 'Step ' + done,
              steps_total: 6, progress_percent: Math.round((done / 6) * 100)
            });
          }
          if (done === 1 && !stepsReached['started']) {
            stepsReached['started'] = true;
            sendCAPI('FormStart', { page: pageName });
          }
          return origNext.apply(this, arguments);
        };
      }

      var origSubmit = window.submitForm;
      if (typeof origSubmit === 'function') {
        window.submitForm = function() {
          var em = document.getElementById('formEmail') ? document.getElementById('formEmail').value.trim() : '';
          var ph = document.getElementById('formPhone') ? document.getElementById('formPhone').value.trim() : '';
          var fn = document.getElementById('formName') ? document.getElementById('formName').value.trim() : '';
          if (!stepsReached[6]) {
            stepsReached[6] = true;
            sendCAPI('FormStep', { step_number: 6, step_name: stepNames[6], steps_total: 6, progress_percent: 100 });
          }
          sendCAPI('Lead', { content_name: 'Erstgespraech Bewerbung', content_category: 'form_submission', page: pageName },
            { em: em, ph: ph, fn: fn });
          return origSubmit.apply(this, arguments);
        };
      }
    }

    // Section observers
    if ('IntersectionObserver' in window) {
      var so = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          var id = entry.target.id;
          var sd = sections.find(function(s) { return s.id === id; });
          if (!sd) return;
          if (entry.isIntersecting) {
            if (!sectionSeen[id]) { sectionSeen[id] = true; firePixelOnly('SectionView', { section_name: sd.name, section_id: id, page: pageName, page_type: pageType }); }
            if (!sectionTimers[id]) { sectionTimers[id] = setInterval(function() { if (tabVisible) sectionSeconds[id]++; }, 1000); }
          } else {
            if (sectionTimers[id]) { clearInterval(sectionTimers[id]); sectionTimers[id] = null; }
          }
        });
      }, { threshold: 0.5 });

      sections.forEach(function(s) { var el = document.getElementById(s.id); if (el) so.observe(el); });
      var hero = document.querySelector('.hero');
      if (hero) { hero.id = hero.id || '_hero'; sections.push({ id: hero.id, name: 'Hero' }); sectionTimers[hero.id] = null; sectionSeconds[hero.id] = 0; so.observe(hero); }
    }

    // Scroll depth
    window.addEventListener('scroll', function() {
      var st = window.pageYOffset || document.documentElement.scrollTop;
      var dh = document.documentElement.scrollHeight - window.innerHeight;
      if (dh <= 0) return;
      var pct = Math.round((st / dh) * 100);
      if (pct > maxScroll) maxScroll = pct;
      [25, 50, 75, 90, 100].forEach(function(m) {
        if (pct >= m && !scrollMilestones[m]) { scrollMilestones[m] = true; firePixelOnly('ScrollDepth', { depth_percent: m, page: pageName, page_type: pageType }); }
      });
    }, { passive: true });

    // Project page CTAs
    if (pageType === 'project') {
      document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href*="index.html#bewerbung"], a[href*="/#bewerbung"]');
        if (link) { sendCAPI('ProjectCTA', { project_name: document.querySelector('h1') ? document.querySelector('h1').textContent.trim() : pageName, cta_text: link.textContent.trim() }); }
      });
      var ps = document.querySelectorAll('section');
      if (ps.length > 0 && 'IntersectionObserver' in window) {
        var po = new IntersectionObserver(function(entries) {
          entries.forEach(function(entry) {
            if (entry.isIntersecting) {
              var cn = entry.target.className.split(' ')[0];
              if (!sectionSeen[cn]) { sectionSeen[cn] = true; firePixelOnly('SectionView', { section_name: cn, page: pageName, page_type: 'project' }); }
            }
          });
        }, { threshold: 0.3 });
        ps.forEach(function(s) { po.observe(s); });
      }
    }
  }

  // Expose for cookie-consent.js
  window._startTracking = initTracking;

  // Auto-init if consent already exists
  try {
    if (localStorage.getItem('cookie_consent') === 'accepted') {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTracking);
      } else {
        initTracking();
      }
    }
  } catch(e) {}

})();
