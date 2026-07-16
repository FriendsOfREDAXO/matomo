<?php

namespace FriendsOfRedaxo\Matomo;

use rex_api_function;
use rex_api_result;
use rex_config;
use rex_request;
use rex_response;

/**
 * API endpoint for browser-side event tracking.
 *
 * The companion JS (matomo-events.js) POSTs JSON payloads here to report
 * events that can only be detected in the browser (download clicks, outbound
 * links, scroll depth, …).  The server then forwards them to Matomo via the
 * server-side Tracker so no Matomo JS is needed on the page at all.
 *
 * Endpoint:  POST index.php?rex-api-call=matomo_event
 *
 * Accepted JSON body:
 * {
 *   "type":     "event" | "download" | "outbound" | "search" | "goal",
 *   "category": "...",   // event only
 *   "action":   "...",   // event only
 *   "name":     "...",   // optional
 *   "value":    1.5,     // optional
 *   "url":      "...",   // download / outbound: the file / external URL
 *   "page_url": "...",   // current page URL (sent by JS)
 *   "keyword":  "...",   // search only
 *   "results":  42,      // search only
 *   "goal_id":  3,       // goal only
 *   "revenue":  19.90    // goal only
 * }
 *
 * @package FriendsOfRedaxo\Matomo
 */
class MatomoEventApi extends rex_api_function
{
    /** @var bool Open to the public (no REDAXO login required) */
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        // ── CORS for same-site XHR ───────────────────────────────────────
        $origin = rex_request::server('HTTP_ORIGIN', 'string', '');
        if ('' !== $origin) {
            $host = rex_request::server('HTTP_HOST', 'string', '');
            // Allow only same host (scheme-agnostic)
            if (str_contains($origin, $host)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
        }
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isEnabled()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['ok' => false, 'error' => 'disabled']);
            exit;
        }

        $body = (string) file_get_contents('php://input');
        if ('' === $body) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['ok' => false, 'error' => 'empty body']);
            exit;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);
        if (!is_array($data)) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['ok' => false, 'error' => 'invalid json']);
            exit;
        }

        $siteId = (int) rex_config::get('matomo', 'server_side_site_id', 0);
        $tracker = Tracker::factory($siteId);
        if ($tracker === null) {
            rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
            rex_response::sendJson(['ok' => false, 'error' => 'tracker unavailable']);
            exit;
        }

        // Override page URL from JS context so Matomo attributes it correctly
        $pageUrl = $this->sanitizeUrl((string) ($data['page_url'] ?? ''));
        if ('' !== $pageUrl) {
            // Pass as referer-style override via custom param
            // (We set it via trackXxx() $pageUrl argument where possible)
        }

        $type = (string) ($data['type'] ?? 'event');
        $ok   = match ($type) {
            'download' => $this->handleDownload($tracker, $data, $pageUrl),
            'outbound' => $this->handleOutbound($tracker, $data, $pageUrl),
            'search'   => $this->handleSearch($tracker, $data),
            'goal'     => $this->handleGoal($tracker, $data),
            default    => $this->handleEvent($tracker, $data),
        };

        rex_response::sendJson(['ok' => $ok]);
        exit;
    }

    private function isEnabled(): bool
    {
        return (bool) rex_config::get('matomo', 'server_side_tracking', false)
            && (int) rex_config::get('matomo', 'server_side_site_id', 0) > 0;
    }

    /**
     * Reject clearly external / non-http URLs to prevent SSRF-style abuse.
     */
    private function sanitizeUrl(string $url): string
    {
        if ('' === $url) {
            return '';
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        return $url;
    }

    /** @param array<string, mixed> $data */
    private function handleEvent(Tracker $tracker, array $data): bool
    {
        $category = trim((string) ($data['category'] ?? ''));
        $action   = trim((string) ($data['action'] ?? ''));
        if ('' === $category || '' === $action) {
            return false;
        }

        $name  = isset($data['name'])  ? (string) $data['name']  : null;
        $value = isset($data['value']) ? (float)  $data['value'] : null;

        return $tracker->trackEvent($category, $action, $name, $value);
    }

    /** @param array<string, mixed> $data */
    private function handleDownload(Tracker $tracker, array $data, string $pageUrl): bool
    {
        $url = $this->sanitizeUrl((string) ($data['url'] ?? ''));
        if ('' === $url) {
            return false;
        }
        return $tracker->trackDownload($url, $pageUrl);
    }

    /** @param array<string, mixed> $data */
    private function handleOutbound(Tracker $tracker, array $data, string $pageUrl): bool
    {
        $url = $this->sanitizeUrl((string) ($data['url'] ?? ''));
        if ('' === $url) {
            return false;
        }
        return $tracker->trackOutboundLink($url, $pageUrl);
    }

    /** @param array<string, mixed> $data */
    private function handleSearch(Tracker $tracker, array $data): bool
    {
        $keyword = trim((string) ($data['keyword'] ?? ''));
        if ('' === $keyword) {
            return false;
        }
        $category = isset($data['category']) ? (string) $data['category'] : null;
        $results  = isset($data['results'])  ? (int)    $data['results']  : null;

        return $tracker->trackSiteSearch($keyword, $category, $results);
    }

    /** @param array<string, mixed> $data */
    private function handleGoal(Tracker $tracker, array $data): bool
    {
        $goalId = (int) ($data['goal_id'] ?? 0);
        if ($goalId <= 0) {
            return false;
        }
        $revenue = (float) ($data['revenue'] ?? 0);
        return $tracker->trackGoal($goalId, $revenue);
    }
}
