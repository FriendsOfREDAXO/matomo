<?php

namespace FriendsOfRedaxo\Matomo;

use rex_config;

/**
 * Static facade for server-side Matomo tracking.
 *
 * Provides convenience helpers that can be called from anywhere in REDAXO
 * (modules, templates, rex_api functions, plugins) without needing to
 * instantiate or configure the Tracker manually.
 *
 * All methods are no-ops when server-side tracking is disabled or not fully
 * configured, so they are safe to call unconditionally.
 *
 * Usage examples
 * --------------
 * // Track a custom event (e.g. form submission):
 * MatomoTrack::event('Form', 'Submit', 'Contact form');
 *
 * // Track a file download:
 * MatomoTrack::download('https://example.com/files/brochure.pdf');
 *
 * // Track an outbound link:
 * MatomoTrack::outboundLink('https://partner.example.com');
 *
 * // Track a search:
 * MatomoTrack::search('redaxo templates', 'documentation', 42);
 *
 * // Track a custom goal:
 * MatomoTrack::goal(3, 19.90);
 *
 * @package FriendsOfRedaxo\Matomo
 */
class MatomoTrack
{
    /**
     * Returns a ready-to-use Tracker instance or null if tracking is
     * not enabled / not configured.
     */
    private static function tracker(): ?Tracker
    {
        if (!(bool) rex_config::get('matomo', 'server_side_tracking', false)) {
            return null;
        }

        $siteId = (int) rex_config::get('matomo', 'server_side_site_id', 0);
        if ($siteId === 0) {
            return null;
        }

        return Tracker::factory($siteId);
    }

    /**
     * Tracks a custom event.
     *
     * @param string          $category  Event category (e.g. "Form", "Video", "Download")
     * @param string          $action    Event action  (e.g. "Submit", "Play", "Click")
     * @param string|null     $name      Optional event name / label
     * @param float|int|null  $value     Optional numeric event value
     */
    public static function event(string $category, string $action, ?string $name = null, float|int|null $value = null): bool
    {
        $tracker = self::tracker();
        if ($tracker === null) {
            return false;
        }

        return $tracker->trackEvent($category, $action, $name, $value);
    }

    /**
     * Tracks a file download.
     *
     * @param string $fileUrl  Absolute URL of the file (must match Matomo's download-extension list)
     * @param string $pageUrl  Page where the download was triggered (empty = current request URI)
     */
    public static function download(string $fileUrl, string $pageUrl = ''): bool
    {
        $tracker = self::tracker();
        if ($tracker === null) {
            return false;
        }

        return $tracker->trackDownload($fileUrl, $pageUrl);
    }

    /**
     * Tracks an outbound link click.
     *
     * @param string $linkUrl  Absolute URL of the external target
     * @param string $pageUrl  Page where the link was clicked (empty = current request URI)
     */
    public static function outboundLink(string $linkUrl, string $pageUrl = ''): bool
    {
        $tracker = self::tracker();
        if ($tracker === null) {
            return false;
        }

        return $tracker->trackOutboundLink($linkUrl, $pageUrl);
    }

    /**
     * Tracks an internal site search.
     *
     * @param string      $keyword       Search term
     * @param string|null $category      Optional search category
     * @param int|null    $resultCount   Number of results returned
     */
    public static function search(string $keyword, ?string $category = null, ?int $resultCount = null): bool
    {
        $tracker = self::tracker();
        if ($tracker === null) {
            return false;
        }

        return $tracker->trackSiteSearch($keyword, $category, $resultCount);
    }

    /**
     * Tracks a goal conversion.
     *
     * @param int             $goalId   Matomo goal ID
     * @param float|int       $revenue  Optional revenue value
     */
    public static function goal(int $goalId, float|int $revenue = 0): bool
    {
        $tracker = self::tracker();
        if ($tracker === null) {
            return false;
        }

        return $tracker->trackGoal($goalId, $revenue);
    }

    /**
     * Tracks a page view manually (e.g. from a custom controller or API).
     *
     * @param string      $title  Page title
     * @param string|null $url    Full URL (empty = current request URI)
     */
    public static function pageView(string $title, ?string $url = null): bool
    {
        $tracker = self::tracker();
        if ($tracker === null) {
            return false;
        }

        return $tracker->trackPageView($title, $url);
    }
}
