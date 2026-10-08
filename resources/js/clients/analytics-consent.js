export function initAnalyticsConsent() {
    const root = document.querySelector('[data-analytics-consent]');
    if (!root) return;

    const config = JSON.parse(root.querySelector('script[type="application/json"]').textContent);
    const notice = root.querySelector('[data-consent-notice]');
    const preferences = root.querySelector('[data-consent-preferences]');
    const key = `portal-analytics-v1:${config.scope}`;
    let loaded = false;
    let decision;

    try {
        const saved = JSON.parse(localStorage.getItem(key));
        if (['accepted', 'rejected'].includes(saved?.choice)) {
            decision = saved.choice;
        }
    } catch (_) {
        // Storage may be unavailable; the current page still respects the choice.
    }

    function script(src) {
        const element = document.createElement('script');
        element.async = true;
        element.src = src;
        document.head.append(element);
    }

    function loadAnalytics() {
        if (loaded) return;
        loaded = true;

        if (config.matomo) {
            const url = config.matomo.url.replace(/\/?$/, '/');
            window._paq = window._paq || [];
            window._paq.push(['setTrackerUrl', `${url}matomo.php`], ['setSiteId', config.matomo.id]);
            if (config.matomo.userId) window._paq.push(['setUserId', config.matomo.userId]);
            window._paq.push(['trackPageView'], ['enableLinkTracking']);
            script(`${url}matomo.js`);
        } else if (config.trackingId) {
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };
            window.gtag('js', new Date());
            window.gtag('config', config.trackingId, { anonymize_ip: true });
            script(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(config.trackingId)}`);
        }

        if (config.googleAnalyticsKey) {
            window.GoogleAnalyticsObject = 'ga';
            window.ga = function () { (window.ga.q = window.ga.q || []).push(arguments); };
            window.ga.l = Date.now();
            window.ga('create', config.googleAnalyticsKey, 'auto');
            window.ga('set', 'anonymizeIp', true);
            window.ga('send', 'pageview');
            window.trackEvent = (category, action) => window.ga('send', 'event', category, action);
            script('https://www.google-analytics.com/analytics.js');
        }

        if (config.tagManager) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            script(`https://www.googletagmanager.com/gtm.js?id=${config.tagManager}`);
        }
    }

    function render() {
        notice.hidden = !!decision;
        preferences.hidden = !decision;
    }

    root.querySelectorAll('[data-consent-choice]').forEach(button => {
        button.addEventListener('click', () => {
            decision = button.dataset.consentChoice;
            try {
                localStorage.setItem(key, JSON.stringify({ choice: decision }));
            } catch (_) {}
            render();
            if (decision === 'accepted') loadAnalytics();
            // Unload SDK timers and listeners after withdrawal.
            else if (loaded) window.location.reload();
            preferences.focus();
        });
    });
    preferences.addEventListener('click', () => {
        notice.hidden = false;
        preferences.hidden = true;
        notice.querySelector('button').focus();
    });
    render();
    if (decision === 'accepted') loadAnalytics();
}
