<?php

namespace FriendsOfRedaxo\Matomo;

use KLXM\InfoCenter\AbstractWidget;
use rex;
use rex_addon;
use rex_config;
use rex_url;
use rex_escape;
use rex_i18n;
use Exception;

class MatomoInfoCenterWidget extends AbstractWidget
{
    protected bool $supportsLazyLoading = false;

    public function __construct()
    {
        parent::__construct();
        $this->title = '📊 ' . rex_i18n::msg('matomo_widget_title');
        $this->priority = 3; // Nach Article Widget, vor Upkeep Widget
    }

    public function render(): string
    {
        $addon = rex_addon::get('matomo');
        
        // Prüfen ob Matomo konfiguriert ist
        $matomo_url = rex_config::get('matomo', 'matomo_url', '');
        $admin_token = rex_config::get('matomo', 'admin_token', '');
        
        if (!$matomo_url || !$admin_token) {
            return $this->wrapContent($this->renderNotConfigured());
        }
        
        try {
            $api = new MatomoApi($matomo_url, $admin_token);
            $sites = $api->getSites();
            
            // YRewrite Integration - nur YRewrite Domains anzeigen (falls verfügbar)
            if (class_exists('FriendsOfRedaxo\Matomo\YRewriteHelper') && YRewriteHelper::isAvailable()) {
                $sites = YRewriteHelper::filterMatomoSitesByYRewrite($sites);
            }
            
            return $this->wrapContent($this->renderStats($sites, $api));
            
        } catch (Exception $e) {
            return $this->wrapContent($this->renderError($e->getMessage()));
        }
    }
    
    private function renderNotConfigured(): string
    {
        return '
        <div class="alert alert-info" style="margin-bottom: 0;">
            <h4><i class="fa fa-info-circle"></i> ' . rex_i18n::msg('matomo_widget_not_configured') . '</h4>
            <p>' . rex_i18n::msg('matomo_widget_configure_help') . '</p>
            <a href="' . rex_url::currentBackendPage(['page' => 'matomo/settings']) . '" class="btn btn-primary btn-sm">
                <i class="fa fa-cog"></i> ' . rex_i18n::msg('matomo_configure') . '
            </a>
        </div>';
    }
    
    private function renderStats($sites, $api): string
    {
        if (empty($sites)) {
            return '
            <div class="alert alert-warning" style="margin-bottom: 0;">
                <h4><i class="fa fa-warning"></i> ' . rex_i18n::msg('matomo_widget_no_sites') . '</h4>
                <p>' . rex_i18n::msg('matomo_widget_add_sites_help') . '</p>
                <a href="' . rex_url::currentBackendPage(['page' => 'matomo/domains']) . '" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> ' . rex_i18n::msg('matomo_add_domain') . '
                </a>
            </div>';
        }
        
        $content = '<div class="matomo-widget-stats">';
        
        // Header mit Anzahl Sites
        $content .= '
        <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
            <small class="text-muted">' . count($sites) . ' ' . rex_i18n::msg('matomo_sites') . '</small>
        </div>';
        
        // Top 3 Sites mit heutigen Besuchern
        $today_stats = [];
        foreach (array_slice($sites, 0, 3) as $site) {
            try {
                $visitors = $api->getVisitorsToday($site['idsite']);
                $today_stats[] = [
                    'name' => $site['name'],
                    'url' => $site['main_url'],
                    'visitors' => $visitors,
                    'id' => $site['idsite']
                ];
            } catch (Exception $e) {
                // Bei Fehlern Site überspringen
                continue;
            }
        }
        
        if (!empty($today_stats)) {
            foreach ($today_stats as $stat) {
                $content .= '
                <div class="matomo-site-stat" style="margin-bottom: 10px; padding: 8px; background: #f8f9fa; border-radius: 3px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 13px;">' . rex_escape($stat['name']) . '</strong><br>
                            <small class="text-muted">' . rex_escape(parse_url($stat['url'], PHP_URL_HOST)) . '</small>
                        </div>
                        <div>
                            <span class="badge badge-' . ($stat['visitors'] > 0 ? 'success' : 'default') . '">
                                ' . $stat['visitors'] . '
                            </span>
                        </div>
                    </div>
                </div>';
            }
        }
        
        // Action Button
        $content .= '
        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; text-align: center;">
            <a href="' . rex_url::currentBackendPage(['page' => 'matomo/overview']) . '" class="btn btn-primary btn-sm">
                <i class="fa fa-bar-chart"></i> ' . rex_i18n::msg('matomo_full_stats') . '
            </a>
        </div>';
        
        $content .= '</div>';
        
        return $content;
    }
    
    private function renderError($error): string
    {
        return '
        <div class="alert alert-danger" style="margin-bottom: 0;">
            <h4><i class="fa fa-exclamation-triangle"></i> ' . rex_i18n::msg('matomo_widget_error') . '</h4>
            <p><small>' . rex_escape($error) . '</small></p>
            <a href="' . rex_url::currentBackendPage(['page' => 'matomo/settings']) . '" class="btn btn-primary btn-sm">
                <i class="fa fa-cog"></i> ' . rex_i18n::msg('matomo_check_settings') . '
            </a>
        </div>';
    }
}