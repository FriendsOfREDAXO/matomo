# Matomo AddOn für REDAXO 5

Das **Matomo AddOn** bietet eine vollständige Integration der Open-Source Web-Analytics-Plattform Matomo in REDAXO 5. Es ermöglicht das einfache Herunterladen, Installieren und Verwalten von Matomo direkt aus dem REDAXO Backend.

## 🚀 Features

### ✅ **Automatisierte Installation**
- **Ein-Klick Download** der neuesten Matomo-Version
- **Automatische Konfiguration** von URL und Pfad
- **REDAXO-Implementation** für API-Steuerung

### 📊 **Übersichten & Analytics**
- **Kompakte Übersichtsseite** mit Statistiken aller Domains
- **Top 5 Seiten Feature** - zeigt meistbesuchte Seiten der aktuellen Woche
- **Echtzeitdaten** mit automatischem Refresh (alle 5 Minuten)
- **Automatisches Login-System** für nahtlosen Matomo-Zugang
- **Direkte Links** zu spezifischen Matomo-Dashboards

### 🌐 **Domain-Management**
- **API-basierte Domain-Verwaltung** über Matomo API
- **YRewrite Integration** - automatische Filterung und Import von YRewrite-Domains
- **Intelligente Duplikatserkennung** - verhindert Import bereits vorhandener Domains
- **Domain-Löschung** - Entfernung von Domains aus Matomo mit Bestätigung
- **Tracking-Code Generierung** für jede Domain
- **Copy-to-Clipboard Funktionalität** für Tracking-Codes
- **Consent-Manager Integration** Empfehlungen

### ⚙️ **Erweiterte Konfiguration**
- **Flexible API-Einstellungen** (Timeout, SSL-Verifikation)
- **Datenschutz-Optionen** (IP-Anonymisierung, Cookie-freies Tracking)
- **Multi-Token Support** (Admin + User Token)

### 🔒 **DSGVO-Konformität**
- **IP-Anonymisierung** aktivierbar
- **Cookie-freies Tracking** verfügbar
- **Do Not Track** Unterstützung
- **Consent-Manager** Integration empfohlen

### 🌍 **Mehrsprachigkeit**
- **Vollständig übersetzt** (Deutsch/Englisch)
- **REDAXO i18n System** Integration
- **Konsistente Terminologie** über alle Seiten

## 🖥️ Systemvoraussetzungen & Empfehlungen

### Voraussetzungen
- **REDAXO 5.16.1+**
- **PHP 8.2+** (Empfohlen: PHP 8.4+)
- **rex_socket** (Core-Komponente) für API-Verwaltung

### Empfehlungen
- **PHP cURL Extension**: Dringend empfohlen für Server-Side Tracking.
    - Ermöglicht "Fire-and-Forget" Requests (minimiert Auswirkungen auf die Ladezeit)
    - Bei fehlendem cURL wird automatisch auf eine performante native Socket-Lösung zurückgegriffen (seit v2.2)
- **SSL-Zertifikat**: Empfohlen für alle Domains (HTTPS)
- **YRewrite AddOn**: Empfohlen für Multi-Domain-Verwaltung

## ️ Installation

1. **AddOn installieren** über den REDAXO Installer oder manuell
2. **AddOn aktivieren** im REDAXO Backend
3. **Matomo-Setup** aufrufen und Installation durchführen

## 📖 Verwendung

### 1. **Matomo-Setup**
Unter **Matomo → Matomo-Setup**:
- Matomo automatisch herunterladen und installieren
- Oder manuelle Konfiguration von Pfad, URL und API-Token

### 2. **Konfiguration**
Unter **Matomo → Konfiguration**:
- API-Einstellungen (Timeout, SSL-Verifikation)
- Tracking-Optionen (IP-Anonymisierung, Cookie-freies Tracking)
- Datenschutz-Einstellungen konfigurieren

### 3. **Domains verwalten**
Under **Matomo → Domains**:
- Neue Domains manuell zu Matomo hinzufügen
- **YRewrite-Domains importieren** - Domains aus YRewrite-Konfiguration auswählen und importieren
- **Domains löschen** aus Matomo mit Sicherheitsbestätigung
- Tracking-Codes anzeigen und kopieren
- Consent-Manager Empfehlungen beachten

#### **YRewrite Integration:**
- **Automatische Filterung**: Übersicht zeigt nur YRewrite-Domains (+ Standard-Domain)
- **Intelligenter Import**: YRewrite-Domains auswählen und in Matomo importieren
- **Duplikatsverhinderung**: Bereits vorhandene Domains werden markiert und übersprungen
- **Domain-Synchronisation**: Matomo und YRewrite-Domains synchron halten

### 4. **Statistiken ansehen**
- **Matomo → Übersicht**: Kompakte Statistiken aller Domains mit optionalen Top 5 Seiten
- **Automatisch anmelden**: Nahtloser Zugang zu Matomo ohne manuelle Anmeldung
- **Direkte Domain-Links**: Schneller Zugriff auf spezifische Domain-Statistiken

### 5. **Dashboard & Info-Center Widgets** 📊

#### **Info-Center Widget** (kompakt)
- **Automatische Integration**: Falls Info-Center AddOn installiert ist
- **Berechtigungsbasiert**: Nur sichtbar für Benutzer mit `matomo[overview]` Berechtigung
- **Live-Statistiken**: Zeigt heutige Besucher der Top 3 Websites
- **YRewrite-Synchron**: Filtert automatisch auf YRewrite-Domains
- **Direktzugang**: Ein-Klick-Zugang zur vollständigen Matomo-Übersicht

#### **Dashboard Widget** (erweitert)
- **Automatische Integration**: Falls Dashboard AddOn installiert ist
- **Berechtigungsbasiert**: Nur sichtbar für Benutzer mit `matomo[overview]` Berechtigung
- **Erweiterte Statistiken**: Top 5 Websites mit heutigen Besucherzahlen in Tabellenform
- **Größeres Format**: 2-spaltig für mehr Informationen
- **YRewrite-Integration**: Automatische Filterung auf YRewrite-Domains

## 🔐 API-Token einrichten

### Admin Token (erforderlich)
Für Verwaltungsaufgaben wie Domain-Erstellung:
1. In Matomo anmelden
2. **Administration → Platform → API → User Authentication**
3. **Admin Token** kopieren und in REDAXO einfügen

### User Token (optional)
Für Statistik-Zugriff:
1. **User Authentication** in Matomo öffnen
2. **User Token** kopieren (falls nicht vorhanden, wird Admin Token verwendet)

### Auto-Login Setup (optional)
Für automatischen Login über "Automatisch anmelden" Buttons:
1. **Matomo Username und Passwort** in den Einstellungen hinterlegen
2. **Automatische Konfiguration**: Das AddOn kann `login_allow_logme = 1` automatisch in der Matomo `config.ini.php` setzen
3. **Manuelle Konfiguration**: Falls automatisch nicht möglich, manuell in `config/config.ini.php` hinzufügen:
   ```ini
   [General]
   login_allow_logme = 1
   ```

## 🎯 Tracking-Code Integration

**Wichtig**: Das AddOn bindet Tracking-Codes **nicht automatisch** ein. 

### Empfohlene Integration:
1. **Consent-Manager AddOn** verwenden (empfohlen: "Consent Manager")
2. **Tracking-Code kopieren** aus der Domains-Seite
3. **Manuell in Templates** einfügen oder über Consent-Manager verwalten

### DSGVO-konforme Optionen:
- IP-Anonymisierung aktivieren
- Cookie-freies Tracking nutzen
- Do Not Track respektieren
- Consent-Manager für Cookie-Zustimmung

## 🔧 Konfigurationsoptionen

### API-Einstellungen
- `api_timeout`: Request-Timeout (10-120 Sekunden)
- `ssl_verify`: SSL-Zertifikat Verifikation

### Tracking-Optionen
- `anonymize_ip`: IP-Adressen anonymisieren
- `cookieless_tracking`: Cookie-freies Tracking
- `respect_dnt`: Do Not Track Header beachten
- `cookie_lifetime`: Cookie-Lebensdauer

### Statistik-Features
- `show_top_pages`: Top 5 Seiten Feature aktivieren/deaktivieren

### Auto-Login
- `matomo_user`: Matomo Benutzername für automatischen Login
- `matomo_password`: Matomo Passwort für automatischen Login

## 🔄 API-Integration

Das AddOn nutzt die **Matomo HTTP API** für:
- Site-Verwaltung (Erstellen, Auflisten)
- Statistik-Abfrage (Besucher, Seitenaufrufe)
- Tracking-Code Generierung
- Dashboard-Widget Integration

Alle API-Verwaltungs-Requests erfolgen über `rex_socket` mit konfigurierbaren Timeouts und SSL-Optionen.

## Server-Side Tracking (ohne JS & Cookies)

REDAXO kann die Rolle des Matomo-JavaScripts komplett übernehmen und Seitenaufrufe direkt serverseitig an Matomo melden. Damit ist Tracking unabhängig von Adblockern, JavaScript-Deaktivierung und Browser-Tracking-Schutz.

### Aktivierung

Unter **Matomo → Konfiguration**:
1. **"Server-seitiges Tracking aktivieren"** einschalten
2. **Matomo Site-ID** eintragen (zu finden in Matomo unter Administration → Websites)

### Was wird automatisch erfasst
- Seitenaufrufe (Titel + URL) aller REDAXO-Artikel
- Echte Besucher-IP (wenn Admin-Token hinterlegt)
- User-Agent + Accept-Language
- HTTP-Referer
- YCom-Benutzer als User-ID (wenn YCom installiert)
- Bots werden automatisch herausgefiltert

### Browser-Event-Tracking (`matomo-events.js`)

Ergänzend zum serverseitigen Page-Tracking kann ein leichtgewichtiges JS-Script aktiviert werden, das rein browserseitige Ereignisse erkennt und über den REDAXO-Server an Matomo meldet:

- **Downloads** (PDF, ZIP, MP3 u.v.m. – Endungen konfigurierbar)
- **Outbound-Links** (Klicks auf externe Domains)
- **Formular-Versendungen** (alle `<form>`-Elemente)
- **Beliebige Custom-Events** per `data-matomo-event`-Attribut

Aktivierung unter **Matomo → Konfiguration → "Browser-Event-Tracking aktivieren"**.

Wichtig: Das Script wird bewusst **nicht automatisch** vom AddOn in das Frontend injiziert.
Die Einbindung erfolgt manuell durch den Integrator, z.B. im Consent-Manager oder direkt im Template.

#### Manuelle Einbindung (Consent-Manager / Template)
```html
<script>
window.MatomoEventsConfig = {
    endpoint: '/index.php?rex-api-call=matomo_event'
};
</script>
<script defer src="/assets/addons/matomo/matomo-events.js"></script>
```

Hinweis: Bei Unterordner-Installationen muss der Pfad mit dem korrekten Webroot gesetzt werden (z.B. `/subdir/index.php?rex-api-call=matomo_event`).

#### Custom-Events per Data-Attribut (kein JS nötig)
```html
<button data-matomo-event='{"category":"CTA","action":"Click","name":"Hero-Button"}'>
    Jetzt buchen
</button>

<a href="/produkt" data-matomo-event='{"category":"Product","action":"View"}'>
    Produkt ansehen
</a>
```

#### Konfiguration des Scripts (optional)
```html
<script>
window.MatomoEventsConfig = {
    trackOutbound:  true,
    trackDownloads: true,
    extensions: ['pdf', 'zip', 'docx', 'mp4'],  // eigene Endungen
};
</script>
```

## 🎯 `MatomoTrack` – PHP-Tracking-Facade

Für Tracking aus PHP-Code (Module, Templates, `rex_api`-Funktionen) steht die statische Hilfsklasse `MatomoTrack` bereit. Sie ist ein no-op wenn Tracking nicht konfiguriert ist – kann also immer aufgerufen werden.

```php
use FriendsOfRedaxo\Matomo\MatomoTrack;

// Formular-Versand (z.B. in YForm-Action oder rex_api)
MatomoTrack::event('Form', 'Submit', 'Kontaktformular');

// Download-Controller
MatomoTrack::download('https://example.com/files/broschuere.pdf');

// Outbound-Weiterleitung
MatomoTrack::outboundLink('https://partner.de');

// Interne Suche
MatomoTrack::search('redaxo themes', 'Dokumentation', 12);

// Ziel / Konversion
MatomoTrack::goal(3, 29.90);

// Manueller Page View (z.B. aus Headless-Controller)
MatomoTrack::pageView('Produktdetail – Rotes T-Shirt', 'https://example.com/produkte/rotes-tshirt');
```

## 💻 PHP Tracking API (Serverseitig – direkte Klasse)

Das AddOn bringt eine leistungsfähige PHP-Klasse `Tracker` für direktes Server-Side Tracking mit (z.B. für API-Endpunkte, Cronjobs oder Headless-Anwendungen). Diese nutzt **Native Sockets** im Fire-and-Forget-Modus.

### Einfache Verwendung

```php
use FriendsOfRedaxo\Matomo\Tracker;

// 1. Tracker initialisieren (zieht sich URL & Token automatisch aus der Config)
// Die Site ID muss jedoch übergeben werden (z.B. 1)
$tracker = Tracker::factory(1);

if ($tracker) {
    // 2. Einfachen PageView erfassen
    // URL ist optional (nimmt die aktuelle, falls leer)
    $tracker->trackPageView('Startseite', 'https://beispiel.de/');
    
    // 3. Event tracken
    // Kategorie, Aktion, Name (optional), Wert (optional)
    $tracker->trackEvent('Kontaktformular', 'Absenden', 'Allgemeine Anfrage', 1);

    // 4. Download tracken
    $tracker->trackDownload('https://example.com/files/broschuere.pdf');

    // 5. Outbound-Link tracken
    $tracker->trackOutboundLink('https://partner.de');
    
    // 4. Ziel (Goal) erfassen
    // Goal ID, Umsatz (optional)
    $tracker->trackGoal(1, 49.90);
    
    // 5. Interne Suche (Site Search)
    // Suchbegriff, Kategorie (optional), Anzahl Treffer (optional)
    $tracker->trackSiteSearch('redaxo', 'CMS', 12);
}
```

### Erweiterte Features

#### User ID & Custom Dimensions
```php
// User ID setzen (für Cross-Device Tracking)
$tracker->setUserId('user_123');

// Custom Dimension setzen (muss in Matomo angelegt sein)
$tracker->setCustomDimension(1, 'premium-user'); // Dimension ID 1
```

#### E-Commerce Tracking
```php
// 1. Artikel zum Warenkorb/Bestellung hinzufügen
$tracker->addEcommerceItem(
    'SKU12345',      // SKU
    'Rotes T-Shirt', // Produktname
    ['Kleidung', 'Shirts'], // Kategorie (String oder Array)
    19.99,           // Preis
    1                // Menge
);

// 2. Bestellung abschließen
$tracker->trackEcommerceOrder(
    'ORDER-2024-001', // Bestell-ID
    19.99,            // Gesamtsumme (Revenue)
    16.80,            // Zwischensumme (optional)
    3.19,             // Steuer (optional)
    0.00,             // Versandkosten (optional)
    false             // Rabatt (optional)
);
```

### Automatische Daten
Der Tracker ermittelt automatisch:
- **IP-Adresse**: Wird an Matomo übergeben (benötigt Admin-Token in der Config)
- **User Agent**: Wird aus dem aktuellen Request übernommen
- **Visitor ID**: Wird aus IP/UA Hash oder Cookie generiert
- **Zeit/Datum**: Aktuelle Serverzeit

### ⚙️ Voraussetzungen in Matomo

Damit das Server-Side Tracking korrekt läuft, sind evtl. Einstellungen in Matomo nötig:

1.  **Admin Token**: Damit Matomo die User-IP (`cip`) akzeptiert, benötigt der Request einen Token mit **Write**- oder **Admin**-Rechten. Das erledigt die Klasse automatisch, wenn im AddOn der Admin-Token hinterlegt ist.
2.  **E-Commerce**: Falls `trackEcommerceOrder()` genutzt wird, muss für die Webseite in Matomo E-Commerce aktiviert sein (**Einstellungen > Messgrößen > Verwalten > Webseite bearbeiten**).
3.  **Custom Dimensions**: Vor Nutzung von `setCustomDimension()` muss die Dimension in Matomo angelegt sein.
4.  **Site Search**: Die interne Suche muss in den Webseiten-Einstellungen aktiviert sein, damit sie in den Berichten auftaucht.

## 🆘 Troubleshooting

### Matomo nicht gefunden
- Prüfen Sie Pfad und URL in der Konfiguration
- Stellen Sie sicher, dass Matomo korrekt installiert ist

### API-Fehler
- Überprüfen Sie die API-Tokens
- Testen Sie die Matomo-URL im Browser
- Prüfen Sie SSL-Einstellungen bei HTTPS

### Dashboard lädt nicht
- User Token konfigurieren oder Admin Token verwenden
- Browser-Console auf Fehler prüfen
- CORS-Einstellungen in Matomo überprüfen

## 📝 Changelog

### Version 2.4.0
- **Server-Side Page Tracking**: REDAXO sendet bei jedem Frontend-Aufruf einen direkten Request an die Matomo Tracking API – ohne JavaScript, ohne Cookies, ohne Adblocker-Problem
- **Bot-Filter**: Automatische Erkennung und Filterung von 30+ bekannten Crawler- und Bot-User-Agents
- **HTTP-Referer**: Referer-Header wird automatisch an Matomo weitergeleitet
- **YCom-Integration**: Eingeloggte YCom-Benutzer werden als User-ID getrackt (Cross-Device)
- **`MatomoTrack`-Facade**: Neue statische Hilfsklasse `MatomoTrack` für bequemes Tracking aus Modulen, Plugins und rex_api-Funktionen – Events, Downloads, Outbound-Links, Suche, Ziele, Page Views per einzeiligem Aufruf
- **`trackDownload()` / `trackOutboundLink()`**: Neue Methoden direkt im `Tracker`
- **Browser-Event-Tracking** (`matomo-events.js`): Leichtgewichtiges Script, das Downloads, externe Links und Formular-Versendungen automatisch erkennt und über den REDAXO-Server an Matomo meldet – kein Matomo-JS nötig
- **`data-matomo-event`-Attribut**: Beliebige HTML-Elemente können Custom-Events per Data-Attribut auslösen (kein JS nötig)
- **`MatomoEventApi`**: Neuer REDAXO-API-Endpunkt, der Browser-Events als JSON entgegennimmt und serverseitig an Matomo weiterleitet (`POST index.php?rex-api-call=matomo_event`)
- **Zwei neue Einstellungen**: "Server-seitiges Tracking" (mit Site-ID) und "Browser-Event-Tracking aktivieren"

### Version 2.1
- **YRewrite Integration**: Vollständige Integration mit YRewrite AddOn (nun erforderlich)
- **Automatische Domain-Filterung**: Zeigt nur YRewrite-Domains in der Übersicht (+ Standard-Domain)
- **Smart Domain Import**: Import von YRewrite-Domains in Matomo mit Auswahl-Interface
- **Info-Center Widget**: Kompakte Matomo-Statistiken im REDAXO Info-Center (nur für Benutzer mit `matomo[overview]` Berechtigung)
- **Dashboard Widget**: Erweiterte Matomo-Statistiken im REDAXO Dashboard AddOn (Top 5 Websites, Tabellen-View)
- **Domain-Löschung**: Entfernung von Domains aus Matomo mit Sicherheitsbestätigung
- **Intelligente Duplikatserkennung**: Verhindert Import bereits vorhandener Domains
- **Vollständige Internationalisierung**: Alle Texte professionell übersetzt
- **Verbesserte UX**: Benutzerfreundliche Dialoge und aussagekräftige Statusmeldungen
- **Saubere Architektur**: YRewrite als Dependency für konsistente Multi-Domain-Verwaltung

### Version 2.0
- **Auto-Login System**: Nahtloser Matomo-Zugang ohne manuelle Anmeldung
- **Top 5 Seiten Feature**: Zeigt meistbesuchte Seiten der aktuellen Woche
- **Externe Matomo Unterstützung**: Vollständige Integration externer Matomo-Installationen
- **Enhanced Overview Page**: Erweiterte Statistiken mit Trend-Anzeige
- **Automatische Konfiguration**: Auto-Login kann automatisch in Matomo konfiguriert werden
- **Verbessertes UI**: Einheitliches Panel-Design und bessere Benutzerführung
- **Namespace Migration**: Vollständige Migration zu FriendsOfRedaxo\Matomo Namespace
- **Dashboard Entfernung**: Fokus auf streamlined Overview-basierte Ansätze

## Credits

**Projekt-Leads**  
[Daniel Springer](https://github.com/danspringer)

[Thomas Skerbis](https://github.com/skerbis)

**Mitwirkende**  
Danke an [VIEWSION](https://github.com/VIEWSION) für das Tracker-Refactoring in [PR #22](https://github.com/FriendsOfREDAXO/matomo/pull/22)

## 🤝 Support

- **GitHub**: https://github.com/FriendsOfREDAXO/matomo
- **REDAXO Community**: https://redaxo.org/forum/
- **Matomo Documentation**: https://matomo.org/docs/

## 📄 Lizenz

Dieses AddOn steht unter der MIT-Lizenz. Matomo selbst ist unter der GPL v3 Lizenz verfügbar.

---

**Entwickelt von Friends Of REDAXO**  
Für REDAXO 5.16.1+ | Matomo 4.x/5.x kompatibel
