# Matomo AddOn für REDAXO 5

Das **Matomo AddOn** bietet eine vollständige Integration der Open-Source Web-Analytics-Plattform Matomo in REDAXO 5. Es ermöglicht das einfache Herunterladen, Installieren und Verwalten von Matomo direkt aus dem REDAXO Backend.

## 🚀 Features

### ✅ **Automatisierte Installation**
- **Ein-Klick Download** der neuesten Matomo-Version
- **Automatische Konfiguration** von URL und Pfad
- **REDAXO-native Implementierung** mit `rex_socket`, `rex_file` und `rex_dir`

### 📊 **Übersichten & Analytics**
- **Kompakte Übersichtsseite** mit Statistiken aller Domains
- **Top 5 Seiten Feature** - zeigt meistbesuchte Seiten der aktuellen Woche
- **Echtzeitdaten** mit automatischem Refresh (alle 5 Minuten)
- **Automatisches Login-System** für nahtlosen Matomo-Zugang
- **Direkte Links** zu spezifischen Matomo-Dashboards

### 🌐 **Domain-Management**
- **API-basierte Domain-Verwaltung** über Matomo API
- **Tracking-Code Generierung** für jede Domain
- **Copy-to-Clipboard Funktionalität** für Tracking-Codes
- **Consent-Manager Integration** Empfehlungen

### ⚙️ **Erweiterte Konfiguration**
- **Flexible API-Einstellungen** (Timeout, SSL-Verifikation)
- **Datenschutz-Optionen** (IP-Anonymisierung, Cookie-freies Tracking)
- **Dashboard-Domain Auswahl** für personalisierte Ansichten
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

## 📋 Systemanforderungen

- **REDAXO**: Version 5.7.0 oder höher
- **PHP**: Version 7.1 oder höher
- **PHP Extensions**: cURL
- **Matomo**: Kompatibel mit Matomo 4.x und 5.x

## 🛠️ Installation

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
- Dashboard-Domain auswählen
- Datenschutz-Einstellungen konfigurieren

### 3. **Domains verwalten**
Unter **Matomo → Domains**:
- Neue Domains zu Matomo hinzufügen
- Tracking-Codes anzeigen und kopieren
- Consent-Manager Empfehlungen beachten

### 4. **Statistiken ansehen**
- **Matomo → Übersicht**: Kompakte Statistiken aller Domains mit optionalen Top 5 Seiten
- **Automatisch anmelden**: Nahtloser Zugang zu Matomo ohne manuelle Anmeldung
- **Direkte Domain-Links**: Schneller Zugriff auf spezifische Domain-Statistiken

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

Alle HTTP-Requests erfolgen über `rex_socket` mit konfigurierbaren Timeouts und SSL-Optionen.

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

### Version 1.3.0
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

## 🤝 Support

- **GitHub**: https://github.com/FriendsOfREDAXO/matomo
- **REDAXO Community**: https://redaxo.org/forum/
- **Matomo Documentation**: https://matomo.org/docs/

## 📄 Lizenz

Dieses AddOn steht unter der MIT-Lizenz. Matomo selbst ist unter der GPL v3 Lizenz verfügbar.

---

**Entwickelt von Friends Of REDAXO**  
Für REDAXO 5.7+ | Matomo 4.x/5.x kompatibel
