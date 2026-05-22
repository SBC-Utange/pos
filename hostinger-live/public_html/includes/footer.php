        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        var html = document.documentElement;
        var btn = document.getElementById('themeToggle');
        var label = document.getElementById('themeToggleLabel');
        if (!btn || !label) {
            return;
        }

        function syncLabel() {
            var current = html.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            if (current === 'dark') {
                label.textContent = 'Light';
                btn.querySelector('i').className = 'bi bi-sun-fill';
            } else {
                label.textContent = 'Dark';
                btn.querySelector('i').className = 'bi bi-moon-stars-fill';
            }
        }

        btn.addEventListener('click', function () {
            var current = html.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('srm-theme', next);
            syncLabel();
        });

        syncLabel();
    })();
</script>
</body>
</html>
