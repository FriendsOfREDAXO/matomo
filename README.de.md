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
- **Persönliche Zugänge** – Matomo öffnet sich direkt ohne Login (Token-Zugang, keine Änderung an der Matomo-Konfiguration)
- **Direkte Links** zu spezifischen Matomo-Dashboards

### 🌐 **Domain-Management**
- **API-basierte Domain-Verwaltung** über Matomo API
- **YRewrite Integration** - automatische Filterung und Import von YRewrite-Domains
- **Intelligente Duplikatserkennung** - verhindert Import bereits vorhandener Domains
- **Domain-Löschung** - Entfernung von Domains aus Matomo mit Bestätigung
- **Tracking-Code Generierung** für jede Domain
- **Copy-to-Clipboard Funktionalität** für Tracking-Codes
- **Consent-Registrierung** – Matomo per Klick in consent_kit oder consent_manager anlegen

### ⚙️ **Erweiterte Konfiguration**
- **Flexible API-Einstellungen** (Timeout, SSL-Verifikation)
- **Datenschutz-Optionen** (IP-Anonymisierung, Cookie-freies Tracking)
- **Ein API-Token**, auf Wunsch direkt aus Matomo-Login und Passwort erzeugt
- **Consent-Registrierung**: Matomo per Klick in consent_kit oder consent_manager anlegen

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
3. **Matomo → Einrichtung** aufrufen und die fünf Schritte durchgehen

## 📖 Verwendung

### 1. **Einrichtung**
Unter **Matomo → Einrichtung** führt eine Seite in fünf Schritten durch die Ersteinrichtung. Jeder Schritt zeigt, ob er erledigt ist:

1. **Matomo bereitstellen** – Matomo in einen Ordner unterhalb des Web-Roots herunterladen und anschließend den Matomo-Installationsassistenten (Datenbank, Superuser, erste Website) durchlaufen. Wer bereits ein Matomo hat (auch extern), überspringt den Schritt. Bei lokaler Installation deaktiviert das AddOn anschließend automatisch das Werbe-Plugin „ProfessionalServices“ (über Matomos Konsole per PHP-CLI; ohne CLI erscheint ein Hinweis).
2. **Verbindung** – Matomo-URL eintragen und das API-Token hinterlegen. Am einfachsten: Matomo-Benutzername und Passwort eines Superusers eingeben, das AddOn erzeugt das Token selbst (das Passwort wird nicht gespeichert). Alternativ ein vorhandenes Token eintragen.
3. **Websites** – zeigt die in Matomo angelegten Websites, mit Sprung zur Domain-Verwaltung (YRewrite-Import).
4. **Consent-Tool** – legt Matomo als Dienst in **consent_kit** oder **consent_manager** an, sofern installiert (siehe unten).
5. **Persönliche Zugänge** – je REDAXO-Benutzer einen Matomo-Zugang anlegen (siehe unten).

### 2. **Konfiguration**
Unter **Matomo → Konfiguration** liegen nur noch die Optionen:
- API-Einstellungen (Timeout, SSL-Verifikation, Socket-Timeout)
- Tracking-Features (Proxy, serverseitiges Tracking, Browser-Events, Top 5 Seiten)
- Datenschutz (IP-Anonymisierung, Cookie-freies Tracking, Do Not Track, Cookie-Lebensdauer)

### 3. **Domains verwalten**
Under **Matomo → Domains**:
- Neue Domains manuell zu Matomo hinzufügen
- **YRewrite-Domains importieren** - Domains aus YRewrite-Konfiguration auswählen und importieren
- **Domains löschen** aus Matomo mit Sicherheitsbestätigung
- Tracking-Codes anzeigen und kopieren
- Consent-Manager Empfehlungen beachten

#### **YRewrite Integration:**
- **Automatische Filterung**: Übersicht zeigt nur YRewrite-Domains (+ Standard-Domain)
- **Intelligenter Import**: YRewrite-Domains auswählen und in Matomo importieren (Site-Name = Host)
- **Duplikatsverhinderung**: Bereits vorhandene Domains werden markiert und übersprungen
- **Domain-Synchronisation**: Matomo und YRewrite-Domains synchron halten

### 4. **Statistiken ansehen**
- **Matomo → Übersicht**: Kennzahlen (Besuche, eindeutige Besucher, Seitenaufrufe, Absprungrate, Ø Besuchsdauer, Aktionen je Besuch, Conversions, Konversionsrate) jeweils mit Vergleich zur Vorperiode, Verlaufsdiagramm (stündlich, täglich oder monatlich, auch als Tabelle), Top-Seiten, Herkunft, Geräte, Länder und eine Tabelle je Domain
- **Filter** nach Domain und Zeitraum (heute, gestern, 7 Tage, 30 Tage, Monat, Jahr); die Auswahl bleibt im Browser gespeichert
- **Nicht blockierend und serverschonend**: Die Seite erscheint sofort, die Abschnitte laden nacheinander über `rex-api-call=matomo_stats` nach (immer nur ein Matomo-Request gleichzeitig). Antworten werden serverseitig 10 Minuten gecacht (abgeschlossene Zeiträume 6 Stunden), der Auto-Refresh läuft alle 15 Minuten nur bei sichtbarem Tab; „Aktualisieren“ erzwingt neue Daten höchstens einmal pro Minute
- **Matomo öffnen**: Mit persönlichem Zugang landet der Benutzer direkt in Matomo, ohne Login
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

## 🔐 API-Token

Das AddOn braucht genau **ein Token eines Matomo-Superusers** (für Domains, Consent-Registrierung, Zugänge und Statistiken). Zwei Wege:

- **Automatisch (empfohlen)**: In der Einrichtung Matomo-Benutzername und Passwort eingeben. Das AddOn ruft `UsersManager.createAppSpecificTokenAuth` auf, Matomo erlaubt das ohne bestehendes Token. Das Passwort wird nur für diesen Aufruf verwendet und nicht gespeichert.
- **Manuell**: In Matomo unter **Administration → Persönlich → Sicherheit → Auth-Tokens** ein Token erzeugen und eintragen.

Der frühere getrennte „User Token“ entfällt; ein vorhandener Eintrag wird beim Update entfernt.

### Admin-Passwort und Token zurücksetzen

Die Matomo-API erlaubt keine Passwortänderung ohne das aktuelle Passwort. Ist es verloren, bietet die Einrichtung (Schritt 2, aufklappbar) bei **lokaler Installation** einen Reset. Bevorzugt ruft das AddOn dafür per PHP-CLI `bin/matomo-user-password.php` auf: Das Script bootstrappt Matomo selbst und setzt das Passwort über Matomos UsersManager-API, also mit Matomos eigener Datenbankverbindung. Steht keine CLI zur Verfügung (kein `proc_open`, kein PHP-Binary), liest das AddOn die Datenbankzugangsdaten aus `config/config.ini.php` und setzt das Passwort direkt in der Benutzertabelle. Anschließend werden alle Tokens des Superusers verworfen und ein neues Token erzeugt. Das neue Passwort wird einmalig angezeigt. Bei externem Matomo dort „Passwort vergessen“ nutzen und anschließend hier das Token neu erzeugen lassen.

Schlägt die Datenbankverbindung mit den Daten aus der config.ini.php fehl (Meldung nennt Datei, Benutzer, Datenbank und die versuchten Wege Socket/TCP), lassen sich unter **Konfiguration → Matomo-Datenbank für Passwort-Reset** eigene Zugangsdaten hinterlegen.

## 👤 Persönliche Zugänge und Auto-Login

Jeder Redakteur bekommt ein eigenes Matomo-Konto. „Matomo öffnen“ meldet ihn damit an, bevorzugt über Matomos `logme`-Funktion mit regulärer Session (Auto-Login), sonst über sein persönliches Token:

- In der Einrichtung, Schritt 5, bekommt jeder REDAXO-Benutzer per Klick einen **eigenen Matomo-Benutzer** (Leserecht auf alle Websites), ein zufälliges **Passwort** und ein **persönliches App-Token**. Login und E-Mail werden aus dem REDAXO-Benutzer übernommen; ohne gültige E-Mail-Adresse im REDAXO-Benutzer wird kein Zugang angelegt, weil Matomo je Benutzer eine eindeutige Adresse verlangt. Passwort und Token liegen in der REDAXO-Konfiguration (`user_access`), damit der Benutzer sein Passwort auf der Übersicht sehen und ändern kann.
- „Matomo öffnen“ in der Übersicht ruft dann `index.php?module=CoreHome&…&token_auth=…` auf – Matomo öffnet die komplette Oberfläche als dieser Benutzer, ohne Login und ohne Änderung an der Matomo-Konfiguration.
- Matomo lässt Token-Zugänge in der Oberfläche nur für Benutzer **ohne** Schreib-/Superuser-Rechte zu. Für die Matomo-Administration meldet man sich weiterhin normal an.
- **Sichtbare Websites je Benutzer**: In der Zugangstabelle lässt sich je REDAXO-Benutzer festlegen, welche Matomo-Websites er sehen darf (keine Auswahl = alle). Die Auswahl wird als Leserecht je Website in Matomo gesetzt und filtert zusätzlich Übersicht und Widgets in REDAXO.
- „Entfernen“ löscht Matomo-Benutzer und Token wieder. Die Tokens liegen in der REDAXO-Konfiguration (`user_access`).
- **Auto-Login (logme)**: Ist in Matomos `config.ini.php` `login_allow_logme = 1` gesetzt, meldet „Matomo öffnen“ den Redakteur per POST mit Login und md5-Passwort seines eigenen Kontos an; Matomo legt eine reguläre Session an, die beim Klicken erhalten bleibt. Bei lokaler Installation aktiviert die Einrichtung (Schritt 5) die Einstellung per Klick über Matomos Konsole `config:set`, ersatzweise direkt in der Datei; bei externem Matomo trägt man sie dort ein und bestätigt sie in der Einrichtung. Matomo erlaubt logme nur für Konten ohne Superuser-Rechte, die Zugänge des Addons sind Lesekonten.
- **Token-Zugang** (ohne Auto-Login): Das Token ist kein Login, sondern authentifiziert jede Anfrage einzeln. Jede URL ohne Token (Logo, Lesezeichen, manche Aktionen) landet auf der Anmeldeseite.

### Mein Matomo-Zugang (Übersicht)

Redakteure mit persönlichem Zugang sehen unten auf der Übersicht Matomo-URL, Benutzernamen, ihr **Passwort** (auf Klick sichtbar), ihre sichtbaren Websites und einen Link zur Matomo-Anmeldung. Über „Passwort ändern“ setzen sie ein eigenes oder lassen ein neues erzeugen. Die Änderung läuft über die Matomo-API mit dem eigenen Token (Matomo verlangt dafür nur das eigene aktuelle Passwort), funktioniert also bei lokalem und externem Matomo. Damit ist die reguläre Anmeldung in Matomo inklusive „Angemeldet bleiben“ möglich.
- Voraussetzung: In Matomo darf `only_allow_secure_auth_tokens` nicht aktiv sein (Standard: inaktiv).

## 🍪 Consent-Registrierung

Ist **consent_kit** oder **consent_manager** installiert, legt die Einrichtung (Schritt 4) Matomo dort als Dienst an, inklusive Tracking-Code (auch mit aktiviertem Proxy) und Cookie-Angaben (`_pk_id*`, `_pk_ses*`, `_pk_ref*`):

- **consent_kit** (Namespace `KLXM\ConsentKit` oder neu `FriendsOfRedaxo\ConsentKit`): Dienst `matomo` aus dem mitgelieferten Preset, Parameter `matomo_url` und `site_id` aus den Addon-Einstellungen. Für jede consent_kit-Domain, deren Host einer Matomo-Website entspricht, entsteht eine Variante mit der passenden Site-ID. Bereits gepflegte Texte, Gruppe und Domains bleiben beim Aktualisieren erhalten.
- **consent_manager**: Cookie `matomo` in der Gruppe `statistics` für alle Sprachen; fehlt die Gruppe, wird sie angelegt und allen Domains zugeordnet. Da consent_manager Dienste nur domainübergreifend kennt, wählt der hinterlegte Tracking-Code die Site-ID zur Laufzeit anhand des Hostnamens (alle Matomo-Websites, jeweils mit und ohne `www.`); die in der Einrichtung gewählte Website ist der Fallback. Beim Aktualisieren wird nur der Tracking-Code neu geschrieben.

## 🎯 Tracking-Code Integration

**Wichtig**: Das AddOn bindet Tracking-Codes **nicht automatisch** ein. 

### Empfohlene Integration:
1. **consent_kit** oder **consent_manager** installieren und Matomo in der Einrichtung (Schritt 4) registrieren – Tracking-Code und Cookie-Angaben werden dort automatisch hinterlegt
2. Ohne Consent-Tool: **Tracking-Code kopieren** von der Domains-Seite und **manuell in Templates** einfügen

### DSGVO-konforme Optionen:
- IP-Anonymisierung aktivieren
- Cookie-freies Tracking nutzen
- Do Not Track respektieren
- Consent-Manager für Cookie-Zustimmung

## 🔧 Konfigurationsoptionen

### API-Einstellungen
- `api_timeout`: Request-Timeout (10-120 Sekunden)
- `verify_ssl`: SSL-Zertifikat Verifikation (Standard: an)

### Tracking-Optionen
- `anonymize_ip`: IP-Adressen anonymisieren
- `cookieless_tracking`: Cookie-freies Tracking
- `respect_dnt`: Do Not Track Header beachten
- `cookie_lifetime`: Cookie-Lebensdauer

### Statistik-Features
- `show_top_pages`: Top-Seiten in der Übersicht anzeigen

### Verbindung & Zugänge
- `matomo_url`, `matomo_path`, `admin_token`: Verbindung (Einrichtung, Schritt 2)
- `user_access`: persönliche Zugänge je REDAXO-Benutzer-ID (Matomo-Login + Token)

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
- Echte Besucher-IP (wenn API-Token hinterlegt)
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
- **IP-Adresse**: Wird an Matomo übergeben (benötigt das API-Token aus der Einrichtung)
- **User Agent**: Wird aus dem aktuellen Request übernommen
- **Visitor ID**: Wird aus IP/UA Hash oder Cookie generiert
- **Zeit/Datum**: Aktuelle Serverzeit

### ⚙️ Voraussetzungen in Matomo

Damit das Server-Side Tracking korrekt läuft, sind evtl. Einstellungen in Matomo nötig:

1.  **API-Token**: Damit Matomo die User-IP (`cip`) akzeptiert, benötigt der Request einen Token mit **Write**- oder **Admin**-Rechten. Das erledigt die Klasse automatisch mit dem in der Einrichtung hinterlegten Token.
2.  **E-Commerce**: Falls `trackEcommerceOrder()` genutzt wird, muss für die Webseite in Matomo E-Commerce aktiviert sein (**Einstellungen > Messgrößen > Verwalten > Webseite bearbeiten**).
3.  **Custom Dimensions**: Vor Nutzung von `setCustomDimension()` muss die Dimension in Matomo angelegt sein.
4.  **Site Search**: Die interne Suche muss in den Webseiten-Einstellungen aktiviert sein, damit sie in den Berichten auftaucht.

## 🆘 Troubleshooting

### Matomo nicht gefunden
- Pfad und URL in der Einrichtung prüfen
- Stellen Sie sicher, dass Matomo korrekt installiert ist

### API-Fehler
- API-Token in der Einrichtung prüfen bzw. neu erzeugen lassen
- Testen Sie die Matomo-URL im Browser
- Prüfen Sie SSL-Einstellungen bei HTTPS

### Dashboard lädt nicht
- API-Token unter „Einrichtung“ prüfen (Superuser-Token nötig)
- Browser-Console auf Fehler prüfen
- CORS-Einstellungen in Matomo überprüfen

## 📝 Changelog

### Version 2.10.1
- Der Auto-Login-Block in Einrichtung Schritt 5 erscheint jetzt unabhängig von Verbindung und Superuser-Token, die Aktivierung braucht keine API

### Version 2.10.0
- **Auto-Login zurück**: „Matomo öffnen“ meldet Redakteure über Matomos logme-Funktion mit ihrem eigenen Konto an (POST, reguläre Session). Einrichtung Schritt 5 aktiviert `login_allow_logme = 1` bei lokaler Installation per Klick (Matomo-Konsole `config:set`, ersatzweise Datei), bei externem Matomo per Bestätigung. Ohne Auto-Login bleibt der Token-Zugang

### Version 2.9.0
- **Passwort je Zugang gespeichert und änderbar**: Das beim Anlegen erzeugte Matomo-Passwort wird zum Zugang gespeichert; „Mein Matomo-Zugang“ zeigt es auf Klick und ändert es über die Matomo-API mit dem eigenen Token. Funktioniert bei lokalem und externem Matomo, ohne Datenbank- oder CLI-Zugriff. Zugänge aus älteren Versionen ohne Passwort werden beim ersten Ändern mit gleichem Login neu angelegt
- **ProfessionalServices aus**: Bei lokaler Installation wird Matomos Werbe-Plugin „ProfessionalServices“ automatisch deaktiviert
- **E-Mail Pflicht**: Zugänge werden nur für REDAXO-Benutzer mit gültiger E-Mail-Adresse angelegt (Matomo verlangt je Benutzer eine eindeutige Adresse); die Einrichtung markiert Benutzer ohne Adresse, keine erzeugten Ersatzadressen mehr

### Version 2.8.2
- **Passwort setzen über Matomo selbst**: Admin-Reset und „Mein Matomo-Zugang“ nutzen jetzt bevorzugt `bin/matomo-user-password.php` per PHP-CLI, das Matomo bootstrappt und dessen UsersManager-API mit Matomos eigener Datenbankverbindung verwendet. Der direkte Datenbankzugriff bleibt als Fallback; Fehlermeldungen nennen beide Wege

### Version 2.8.1
- **Passwort-Reset**: Verbindung zur Matomo-Datenbank probiert bei „localhost“ zusätzlich TCP (127.0.0.1) und umgekehrt; die Fehlermeldung nennt Konfigurationsdatei, Benutzer, Datenbank, Passwortlänge und alle Versuche
- **Konfiguration**: optionale manuelle Zugangsdaten zur Matomo-Datenbank für den Passwort-Reset

### Version 2.8.0
- **Mein Matomo-Zugang** auf der Übersicht: Redakteure sehen Benutzername und sichtbare Websites, gelangen zur Matomo-Anmeldung und setzen sich bei lokaler Installation selbst ein Matomo-Passwort
- README: Grenze des Token-Zugangs dokumentiert (kein Login, URLs ohne Token landen auf der Anmeldeseite)
- **Fix Admin-Reset**: config.ini.php wird wie von Matomo gelesen (maskierte Anführungszeichen und Backslashes im Passwort, `unix_socket`, `port`); bisher scheiterte die Datenbankverbindung bei Passwörtern mit Sonderzeichen

### Version 2.7.2
- Auswahl der sichtbaren Websites je Benutzer nutzt den Bootstrap-Selectpicker (Alle auswählen, Zähler, Platzhalter „Alle Websites“) statt eines nativen Multiselects

### Version 2.7.1
- **Serverlast der Übersicht**: Abschnitte laden nacheinander statt parallel, Antworten werden serverseitig gecacht (10 Minuten, abgeschlossene Zeiträume 6 Stunden), Auto-Refresh alle 15 Minuten nur bei sichtbarem Tab, weniger Matomo-Requests je Abschnitt (kein separater Goals-Aufruf, kleinere Listen)
- Kennzahl „Conversions“ heißt jetzt „Besuche mit Conversion“ (aus VisitsSummary)

### Version 2.7.0
- **Übersicht neu**: Kennzahlen mit Vergleich zur Vorperiode, Verlaufsdiagramm mit Hover-Tooltip und Tabellenansicht, Top-Seiten, Herkunft (Typen und Websites), Geräte, Länder, Domain-Tabelle; Dark Mode
- **Filter** nach Domain und Zeitraum, Auswahl bleibt gespeichert; „Matomo öffnen“ folgt dem Domain-Filter
- **Nicht blockierend**: Seite rendert sofort, Abschnitte laden parallel per API nach (`matomo_stats`, gebündelte Matomo-Bulk-Requests, Session wird freigegeben)
- **Fix**: YRewrite-Domainfilter vergleicht Hosts ohne Port und `www.` (Domains mit Port wurden ausgeblendet)

### Version 2.6.2
- **Korrektur zu 2.6.1**: Es gibt kein „consent_manager 6“, consent_manager bleibt wie es ist. Stattdessen wird consent_kit unter dem alten (`KLXM\ConsentKit`) und dem neuen Namespace (`FriendsOfRedaxo\ConsentKit`) unterstützt

### Version 2.6.1
- consent_kit-Anbindung über eine gemeinsame Repository-Erkennung (siehe 2.6.2)

### Version 2.6.0
- **Admin-Reset**: Passwort des Matomo-Superusers und API-Token bei lokaler Installation direkt zurücksetzen (Einrichtung, Schritt 2); alte Tokens werden verworfen
- **Sichtbare Websites je Benutzer**: Persönliche Zugänge lassen sich auf ausgewählte Matomo-Websites beschränken; gilt in Matomo (Leserecht je Website) und für Übersicht und Widgets in REDAXO

### Version 2.5.2
- **Fix Site-Namen**: Der YRewrite-Import nutzte das Seitentitel-Schema der Domain (z. B. `%T / %SN`) als Matomo-Site-Name. Jetzt ist der Host der Site-Name; bereits falsch benannte Sites werden beim Aufruf von Einrichtung oder Domains automatisch umbenannt (mit Hinweis)
- Übersicht zeigt in der Spalte „Domain“ den Host, der Matomo-Name steht darunter

### Version 2.5.1
- **Fix Multidomain in consent_manager**: Der Dienst „matomo“ trug nur eine Site-ID. Der Tracking-Code wählt die Site-ID jetzt zur Laufzeit anhand des Hostnamens, eine neu angelegte Gruppe „statistics“ wird allen Domains zugeordnet
- **Fix YRewrite-Import**: Domain-Titel mit Platzhaltern (z. B. `%T / %SN`) werden nicht mehr als Matomo-Site-Name übernommen, stattdessen der Host

### Version 2.5.0
- **Einrichtung in fünf Schritten**: Die Seiten „Matomo-Setup“ und „Konfiguration“ überschnitten sich (URL, Pfad, Token doppelt). Jetzt: eine geführte Einrichtungsseite (Bereitstellen, Verbindung, Websites, Consent-Tool, Zugänge) und eine Konfigurationsseite nur für Tracking-/Datenschutz-Optionen
- **Ein API-Token**: Admin- und User-Token zusammengelegt (der User-Token fiel ohnehin auf den Admin-Token zurück). Token wird auf Wunsch direkt aus Matomo-Login und Passwort erzeugt, Passwort wird nicht gespeichert
- **Persönliche Zugänge statt Auto-Login**: Je REDAXO-Benutzer ein Matomo-Benutzer mit Leserecht plus App-Token; „Matomo öffnen“ nutzt Matomos Token-Zugang. Der `logme`-Auto-Login samt Patch der `config.ini.php` und gespeichertem Matomo-Passwort ist entfernt
- **Consent-Registrierung**: Matomo per Klick in consent_kit (Preset + Domain-Varianten) oder consent_manager (Gruppe „statistics“, alle Sprachen) anlegen
- **Fix**: Die Konfigurationsseite speicherte `ssl_verify`, gelesen wurde `verify_ssl` – die Einstellung war wirkungslos. Jetzt einheitlich `verify_ssl` (Update migriert den Wert)
- **Fix**: `api_timeout` wurde gespeichert, aber die API nutzte fest 10 Sekunden

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
