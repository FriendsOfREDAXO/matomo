# matomo
Matomo - Dashboard für REDAXO 5.x

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/matomo/assets/matomo.png)

## Features: 
- Bindet das Matomo-Dashboard für eine festgelegte Präsenz ein
- Unterstützung für mehrere Domains (Multidomain-Websites mit YRewrite)
- Button zum direkten Einloggen in matomo
- Per API wird nach dem Speichern der Einstellungen der Tracking-Code abgeholt
- Tracking-Code-Optionen können gesetzt werden
- Dashboard-Umschaltung zwischen verschiedenen Domains

## Tracking-Code:

### Einzelne Domain (Legacy)
Der Tracking-Code kann per Copy&Paste oder mit nachfolgendem PHP-Code im Template eingebunden werden: 

```php
echo rex_addon::get('matomo')->getConfig('matomojs');
```

### Mehrere Domains (Multidomain)
Für Multidomain-Setups können Sie domain-spezifische Tracking-Codes verwenden:

```php
$addon = rex_addon::get('matomo');
$tracking_codes = $addon->getConfig('domain_tracking_codes', []);

// Tracking-Code für eine bestimmte Domain abrufen
if (isset($tracking_codes['ihredomain.de'])) {
    echo $tracking_codes['ihredomain.de'];
}

// Oder automatisch basierend auf der aktuellen Domain
$current_domain = $_SERVER['HTTP_HOST'];
if (isset($tracking_codes[$current_domain])) {
    echo $tracking_codes[$current_domain];
}
```
## Credits

**Projekt-Lead**

[Daniel Springer](https://github.com/danspringer)

**Projekt-Initiator**

[Thomas Skerbis](https://github.com/skerbis)
