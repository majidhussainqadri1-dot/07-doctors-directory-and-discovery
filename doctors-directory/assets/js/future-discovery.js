(function () {
  'use strict';
  var cfg = window.dddFutureDiscovery || {};
  function msg(key, fallback) { return cfg.messages && cfg.messages[key] ? cfg.messages[key] : fallback; }
  var root = document.querySelector('[data-ddd-future]');
  if (!root || !cfg.base) return;

  var form = root.querySelector('[data-ddd-future-form]');
  var status = root.querySelector('[data-ddd-future-status]');
  var results = root.querySelector('[data-ddd-future-results]');
  var map = root.querySelector('[data-ddd-future-map]');
  var recovery = root.querySelector('[data-ddd-recovery]');
  var compareBox = root.querySelector('[data-ddd-compare]');
  var compareOutput = root.querySelector('[data-ddd-compare-output]');
  var loadMore = root.querySelector('[data-ddd-load-more]');
  var selected = new Set();
  var renderedIds = new Set();
  var accumulatedMapPoints = [];
  var nextCursor = '';

  var tz = form.querySelector('[name="timezone"]');
  try { tz.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; } catch (e) { tz.value = 'UTC'; }

  function endpoint(path, params) {
    var url = new URL(cfg.base + path, window.location.origin);
    Object.keys(params || {}).forEach(function (key) {
      var value = params[key];
      if (value !== '' && value !== null && typeof value !== 'undefined') url.searchParams.set(key, value);
    });
    return url.toString();
  }

  function fetchJson(url, options) {
    options = options || {};
    options.headers = Object.assign({'Accept': 'application/json'}, options.headers || {});
    if (cfg.nonce) options.headers['X-WP-Nonce'] = cfg.nonce;
    return fetch(url, options).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok) throw new Error(body && body.message ? body.message : 'Request failed');
        return body;
      });
    });
  }

  function postJson(path, params) {
    return fetchJson(cfg.base + path, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(params || {})
    });
  }

  function mutationKey(scope) {
    var token = '';
    try {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') token = window.crypto.randomUUID();
      else if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
        var bytes = new Uint32Array(4); window.crypto.getRandomValues(bytes); token = Array.prototype.map.call(bytes, function (n) { return n.toString(16); }).join('-');
      }
    } catch (e) { token = ''; }
    if (!token) token = String(Date.now()) + '-' + String(Math.random()).slice(2);
    return ('ddd-' + scope + '-' + token).replace(/[^A-Za-z0-9._:-]/g, '-').slice(0, 128);
  }

  function mutationJson(path, params, scope, method) {
    return fetchJson(cfg.base + path, {
      method: method || 'POST',
      headers: {'Content-Type': 'application/json', 'Idempotency-Key': mutationKey(scope)},
      body: typeof params === 'undefined' ? undefined : JSON.stringify(params || {})
    });
  }

  function safeSameOriginHref(value) {
    if (!value) return '';
    try {
      var u = new URL(String(value), window.location.origin);
      return u.origin === window.location.origin ? u.href : '';
    } catch (e) { return ''; }
  }

  function prefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function paramsFromForm() {
    var fd = new FormData(form), out = {}, weights = {};
    fd.forEach(function (value, key) {
      if (String(value).trim() === '') return;
      if (key.indexOf('weight_') === 0) { var n = Number(value); if (n > 0) weights[key.replace('weight_', '')] = Math.max(0, Math.min(10, n)); return; }
      out[key] = String(value).trim();
    });
    if (Object.keys(weights).length) out.weights = JSON.stringify(weights);
    return out;
  }

  function text(el, value) { el.textContent = value == null ? '' : String(value); return el; }
  function make(tag, cls, value) { var el = document.createElement(tag); if (cls) el.className = cls; if (typeof value !== 'undefined') text(el, value); return el; }

  function renderDoctor(d) {
    var card = make('article', 'ddd-future-card');
    var top = make('div', 'ddd-future-card__top');
    var check = document.createElement('input'); check.type = 'checkbox'; check.setAttribute('aria-label', 'Select ' + (d.display_name || 'doctor') + ' for comparison'); check.value = d.public_id;
    check.addEventListener('change', function () {
      if (check.checked) {
        if (selected.size >= 4) { check.checked = false; status.textContent = msg('compareLimit', 'Select two to four doctors.'); return; }
        selected.add(check.value);
      } else selected.delete(check.value);
      compareBox.hidden = selected.size < 2;
    });
    top.appendChild(check);
    var titleWrap = make('div', 'ddd-future-card__title');
    titleWrap.appendChild(make('h3', '', d.display_name || 'Verified doctor'));
    if (d.professional_title) titleWrap.appendChild(make('p', 'ddd-muted', d.professional_title));
    top.appendChild(titleWrap); card.appendChild(top);

    var facts = make('dl', 'ddd-future-card__facts');
    [[msg('specialty', 'Specialty'), d.specialty], [msg('location', 'Location'), [d.city, d.country].filter(Boolean).join(', ')], [msg('languages', 'Languages'), (d.languages || []).join(', ')], [msg('experience', 'Experience'), d.experience_years ? d.experience_years + ' ' + msg('years', 'years') : ''], [msg('distance', 'Distance'), typeof d.distance_km === 'number' ? d.distance_km + ' ' + msg('kilometers', 'km') : '']].forEach(function (pair) {
      if (!pair[1]) return; facts.appendChild(make('dt', '', pair[0])); facts.appendChild(make('dd', '', pair[1]));
    });
    card.appendChild(facts);

    if (d.local_availability && d.local_availability.next_local) {
      var dt = new Date(d.local_availability.next_local); var label = isNaN(dt.getTime()) ? d.local_availability.next_local : dt.toLocaleString();
      card.appendChild(make('p', 'ddd-future-card__availability', msg('nextAvailability', 'Next published availability:') + ' ' + label));
    }
    if (Array.isArray(d.why_this_doctor) && d.why_this_doctor.length) {
      var why = make('div', 'ddd-future-card__why'); why.appendChild(make('strong', '', msg('whyResult', 'Why this result'))); var ul = document.createElement('ul'); d.why_this_doctor.forEach(function (w) { ul.appendChild(make('li', '', w)); }); why.appendChild(ul); card.appendChild(why);
    }
    if (d.freshness && (d.freshness.verification || d.freshness.profile || d.freshness.availability)) {
      var fr = make('p', 'ddd-muted', msg('freshness', 'Freshness:') + ' ');
      var parts=[]; if(d.freshness.verification) parts.push('verification '+d.freshness.verification); if(d.freshness.profile) parts.push('profile '+d.freshness.profile); if(d.freshness.availability) parts.push('availability '+d.freshness.availability); fr.appendChild(document.createTextNode(parts.join(' · '))); card.appendChild(fr);
    }
    var kc = d.discovery_extensions && d.discovery_extensions.knowledge && d.discovery_extensions.knowledge.knowledge_counts;
    if (kc && typeof kc === 'object') { var vals=[]; Object.keys(kc).slice(0,6).forEach(function(k){ vals.push(k+': '+kc[k]); }); if(vals.length) card.appendChild(make('p','ddd-muted',msg('knowledge', 'Public knowledge footprint') + ' — '+vals.join(' · '))); }
    var actions = make('div', 'ddd-future-card__actions');
    var profileHref = safeSameOriginHref(d.profile_url); if (profileHref) { var a = make('a', 'button', msg('profile', 'Profile')); a.href = profileHref; actions.appendChild(a); }
    var appointmentHref = safeSameOriginHref(d.appointment_url); if (appointmentHref) { var b = make('a', 'button button-primary', msg('appointment', 'Appointment')); b.href = appointmentHref; actions.appendChild(b); }
    if (cfg.loggedIn) { var sl = make('button','button',msg('addShortlist', 'Add to My shortlist')); sl.type='button'; sl.addEventListener('click', function(){ addToShortlist(d.public_id); }); actions.appendChild(sl); }
    card.appendChild(actions);
    return card;
  }

  function renderMap(points) {
    map.innerHTML = '';
    if (!Array.isArray(points) || !points.length) { map.appendChild(make('p', 'ddd-muted', msg('noMap', 'Public clinic coordinates are not available for these results.'))); return; }
    var plane = make('div', 'ddd-future-mapplane');
    points.forEach(function (p) {
      var pin = document.createElement('button'); pin.type = 'button'; pin.className = 'ddd-future-pin'; pin.title = p.name || 'Doctor'; pin.setAttribute('aria-label', p.name || 'Doctor');
      pin.style.left = Math.max(1, Math.min(99, ((Number(p.lng) + 180) / 360) * 100)) + '%';
      pin.style.top = Math.max(1, Math.min(99, ((90 - Number(p.lat)) / 180) * 100)) + '%';
      pin.addEventListener('click', function () { var card = results.querySelector('[data-public-id="' + p.public_id + '"]'); if (card) { card.scrollIntoView({behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center'}); card.focus({preventScroll: true}); } });
      plane.appendChild(pin);
    });
    map.appendChild(plane);
    map.appendChild(make('p', 'ddd-future-mapnote', msg('mapNote', 'Approximate world map using public clinic coordinates; exact user location is not stored by this interface.')));
  }

  function renderRecovery(items) {
    recovery.innerHTML = '';
    if (!Array.isArray(items) || !items.length) { recovery.hidden = true; return; }
    recovery.hidden = false; recovery.appendChild(make('h3', '', msg('tryBroader', 'Try a broader search')));
    items.forEach(function (r) {
      var btn = make('button', 'button', r.label || r.action); btn.type = 'button';
      btn.addEventListener('click', function () {
        if (r.action === 'remove_city') form.querySelector('[name="city"]').value = '';
        if (r.action === 'allow_online') form.querySelector('[name="mode"]').value = 'online';
        if (r.action === 'relax_availability') form.querySelector('[name="availability_days"]').value = '0';
        if (r.action === 'widen_radius') form.querySelector('[name="radius_km"]').value = Math.min(250, Number(form.querySelector('[name="radius_km"]').value || 25) * 2);
        submit();
      });
      recovery.appendChild(btn);
    });
  }

  function render(data, append) {
    if (!append) { results.innerHTML = ''; selected.clear(); compareBox.hidden = true; compareOutput.innerHTML = ''; renderedIds.clear(); accumulatedMapPoints = []; }
    if (data.safety_diversion) {
      var alert = make('div', 'ddd-future-alert', data.safety_diversion.message || msg('urgentCare', 'Seek urgent local care.')); alert.setAttribute('role', 'alert'); results.appendChild(alert); map.innerHTML = ''; nextCursor = ''; if (loadMore) loadMore.hidden = true; return;
    }
    var items = data.items || [], added = 0;
    items.forEach(function (d) {
      if (!d.public_id || renderedIds.has(d.public_id)) return;
      renderedIds.add(d.public_id); added += 1; var card = renderDoctor(d); card.dataset.publicId = d.public_id; card.tabIndex = -1; results.appendChild(card);
    });
    (data.map_points || []).forEach(function (p) { if (p.public_id && !accumulatedMapPoints.some(function(x){ return x.public_id === p.public_id; })) accumulatedMapPoints.push(p); });
    nextCursor = data.next_cursor || '';
    if (loadMore) loadMore.hidden = !nextCursor;
    if (!renderedIds.size && nextCursor) status.textContent = msg('pageNoMatch', 'No match in this page yet. More eligible doctors remain to check.');
    else if (!renderedIds.size) status.textContent = msg('noResults', 'No exact match.');
    else status.textContent = renderedIds.size + ' ' + msg('verifiedResult', 'verified doctor result') + (renderedIds.size === 1 ? '' : 's') + (nextCursor ? ' ' + msg('moreAvailable', 'shown; more are available.') : '.');
    renderMap(accumulatedMapPoints); renderRecovery(data.recovery || []);
    if (!append && data.personal_order_notice) results.insertBefore(make('p', 'ddd-future-notice', data.personal_order_notice), results.firstChild);
  }

  function addToShortlist(publicId) {
    fetchJson(cfg.base + 'shortlists').then(function(data){
      var lists = data.items || [], row = lists.length ? lists[0] : {label:'My shortlist', public_ids:[]};
      row.public_ids = Array.isArray(row.public_ids) ? row.public_ids : []; if (row.public_ids.indexOf(publicId) === -1) row.public_ids.push(publicId);
      return mutationJson('shortlists', {id:row.id || '', label:row.label || 'My shortlist', public_ids:row.public_ids}, 'shortlist-save', 'POST');
    }).then(function(){ status.textContent=msg('shortlistSaved', 'Doctor added to your private shortlist.'); }).catch(function(err){ status.textContent=err.message; });
  }

  function submit() {
    status.textContent = msg('loading', 'Loading…'); nextCursor = '';
    postJson('discover', paramsFromForm()).then(function(data){ render(data, false); }).catch(function (err) { status.textContent = err.message; });
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

  if (loadMore) loadMore.addEventListener('click', function () {
    if (!nextCursor) return;
    var p = paramsFromForm(); p.cursor = nextCursor; loadMore.disabled = true; status.textContent = msg('loading', 'Loading…');
    postJson('discover', p).then(function(data){ render(data, true); }).catch(function(err){ status.textContent = err.message; }).finally(function(){ loadMore.disabled = false; });
  });

  var nearby = root.querySelector('[data-ddd-nearby]');
  if (nearby) nearby.addEventListener('click', function () {
    if (!navigator.geolocation) { status.textContent = msg('locationDenied', 'Location unavailable.'); return; }
    status.textContent = msg('requestingLocation', 'Requesting your location only for this search…');
    navigator.geolocation.getCurrentPosition(function (pos) {
      form.querySelector('[name="lat"]').value = pos.coords.latitude.toFixed(3); form.querySelector('[name="lng"]').value = pos.coords.longitude.toFixed(3); submit();
    }, function () { status.textContent = msg('locationDenied', 'Location was not shared.'); }, {enableHighAccuracy: false, timeout: 8000, maximumAge: 300000});
  });

  ['country', 'city'].forEach(function (name) {
    var field = form.querySelector('[name="' + name + '"]');
    if (field) field.addEventListener('input', function () {
      form.querySelector('[name="lat"]').value = '';
      form.querySelector('[name="lng"]').value = '';
    });
  });

  var saveSearch = root.querySelector('[data-ddd-save-search]');
  if (saveSearch) saveSearch.addEventListener('click', function () {
    if (!cfg.loggedIn) { status.textContent = msg('loginSave', 'Log in to save searches and receive matching-doctor alerts.'); return; }
    var label = window.prompt(msg('saveName', 'Name this saved search'), msg('savedSearch', 'Saved doctor search')); if (label === null) return;
    var p = paramsFromForm(); delete p.lat; delete p.lng; delete p.weights; delete p.cursor;
    mutationJson('saved-searches', {label: label, filters: p}, 'saved-search-save', 'POST').then(function () { status.textContent = msg('searchSaved', 'Search saved. Matching new eligible doctors can notify you through the platform notification system.'); }).catch(function (err) { status.textContent = err.message; });
  });

  var transparency = root.querySelector('[data-ddd-transparency]');
  if (transparency) transparency.addEventListener('click', function(){
    fetchJson(cfg.base + 'transparency').then(function(data){
      var policy = data.policy || {}; status.textContent = 'Official ranking owner: '+(data.owner || 'File 26')+'. Policy '+(policy.policy_version || 'not supplied')+', monthly version '+(policy.monthly_version || 'not supplied')+'. Paid/donor/purchased-engagement advantage is prohibited.';
    }).catch(function(err){ status.textContent=err.message; });
  });
  var offline = root.querySelector('[data-ddd-offline]');
  if (offline) offline.addEventListener('click', function(){ var p=paramsFromForm(); window.open(endpoint('offline-pack',{country:p.country||'',city:p.city||'',language:p.language||''}), '_blank', 'noopener'); });

  var runCompare = root.querySelector('[data-ddd-run-compare]');
  if (runCompare) runCompare.addEventListener('click', function () {
    if (selected.size < 2 || selected.size > 4) { status.textContent = msg('compareLimit', 'Select two to four doctors.'); return; }
    fetchJson(endpoint('compare', {ids: Array.from(selected).join(','), timezone: (tz && tz.value) || 'UTC'})).then(function (data) {
      compareOutput.innerHTML = ''; compareOutput.appendChild(make('p', 'ddd-future-notice', data.notice || msg('factualCompare', 'Factual comparison only.')));
      var table = make('div', 'ddd-future-comparegrid');
      (data.items || []).forEach(function (d) {
        var col = make('section', 'ddd-future-comparecol'); col.appendChild(make('h4', '', d.display_name || 'Doctor'));
        [[msg('specialty', 'Specialty'), d.specialty], [msg('location', 'Location'), [d.city, d.country].filter(Boolean).join(', ')], [msg('languages', 'Languages'), (d.languages || []).join(', ')], [msg('experience', 'Experience'), d.experience_years ? d.experience_years + ' ' + msg('years', 'years') : ''], [msg('fee', 'Fee'), d.fee && d.fee.min != null ? String(d.fee.min) + (d.fee.max != null && d.fee.max !== d.fee.min ? '–' + String(d.fee.max) : '') + ' ' + (d.fee.currency || '') : ''], [msg('availability', 'Availability'), d.local_availability && d.local_availability.next_local ? new Date(d.local_availability.next_local).toLocaleString() : '']].forEach(function (pair) { if (pair[1]) col.appendChild(make('p', '', pair[0] + ': ' + pair[1])); });
        table.appendChild(col);
      }); compareOutput.appendChild(table);
    }).catch(function (err) { status.textContent = err.message; });
  });

  submit();
}());
