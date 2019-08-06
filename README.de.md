# matomo
Matomo - Dashboard für REDAXO 5.x

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/matomo/assets/matomo.png)

## Features: 
- Bindet das Matomo-Dashboard für eine festgelegte Präsenz ein
- Buttun zum direkten Einloggen in matomo
- Per API wird nach dem Speichern der Einstellungen der Tracking-Code abgeholt
- Tracking-Code-Optionen können gesetzt werden

## Tracking-Code:

Der Tracking-Code kann per Copy&Paste oder mit nachfolgendem PHP-Code im Template eingebunden werden: 

```php
echo rex_addon::get('matomo')->getConfig('matomojs');
```
## Credits

**Projekt-Lead**

[Thomas Skerbis](https://github.com/skerbis)
