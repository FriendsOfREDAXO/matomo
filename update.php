<?php

/**
 * Matomo AddOn Update-Skript
 * 
 * Migriert die wichtigsten Konfigurationen von der alten Version (1.x) zur neuen Version (2.x)
 */

$addon = rex_addon::get('matomo');

// Update von Version 1.x auf 2.x
if (rex_version::compare($addon->getVersion(), '2.0.0', '<')) {
    
    // Nur die wichtigsten Konfigurationen migrieren
    $migrations = [
        'url' => 'matomo_url',
        'token' => 'admin_token',
        'user' => 'matomo_user',
        'password' => 'matomo_password'
    ];
    
    foreach ($migrations as $old_key => $new_key) {
        $old_value = $addon->getConfig($old_key);
        if ($old_value !== null && $old_value !== '') {
            rex_config::set('matomo', $new_key, $old_value);
        }
    }
    
    // Alle alten Konfigurationen entfernen
    $old_keys = [
        'url', 'token', 'user', 'password', 'id', 
        'tracking_setup', 'matomocheck', 'matomojs'
    ];
    
    foreach ($old_keys as $key) {
        rex_config::remove('matomo', $key);
    }
}

// Update auf 2.5: ein API-Token statt Admin-/User-Token, Token-Zugang statt logme-Auto-Login,
// einheitlicher Schlüssel verify_ssl (config.php schrieb bisher ssl_verify, gelesen wurde verify_ssl)
if (rex_version::compare($addon->getVersion(), '2.5.0', '<')) {
    if (null !== $addon->getConfig('ssl_verify') && null === $addon->getConfig('verify_ssl')) {
        rex_config::set('matomo', 'verify_ssl', (bool) $addon->getConfig('ssl_verify'));
    }
    foreach (['ssl_verify', 'user_token', 'matomo_user', 'matomo_password'] as $key) {
        rex_config::remove('matomo', $key);
    }
}
