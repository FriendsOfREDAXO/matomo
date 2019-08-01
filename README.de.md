# matomo
Matomo - Dashboard für REDAXO 5.x

## Features: 
- Bindet das Matomo-Dashboard für eine festgelegte Präsenz ein
- Buttun zum direkten Einloggen in matomo
- Per API wird nach dem Speichern der Einstellungen der Tracking-Code abgeholt

## Geplant: 
- minibar widget

## Tracking-Code:

Der Tracking-Code kann per Copy&Paste oder mit nachfolgendem PHP-Code im Template eingebunden werden: 

```php
echo rex_addon::get('matomo')->getConfig('matomojs');
```
## Credits

**Projekt-Lead**

[Thomas Skerbis](https://github.com/skerbis)
