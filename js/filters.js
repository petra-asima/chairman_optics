// Shop page filters.
// Everything works without JavaScript (the "Show Products" button sends
// the form). This file only makes it quicker to use.

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Apply a dropdown filter as soon as it changes ----------
    var autoSelects = document.querySelectorAll('.js-auto-submit');

    autoSelects.forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.requestSubmit();
        });
    });

    // ---------- Keep the address short ----------
    // Empty boxes are not sent, so the address shows only the filters in use
    // (for example ?brand=3 instead of ?q=&cat=&brand=3&colour=...).
    var filterForms = document.querySelectorAll('#shop-filters, .shop-sort');

    filterForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('input, select').forEach(function (field) {
                if (field.value === '' || (field.name === 'sort' && field.value === 'newest')) {
                    field.disabled = true;
                }
            });
        });
    });

    // ---------- On phones, start with the filter box closed ----------
    var filterBox = document.querySelector('.filter-box');

    if (filterBox && window.matchMedia('(max-width: 800px)').matches) {
        filterBox.open = false;
    }
});
