(function () {
  'use strict';
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
