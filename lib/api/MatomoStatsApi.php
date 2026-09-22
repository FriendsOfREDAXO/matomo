<?php

namespace FriendsOfRedaxo\Matomo;

use DateInterval;
use DateTimeImmutable;
use Exception;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_config;
use rex_response;

/**
 * Liefert die Kennzahlen der Übersichtsseite abschnittsweise als JSON, damit die
 * Seite sofort rendert und Matomo-Antworten nachgeladen werden können.
 *
 * Parameter: part (summary|chart|pages|referrers|devices|countries),
 *            site (0 = alle erlaubten Websites), range (today|yesterday|last7|last30|month|year)
 */
class MatomoStatsApi extends rex_api_function
{
    protected $published = false;

    private const RANGES = ['today', 'yesterday', 'last7', 'last30', 'month', 'year'];

    public function execute()
    {
        $user = rex::getUser();
        if (null === $user || !$user->hasPerm('matomo[overview]')) {
            throw new rex_api_exception('Keine Berechtigung');
        }

        // Session freigeben, sonst serialisiert PHP die parallelen Abschnitts-Requests
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        $part = rex_request('part', 'string', 'summary');
        $siteId = rex_request('site', 'int', 0);
        $range = rex_request('range', 'string', 'last7');
        if (!in_array($range, self::RANGES, true)) {
            $range = 'last7';
        }

        try {
            $api = new MatomoApi((string) rex_config::get('matomo', 'matomo_url', ''), (string) rex_config::get('matomo', 'admin_token', ''));
            $sites = self::allowedSites($api);
            if ($siteId > 0) {
                $sites = array_values(array_filter($sites, static fn (array $s): bool => (int) $s['idsite'] === $siteId));
                if ([] === $sites) {
                    throw new Exception('Website nicht erlaubt');
                }
            }
            $data = match ($part) {
                'summary' => self::summary($api, $sites, $range),
                'chart' => self::chart($api, $sites, $range),
                'pages' => self::pages($api, $sites, $range),
                'referrers' => self::referrers($api, $sites, $range),
                'devices' => self::simpleList($api, $sites, $range, 'DevicesDetection.getType', 8),
                'countries' => self::simpleList($api, $sites, $range, 'UserCountry.getCountry', 8, true),
                default => throw new Exception('Unbekannter Abschnitt'),
            };
            $payload = ['success' => true, 'part' => $part, 'data' => $data];
        } catch (Exception $e) {
            $payload = ['success' => false, 'part' => $part, 'message' => $e->getMessage()];
        }

        rex_response::cleanOutputBuffers();
        rex_response::sendJson($payload);
        exit;
    }

    public function requiresCsrfProtection()
    {
        return false;
    }

    /**
     * Websites, die der aktuelle Benutzer sehen darf (YRewrite-Filter + persönlicher Zugang).
     *
     * @return list<array<string, mixed>>
     * @throws Exception
     */
    public static function allowedSites(MatomoApi $api): array
    {
        $sites = $api->getSites();
        if (YRewriteHelper::isAvailable()) {
            $sites = YRewriteHelper::filterMatomoSitesByYRewrite($sites);
        }
        return array_values(UserAccess::filterSites($sites));
    }

    /**
     * Matomo-Parameter (period/date) für den aktuellen und den vorherigen Zeitraum
     * sowie für den Verlauf.
     *
     * @return array{current: array{period: string, date: string}, previous: array{period: string, date: string}, series: array{period: string, date: string, kind: string}}
     */
    public static function resolveRange(string $range): array
    {
        $today = new DateTimeImmutable('today');
        $fmt = static fn (DateTimeImmutable $d): string => $d->format('Y-m-d');
        switch ($range) {
            case 'today':
                return [
                    'current' => ['period' => 'day', 'date' => 'today'],
                    'previous' => ['period' => 'day', 'date' => 'yesterday'],
                    'series' => ['period' => 'day', 'date' => 'today', 'kind' => 'hour'],
                ];
            case 'yesterday':
                $y = $today->sub(new DateInterval('P1D'));
                return [
                    'current' => ['period' => 'day', 'date' => 'yesterday'],
                    'previous' => ['period' => 'day', 'date' => $fmt($y->sub(new DateInterval('P1D')))],
                    'series' => ['period' => 'day', 'date' => 'yesterday', 'kind' => 'hour'],
                ];
            case 'month':
                $start = $today->modify('first day of this month');
                $prevStart = $start->modify('first day of previous month');
                return [
                    'current' => ['period' => 'range', 'date' => $fmt($start) . ',' . $fmt($today)],
                    'previous' => ['period' => 'range', 'date' => $fmt($prevStart) . ',' . $fmt($prevStart->modify('last day of this month'))],
                    'series' => ['period' => 'day', 'date' => $fmt($start) . ',' . $fmt($today), 'kind' => 'day'],
                ];
            case 'year':
                $start = $today->modify('first day of january this year');
                $prevStart = $start->modify('-1 year');
                return [
                    'current' => ['period' => 'range', 'date' => $fmt($start) . ',' . $fmt($today)],
                    'previous' => ['period' => 'range', 'date' => $fmt($prevStart) . ',' . $fmt($prevStart->modify('last day of december this year'))],
                    'series' => ['period' => 'month', 'date' => $fmt($start) . ',' . $fmt($today), 'kind' => 'month'],
                ];
            case 'last30':
            case 'last7':
            default:
                $days = 'last30' === $range ? 30 : 7;
                $start = $today->sub(new DateInterval('P' . ($days - 1) . 'D'));
                $prevEnd = $start->sub(new DateInterval('P1D'));
                $prevStart = $prevEnd->sub(new DateInterval('P' . ($days - 1) . 'D'));
                return [
                    'current' => ['period' => 'range', 'date' => $fmt($start) . ',' . $fmt($today)],
                    'previous' => ['period' => 'range', 'date' => $fmt($prevStart) . ',' . $fmt($prevEnd)],
                    'series' => ['period' => 'day', 'date' => $fmt($start) . ',' . $fmt($today), 'kind' => 'day'],
                ];
        }
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function summary(MatomoApi $api, array $sites, string $range): array
    {
        $r = self::resolveRange($range);
        $requests = [];
        foreach ($sites as $site) {
            $id = (int) $site['idsite'];
            $requests[] = ['method' => 'VisitsSummary.get', 'idSite' => $id] + $r['current'];
            $requests[] = ['method' => 'VisitsSummary.get', 'idSite' => $id] + $r['previous'];
            $requests[] = ['method' => 'Goals.get', 'idSite' => $id] + $r['current'];
        }
        $results = $api->bulk($requests);

        $current = self::emptyMetrics();
        $previous = self::emptyMetrics();
        $perSite = [];
        foreach ($sites as $i => $site) {
            $cur = self::metrics($results[$i * 3] ?? []);
            $prev = self::metrics($results[$i * 3 + 1] ?? []);
            $goals = is_array($results[$i * 3 + 2] ?? null) ? $results[$i * 3 + 2] : [];
            $cur['conversions'] = (int) ($goals['nb_conversions'] ?? 0);
            $cur['revenue'] = (float) ($goals['revenue'] ?? 0);
            $current = self::addMetrics($current, $cur);
            $previous = self::addMetrics($previous, $prev);
            $perSite[] = [
                'idsite' => (int) $site['idsite'],
                'name' => (string) $site['name'],
                'host' => (string) parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST),
                'open_url' => UserAccess::openUrl((int) $site['idsite']),
                'metrics' => self::finish($cur),
                'previous' => self::finish($prev),
            ];
        }

        // Matomo berechnet eindeutige Besucher nicht für freie Zeiträume (period=range)
        $hasUnique = 'range' !== $r['current']['period'];

        return [
            'current' => self::finish($current),
            'previous' => self::finish($previous),
            'sites' => $perSite,
            'range' => $r['current'],
            'has_unique' => $hasUnique,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function chart(MatomoApi $api, array $sites, string $range): array
    {
        $r = self::resolveRange($range)['series'];
        $requests = [];
        foreach ($sites as $site) {
            $requests[] = 'hour' === $r['kind']
                ? ['method' => 'VisitTime.getVisitInformationPerServerTime', 'idSite' => (int) $site['idsite'], 'period' => $r['period'], 'date' => $r['date']]
                : ['method' => 'VisitsSummary.get', 'idSite' => (int) $site['idsite'], 'period' => $r['period'], 'date' => $r['date']];
        }
        $results = $api->bulk($requests);

        $visits = [];
        $actions = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            if ('hour' === $r['kind']) {
                foreach ($result as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $label = (string) ($row['label'] ?? '');
                    $visits[$label] = ($visits[$label] ?? 0) + (int) ($row['nb_visits'] ?? 0);
                    $actions[$label] = ($actions[$label] ?? 0) + (int) ($row['nb_actions'] ?? 0);
                }
            } else {
                // Einzelner Tag liefert flache Metriken, ein Zeitraum ein Datum => Metriken
                $rows = isset($result['nb_visits']) ? [$r['date'] => $result] : $result;
                foreach ($rows as $date => $row) {
                    $visits[(string) $date] = ($visits[(string) $date] ?? 0) + (int) (is_array($row) ? ($row['nb_visits'] ?? 0) : 0);
                    $actions[(string) $date] = ($actions[(string) $date] ?? 0) + (int) (is_array($row) ? ($row['nb_actions'] ?? 0) : 0);
                }
            }
        }
        ksort($visits);
        ksort($actions);

        return [
            'kind' => $r['kind'],
            'labels' => array_keys($visits),
            'visits' => array_values($visits),
            'actions' => array_values($actions),
        ];
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function pages(MatomoApi $api, array $sites, string $range): array
    {
        $r = self::resolveRange($range)['current'];
        $requests = [];
        foreach ($sites as $site) {
            $requests[] = ['method' => 'Actions.getPageUrls', 'idSite' => (int) $site['idsite'], 'flat' => 1, 'filter_limit' => 25, 'filter_sort_column' => 'nb_hits'] + $r;
        }
        $merged = [];
        foreach ($api->bulk($requests) as $i => $result) {
            foreach (is_array($result) ? $result : [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $key = (string) ($row['url'] ?? $row['label'] ?? '');
                if ('' === $key) {
                    continue;
                }
                $entry = $merged[$key] ?? ['label' => (string) ($row['label'] ?? $key), 'url' => (string) ($row['url'] ?? ''), 'host' => (string) parse_url((string) ($sites[$i]['main_url'] ?? ''), PHP_URL_HOST), 'hits' => 0, 'visits' => 0, 'time' => 0];
                $entry['hits'] += (int) ($row['nb_hits'] ?? 0);
                $entry['visits'] += (int) ($row['nb_visits'] ?? 0);
                $entry['time'] += (int) ($row['sum_time_spent'] ?? 0);
                $merged[$key] = $entry;
            }
        }
        usort($merged, static fn (array $a, array $b): int => $b['hits'] <=> $a['hits']);
        $rows = [];
        foreach (array_slice($merged, 0, 10) as $entry) {
            $entry['avg_time'] = $entry['hits'] > 0 ? (int) round($entry['time'] / $entry['hits']) : 0;
            unset($entry['time']);
            $rows[] = $entry;
        }
        return ['rows' => $rows];
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function referrers(MatomoApi $api, array $sites, string $range): array
    {
        $r = self::resolveRange($range)['current'];
        $requests = [];
        foreach ($sites as $site) {
            $requests[] = ['method' => 'Referrers.getReferrerType', 'idSite' => (int) $site['idsite']] + $r;
            $requests[] = ['method' => 'Referrers.getWebsites', 'idSite' => (int) $site['idsite'], 'filter_limit' => 10, 'filter_sort_column' => 'nb_visits'] + $r;
        }
        $results = $api->bulk($requests);
        $types = [];
        $websites = [];
        foreach ($sites as $i => $site) {
            foreach (is_array($results[$i * 2] ?? null) ? $results[$i * 2] : [] as $row) {
                if (is_array($row)) {
                    $label = (string) ($row['label'] ?? '');
                    $types[$label] = ($types[$label] ?? 0) + (int) ($row['nb_visits'] ?? 0);
                }
            }
            foreach (is_array($results[$i * 2 + 1] ?? null) ? $results[$i * 2 + 1] : [] as $row) {
                if (is_array($row)) {
                    $label = (string) ($row['label'] ?? '');
                    $websites[$label] = ($websites[$label] ?? 0) + (int) ($row['nb_visits'] ?? 0);
                }
            }
        }
        arsort($types);
        arsort($websites);
        return [
            'types' => self::toRows($types),
            'websites' => array_slice(self::toRows($websites), 0, 6),
        ];
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function simpleList(MatomoApi $api, array $sites, string $range, string $method, int $limit, bool $withCode = false): array
    {
        $r = self::resolveRange($range)['current'];
        $requests = [];
        foreach ($sites as $site) {
            $requests[] = ['method' => $method, 'idSite' => (int) $site['idsite'], 'filter_limit' => 25] + $r;
        }
        $values = [];
        $codes = [];
        foreach ($api->bulk($requests) as $result) {
            foreach (is_array($result) ? $result : [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = (string) ($row['label'] ?? '');
                $values[$label] = ($values[$label] ?? 0) + (int) ($row['nb_visits'] ?? 0);
                if ($withCode && isset($row['code'])) {
                    $codes[$label] = (string) $row['code'];
                }
            }
        }
        arsort($values);
        $rows = array_slice(self::toRows($values), 0, $limit);
        if ($withCode) {
            foreach ($rows as &$row) {
                $row['code'] = $codes[$row['label']] ?? '';
            }
            unset($row);
        }
        return ['rows' => $rows];
    }

    /**
     * @param array<string, int> $values
     * @return list<array{label: string, visits: int}>
     */
    private static function toRows(array $values): array
    {
        $rows = [];
        foreach ($values as $label => $visits) {
            if ($visits > 0) {
                $rows[] = ['label' => (string) $label, 'visits' => $visits];
            }
        }
        return $rows;
    }

    /** @return array<string, int|float> */
    private static function emptyMetrics(): array
    {
        return ['visits' => 0, 'unique' => 0, 'actions' => 0, 'bounce_count' => 0, 'visit_length' => 0, 'converted' => 0, 'conversions' => 0, 'revenue' => 0.0, 'max_actions' => 0];
    }

    /**
     * @param mixed $row
     * @return array<string, int|float>
     */
    private static function metrics($row): array
    {
        $m = self::emptyMetrics();
        if (!is_array($row) || !isset($row['nb_visits'])) {
            return $m;
        }
        $m['visits'] = (int) $row['nb_visits'];
        $m['unique'] = (int) ($row['nb_uniq_visitors'] ?? 0);
        $m['actions'] = (int) ($row['nb_actions'] ?? 0);
        $m['bounce_count'] = (int) ($row['bounce_count'] ?? 0);
        $m['visit_length'] = (int) ($row['sum_visit_length'] ?? 0);
        $m['converted'] = (int) ($row['nb_visits_converted'] ?? 0);
        $m['max_actions'] = (int) ($row['max_actions'] ?? 0);
        return $m;
    }

    /**
     * @param array<string, int|float> $a
     * @param array<string, int|float> $b
     * @return array<string, int|float>
     */
    private static function addMetrics(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            $a[$key] = 'max_actions' === $key ? max($a[$key] ?? 0, $value) : ($a[$key] ?? 0) + $value;
        }
        return $a;
    }

    /**
     * Abgeleitete Kennzahlen (Absprungrate, Ø Dauer, Aktionen/Besuch).
     *
     * @param array<string, int|float> $m
     * @return array<string, int|float>
     */
    private static function finish(array $m): array
    {
        $visits = (int) ($m['visits'] ?? 0);
        $m['bounce_rate'] = $visits > 0 ? round(((int) $m['bounce_count'] / $visits) * 100, 1) : 0;
        $m['avg_time'] = $visits > 0 ? (int) round((int) $m['visit_length'] / $visits) : 0;
        $m['actions_per_visit'] = $visits > 0 ? round((int) $m['actions'] / $visits, 1) : 0;
        $m['conversion_rate'] = $visits > 0 ? round(((int) ($m['converted'] ?? 0) / $visits) * 100, 1) : 0;
        return $m;
    }
}
