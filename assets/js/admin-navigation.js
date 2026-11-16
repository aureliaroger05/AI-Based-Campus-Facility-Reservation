(function () {
    const shell = document.querySelector('.admin-dashboard-shell');
    const workspace = document.querySelector('.admin-dashboard-workspace');
    const viewRoutes = {
        dashboard: 'index.php',
        reservations: 'reservations.php',
        forecast: 'ai-forecast.php',
        schedules: 'class_schedules.php',
        'add-user': 'add_user.php',
        'edit-user': 'edit_user.php',
        'add-facility': 'add_facility.php',
        'edit-facility': 'edit_facility.php',
        calendar: '../index.php',
        profile: '../profile.php'
    };

    if (!shell || !workspace || !viewRoutes.dashboard) {
        return;
    }

    let activeMain = workspace.querySelector('main');
    let requestNumber = 0;
    let activeView = 'dashboard';
    let activeSection = '';
    let liveRevision = null;
    let liveStatus = 'connecting';
    let livePollInProgress = false;
    const themeToggle = document.querySelector('[data-admin-theme-toggle]');
    const notificationToggle = document.querySelector('[data-admin-notifications-toggle]');
    const notificationPanel = document.getElementById('adminNotificationPanel');

    function updateChartTheme(isDark) {
        if (!window.Chart) return;

        const tickColor = isDark ? '#c1ccda' : '#788397';
        const gridColor = isDark ? 'rgba(170, 184, 204, 0.2)' : 'rgba(120, 131, 151, 0.14)';
        document.querySelectorAll('.admin-dashboard-shell main canvas').forEach(function (canvas) {
            const chart = window.Chart.getChart(canvas);
            if (!chart) return;

            Object.keys(chart.options.scales || {}).forEach(function (scaleName) {
                const scale = chart.options.scales[scaleName];
                if (scale.ticks) scale.ticks.color = tickColor;
                if (scale.grid && scale.grid.display !== false) scale.grid.color = gridColor;
                if (scale.border) scale.border.color = gridColor;
            });

            const tooltip = chart.options.plugins && chart.options.plugins.tooltip;
            if (tooltip) {
                tooltip.backgroundColor = isDark ? '#25334a' : '#172b4d';
                tooltip.titleColor = isDark ? '#f4f7fc' : '#fff';
                tooltip.bodyColor = isDark ? '#e5eaf2' : '#fff';
            }
            chart.update('none');
        });
    }

    function setAdminTheme(theme) {
        const isDark = theme === 'dark';
        document.documentElement.dataset.adminTheme = isDark ? 'dark' : 'light';
        updateChartTheme(isDark);
        if (!themeToggle) return;

        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-sun', isDark);
            icon.classList.toggle('bi-moon-stars', !isDark);
        }
        themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        themeToggle.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    }

    function updateNotificationCount(count) {
        if (!Number.isSafeInteger(count) || count < 0) {
            throw new Error('Live updates returned an invalid pending-reservation count.');
        }

        if (!notificationToggle) return;
        const badge = notificationToggle.querySelector('[data-admin-notification-badge]');
        const message = notificationPanel && notificationPanel.querySelector('[data-admin-notification-message]');
        const label = count === 1 ? 'pending reservation' : 'pending reservations';

        notificationToggle.setAttribute(
            'aria-label',
            count > 0 ? 'Notifications, ' + count + ' ' + label : 'Notifications, no pending reservations'
        );
        if (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count === 0;
        }
        if (message) {
            message.textContent = count > 0
                ? count + ' reservation' + (count === 1 ? '' : 's') + ' waiting for review.'
                : 'You are all caught up.';
        }
    }

    setAdminTheme(document.documentElement.dataset.adminTheme || 'light');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const nextTheme = document.documentElement.dataset.adminTheme === 'dark' ? 'light' : 'dark';
            setAdminTheme(nextTheme);
            window.localStorage.setItem('admin-dashboard-theme', nextTheme);
        });
    }
    if (notificationToggle) {
        updateNotificationCount(Number(notificationToggle.dataset.pendingCount || 0));
    }

    function closeNotifications(returnFocus) {
        if (!notificationToggle || !notificationPanel) return;
        notificationPanel.hidden = true;
        notificationToggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) notificationToggle.focus();
    }

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-admin-notifications-toggle]');
        if (toggle && notificationPanel) {
            const isOpening = notificationPanel.hidden;
            notificationPanel.hidden = !isOpening;
            toggle.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
            return;
        }

        if (notificationPanel && !notificationPanel.hidden && !notificationPanel.contains(event.target)) {
            closeNotifications(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && notificationPanel && !notificationPanel.hidden) {
            closeNotifications(true);
        }
    });

    function updateSidebar(view, section) {
        const selectedSection = section ||
            (view === 'add-user' || view === 'edit-user' ? 'users' :
                view === 'add-facility' || view === 'edit-facility' ? 'facilities' : '');
        document.querySelectorAll('.admin-sidebar-link[data-spa-view]').forEach(function (link) {
            const isActive = selectedSection
                ? link.dataset.spaSection === selectedSection
                : link.dataset.spaView === view && !link.dataset.spaSection;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function updateAddress(view, section, replace, routeParams) {
        const address = new URL('index.php', window.location.href);
        address.search = '';
        address.hash = section || '';
        if (view !== 'dashboard') {
            address.searchParams.set('view', view);
        }
        Object.keys(routeParams || {}).forEach(function (key) {
            if (routeParams[key] !== null && routeParams[key] !== '') {
                address.searchParams.set(key, routeParams[key]);
            }
        });

        const state = { adminShell: true, view: view, section: section || '', routeParams: routeParams || {} };
        if (replace) {
            window.history.replaceState(state, '', address);
        } else {
            window.history.pushState(state, '', address);
        }
    }

    function queryParamsFromUrl(url) {
        const params = {};
        url.searchParams.forEach(function (value, key) {
            if (key !== 'view') params[key] = value;
        });
        return params;
    }

    function routeFromUrl(value) {
        const target = new URL(value, window.location.href);
        if (target.origin !== window.location.origin) return null;

        const view = Object.keys(viewRoutes).find(function (route) {
            return new URL(viewRoutes[route], window.location.href).pathname === target.pathname;
        });
        if (!view) return null;

        return {
            view: view,
            targetUrl: target.href,
            routeParams: queryParamsFromUrl(target),
            section: target.hash.slice(1)
        };
    }

    function isUnmodifiedPrimaryClick(event) {
        return !event.defaultPrevented &&
            event.button === 0 &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.shiftKey &&
            !event.altKey;
    }

    function hasUnsavedFormChanges() {
        return Array.from(activeMain.querySelectorAll('input, textarea, select')).some(function (field) {
            if (field.type === 'file') return field.files.length > 0;
            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked !== field.defaultChecked;
            }
            if (field.tagName === 'SELECT') {
                return Array.from(field.options).some(function (option) {
                    return option.selected !== option.defaultSelected;
                });
            }
            return field.value !== field.defaultValue;
        });
    }

    function showNavigationError(message) {
        if (!activeMain) return;
        let alert = activeMain.querySelector('.admin-navigation-error');
        if (!alert) {
            alert = document.createElement('div');
            alert.className = 'alert alert-danger admin-navigation-error';
            alert.setAttribute('role', 'alert');
            activeMain.prepend(alert);
        }
        alert.textContent = message;
    }

    function prepareRelativeUrls(main, baseUrl) {
        main.querySelectorAll('a[href]').forEach(function (link) {
            const href = link.getAttribute('href');
            if (href && !/^(?:#|[a-z][a-z0-9+.-]*:|\/\/)/i.test(href)) {
                link.href = new URL(href, baseUrl).href;
            }
        });

        main.querySelectorAll('form').forEach(function (form) {
            const action = form.getAttribute('action');
            form.action = action ? new URL(action, baseUrl).href : baseUrl;
        });

        main.querySelectorAll('img[src]').forEach(function (image) {
            const src = image.getAttribute('src');
            if (src && !/^(?:[a-z][a-z0-9+.-]*:|\/\/|data:)/i.test(src)) {
                image.src = new URL(src, baseUrl).href;
            }
        });
    }

    function createViewHeading(title, description, iconName) {
        const heading = document.createElement('section');
        heading.className = 'admin-page-heading admin-shell-view-heading';
        heading.setAttribute('aria-label', title);

        const icon = document.createElement('span');
        icon.className = 'admin-heading-icon';
        icon.setAttribute('aria-hidden', 'true');
        const iconGlyph = document.createElement('i');
        iconGlyph.className = 'bi ' + iconName;
        icon.appendChild(iconGlyph);

        const copy = document.createElement('div');
        const titleElement = document.createElement('h2');
        titleElement.className = 'mb-1';
        titleElement.textContent = title;
        const descriptionElement = document.createElement('p');
        descriptionElement.className = 'mb-0';
        descriptionElement.textContent = description;
        copy.append(titleElement, descriptionElement);
        heading.append(icon, copy);
        return heading;
    }

    function ensureViewHeading(main, view) {
        const headingSelectors = {
            dashboard: '.admin-dashboard-heading',
            reservations: '.admin-page-heading',
            forecast: '.forecast-hero',
            schedules: '.admin-page-heading',
            'add-user': '.admin-page-heading',
            'edit-user': '.admin-page-heading',
            'add-facility': '.admin-page-heading',
            'edit-facility': '.admin-page-heading',
            profile: '.profile-page-heading'
        };
        const existingHeading = headingSelectors[view]
            ? main.querySelector(headingSelectors[view])
            : null;
        if (existingHeading) {
            existingHeading.classList.add('admin-shell-view-heading');
            return;
        }

        const details = {
            calendar: [
                'Reservation calendar',
                'Choose a date and facility to check availability or request a booking.',
                'bi-calendar2-week'
            ]
        }[view];
        if (!details) return;

        const container = main.querySelector('.container-fluid, .container') || main;
        container.prepend(createViewHeading(details[0], details[1], details[2]));
    }

    function runPageScripts(container) {
        container.querySelectorAll('script:not([src])').forEach(function (pageScript) {
            if (
                pageScript.hasAttribute('data-admin-shell-script') ||
                pageScript.hasAttribute('data-admin-dashboard-chart') ||
                (pageScript.type && pageScript.type !== 'text/javascript')
            ) {
                return;
            }

            const source = pageScript.textContent.trim();
            if (!source) return;

            const script = document.createElement('script');
            script.textContent = '(function () {\n' + source + '\n})();';
            document.body.appendChild(script);
            script.remove();
        });
    }

    function setViewClasses(pageDocument) {
        const pageClasses = Array.from(pageDocument.body.classList).filter(function (className) {
            return className && className !== 'admin-dashboard-page';
        });
        document.body.className = ['admin-dashboard-page'].concat(pageClasses).join(' ');

        document.querySelectorAll('style[data-admin-view-style]').forEach(function (style) {
            style.remove();
        });
        pageDocument.querySelectorAll('style').forEach(function (pageStyle) {
            const style = document.createElement('style');
            style.dataset.adminViewStyle = 'true';
            style.textContent = pageStyle.textContent;
            document.head.appendChild(style);
        });
    }

    function setLiveStatus(state) {
        if (liveStatus === state) return;
        liveStatus = state;
        const indicator = document.querySelector('[data-admin-live-status]');
        if (!indicator) return;

        indicator.dataset.state = state;
        const label = indicator.querySelector('span:last-child');
        if (label) {
            label.textContent = state === 'online'
                ? 'Live'
                : state === 'offline' ? 'Updates paused' : 'Connecting';
        }
    }

    async function loadView(view, options) {
        const settings = options || {};
        const target = settings.targetUrl
            ? new URL(settings.targetUrl, window.location.href)
            : new URL(viewRoutes[view] || viewRoutes.dashboard, window.location.href);
        if (!settings.targetUrl) {
            Object.keys(settings.routeParams || {}).forEach(function (key) {
                if (settings.routeParams[key] !== null && settings.routeParams[key] !== '') {
                    target.searchParams.set(key, settings.routeParams[key]);
                }
            });
        }
        if (!settings.targetUrl && view === 'calendar') {
            const currentParams = new URLSearchParams(window.location.search);
            if (currentParams.get('view') === 'calendar' || activeView === 'calendar') {
                ['month', 'reserved'].forEach(function (key) {
                    if (currentParams.has(key)) {
                        target.searchParams.set(key, currentParams.get(key));
                    }
                });
            }
        }
        const currentRequest = ++requestNumber;
        const previousScroll = window.scrollY;
        shell.setAttribute('aria-busy', 'true');

        try {
            let responseUrl = target.href;
            let pageDocument = settings.pageDocument;
            if (!pageDocument) {
                const requestHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
                if (settings.liveRefresh) {
                    requestHeaders['X-Admin-Live-Refresh'] = 'true';
                }
                const response = await fetch(target.href, {
                    credentials: 'same-origin',
                    headers: requestHeaders
                });
                if (!response.ok) {
                    throw new Error('The requested view could not be loaded (HTTP ' + response.status + ').');
                }
                responseUrl = response.url;
                pageDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            } else {
                responseUrl = settings.responseUrl || target.href;
            }

            if (pageDocument.body.classList.contains('login-page') || /\/login\.php(?:$|\?)/i.test(responseUrl)) {
                window.location.assign(responseUrl);
                return false;
            }

            const nextMain = pageDocument.querySelector('main');
            if (!nextMain) {
                throw new Error('The requested view did not return a page content area.');
            }
            if (currentRequest !== requestNumber) return false;

            let sectionTitle = '';
            if (settings.section) {
                const section = nextMain.querySelector('#' + settings.section);
                if (!section) {
                    throw new Error('The requested section could not be found.');
                }

                sectionTitle = settings.section === 'users' ? 'Users' : 'Facilities';
                const sectionContainer = document.createElement('div');
                sectionContainer.className = 'container-fluid admin-dashboard-content';
                const sectionDetails = settings.section === 'users'
                    ? ['Users', 'Manage campus accounts and access roles.', 'bi-people']
                    : ['Facilities', 'Manage campus spaces and their availability.', 'bi-buildings'];
                sectionContainer.appendChild(createViewHeading(
                    sectionDetails[0],
                    sectionDetails[1],
                    sectionDetails[2]
                ));
                sectionContainer.appendChild(section);
                nextMain.replaceChildren(sectionContainer);
            }
            if (!settings.section) {
                ensureViewHeading(nextMain, view);
            }

            const currentChart = document.getElementById('adminReservationChart');
            if (currentChart && window.Chart) {
                const chart = Chart.getChart(currentChart);
                if (chart) chart.destroy();
            }

            prepareRelativeUrls(nextMain, responseUrl);
            setViewClasses(pageDocument);
            document.dispatchEvent(new CustomEvent('admin:view-unloading', {
                detail: { view: activeView }
            }));
            activeMain.replaceWith(nextMain);
            activeMain = nextMain;
            activeView = view;
            activeSection = settings.section || '';

            const viewTitle = sectionTitle || pageDocument.title.split('|')[0].trim();
            const breadcrumb = document.querySelector('.admin-topbar-breadcrumb strong');
            if (breadcrumb) breadcrumb.textContent = viewTitle;
            document.title = sectionTitle
                ? sectionTitle + ' | Admin Dashboard | ACLC Facility Reservation System'
                : pageDocument.title;

            updateSidebar(view, settings.section || '');
            if (settings.updateAddress !== false) {
                updateAddress(
                    view,
                    settings.section || '',
                    settings.replaceAddress === true,
                    settings.routeParams
                );
            }

            runPageScripts(activeMain);
            runPageScripts(pageDocument.body);
            document.dispatchEvent(new CustomEvent('admin:view-loaded', {
                detail: { view: view }
            }));

            if (settings.preserveScroll) {
                window.requestAnimationFrame(function () {
                    window.scrollTo(0, previousScroll);
                });
            } else if (settings.section) {
                window.requestAnimationFrame(function () {
                    const section = document.getElementById(settings.section);
                    if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            } else {
                document.documentElement.scrollTop = 0;
                document.body.scrollTop = 0;
                window.scrollTo(0, 0);
            }
            shell.classList.remove('is-sidebar-open');
            const toggle = document.querySelector('.admin-sidebar-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            return true;
        } catch (error) {
            if (currentRequest === requestNumber) {
                console.error('Admin sidebar navigation failed:', error);
                showNavigationError(error.message || 'The requested view could not be loaded.');
            }
            return false;
        } finally {
            if (currentRequest === requestNumber) {
                shell.removeAttribute('aria-busy');
            }
        }
    }

    async function submitCalendarReservation(form, submitter) {
        const currentRequest = ++requestNumber;
        const selectedDate = form.elements.reserve_date.value;
        shell.setAttribute('aria-busy', 'true');

        try {
            const formData = new FormData(form);
            if (submitter && submitter.name && !formData.has(submitter.name)) {
                formData.append(submitter.name, submitter.value);
            }

            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            if (!response.ok) {
                throw new Error('The reservation could not be submitted (HTTP ' + response.status + ').');
            }
            if (/\/login\.php(?:$|\?)/i.test(response.url)) {
                window.location.assign(response.url);
                return;
            }

            const resultUrl = new URL(response.url);
            const result = resultUrl.searchParams.get('reserved');
            if (currentRequest !== requestNumber) return;
            if (result !== '1' && result !== '0') {
                throw new Error('The reservation service returned an unexpected response.');
            }

            const month = selectedDate.slice(0, 7);
            resultUrl.searchParams.set('month', month);
            const refreshed = await loadView('calendar', {
                targetUrl: resultUrl.href,
                routeParams: { month: month, reserved: result }
            });
            if (!refreshed && currentRequest === requestNumber) {
                showNavigationError('The calendar could not be refreshed after submitting the reservation.');
            }
        } catch (error) {
            if (currentRequest === requestNumber) {
                console.error('Calendar reservation submission failed:', error);
                showNavigationError(error.message || 'The reservation could not be submitted.');
            }
        } finally {
            if (currentRequest === requestNumber) {
                shell.removeAttribute('aria-busy');
            }
        }
    }

    async function submitPageForm(form, submitter) {
        const currentRequest = ++requestNumber;
        const submitButtonWasDisabled = submitter ? submitter.disabled : false;
        if (submitter) submitter.disabled = true;
        shell.setAttribute('aria-busy', 'true');

        try {
            const action = submitter && submitter.hasAttribute('formaction') ? submitter.formAction : form.action;
            const method = (submitter && submitter.hasAttribute('formmethod') ? submitter.formMethod : form.method).toUpperCase();
            const requestUrl = new URL(action, window.location.href);
            const formData = new FormData(form);
            if (submitter && submitter.name) {
                formData.set(submitter.name, submitter.value);
            }

            const requestOptions = {
                method: method,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            };
            if (method === 'GET') {
                formData.forEach(function (value, key) {
                    requestUrl.searchParams.append(key, value);
                });
            } else {
                requestOptions.body = formData;
            }

            const response = await fetch(requestUrl.href, requestOptions);
            if (!response.ok) {
                throw new Error('The requested action failed (HTTP ' + response.status + ').');
            }
            if (/\/login\.php(?:$|\?)/i.test(response.url)) {
                window.location.assign(response.url);
                return;
            }
            if (currentRequest !== requestNumber) return;

            const responseRoute = routeFromUrl(response.url);
            if (!responseRoute) {
                throw new Error('The action returned a page outside the admin workspace.');
            }

            const pageDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const refreshed = await loadView(responseRoute.view, {
                targetUrl: responseRoute.targetUrl,
                routeParams: responseRoute.routeParams,
                section: responseRoute.section,
                pageDocument: pageDocument,
                responseUrl: response.url
            });
            if (!refreshed && currentRequest === requestNumber) {
                showNavigationError('The workspace could not be refreshed after the action.');
            }
        } catch (error) {
            if (currentRequest === requestNumber) {
                console.error('Admin form action failed:', error);
                showNavigationError(error.message || 'The requested action could not be completed.');
            }
        } finally {
            if (submitter) submitter.disabled = submitButtonWasDisabled;
            if (currentRequest === requestNumber) {
                shell.removeAttribute('aria-busy');
            }
        }
    }

    async function pollLiveState() {
        if (livePollInProgress || document.hidden) return;
        livePollInProgress = true;

        try {
            const response = await fetch(new URL('realtime-state.php', window.location.href).href, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error('Live updates could not be checked (HTTP ' + response.status + ').');
            }

            const state = await response.json();
            if (
                !state ||
                typeof state.revision !== 'string' ||
                !Number.isSafeInteger(state.pendingReservations) ||
                state.pendingReservations < 0
            ) {
                throw new Error('Live updates returned an invalid response.');
            }

            setLiveStatus('online');
            updateNotificationCount(state.pendingReservations);
            const changed = liveRevision !== null && liveRevision !== state.revision;
            if (liveRevision === null) {
                liveRevision = state.revision;
            } else if (changed) {
                const focusedControl = activeMain.contains(document.activeElement)
                    && document.activeElement.matches('input, select, textarea, button, [contenteditable="true"]');
                const modalIsOpen = activeMain.querySelector('.modal.show');
                const navigationInProgress = shell.getAttribute('aria-busy') === 'true';
                if (focusedControl || modalIsOpen || navigationInProgress || hasUnsavedFormChanges()) return;

                const refreshed = await loadView(activeView, {
                    section: activeSection,
                    updateAddress: false,
                    preserveScroll: true,
                    liveRefresh: true
                });
                if (refreshed) liveRevision = state.revision;
            }
        } catch (error) {
            if (liveStatus !== 'offline') {
                console.error('Admin live updates failed:', error);
            }
            setLiveStatus('offline');
        } finally {
            livePollInProgress = false;
        }
    }

    document.addEventListener('click', function (event) {
        const shellViewLink = event.target.closest('[data-admin-shell-view]');
        if (
            shellViewLink &&
            isUnmodifiedPrimaryClick(event) &&
            !event.defaultPrevented
        ) {
            event.preventDefault();
            closeNotifications(false);
            const view = shellViewLink.dataset.adminShellView;
            if (viewRoutes[view]) loadView(view);
            return;
        }

        const monthLink = event.target.closest('.facility-calendar .calendar-heading a[href]');
        if (
            monthLink &&
            activeView === 'calendar' &&
            activeMain.contains(monthLink) &&
            !event.defaultPrevented &&
            event.button === 0 &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.shiftKey &&
            !event.altKey
        ) {
            event.preventDefault();
            const target = new URL(monthLink.href, window.location.href);
            const month = target.searchParams.get('month');
            if (month) {
                loadView('calendar', {
                    targetUrl: target.href,
                    routeParams: { month: month }
                });
            }
            return;
        }

        const contentLink = event.target.closest('a[href]');
        if (
            contentLink &&
            activeMain.contains(contentLink) &&
            isUnmodifiedPrimaryClick(event) &&
            !contentLink.hasAttribute('download') &&
            (!contentLink.target || contentLink.target === '_self') &&
            !contentLink.getAttribute('href').startsWith('#')
        ) {
            const route = routeFromUrl(contentLink.href);
            if (route) {
                event.preventDefault();
                loadView(route.view, {
                    targetUrl: route.targetUrl,
                    routeParams: route.routeParams,
                    section: route.section
                });
                return;
            }
        }

        const link = event.target.closest('.admin-sidebar-link[data-spa-view]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        const view = link.dataset.spaView || 'dashboard';
        const section = link.dataset.spaSection || '';
        if (view === activeView && section === activeSection) {
            const target = section ? document.getElementById(section) : null;
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            shell.classList.remove('is-sidebar-open');
            const toggle = document.querySelector('.admin-sidebar-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            return;
        }
        loadView(view, { section: section });
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (event.defaultPrevented || !activeMain.contains(form) || form.target && form.target !== '_self') return;

        const submitter = event.submitter;
        if (activeView === 'calendar' && form.matches('.reservation-form')) {
            event.preventDefault();
            submitCalendarReservation(form, submitter);
            return;
        }

        const action = submitter && submitter.hasAttribute('formaction') ? submitter.formAction : form.action;
        const route = routeFromUrl(action);
        if (!route) return;

        const method = (submitter && submitter.hasAttribute('formmethod') ? submitter.formMethod : form.method).toUpperCase();
        if (method !== 'GET' && method !== 'POST') return;

        event.preventDefault();
        submitPageForm(form, submitter);
    });

    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view') || 'dashboard';
        const section = window.location.hash.slice(1);
        loadView(viewRoutes[view] ? view : 'dashboard', {
            section: section === 'users' || section === 'facilities' ? section : '',
            routeParams: queryParamsFromUrl(new URL(window.location.href)),
            updateAddress: false
        });
    });

    const initialParams = new URLSearchParams(window.location.search);
    const initialView = initialParams.get('view');
    const initialSection = window.location.hash.slice(1);
    if ((initialView && viewRoutes[initialView]) || initialSection === 'users' || initialSection === 'facilities') {
        loadView(initialView && viewRoutes[initialView] ? initialView : 'dashboard', {
            section: initialSection === 'users' || initialSection === 'facilities' ? initialSection : '',
            routeParams: queryParamsFromUrl(new URL(window.location.href)),
            updateAddress: false
        });
    }

    pollLiveState();
    window.setInterval(pollLiveState, 10000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollLiveState();
    });
})();
