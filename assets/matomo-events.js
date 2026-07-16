/**
 * matomo-events.js
 *
 * Leichtgewichtiger Browser-Tracker für server-seitiges Matomo-Tracking.
 * Kein Matomo-JS nötig. Fängt Klicks per Event-Delegation ab und sendet
 * sie über den REDAXO-Endpunkt (MatomoEventApi) an Matomo weiter.
 *
 * Erkannte Ereignisse (konfigurierbar via window.MatomoEventsConfig):
 *   - Download-Links  (Links auf konfigurierte Dateiendungen)
 *   - Outbound-Links  (Links auf eine fremde Domain)
 *   - [data-matomo-event]-Attribute (beliebige Custom-Events)
 *
 * Konfiguration (optionales globales Objekt vor dem Script-Tag setzen):
 *   window.MatomoEventsConfig = {
 *     endpoint:   '/index.php?rex-api-call=matomo_event',  // Standard
 *     extensions: ['pdf','doc','docx','xls','xlsx','zip','rar','7z','tar',
 *                  'gz','mp3','mp4','ogg','webm','avi','mov'],
 *     trackOutbound:  true,
 *     trackDownloads: true,
 *   };
 *
 * Custom-Event per data-Attribut (kein JS nötig):
 *   <button data-matomo-event='{"category":"Form","action":"Submit","name":"Contact"}'>
 *   <a href="..." data-matomo-event='{"category":"CTA","action":"Click"}'>
 */
(function () {
    'use strict';

    /* ── Config ───────────────────────────────────────────────────────── */
    const cfg = Object.assign({
        endpoint:       document.querySelector('meta[name="matomo-event-endpoint"]')
                            ?.getAttribute('content')
                        ?? '/index.php?rex-api-call=matomo_event',
        extensions:     ['pdf','doc','docx','xls','xlsx','ppt','pptx',
                         'zip','rar','7z','tar','gz','bz2',
                         'mp3','mp4','ogg','webm','avi','mov','wmv',
                         'exe','dmg','pkg','deb','rpm'],
        trackOutbound:  true,
        trackDownloads: true,
    }, window.MatomoEventsConfig ?? {});

    const currentHost = location.hostname;

    /* ── Helper ───────────────────────────────────────────────────────── */

    function send(payload) {
        payload.page_url = location.href;

        // navigator.sendBeacon preferred (survives page unload)
        const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(cfg.endpoint, blob);
        } else {
            fetch(cfg.endpoint, {
                method:      'POST',
                body:        blob,
                keepalive:   true,
                credentials: 'same-origin',
            }).catch(function () { /* fire-and-forget */ });
        }
    }

    function extension(url) {
        try {
            const path = new URL(url).pathname;
            const dot  = path.lastIndexOf('.');
            return dot !== -1 ? path.slice(dot + 1).toLowerCase() : '';
        } catch (_) {
            return '';
        }
    }

    function isDownload(url) {
        return cfg.trackDownloads && cfg.extensions.includes(extension(url));
    }

    function isOutbound(url) {
        if (!cfg.trackOutbound) return false;
        try {
            return new URL(url).hostname !== currentHost;
        } catch (_) {
            return false;
        }
    }

    /* ── Click delegation ─────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        const target = e.target;
        if (!(target instanceof Element)) return;

        // ── Custom data-matomo-event attribute ─────────────────────────
        const eventEl = target.closest('[data-matomo-event]');
        if (eventEl) {
            try {
                const eventData = JSON.parse(eventEl.getAttribute('data-matomo-event') ?? '{}');
                if (eventData.category && eventData.action) {
                    send(Object.assign({ type: 'event' }, eventData));
                }
            } catch (_) { /* ignore malformed JSON */ }
        }

        // ── Anchor links ───────────────────────────────────────────────
        const anchor = target.closest('a[href]');
        if (!anchor) return;

        const href = anchor.getAttribute('href') ?? '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        let absoluteUrl;
        try {
            absoluteUrl = new URL(href, location.href).href;
        } catch (_) {
            return;
        }

        if (isDownload(absoluteUrl)) {
            send({ type: 'download', url: absoluteUrl });
            return; // don't double-count as outbound
        }

        if (isOutbound(absoluteUrl)) {
            send({ type: 'outbound', url: absoluteUrl });
        }
    });

    /* ── Form submissions ─────────────────────────────────────────────── */
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // Formular-Name aus id > name > action-Pfad ableiten
        const formName = form.id || form.getAttribute('name')
            || form.action.replace(location.origin, '').replace(/\?.*$/, '')
            || 'form';

        send({ type: 'event', category: 'Form', action: 'Submit', name: formName });
    });

})();
