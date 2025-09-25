# MATOMO ADDON - Namespace Migration abgeschlossen ✅

## 🔄 Namespace-Umstellung auf FriendsOfRedaxo\Matomo

**Datum**: Dezember 2024  
**AddOn Name**: Bleibt `matomo`  
**Namespace**: Neu `FriendsOfRedaxo\Matomo`

---

## ✅ Durchgeführte Änderungen

### 1. MatomoApi Klasse mit Namespace ✅
- **Namespace**: `FriendsOfRedaxo\Matomo\MatomoApi`
- **Use-Statements**: Alle REDAXO und PHP Klassen korrekt importiert
- **Funktionalität**: Unverändert, vollständig funktionsfähig

### 2. Autoloader erstellt ✅
- **vendor/autoload.php**: PSR-4 kompatibler Autoloader
- **boot.php**: Autoloader wird geladen
- **Namespacing**: Automatische Klassenladung funktioniert

### 3. Seitenintegration aktualisiert ✅
- **domains.php**: `use FriendsOfRedaxo\Matomo\MatomoApi;`
- **dashboard.php**: `use FriendsOfRedaxo\Matomo\MatomoApi;`  
- **settings.php**: `use FriendsOfRedaxo\Matomo\MatomoApi;`
- **overview.php**: Keine Änderung nötig (verwendet Klasse nicht)

### 4. Assets korrekt integriert ✅
- **iframeResizer.min.js**: Matomo-empfohlene Library
- **boot.php**: Asset-Loading für Dashboard-Seiten
- **dashboard.js**: Korrekte iframeResizer Integration

---

## 📁 Neue Dateistruktur

```
matomo/
├── boot.php                     # Autoloader + Asset-Loading
├── vendor/
│   └── autoload.php            # FriendsOfRedaxo\Matomo Autoloader
├── lib/
│   └── MatomoApi.php           # FriendsOfRedaxo\Matomo\MatomoApi
├── pages/
│   ├── dashboard.php           # use FriendsOfRedaxo\Matomo\MatomoApi
│   ├── domains.php            # use FriendsOfRedaxo\Matomo\MatomoApi  
│   ├── settings.php           # use FriendsOfRedaxo\Matomo\MatomoApi
│   └── overview.php           # Keine API-Verwendung
└── assets/
    ├── iframeResizer.min.js   # Matomo Dashboard Integration
    └── iframeResizer.map      # Source Map
```

---

## 🚀 Verwendung der namespaced Klasse

### In AddOn-Seiten:
```php
<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

// API verwenden
$api = new MatomoApi($matomo_url, $admin_token, $user_token);
$sites = $api->getSites();
```

### Statische Methoden:
```php
<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

// Download
MatomoApi::downloadMatomo($target_path);
```

---

## 🔧 Autoloader-Details

**Namespace-Prefix**: `FriendsOfRedaxo\Matomo\`  
**Base Directory**: `lib/`  
**PSR-4 Standard**: Vollständig kompatibel

**Beispiel-Mapping**:
- `FriendsOfRedaxo\Matomo\MatomoApi` → `lib/MatomoApi.php`
- `FriendsOfRedaxo\Matomo\Helper\Utils` → `lib/Helper/Utils.php`

---

## ✅ Funktionalitätstest

- [x] **Autoloader**: Klasse wird korrekt geladen
- [x] **API-Calls**: rex_socket Funktionen arbeiten
- [x] **Dashboard**: iframe mit iframeResizer funktioniert
- [x] **Domain-Management**: API-Integration funktioniert
- [x] **Settings**: Download-Funktionalität arbeitet
- [x] **Keine PHP-Fehler**: Alle Seiten laden fehlerfrei

---

**Status**: ✅ **NAMESPACE MIGRATION ABGESCHLOSSEN**  
**AddOn Name**: `matomo` (unverändert)  
**Klasse**: `FriendsOfRedaxo\Matomo\MatomoApi`  
**Kompatibilität**: REDAXO 5.7+ | Matomo 4.x/5.x