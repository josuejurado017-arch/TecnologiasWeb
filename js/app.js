document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]') || document.getElementById('sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-close]');

    const closeSidebar = () => {
        if (!sidebar) {
            return;
        }

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
    document.querySelectorAll('.sidebar .nav-link').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
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
                if (matches) {
                    visibleRows += 1;
                }
            });

            if (emptySearch) {
                emptySearch.hidden = visibleRows !== 0 || rows.length === 0;
            }
            if (count) {
                count.textContent = `${visibleRows} resultado${visibleRows === 1 ? '' : 's'}`;
            }
        });
    });

    document.querySelectorAll('[data-password-confirmation]').forEach((confirmation) => {
        const form = confirmation.form;
        const password = form?.querySelector('[data-password-field]');
        if (!password) {
            return;
        }

        const validatePasswords = () => {
            if (confirmation.value !== '' || password.value !== '') {
                confirmation.setCustomValidity(
                    confirmation.value === password.value ? '' : 'Las contrasenas no coinciden.'
                );
            } else {
                confirmation.setCustomValidity('');
            }
        };

        password.addEventListener('input', validatePasswords);
        confirmation.addEventListener('input', validatePasswords);
    });

    document.querySelectorAll('[data-sabados-toggle]').forEach((toggle) => {
        const panel = toggle.closest('form')?.querySelector('[data-sabados-panel]');
        if (!panel) {
            return;
        }

        const sync = () => {
            panel.hidden = !toggle.checked;
        };

        toggle.addEventListener('change', sync);
        sync();
    });

    // El selector de dia solo aplica al patron "un dia por semana".
    document.querySelectorAll('[data-patron-dia]').forEach((panel) => {
        const form = panel.closest('form');
        const opciones = form?.querySelectorAll('[data-patron]');
        if (!opciones || !opciones.length) {
            return;
        }

        const sync = () => {
            panel.hidden = form.querySelector('[data-patron]:checked')?.value !== 'uno';
        };

        opciones.forEach((opcion) => opcion.addEventListener('change', sync));
        sync();
    });

    document.querySelectorAll('[data-time-end]').forEach((end) => {
        const form = end.form;
        const start = form?.querySelector('[data-time-start]');
        if (!start) {
            return;
        }

        const validateTimes = () => {
            end.setCustomValidity(
                !start.value || !end.value || end.value > start.value
                    ? ''
                    : 'La hora final debe ser posterior a la inicial.'
            );
        };

        start.addEventListener('input', validateTimes);
        end.addEventListener('input', validateTimes);
    });
});
