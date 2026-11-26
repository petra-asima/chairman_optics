// Small helpers for the staff pages.
// The server checks everything again, so these are only for convenience.

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Ask before deleting ----------
    // Any form with a data-confirm message asks "Are you sure?" first.
    var confirmForms = document.querySelectorAll('form[data-confirm]');

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // ---------- Product form: preview the chosen image ----------
    var imageInput   = document.getElementById('product_image');
    var imagePreview = document.getElementById('image-preview');

    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function () {
            var file = imageInput.files[0];
            if (!file) {
                return;
            }
            // Warn early about large files (the server also checks)
            if (file.size > 2 * 1024 * 1024) {
                window.alert('This image is larger than 2 MB. Please choose a smaller one.');
                imageInput.value = '';
                return;
            }
            imagePreview.src = URL.createObjectURL(file);
            imagePreview.hidden = false;
        });
    }

    // ---------- Product form: services have no stock ----------
    // Ticking "This is a service" hides the stock fields.
    var serviceBox = document.getElementById('is_service');

    if (serviceBox) {
        var stockFields = document.querySelectorAll('.js-stock-fields');

        serviceBox.addEventListener('change', function () {
            stockFields.forEach(function (field) {
                field.hidden = serviceBox.checked;
            });
        });
    }

    // ---------- Stock form: "add or remove" only for adjustments ----------
    var reasonSelect = document.getElementById('reason');
    var directionBox = document.querySelector('.js-direction');

    if (reasonSelect && directionBox) {
        reasonSelect.addEventListener('change', function () {
            var isAdjustment = reasonSelect.value === 'Adjustment';
            directionBox.hidden = !isAdjustment;

            // Restock and Return always add stock
            if (!isAdjustment) {
                document.getElementById('direction').value = 'in';
            }
        });
    }
});
