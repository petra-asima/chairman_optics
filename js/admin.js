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

    // ---------- In-store sale: show line totals and the total while typing ----------
    // This is only a preview for the customer at the counter. The server
    // works out the real total from the database when the sale is saved.
    var saleForm = document.getElementById('sale-form');

    if (saleForm) {
        var cedis = function (amount) {
            return 'GH₵ ' + amount.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        var updateSaleTotal = function () {
            var total = 0;
            saleForm.querySelectorAll('.js-sale-line').forEach(function (row) {
                var product = row.querySelector('.js-product');
                var chosen  = product.options[product.selectedIndex];
                var qty     = parseInt(row.querySelector('.js-qty-sale').value, 10) || 0;
                var cell    = row.querySelector('.js-line-total');

                if (product.value === '0') {
                    cell.textContent = '-';
                    return;
                }
                var each = parseFloat(chosen.dataset.price) || 0;
                // Lens prices only count for frames that take lenses
                if (chosen.dataset.lenses === '1') {
                    row.querySelectorAll('.js-lens').forEach(function (lens) {
                        each += parseFloat(lens.options[lens.selectedIndex].dataset.price) || 0;
                    });
                }
                cell.textContent = cedis(each * qty);
                total += each * qty;
            });
            saleForm.querySelector('.js-sale-total').textContent = cedis(total);
        };

        saleForm.addEventListener('change', updateSaleTotal);
        saleForm.addEventListener('input', updateSaleTotal);
        updateSaleTotal();
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
