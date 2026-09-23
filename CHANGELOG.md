# Changelog

Alle Änderungen am Matomo-AddOn für REDAXO. Die Versionen sind absteigend sortiert.

## 2.13.0 – 2026-09-23
- **Weitere Websites in der Übersicht**: Unter Konfiguration lassen sich Matomo-Websites freischalten, die nicht zu den YRewrite-Domains dieser Installation gehören (z. B. extern betreute Seiten im selben Matomo). Sie erscheinen in Übersicht, Widgets und Domain-Filter; Website-Rechte je Benutzer gelten weiterhin

## 2.12.0 – 2026-09-23
- **Zugänge je REDAXO-Rolle**: In Schritt 5 lassen sich persönliche Zugänge für alle Benutzer einer Rolle auf einmal anlegen (nur mit gültiger E-Mail, bereits vorhandene werden übersprungen), wahlweise beschränkt auf ausgewählte Websites. Die Zugangstabelle zeigt die Rollen der Benutzer

## 2.11.2 – 2026-09-23
- **Fix**: Fehlt ein Matomo-Benutzer zu einem gespeicherten Zugang (z. B. direkt in Matomo gelöscht), brach der Abgleich beim Laden der Einrichtung mit „Zugang konnte nicht angelegt werden: User … doesn't exist“ ab. Solche Zugänge werden jetzt in der Tabelle markiert und lassen sich neu anlegen oder entfernen

## 2.11.1 – 2026-09-23
- **Fix**: Leserecht auf „alle Websites“ ist in Matomo ein Schnappschuss; später angelegte Websites fehlten dem Benutzer (Fehler „benötigt view-Zugriff für Webseite id = …“ beim Öffnen). Zugänge mit „alle“ werden jetzt abgeglichen: beim Anlegen/Importieren von Domains über das AddOn, beim Aufruf der Einrichtung bei geänderter Website-Liste und per Knopf „Website-Rechte abgleichen“

## 2.11.0 – 2026-09-22

Sammelrelease: fasst alle Neuerungen seit 2.5.0 zusammen (die Zwischenversionen 2.5.0 bis 2.10.2 werden aus dem Installer entfernt). Die Einzelheiten und Korrekturen stehen in den Einträgen darunter.

- **Einrichtung in fünf Schritten** statt zweier sich überschneidender Seiten: Bereitstellen, Verbindung, Websites, Consent-Tool, persönliche Zugänge
- **Ein API-Token**, auf Wunsch direkt aus Matomo-Login und Passwort erzeugt (Passwort wird nicht gespeichert); Superuser-Reset bei verlorenem Passwort (lokale Installation)
- **Persönliche Zugänge**: eigenes Matomo-Konto je REDAXO-Benutzer mit Passwort, Token und Auswahl der sichtbaren Websites; „Mein Matomo-Zugang“ auf der Übersicht zum Anzeigen und Ändern des Passworts
- **Auto-Login** über Matomos logme mit dem eigenen Konto, Aktivierung per Klick
- **Consent-Registrierung** in consent_kit und consent_manager
- **Neue Übersicht**: Kennzahlen mit Vorperiode, Verlauf, Top-Seiten, Herkunft, Geräte, Länder, Domain-Tabelle; Filter nach Domain und Zeitraum; nicht blockierend mit Server-Cache
- **ProfessionalServices** wird bei lokaler Installation automatisch deaktiviert

## 2.10.2
- **Fix**: Der obere Knopf „Matomo öffnen“ zielte ohne Website-Einschränkung fest auf Site-ID 1; existiert die nicht, schlug die Anmeldung fehl. Ohne gewählte Domain öffnet Matomo jetzt die Standard-Website des Benutzers

## 2.10.1
- Der Auto-Login-Block in Einrichtung Schritt 5 erscheint jetzt unabhängig von Verbindung und Superuser-Token, die Aktivierung braucht keine API

## 2.10.0
- **Auto-Login zurück**: „Matomo öffnen“ meldet Redakteure über Matomos logme-Funktion mit ihrem eigenen Konto an (POST, reguläre Session). Einrichtung Schritt 5 aktiviert `login_allow_logme = 1` bei lokaler Installation per Klick (Matomo-Konsole `config:set`, ersatzweise Datei), bei externem Matomo per Bestätigung. Ohne Auto-Login bleibt der Token-Zugang

## 2.9.0
- **Passwort je Zugang gespeichert und änderbar**: Das beim Anlegen erzeugte Matomo-Passwort wird zum Zugang gespeichert; „Mein Matomo-Zugang“ zeigt es auf Klick und ändert es über die Matomo-API mit dem eigenen Token. Funktioniert bei lokalem und externem Matomo, ohne Datenbank- oder CLI-Zugriff. Zugänge aus älteren Versionen ohne Passwort werden beim ersten Ändern mit gleichem Login neu angelegt
- **ProfessionalServices aus**: Bei lokaler Installation wird Matomos Werbe-Plugin „ProfessionalServices“ automatisch deaktiviert
- **E-Mail Pflicht**: Zugänge werden nur für REDAXO-Benutzer mit gültiger E-Mail-Adresse angelegt (Matomo verlangt je Benutzer eine eindeutige Adresse); die Einrichtung markiert Benutzer ohne Adresse, keine erzeugten Ersatzadressen mehr

## 2.8.2
- **Passwort setzen über Matomo selbst**: Admin-Reset und „Mein Matomo-Zugang“ nutzen jetzt bevorzugt `bin/matomo-user-password.php` per PHP-CLI, das Matomo bootstrappt und dessen UsersManager-API mit Matomos eigener Datenbankverbindung verwendet. Der direkte Datenbankzugriff bleibt als Fallback; Fehlermeldungen nennen beide Wege

## 2.8.1
- **Passwort-Reset**: Verbindung zur Matomo-Datenbank probiert bei „localhost“ zusätzlich TCP (127.0.0.1) und umgekehrt; die Fehlermeldung nennt Konfigurationsdatei, Benutzer, Datenbank, Passwortlänge und alle Versuche
- **Konfiguration**: optionale manuelle Zugangsdaten zur Matomo-Datenbank für den Passwort-Reset

## 2.8.0
- **Mein Matomo-Zugang** auf der Übersicht: Redakteure sehen Benutzername und sichtbare Websites, gelangen zur Matomo-Anmeldung und setzen sich bei lokaler Installation selbst ein Matomo-Passwort
- README: Grenze des Token-Zugangs dokumentiert (kein Login, URLs ohne Token landen auf der Anmeldeseite)
- **Fix Admin-Reset**: config.ini.php wird wie von Matomo gelesen (maskierte Anführungszeichen und Backslashes im Passwort, `unix_socket`, `port`); bisher scheiterte die Datenbankverbindung bei Passwörtern mit Sonderzeichen

## 2.7.2
- Auswahl der sichtbaren Websites je Benutzer nutzt den Bootstrap-Selectpicker (Alle auswählen, Zähler, Platzhalter „Alle Websites“) statt eines nativen Multiselects

## 2.7.1
- **Serverlast der Übersicht**: Abschnitte laden nacheinander statt parallel, Antworten werden serverseitig gecacht (10 Minuten, abgeschlossene Zeiträume 6 Stunden), Auto-Refresh alle 15 Minuten nur bei sichtbarem Tab, weniger Matomo-Requests je Abschnitt (kein separater Goals-Aufruf, kleinere Listen)
- Kennzahl „Conversions“ heißt jetzt „Besuche mit Conversion“ (aus VisitsSummary)

## 2.7.0
- **Übersicht neu**: Kennzahlen mit Vergleich zur Vorperiode, Verlaufsdiagramm mit Hover-Tooltip und Tabellenansicht, Top-Seiten, Herkunft (Typen und Websites), Geräte, Länder, Domain-Tabelle; Dark Mode
- **Filter** nach Domain und Zeitraum, Auswahl bleibt gespeichert; „Matomo öffnen“ folgt dem Domain-Filter
- **Nicht blockierend**: Seite rendert sofort, Abschnitte laden parallel per API nach (`matomo_stats`, gebündelte Matomo-Bulk-Requests, Session wird freigegeben)
- **Fix**: YRewrite-Domainfilter vergleicht Hosts ohne Port und `www.` (Domains mit Port wurden ausgeblendet)

## 2.6.2
- **Korrektur zu 2.6.1**: Es gibt kein „consent_manager 6“, consent_manager bleibt wie es ist. Stattdessen wird consent_kit unter dem alten (`KLXM\ConsentKit`) und dem neuen Namespace (`FriendsOfRedaxo\ConsentKit`) unterstützt

## 2.6.1
- consent_kit-Anbindung über eine gemeinsame Repository-Erkennung (siehe 2.6.2)

## 2.6.0
- **Admin-Reset**: Passwort des Matomo-Superusers und API-Token bei lokaler Installation direkt zurücksetzen (Einrichtung, Schritt 2); alte Tokens werden verworfen
- **Sichtbare Websites je Benutzer**: Persönliche Zugänge lassen sich auf ausgewählte Matomo-Websites beschränken; gilt in Matomo (Leserecht je Website) und für Übersicht und Widgets in REDAXO

## 2.5.2
- **Fix Site-Namen**: Der YRewrite-Import nutzte das Seitentitel-Schema der Domain (z. B. `%T / %SN`) als Matomo-Site-Name. Jetzt ist der Host der Site-Name; bereits falsch benannte Sites werden beim Aufruf von Einrichtung oder Domains automatisch umbenannt (mit Hinweis)
- Übersicht zeigt in der Spalte „Domain“ den Host, der Matomo-Name steht darunter

## 2.5.1
- **Fix Multidomain in consent_manager**: Der Dienst „matomo“ trug nur eine Site-ID. Der Tracking-Code wählt die Site-ID jetzt zur Laufzeit anhand des Hostnamens, eine neu angelegte Gruppe „statistics“ wird allen Domains zugeordnet
- **Fix YRewrite-Import**: Domain-Titel mit Platzhaltern (z. B. `%T / %SN`) werden nicht mehr als Matomo-Site-Name übernommen, stattdessen der Host

## 2.5.0
- **Einrichtung in fünf Schritten**: Die Seiten „Matomo-Setup“ und „Konfiguration“ überschnitten sich (URL, Pfad, Token doppelt). Jetzt: eine geführte Einrichtungsseite (Bereitstellen, Verbindung, Websites, Consent-Tool, Zugänge) und eine Konfigurationsseite nur für Tracking-/Datenschutz-Optionen
- **Ein API-Token**: Admin- und User-Token zusammengelegt (der User-Token fiel ohnehin auf den Admin-Token zurück). Token wird auf Wunsch direkt aus Matomo-Login und Passwort erzeugt, Passwort wird nicht gespeichert
- **Persönliche Zugänge statt Auto-Login**: Je REDAXO-Benutzer ein Matomo-Benutzer mit Leserecht plus App-Token; „Matomo öffnen“ nutzt Matomos Token-Zugang. Der `logme`-Auto-Login samt Patch der `config.ini.php` und gespeichertem Matomo-Passwort ist entfernt
- **Consent-Registrierung**: Matomo per Klick in consent_kit (Preset + Domain-Varianten) oder consent_manager (Gruppe „statistics“, alle Sprachen) anlegen
- **Fix**: Die Konfigurationsseite speicherte `ssl_verify`, gelesen wurde `verify_ssl` – die Einstellung war wirkungslos. Jetzt einheitlich `verify_ssl` (Update migriert den Wert)
- **Fix**: `api_timeout` wurde gespeichert, aber die API nutzte fest 10 Sekunden

## 2.4.0
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

## 2.1
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

## 2.0
- **Auto-Login System**: Nahtloser Matomo-Zugang ohne manuelle Anmeldung
- **Top 5 Seiten Feature**: Zeigt meistbesuchte Seiten der aktuellen Woche
- **Externe Matomo Unterstützung**: Vollständige Integration externer Matomo-Installationen
- **Enhanced Overview Page**: Erweiterte Statistiken mit Trend-Anzeige
- **Automatische Konfiguration**: Auto-Login kann automatisch in Matomo konfiguriert werden
- **Verbessertes UI**: Einheitliches Panel-Design und bessere Benutzerführung
- **Namespace Migration**: Vollständige Migration zu FriendsOfRedaxo\Matomo Namespace
- **Dashboard Entfernung**: Fokus auf streamlined Overview-basierte Ansätze
