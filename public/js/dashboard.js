// Dashboard JS - Animations, Charts, Table Sort, Particles, Shimmer
document.addEventListener('DOMContentLoaded', function () {
    // Particles background (subtle)
    if (particlesJS) {
        particlesJS('particles-js', {
            particles: {
                number: { value: 50, density: { enable: true, value_area: 800 } },
                color: { value: '#dbeafe' },
                shape: { type: 'circle' },
                opacity: { value: 0.1, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: false },
                move: { enable: true, speed: 1, direction: 'none', random: true }
            },
            interactivity: { detect_on: 'canvas', events: { onhover: { enable: true, mode: 'repulse' } } },
            retina_detect: true
        });
    }

    // Counter animations
    const counters = document.querySelectorAll('.counter');
    const animateCounter = (el) => {
        const target = parseFloat(el.getAttribute('data-target'));
        const increment = target / 100;
        let current = 0;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = Math.floor(current) + (el.getAttribute('data-suffix') || '');
        }, 20);
    };

    // Intersection Observer for counters/charts
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                if (entry.target.classList.contains('counter')) {
                    animateCounter(entry.target);
                }
                observer.unobserve(entry.target);
            }
        });
    });
    document.querySelectorAll('.counter, .chart-container').forEach(el => observer.observe(el));

    // Shimmer loading
    window.shimmerOff = () => {
        document.querySelectorAll('.shimmer').forEach(el => {
            el.classList.remove('animate-pulse', 'shimmer');
            el.classList.add('opacity-100');
        });
    };

    // Table sorting
    document.querySelectorAll('th[data-sort]').forEach(header => {
        header.addEventListener('click', () => {
            const table = header.closest('table');
            const tbody = table.querySelector('tbody');
            const index = Array.from(header.parentNode.children).indexOf(header);
            const rows = Array.from(tbody.querySelectorAll('tr'));

            const direction = header.getAttribute('data-sort') === 'asc' ? 'desc' : 'asc';
            header.setAttribute('data-sort', direction);

            rows.sort((a, b) => {
                const aText = a.cells[index].textContent.trim();
                const bText = b.cells[index].textContent.trim();
                return direction === 'asc' ? aText.localeCompare(bText) : bText.localeCompare(aText);
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Mobile table collapse
    document.querySelectorAll('.table-row').forEach(row => {
        const toggle = row.querySelector('.row-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                const details = row.querySelector('.row-details');
                details.style.display = details.style.display === 'none' ? 'table-row' : 'none';
            });
        }
    });

    // Chart.js charts (init after data load)
    setTimeout(() => {
        // Status doughnut
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx && typeof stats !== 'undefined') {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Scanning', 'Completed', 'Failed'],
                    datasets: [{ data: [stats.pending, stats.scanning, stats.completed, stats.failed], backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'] }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        }

        // Severity bar
        const severityCtx = document.getElementById('severityChart')?.getContext('2d');
        if (severityCtx && typeof severities !== 'undefined') {
            const labels = Object.keys(severities);
            const data = Object.values(severities);
            new Chart(severityCtx, {
                type: 'bar',
                data: { labels, datasets: [{ label: 'Vulnerabilities', data, backgroundColor: '#3b82f6' }] },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });
        }
    }, 500);

    // Skeleton loading simulation then hide
    setTimeout(() => shimmerOff(), 1500);
});
