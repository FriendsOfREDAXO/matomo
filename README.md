# matomo
Matomo - Dashboard and Tracking-Code Generator for REDAXO 5.x

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/matomo/assets/matomo.png)

## Features: 
- Embeds the matomo Dashboard for a specific site
- Support for multiple domains (multidomain websites with YRewrite)
- Delivers a one-click LogIn to matomo
- Load embed code via API
- Settings for Tracking-Code options
- Dashboard switching between different domains

## Tracking-Code:

### Single Domain (Legacy)
You may insert the tracking code by adding 

```php
echo rex_addon::get('matomo')->getConfig('matomojs');
```
to your template.

### Multiple Domains (Multidomain)
For multidomain setups, you can use domain-specific tracking codes:

```php
$addon = rex_addon::get('matomo');
$tracking_codes = $addon->getConfig('domain_tracking_codes', []);

// Get tracking code for a specific domain
if (isset($tracking_codes['yourdomain.com'])) {
    echo $tracking_codes['yourdomain.com'];
}

// Or automatically based on current domain
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
