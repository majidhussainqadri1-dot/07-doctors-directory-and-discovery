document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.sdd-search').forEach(function (form) {
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
});
