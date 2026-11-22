</main>

<footer class="site-footer" id="contact">
    <div class="container footer-grid">
        <div>
            <img src="<?= url('images/logo-white.png') ?>" alt="Chairman Optics and Lens Centre" class="footer-logo">
            <p>Eye examinations, prescription glasses, frames and lenses for families in Kumasi.</p>
        </div>
        <div>
            <h2 class="footer-title">Quick Links</h2>
            <ul class="footer-links">
                <li><a href="<?= url('index.php') ?>">Home</a></li>
                <li><a href="<?= url('views/all_products.php') ?>">Shop</a></li>
                <li><a href="<?= url('views/track_order.php') ?>">Track Order</a></li>
                <li><a href="<?= url('views/book_appointment.php') ?>">Book an Eye Exam</a></li>
            </ul>
        </div>
        <div>
            <h2 class="footer-title">Contact Us</h2>
            <p><?= e(SITE_ADDRESS) ?></p>
            <p><?= e(SITE_PHONE) ?></p>
            <p><?= e(SITE_EMAIL) ?></p>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> Chairman Optics and Lens Centre</p>
    </div>
</footer>

<script src="<?= url('js/site.js') ?>"></script>
<script src="<?= url('js/validate.js') ?>"></script>
</body>
</html>