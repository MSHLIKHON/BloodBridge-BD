<!-- File purpose: Footer provides shared application logic and presentation helpers. -->
    </main>
    <footer class="footer">
        <span class="footer-brand"><span class="footer-dot"></span>BloodBridge BD • Team NullLogic</span>
        <span>Verified Donation &amp; Blood-Bank Management</span>
    </footer>
    <script src="assets/js/app.js?v=70"></script>
    <script src="assets/js/address.js?v=110"></script>
    <?php if(!empty($enableMap)): ?><script src="assets/vendor/leaflet/leaflet.js"></script><script src="assets/js/maps.js?v=110"></script><?php endif; ?>
</body>
</html>
