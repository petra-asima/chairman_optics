// Header menu: mobile menu button and dropdown menus.

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Mobile menu button ----------
    var menuButton = document.querySelector('.menu-toggle');
    var nav = document.getElementById('main-nav');

    if (menuButton && nav) {
        menuButton.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('open');
            menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            menuButton.querySelector('.material-symbols-outlined').textContent = isOpen ? 'close' : 'menu';
        });
    }

    // ---------- Dropdown menus ----------
    // On a computer they open when you hover, and also when you click.
    // On a phone you tap to open and close them.
    var dropdowns = document.querySelectorAll('.dropdown');

    function closeAll(except) {
        dropdowns.forEach(function (item) {
            if (item !== except) {
                item.classList.remove('open');
                item.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
            }
        });
    }

    dropdowns.forEach(function (item) {
        var toggle = item.querySelector('.dropdown-toggle');

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            closeAll(item);
            var isOpen = item.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    // Clicking anywhere else closes the open dropdown
    document.addEventListener('click', function () {
        closeAll(null);
    });

    // The Escape key closes it too
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });
});