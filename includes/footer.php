<footer class="bg-white py-4 mt-auto">
        <div class="container mx-auto px-4">
            <div class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Make sure Alpine.js is properly initialized -->
    <script>
        document.addEventListener('alpine:init', () => {
            console.log('Alpine.js initialized');
        });
    </script>
</body>
</html>