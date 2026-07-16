# Matomo AddOn for REDAXO 5

The **Matomo AddOn** provides complete integrat### 4. **View Statistics**
- **Matomo → Overview**: Compact statistics for all domains with optional top 5 pages
- **Auto-login**: Seamless access to Matomo without manual login
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
- **Automatic Login System** for seamless Matomo access
- **Direct links** to specific Matomo dashboards

### 🌐 **Domain Management**
- **API-based domain management** via Matomo API
- **YRewrite Integration** - automatic filtering and import of YRewrite domains
- **Smart duplicate detection** - prevents importing existing domains
- **Domain deletion** - remove domains from Matomo with confirmation
- **Tracking code generation** for each domain
- **Copy-to-clipboard functionality** for tracking codes
- **Consent manager integration** recommendations

### ⚙️ **Advanced Configuration**
- **Flexible API settings** (timeout, SSL verification)
- **Privacy options** (IP anonymization, cookie-free tracking)
- **Multi-token support** (Admin + User Token)

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
3. **Access Matomo Setup** and perform installation

## 📖 Usage

### 1. **Matomo Setup**
Under **Matomo → Matomo Setup**:
- Automatically download and install Matomo
- Or manually configure path, URL, and API token

### 2. **Configuration**
Under **Matomo → Configuration**:
- API settings (timeout, SSL verification)
- Tracking options (IP anonymization, cookie-free tracking)
- Configure privacy settings

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
- **Auto Login**: Seamless access to Matomo without manual login
- **Direct Domain Links**: Quick access to specific domain statistics

## 🔐 Setting up API Tokens

### Admin Token (required)
For administrative tasks like domain creation:
1. Log into Matomo
2. **Administration → Platform → API → User Authentication**
3. **Copy Admin Token** and paste into REDAXO

### User Token (optional)
For statistics access:
1. Open **User Authentication** in Matomo
2. **Copy User Token** (if not available, Admin Token will be used)

### Auto-Login Setup (optional)
For automatic login via "Auto Login" buttons:
1. **Enter Matomo username and password** in settings
2. **Automatic configuration**: The AddOn can automatically set `login_allow_logme = 1` in Matomo's `config.ini.php`
3. **Manual configuration**: If automatic setup fails, manually add to `config/config.ini.php`:
   ```ini
   [General]
   login_allow_logme = 1
   ```

## 🎯 Tracking Code Integration

**Important**: The AddOn does **not automatically** embed tracking codes.

### Recommended Integration:
1. **Use Consent Manager AddOn** (recommended: "Consent Manager")
2. **Copy tracking code** from the domains page
3. **Manually insert into templates** or manage via consent manager

### GDPR-compliant Options:
- Enable IP anonymization
- Use cookie-free tracking
- Respect Do Not Track
- Use consent manager for cookie consent

## 🔧 Configuration Options

### API Settings
- `api_timeout`: Request timeout (10-120 seconds)
- `ssl_verify`: SSL certificate verification

### Tracking Options
- `anonymize_ip`: Anonymize IP addresses
- `cookieless_tracking`: Cookie-free tracking
- `respect_dnt`: Respect Do Not Track header
- `cookie_lifetime`: Cookie lifetime

### Statistics Features
- `show_top_pages`: Enable/disable Top 5 Pages feature

### Auto-Login
- `matomo_user`: Matomo username for automatic login
- `matomo_password`: Matomo password for automatic login



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
- Real visitor IP (when Admin Token is configured)
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
<script defer src="/redaxo/assets/addons/matomo/matomo-events.js"></script>
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
- **IP Address**: Passed to Matomo (requires Admin Token in config)
- **User Agent**: Taken from current request
- **Visitor ID**: Generated from IP/UA hash or Cookie
- **Time/Date**: Current server time

### ⚙️ Requirements in Matomo

To ensure Server-Side Tracking works correctly, some settings in Matomo might be needed:

1.  **Admin Token**: The tracker needs a Token with **Write** or **Admin** permission to set the Visitor IP (`cip`). This is automatically handled if you entered the Admin Token in the AddOn configuration.
2.  **E-Commerce**: If you use E-Commerce tracking, you must enable "Ecommerce" for the specific website in Matomo (**Measurables > Manage > Edit Site**).
3.  **Custom Dimensions**: If you use `setCustomDimension()`, you must first create these dimensions in Matomo (**Administration > Websites > Custom Dimensions**).
4.  **Site Search**: For Site Search to appear in reports, ensure "Site Search" is enabled in the website settings (usually enabled by default).

## 🆘 Troubleshooting

### Matomo not found
- Check path and URL in configuration
- Ensure Matomo is correctly installed

### API errors
- Verify API tokens
- Test Matomo URL in browser
- Check SSL settings for HTTPS



## 📝 Changelog

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
