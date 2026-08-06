(function () {
  'use strict';

  function debounce(fn, wait) {
    var timer;
    return function () {
      var args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { fn.apply(null, args); }, wait);
    };
  }

  function loadFacet(field) {
    if (!window.dddDirectory || !dddDirectory.facetsUrl || !window.fetch) return;
    var type = field.getAttribute('data-ddd-facet');
    var listId = field.getAttribute('list');
    var list = listId ? document.getElementById(listId) : null;
    if (!type || !list) return;
    var term = field.value.trim();
    var url = dddDirectory.facetsUrl + encodeURIComponent(type) + '?term=' + encodeURIComponent(term) + '&limit=20';
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        if (!response.ok) throw new Error('facet_request_failed');
        return response.json();
      })
      .then(function (payload) {
        list.replaceChildren();
        (payload.items || []).forEach(function (item) {
          var option = document.createElement('option');
          option.value = item.label;
          option.label = item.label + ' (' + item.count + ')';
          list.appendChild(option);
        });
      })
      .catch(function () {
        /* Autocomplete is optional; the ordinary form remains fully usable. */
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ddd-search').forEach(function (form) {
      form.addEventListener('submit', function () {
        form.querySelectorAll('input, select').forEach(function (field) {
          if (field.type === 'checkbox') {
            if (!field.checked) field.disabled = true;
            return;
          }
          if (!field.value) field.disabled = true;
        });
      });
    });

    document.querySelectorAll('[data-ddd-facet]').forEach(function (field) {
      var update = debounce(function () { loadFacet(field); }, 250);
      field.addEventListener('input', update);
      field.addEventListener('focus', function () { loadFacet(field); }, { once: true });
    });

    window.addEventListener('offline', function () {
      document.querySelectorAll('[data-ddd-directory]').forEach(function (node) {
        if (node.querySelector('.ddd-offline-notice')) return;
        var notice = document.createElement('div');
        notice.className = 'ddd-notice ddd-offline-notice';
        notice.setAttribute('role', 'status');
        notice.textContent = 'You are offline. Existing public results remain visible, but live search and protected actions may be unavailable.';
        node.insertBefore(notice, node.firstChild);
      });
    });

    window.addEventListener('online', function () {
      document.querySelectorAll('.ddd-offline-notice').forEach(function (node) { node.remove(); });
    });
  });
}());
