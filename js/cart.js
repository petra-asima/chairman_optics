// Cart page: save a new quantity as soon as it changes.
// Without JavaScript the "Update" button next to each box does the same.

document.addEventListener('DOMContentLoaded', function () {

    var qtyInputs = document.querySelectorAll('.js-qty');

    qtyInputs.forEach(function (input) {
        // The button is not needed when the box saves by itself
        var button = input.form.querySelector('.btn-update');
        if (button) {
            button.hidden = true;
        }

        input.addEventListener('change', function () {
            var max = parseInt(input.max, 10);
            var value = parseInt(input.value, 10);

            // Keep the number inside the allowed range before sending it
            if (isNaN(value) || value < 1) {
                input.value = 1;
            } else if (value > max) {
                input.value = max;
            }

            input.form.requestSubmit();
        });
    });

    // ---------- Checkout: only show the address box for Delivery ----------
    var addressBox = document.querySelector('.js-address');
    var methodRadios = document.querySelectorAll('input[name="delivery_method"]');

    function showAddressIfDelivery() {
        var chosen = document.querySelector('input[name="delivery_method"]:checked');
        addressBox.hidden = !(chosen && chosen.value === 'Delivery');
    }

    if (addressBox && methodRadios.length > 0) {
        methodRadios.forEach(function (radio) {
            radio.addEventListener('change', showAddressIfDelivery);
        });
        showAddressIfDelivery();
    }

    // ---------- Checkout: stop a double click paying twice ----------
    var checkoutForm = document.getElementById('checkout-form');

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function () {
            var payButton = checkoutForm.querySelector('.btn-pay');
            payButton.disabled = true;
            payButton.lastChild.textContent = ' Opening Paystack...';
        });
    }
});
