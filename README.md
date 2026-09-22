# Matomo AddOn for REDAXO 5

The **Matomo AddOn** provides complete integrat### 4. **View Statistics**
- **Matomo → Overview**: Compact statistics for all domains with optional top 5 pages
- **Open Matomo**: With personal access the user lands directly in Matomo, no login
- **Direct Domain Links**: Quick access to specific domain statistics

### 5. **Dashboard & Info-Center Widgets** 📊

#### **Info-Center Widget** (compact)
- **Automatic Integration**: If Info-Center AddOn is installed
- **Permission-based**: Only visible for users with `matomo[overview]` permission
- **Live Statistics**: Shows today's visitors for top 3 websites
- **YRewrite-Synced**: Automatically filters to YRewrite domains
- **Direct Access**: One-click access to full Matomo overview

#### **Dashboard Widget** (extended)
- **Automatic Integration**: If Dashboard AddOn is installed
- **Permission-based**: Only visible for users with `matomo[overview]` permission
- **Extended Statistics**: Top 5 websites with today's visitor counts in table format
- **Larger Format**: 2-column layout for more information
- **YRewrite Integration**: Automatic filtering to YRewrite domainsof the open-source web analytics platform Matomo into REDAXO 5. It enables easy downloading, installing, and managing of Matomo directly from the REDAXO backend.

## 🚀 Features

### ✅ **Automated Installation**
- **One-click download** of the latest Matomo version
- **Automatic configuration** of URL and path
- **REDAXO-native implementation** using `rex_socket`, `rex_file`, and `rex_dir`

### 📊 **Statistics Overview**
- **Compact overview page** with statistics for all domains
- **Top 5 Pages Feature** - shows most visited pages of the current week
- **Real-time data** with automatic refresh (every 5 minutes)
- **Personal access** – Matomo opens directly without login (token access, no change to the Matomo configuration)
- **Direct links** to specific Matomo dashboards

### 🌐 **Domain Management**
- **API-based domain management** via Matomo API
- **YRewrite Integration** - automatic filtering and import of YRewrite domains
- **Smart duplicate detection** - prevents importing existing domains
- **Domain deletion** - remove domains from Matomo with confirmation
- **Tracking code generation** for each domain
- **Copy-to-clipboard functionality** for tracking codes
- **Consent registration** – create Matomo in consent_kit or consent_manager with one click

### ⚙️ **Advanced Configuration**
- **Flexible API settings** (timeout, SSL verification)
- **Privacy options** (IP anonymization, cookie-free tracking)
- **One API token**, optionally generated straight from Matomo login and password
- **Consent registration**: create Matomo in consent_kit or consent_manager with one click

### 🔒 **GDPR Compliance**
- **IP anonymization** can be enabled
- **Cookie-free tracking** available
- **Do Not Track** support
- **Consent manager** integration recommended

### 🌍 **Multi-language Support**
- **Fully translated** (German/English)
- **REDAXO i18n system** integration
- **Consistent terminology** across all pages

## 🖥️ System Requirements & Recommendations

### Requirements
- **REDAXO 5.16.1+**
- **PHP 8.2+** (Recommended: PHP 8.4+)
- **rex_socket** (Core Component) for API management

### Recommendations
- **PHP cURL Extension**: Highly recommended for Server-Side Tracking.
    - Enables "Fire-and-Forget" requests (minimizes page load impact)
    - If cURL is missing, it falls back to a performant native socket implementation (since v2.2)
- **SSL Certificate**: Recommended for all domains (HTTPS)
- **YRewrite AddOn**: Recommended for multi-domain management

## ️ Installation

1. **Install AddOn** via REDAXO installer or manually
2. **Activate AddOn** in REDAXO backend
3. Open **Matomo → Setup** and walk through the five steps

## 📖 Usage

### 1. **Setup**
**Matomo → Setup** guides through the initial configuration in five steps, each showing whether it is done:

1. **Provide Matomo** – download Matomo into a folder below the web root, then complete Matomo's own installation wizard (database, superuser, first website). If you already run Matomo (even externally), skip this step.
2. **Connection** – enter the Matomo URL and the API token. Easiest: enter username and password of a Matomo superuser, the add-on generates the token itself (the password is not stored). Alternatively paste an existing token.
3. **Websites** – lists the websites in Matomo, with a link to domain management (YRewrite import).
4. **Consent tool** – registers Matomo as a service in **consent_kit** or **consent_manager** if installed (see below).
5. **Personal access** – create a Matomo access per REDAXO user (see below).

### 2. **Configuration**
**Matomo → Configuration** now only holds the options:
- API settings (timeout, SSL verification, socket timeout)
- Tracking features (proxy, server-side tracking, browser events, top 5 pages)
- Privacy (IP anonymization, cookie-free tracking, Do Not Track, cookie lifetime)

### 3. **Manage Domains**
Under **Matomo → Domains**:
- Add new domains to Matomo manually
- **Import YRewrite domains** - select and import domains from YRewrite configuration
- **Delete domains** from Matomo with safety confirmation
- Display and copy tracking codes
- Follow consent manager recommendations

#### **YRewrite Integration:**
- **Automatic filtering**: Overview shows only YRewrite domains (+ default domain)
- **Smart import**: Select YRewrite domains to import into Matomo
- **Duplicate prevention**: Already existing domains are marked and skipped
- **Domain synchronization**: Keep Matomo and YRewrite domains in sync

### 4. **View Statistics**
- **Matomo → Overview**: Compact statistics for all domains with optional Top 5 pages
- **Open Matomo**: With personal access the user lands directly in Matomo, no login
- **Direct Domain Links**: Quick access to specific domain statistics

## 🔐 API token

The add-on needs exactly **one token of a Matomo superuser** (domains, consent registration, access management, statistics). Two ways:

- **Automatic (recommended)**: enter Matomo username and password in the setup. The add-on calls `UsersManager.createAppSpecificTokenAuth`, which Matomo allows without an existing token. The password is used for this single call only and never stored.
- **Manual**: create a token in Matomo under **Administration → Personal → Security → Auth tokens** and paste it.

The former separate "user token" is gone; an existing value is removed on update.

### Reset admin password and token

The Matomo API does not allow a password change without the current password. If it is lost, the setup (step 2, collapsible) offers a reset for **local installations**: the add-on reads the database credentials from Matomo's `config/config.ini.php`, resets the password of the given superuser directly in the user table (as described in Matomo's FAQ for lost passwords), revokes all tokens of that user and creates a new token. The new password is shown once. For an external Matomo use "Forgot password" there, then let the token be regenerated here.

## 👤 Personal access (replaces auto-login)

The former auto-login via `login_allow_logme` (MD5 password in the URL, patching Matomo's `config.ini.php`) has been removed. The add-on now uses Matomo's own token access:

- In setup step 5 every REDAXO user gets an **own Matomo user** (view access to all websites) plus a **personal app token** with one click. Login and e-mail come from the REDAXO user, the password is random and not stored.
- "Open Matomo" on the overview then calls `index.php?module=CoreHome&…&token_auth=…` – Matomo opens its full interface as that user, without login and without touching the Matomo configuration.
- Matomo only allows token access to the UI for users **without** write/superuser permissions. For Matomo administration, log in normally.
- **Visible websites per user**: in the access table you can define per REDAXO user which Matomo websites they may see (no selection = all). The selection is set as view access per website in Matomo and additionally filters the overview and widgets in REDAXO.
- "Remove" deletes the Matomo user and token again. Tokens are stored in the REDAXO configuration (`user_access`).
- Requirement: `only_allow_secure_auth_tokens` must not be enabled in Matomo (default: off).

## 🍪 Consent registration

If **consent_kit** or **consent_manager** is installed, setup step 4 creates Matomo as a service there, including the tracking code (also with the proxy enabled) and cookie details (`_pk_id*`, `_pk_ses*`, `_pk_ref*`):

- **consent_kit / consent_manager 6**: service `matomo` from the bundled preset with `matomo_url` and `site_id` from the add-on settings. For every consent_kit domain whose host matches a Matomo website, a variant with the matching site ID is created. Texts, group and domains you already edited are kept on update.
- **consent_manager 5**: cookie `matomo` in the `statistics` group for all languages; the group is created if missing and assigned to all domains. Since consent_manager only knows services across all domains, the stored tracking code picks the site ID at runtime by hostname (all Matomo websites, each with and without `www.`); the website selected in the setup is the fallback. An update only rewrites the tracking code.

## 🎯 Tracking Code Integration

**Important**: The AddOn does **not automatically** embed tracking codes.

### Recommended Integration:
1. Install **consent_kit** or **consent_manager** and register Matomo in setup step 4 – tracking code and cookie details are stored there automatically
2. Without a consent tool: **copy the tracking code** from the domains page and **insert it manually into templates**

### GDPR-compliant Options:
- Enable IP anonymization
- Use cookie-free tracking
- Respect Do Not Track
- Use consent manager for cookie consent

## 🔧 Configuration Options

### API Settings
- `api_timeout`: Request timeout (10-120 seconds)
- `verify_ssl`: SSL certificate verification (default: on)

### Tracking Options
- `anonymize_ip`: Anonymize IP addresses
- `cookieless_tracking`: Cookie-free tracking
- `respect_dnt`: Respect Do Not Track header
- `cookie_lifetime`: Cookie lifetime

### Statistics Features
- `show_top_pages`: Enable/disable Top 5 Pages feature

### Connection & access
- `matomo_url`, `matomo_path`, `admin_token`: connection (setup step 2)
- `user_access`: personal access per REDAXO user ID (Matomo login + token)



## 🔄 API Integration

The AddOn uses the **Matomo HTTP API** for:
- Site management (create, list)
- Statistics queries (visitors, page views)
- Tracking code generation

All HTTP requests are made via `rex_socket` with configurable timeouts and SSL options.

## Server-Side Tracking (No JS & No Cookies)

REDAXO can take over the role of Matomo JavaScript entirely, reporting page views directly from the server to Matomo. This makes tracking independent of adblockers, disabled JavaScript, and browser tracking protection.

### Activation

Under **Matomo → Configuration**:
1. Enable **"Enable server-side tracking"**
2. Enter the **Matomo Site ID** (found in Matomo under Administration → Websites)

### What is tracked automatically
- Page views (title + URL) of all REDAXO articles
- Real visitor IP (when the API token is configured)
- User-Agent + Accept-Language
- HTTP Referer
- YCom users as User ID (when YCom is installed)
- Bots are automatically filtered out

### Browser Event Tracking (`matomo-events.js`)

In addition to server-side page tracking, a lightweight JS script can be enabled that detects browser-only events and reports them via the REDAXO server to Matomo:

- **Downloads** (PDF, ZIP, MP3 etc. – configurable extensions)
- **Outbound links** (clicks to external domains)
- **Form submissions** (all `<form>` elements)
- **Custom events** via `data-matomo-event` attribute

Activate under **Matomo → Configuration → "Enable browser event tracking"**.

Important: The AddOn intentionally does **not** auto-inject this script into frontend output.
Integration must be done manually by the integrator, e.g. via consent manager or template.

#### Manual Integration (Consent Manager / Template)
```html
<script>
window.MatomoEventsConfig = {
    endpoint: '/index.php?rex-api-call=matomo_event'
};
</script>
<script defer src="/assets/addons/matomo/matomo-events.js"></script>
```

Note: For subdirectory installations, adjust paths to the correct webroot (e.g. `/subdir/index.php?rex-api-call=matomo_event`).

#### Custom Events via Data Attribute (no JS required)
```html
<button data-matomo-event='{"category":"CTA","action":"Click","name":"Hero Button"}'>
    Book now
</button>

<a href="/product" data-matomo-event='{"category":"Product","action":"View"}'>
    View product
</a>
```

#### Script Configuration (optional)
```html
<script>
window.MatomoEventsConfig = {
    trackOutbound:  true,
    trackDownloads: true,
    extensions: ['pdf', 'zip', 'docx', 'mp4'],  // custom extensions
};
</script>
```

## 🎯 `MatomoTrack` – PHP Tracking Facade

For tracking from PHP code (modules, templates, `rex_api` functions) the static helper class `MatomoTrack` is available. It is a no-op when tracking is not configured – so it can always be called safely.

```php
use FriendsOfRedaxo\Matomo\MatomoTrack;

// Form submission (e.g. in YForm action or rex_api)
MatomoTrack::event('Form', 'Submit', 'Contact form');

// Download controller
MatomoTrack::download('https://example.com/files/brochure.pdf');

// Outbound redirect
MatomoTrack::outboundLink('https://partner.com');

// Internal search
MatomoTrack::search('redaxo themes', 'Documentation', 12);

// Goal / conversion
MatomoTrack::goal(3, 29.90);

// Manual page view (e.g. from a headless controller)
MatomoTrack::pageView('Product Detail – Red T-Shirt', 'https://example.com/products/red-tshirt');
```

## 💻 PHP Tracking API (Server-Side – direct class)

The AddOn includes a powerful PHP `Tracker` class for direct server-side tracking (e.g. for API endpoints, cronjobs, or headless applications). It uses **native sockets** in fire-and-forget mode.

### Basic Usage

```php
use FriendsOfRedaxo\Matomo\Tracker;

// 1. Initialize Tracker (automatically uses URL & Token from Config)
// You must provide the Site ID (e.g. 1)
$tracker = Tracker::factory(1);

if ($tracker) {
    // 2. Track a simple Page View
    // URL is optional (defaults to current URL)
    $tracker->trackPageView('Home Page', 'https://example.org/');

    // 3. Track an Event
    // Category, Action, Name (optional), Value (optional)
    $tracker->trackEvent('Contact Form', 'Submit', 'General Inquiry', 1);

    // 4. Track a Download
    $tracker->trackDownload('https://example.com/files/brochure.pdf');

    // 5. Track an Outbound Link
    $tracker->trackOutboundLink('https://partner.com');
    
    // 4. Track a Goal (Conversion)
    // Goal ID, Revenue (optional)
    $tracker->trackGoal(1, 49.90);
    
    // 5. Site Search
    // Keyword, Category (optional), Count (optional)
    $tracker->trackSiteSearch('redaxo', 'CMS', 12);
}
```

### Advanced Features

#### User ID & Custom Dimensions
```php
// Set a User ID (for Cross-Device Tracking)
$tracker->setUserId('user_123');

// Set Custom Dimensions (requires Plugin in Matomo)
$tracker->setCustomDimension(1, 'premium-user'); // Dimension ID 1
```

#### E-Commerce Tracking
```php
// 1. Add items to cart/order
$tracker->addEcommerceItem(
    'SKU12345',      // SKU
    'Red T-Shirt',   // Product Name
    ['Clothing', 'Shirts'], // Category (String or Array)
    19.99,           // Price
    1                // Quantity
);

// 2. Track the order
$tracker->trackEcommerceOrder(
    'ORDER-2024-001', // Order ID
    19.99,            // Grand Total
    16.80,            // Sub Total (optional)
    3.19,             // Tax (optional)
    0.00,             // Shipping (optional)
    false             // Discount (optional)
);
```

### Automatic Data
The Tracker automatically determines:
- **IP Address**: Passed to Matomo (requires the API token from the setup)
- **User Agent**: Taken from current request
- **Visitor ID**: Generated from IP/UA hash or Cookie
- **Time/Date**: Current server time

### ⚙️ Requirements in Matomo

To ensure Server-Side Tracking works correctly, some settings in Matomo might be needed:

1.  **API token**: The tracker needs a token with **Write** or **Admin** permission to set the visitor IP (`cip`). This is handled automatically with the token stored in the setup.
2.  **E-Commerce**: If you use E-Commerce tracking, you must enable "Ecommerce" for the specific website in Matomo (**Measurables > Manage > Edit Site**).
3.  **Custom Dimensions**: If you use `setCustomDimension()`, you must first create these dimensions in Matomo (**Administration > Websites > Custom Dimensions**).
4.  **Site Search**: For Site Search to appear in reports, ensure "Site Search" is enabled in the website settings (usually enabled by default).

## 🆘 Troubleshooting

### Matomo not found
- Check path and URL in the setup
- Ensure Matomo is correctly installed

### API errors
- Check the API token in the setup or let it be regenerated
- Test Matomo URL in browser
- Check SSL settings for HTTPS



## 📝 Changelog

### Version 2.6.1
- **consent_manager 6** (successor of consent_kit) is detected and handled like consent_kit: service from the preset, variants per domain. consent_manager 5 still via the cookie tables

### Version 2.6.0
- **Admin reset**: reset the Matomo superuser password and API token directly for local installations (setup, step 2); old tokens are revoked
- **Visible websites per user**: personal access can be limited to selected Matomo websites; applies in Matomo (view access per website) and to the overview and widgets in REDAXO

### Version 2.5.2
- **Fix site names**: the YRewrite import used the domain's page title scheme (e.g. `%T / %SN`) as Matomo site name. The host is the site name now; sites already named wrongly are renamed automatically when the setup or domains page is opened (with a notice)
- The overview shows the host in the "Domain" column, the Matomo name below

### Version 2.5.1
- **Fix multi-domain in consent_manager**: the `matomo` service carried a single site ID. The tracking code now picks the site ID at runtime by hostname, and a newly created `statistics` group is assigned to all domains
- **Fix YRewrite import**: domain titles with placeholders (e.g. `%T / %SN`) are no longer used as Matomo site names, the host is used instead

### Version 2.5.0
- **Five-step setup**: "Matomo Setup" and "Configuration" overlapped (URL, path, token twice). Now one guided setup page (provide, connection, websites, consent tool, access) and a configuration page for tracking/privacy options only
- **One API token**: admin and user token merged (the user token fell back to the admin token anyway). Optionally generated straight from Matomo login and password, the password is not stored
- **Personal access instead of auto-login**: one Matomo user with view access plus app token per REDAXO user; "Open Matomo" uses Matomo's token access. The `logme` auto-login including the `config.ini.php` patch and the stored Matomo password is removed
- **Consent registration**: create Matomo in consent_kit (preset + domain variants) or consent_manager (group "statistics", all languages) with one click
- **Fix**: the configuration page saved `ssl_verify` while `verify_ssl` was read – the setting had no effect. Now `verify_ssl` everywhere (update migrates the value)
- **Fix**: `api_timeout` was saved but the API used a fixed 10 seconds

### Version 2.4.0
- **Server-Side Page Tracking**: REDAXO sends page views directly to the Matomo Tracking API – no JavaScript, no cookies, no adblocker issues
- **Bot Filter**: Automatic detection and filtering of 30+ known crawler and bot User-Agents
- **HTTP Referer**: Referer header is automatically forwarded to Matomo
- **YCom Integration**: Logged-in YCom users are tracked as User ID (cross-device)
- **`MatomoTrack` Facade**: New static helper class `MatomoTrack` for convenient tracking from modules, plugins and rex_api functions – events, downloads, outbound links, searches, goals, page views with a single call
- **`trackDownload()` / `trackOutboundLink()`**: New methods added directly to the `Tracker` class
- **Browser Event Tracking** (`matomo-events.js`): Lightweight script that automatically detects downloads, external links and form submissions and reports them to Matomo via the REDAXO server – no Matomo JS required
- **`data-matomo-event` attribute**: Any HTML element can fire custom events via a data attribute (no JS required)
- **`MatomoEventApi`**: New REDAXO API endpoint receiving browser events as JSON and forwarding them server-side to Matomo (`POST index.php?rex-api-call=matomo_event`)
- **Two new settings**: "Server-Side Tracking" (with Site ID) and "Enable Browser Event Tracking"

### Version 2.1
- **YRewrite Integration**: Full integration with YRewrite AddOn (now required)
- **Automatic Domain Filtering**: Shows only YRewrite domains in overview (+ default domain)
- **Smart Domain Import**: Import YRewrite domains into Matomo with selection interface
- **Info-Center Widget**: Compact Matomo statistics in REDAXO Info-Center (only for users with `matomo[overview]` permission)
- **Dashboard Widget**: Extended Matomo statistics in REDAXO Dashboard AddOn (Top 5 websites, table view)
- **Domain Deletion**: Remove domains from Matomo with safety confirmation
- **Smart Duplicate Detection**: Prevents importing existing domains
- **Complete Internationalization**: All texts professionally translated
- **Improved UX**: User-friendly dialogs and informative status messages
- **Clean Architecture**: YRewrite as dependency for consistent multi-domain management

### Version 2.0
- **Auto-Login System**: Seamless Matomo access without manual login
- **Top 5 Pages Feature**: Shows most visited pages of the current week
- **External Matomo Support**: Full integration of external Matomo installations
- **Enhanced Overview Page**: Extended statistics with trend indicators
- **Automatic Configuration**: Auto-login can be automatically configured in Matomo
- **Improved UI**: Consistent panel design and better user guidance
- **Namespace Migration**: Complete migration to FriendsOfRedaxo\Matomo namespace
- **Dashboard Removal**: Focus on streamlined overview-based approaches

## Credits

**Project Leads**  
[Daniel Springer](https://github.com/danspringer)

[Thomas Skerbis](https://github.com/skerbis)

**Contributors**  
Thanks to [VIEWSION](https://github.com/VIEWSION) for the Tracker refactoring in [PR #22](https://github.com/FriendsOfREDAXO/matomo/pull/22)

## 🤝 Support

- **GitHub**: https://github.com/FriendsOfREDAXO/matomo
- **REDAXO Community**: https://redaxo.org/forum/
- **Matomo Documentation**: https://matomo.org/docs/

## 📄 License

This AddOn is available under the MIT License. Matomo itself is available under the GPL v3 License.

---

**Developed by Friends Of REDAXO**  
For REDAXO 5.16.1+ | Matomo 4.x/5.x compatible
