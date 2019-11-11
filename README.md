# matomo
Matomo - Dashboard for REDAXO 5.x

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/matomo/assets/matomo.png)

## Features: 
- Embeds the matomo Dashboard for a specific site
- Delivers a one-click LogIn to matomo
- Load embed code via API
- Settings for Tracking-Code options

## Tracking-Code:

You may insert the tracking code by adding 

```php
echo rex_addon::get('matomo')->getConfig('matomojs');
```
to your template.

## Credits

**Projekt-Lead**

[Daniel Springer](https://github.com/danspringer)

**Projekt-Initiator**

[Thomas Skerbis](https://github.com/skerbis)
