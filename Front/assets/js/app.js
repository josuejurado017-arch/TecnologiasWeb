document.addEventListener('DOMContentLoaded', () => {
    window.requestCancellationReason = (form) => {
        const reason = window.prompt('Indique el motivo de la cancelacion:');
        if (!reason || reason.trim() === '') return false;
        const field = form.querySelector('[data-cancel-reason]');
        if (!field) return false;
        field.value = reason.trim();
        return true;
    };

    document.querySelectorAll('[data-cancel-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.requestCancellationReason(form)) event.preventDefault();
        });
    });

    const root = document.documentElement;
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const themeIcon = document.querySelector('[data-theme-icon]');

    const getTheme = () => root.dataset.theme === 'light' ? 'light' : 'dark';
    const updateThemeButton = () => {
        const isLight = getTheme() === 'light';
        if (themeIcon) themeIcon.innerHTML = isLight ? '&#9790;' : '&#9728;';
        themeToggle?.setAttribute('aria-pressed', String(isLight));
        themeToggle?.setAttribute('title', isLight ? 'Usar modo oscuro' : 'Usar modo claro');
    };

    updateThemeButton();
    themeToggle?.addEventListener('click', () => {
        const nextTheme = getTheme() === 'light' ? 'dark' : 'light';
        root.dataset.theme = nextTheme;
        try { localStorage.setItem('upds-theme', nextTheme); } catch (error) { /* localStorage puede estar bloqueado */ }
        updateThemeButton();
        window.dispatchEvent(new CustomEvent('upds-theme-change'));
    });

    const sidebar = document.querySelector('[data-sidebar]') || document.getElementById('sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-close]');

    const closeSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        backdrop?.classList.remove('is-visible');
        toggle?.setAttribute('aria-expanded', 'false');
    };

    toggle?.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        backdrop?.classList.toggle('is-visible', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    backdrop?.addEventListener('click', closeSidebar);
    document.querySelectorAll('.sidebar .nav-link').forEach((link) => link.addEventListener('click', closeSidebar));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSidebar();
    });

    const menus = [
        ['[data-notifications-toggle]', '[data-notifications-panel]'],
        ['[data-user-menu-toggle]', '[data-user-menu-panel]'],
    ].map(([toggleSelector, panelSelector]) => {
        const menuToggle = document.querySelector(toggleSelector);
        const panel = document.querySelector(panelSelector);
        return { menuToggle, panel };
    }).filter(({ menuToggle, panel }) => menuToggle && panel);

    const closeMenus = (except = null) => {
        menus.forEach(({ menuToggle, panel }) => {
            if (panel === except) return;
            panel.hidden = true;
            menuToggle.setAttribute('aria-expanded', 'false');
        });
    };

    menus.forEach(({ menuToggle, panel }) => {
        menuToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = !panel.hidden;
            closeMenus(isOpen ? null : panel);
            panel.hidden = isOpen;
            menuToggle.setAttribute('aria-expanded', String(!isOpen));
        });
        panel.addEventListener('click', (event) => event.stopPropagation());
    });
    document.addEventListener('click', () => closeMenus());

    const chartPalette = () => {
        const styles = getComputedStyle(root);
        return {
            text: styles.getPropertyValue('--upds-muted').trim(),
            line: styles.getPropertyValue('--upds-line').trim(),
            colors: ['#8275e8', '#51c8bb', '#dcae36', '#6f9df7', '#f18b9a'],
        };
    };
    const dashboardCharts = [];
    document.querySelectorAll('[data-dashboard-chart]').forEach((canvas) => {
        if (typeof Chart === 'undefined') {
            canvas.parentElement?.classList.add('chart-unavailable');
            return;
        }
        try {
            const chartData = JSON.parse(canvas.dataset.chartData || '{}');
            const palette = chartPalette();
            const isBar = canvas.dataset.chartType === 'bar';
            const chart = new Chart(canvas, {
                type: canvas.dataset.chartType || 'doughnut',
                data: {
                    labels: chartData.labels || [],
                    datasets: [{
                        data: chartData.values || [],
                        backgroundColor: palette.colors,
                        borderColor: 'transparent',
                        borderWidth: 0,
                        borderRadius: isBar ? 6 : 0,
                        hoverOffset: isBar ? 0 : 8,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: palette.text, usePointStyle: true, padding: 18, font: { size: 11 } } },
                    },
                    scales: isBar ? {
                        x: { grid: { display: false }, ticks: { color: palette.text, font: { size: 10 } } },
                        y: { beginAtZero: true, grid: { color: palette.line }, ticks: { color: palette.text, precision: 0, font: { size: 10 } } },
                    } : {},
                },
            });
            dashboardCharts.push(chart);
        } catch (error) {
            canvas.parentElement?.classList.add('chart-unavailable');
        }
    });

    window.addEventListener('upds-theme-change', () => {
        const palette = chartPalette();
        dashboardCharts.forEach((chart) => {
            if (chart.options.plugins?.legend?.labels) chart.options.plugins.legend.labels.color = palette.text;
            if (chart.options.scales?.x) chart.options.scales.x.ticks.color = palette.text;
            if (chart.options.scales?.y) {
                chart.options.scales.y.ticks.color = palette.text;
                chart.options.scales.y.grid.color = palette.line;
            }
            chart.update();
        });
    });

    document.querySelectorAll('[data-table-search]').forEach((search) => {
        const table = search.closest('main')?.querySelector('table');
        const rows = table ? [...table.querySelectorAll('tbody tr[data-row]')] : [];
        const emptySearch = search.closest('main')?.querySelector('[data-search-empty]');
        const count = search.closest('main')?.querySelector('[data-table-count]');

        search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            let visibleRows = 0;
            rows.forEach((row) => {
                const matches = row.textContent.toLowerCase().includes(query);
                row.hidden = !matches;
                if (matches) visibleRows += 1;
            });
            if (emptySearch) emptySearch.hidden = visibleRows !== 0 || rows.length === 0;
            if (count) count.textContent = `${visibleRows} resultado${visibleRows === 1 ? '' : 's'}`;
        });
    });

    document.querySelectorAll('[data-password-confirmation]').forEach((confirmation) => {
        const form = confirmation.form;
        const password = form?.querySelector('[data-password-field]');
        if (!password) return;
        const validatePasswords = () => {
            confirmation.setCustomValidity(
                confirmation.value === '' && password.value === ''
                    ? ''
                    : confirmation.value === password.value ? '' : 'Las contrasenas no coinciden.'
            );
        };
        password.addEventListener('input', validatePasswords);
        confirmation.addEventListener('input', validatePasswords);
    });

    document.querySelectorAll('[data-time-end]').forEach((end) => {
        const form = end.form;
        const start = form?.querySelector('[data-time-start]');
        if (!start) return;
        const validateTimes = () => {
            end.setCustomValidity(!start.value || !end.value || end.value > start.value ? '' : 'La hora final debe ser posterior a la inicial.');
        };
        start.addEventListener('input', validateTimes);
        end.addEventListener('input', validateTimes);
    });
});
