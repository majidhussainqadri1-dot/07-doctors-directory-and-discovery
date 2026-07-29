document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.sdd-search').forEach(function (form) {
    form.addEventListener('submit', function () {
      form.querySelectorAll('input, select').forEach(function (field) {
        if (!field.value) field.disabled = true;
      });
    });
  });
});

