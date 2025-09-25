# MATOMO ADDON - Berechtigungen konfiguriert ✅

## 🔐 Overview-Seite Berechtigung hinzugefügt

**Berechtigung**: `matomo[overview]`  
**Schutz**: Nur Benutzer mit entsprechender Berechtigung  
**Sprache**: Mehrsprachige Titel hinzugefügt

---

## ✅ Durchgeführte Änderungen

### 1. Package.yml erweitert

**Vorher**:
```yaml
overview: { title: 'translate:matomo_overview', icon: rex-icon rex-icon-template}
```

**Nachher**:
```yaml
overview: { title: 'translate:matomo_overview', icon: rex-icon rex-icon-template, perm: matomo[overview]}
```

### 2. Berechtigungsprüfung in overview.php

**Hinzugefügt**:
```php
// Berechtigungsprüfung für Overview
if (!rex::getUser()->hasPerm('matomo[overview]')) {
    throw new rex_exception('Keine Berechtigung für die Overview-Seite!');
}
```

### 3. Sprachschlüssel hinzugefügt

**Deutsch** (`de_de.lang`):
```
matomo_overview = Übersicht
```

**Englisch** (`en_gb.lang`):
```
matomo_overview = Overview
```

---

## 🔐 Aktuelle Berechtigungsstruktur

### Hauptberechtigung
- **`matomo[]`**: Grundberechtigung für das AddOn

### Unterpages-Berechtigungen
- **`matomo[overview]`**: Zugriff auf Übersichts-Seite (NEU ✅)
- **`admin[]`**: Config und Settings (Admin-only)
- **Keine spezielle**: Dashboard, Domains, Help (für alle mit matomo[])

### Übersicht der Zugriffsrechte

| Seite | Berechtigung | Beschreibung |
|-------|-------------|---------------|
| **Dashboard** | `matomo[]` | Matomo Dashboard iframe |
| **Overview** | `matomo[overview]` | Statistik-Übersicht ✅ |
| **Config** | `admin[]` | Erweiterte Konfiguration |
| **Domains** | `matomo[]` | Domain-Verwaltung |
| **Settings** | `admin[]` | Matomo Setup & Download |
| **Help** | `matomo[]` | Hilfe & Dokumentation |

---

## 🎯 Sicherheitslogik

### Overview-Berechtigung erforderlich für:
- ✅ **Statistik-Dashboard**: Gesamt-Übersicht aller Sites
- ✅ **API-Zugriff**: Laden von Besucherdaten über Matomo API
- ✅ **Site-Details**: Detaillierte Informationen zu allen Domains
- ✅ **Matomo-Direktlinks**: Links zu externen Matomo-Dashboards

### Ohne Overview-Berechtigung:
- ❌ **Keine Statistiken**: Zugriff auf Overview-Seite wird verweigert
- ✅ **Dashboard verfügbar**: Normale Dashboard-Seite weiterhin zugänglich
- ✅ **Domain-Management**: Domains hinzufügen/verwalten möglich

---

## 🛠️ Administration

### Berechtigung vergeben in REDAXO
1. **Benutzer → Rollen**
2. **Matomo AddOn** aufklappen  
3. **Overview** ✅ anhaken für gewünschte Rollen
4. **Speichern**

### Empfohlene Rollen-Konfiguration
- **Admin**: Alle Berechtigungen (`matomo[]`, `admin[]`)
- **Editor**: `matomo[]` + `matomo[overview]` (mit Statistics)
- **User**: Nur `matomo[]` (nur Dashboard)

---

**Status**: ✅ **OVERVIEW-BERECHTIGUNG AKTIV**  
**Sicherheit**: Kontrollierter Zugriff auf Statistiken  
**Flexibilität**: Granulare Rechtevergabe möglich