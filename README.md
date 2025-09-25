# Matomo AddOn for REDAXO 5

The **Matomo AddOn** provides complete integration of the open-source web analytics platform Matomo into REDAXO 5. It enables easy downloading, installing, and managing of Matomo directly from the REDAXO backend.

## 🚀 Features

### ✅ **Automated Installation**
- **One-click download** of the latest Matomo version
- **Automatic configuration** of URL and path
- **REDAXO-native implementation** using `rex_socket`, `rex_file`, and `rex_dir`

### 📊 **Dashboard & Overviews**
- **Configurable dashboard page** with Matomo iframe integration
- **Compact overview page** with statistics for all domains
- **Real-time data** with automatic refresh
- **Direct links** to specific Matomo dashboards

### 🌐 **Domain Management**
- **API-based domain management** via Matomo API
- **Tracking code generation** for each domain
- **Copy-to-clipboard functionality** for tracking codes
- **Consent manager integration** recommendations

### ⚙️ **Advanced Configuration**
- **Flexible API settings** (timeout, SSL verification)
- **Privacy options** (IP anonymization, cookie-free tracking)
- **Dashboard domain selection** for personalized views
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

## 📋 System Requirements

- **REDAXO**: Version 5.7.0 or higher
- **PHP**: Version 7.1 or higher
- **PHP Extensions**: cURL
- **Matomo**: Compatible with Matomo 4.x and 5.x

## 🛠️ Installation

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
- Select dashboard domain
- Configure privacy settings

### 3. **Manage Domains**
Under **Matomo → Domains**:
- Add new domains to Matomo
- Display and copy tracking codes
- Follow consent manager recommendations

### 4. **View Statistics**
- **Matomo → Overview**: Compact statistics for all domains
- **Matomo → Dashboard**: Detailed view of configured domain

## 🔐 Setting up API Tokens

### Admin Token (required)
For administrative tasks like domain creation:
1. Log into Matomo
2. **Administration → Platform → API → User Authentication**
3. **Copy Admin Token** and paste into REDAXO

### User Token (optional)
For dashboard access and statistics:
1. Open **User Authentication** in Matomo
2. **Copy User Token** (if not available, Admin Token will be used)

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

### Dashboard
- `dashboard_site`: Default domain for dashboard (0 = All Sites)

## 🔄 API Integration

The AddOn uses the **Matomo HTTP API** for:
- Site management (create, list)
- Statistics queries (visitors, page views)
- Tracking code generation
- Dashboard widget integration

All HTTP requests are made via `rex_socket` with configurable timeouts and SSL options.

## 🆘 Troubleshooting

### Matomo not found
- Check path and URL in configuration
- Ensure Matomo is correctly installed

### API errors
- Verify API tokens
- Test Matomo URL in browser
- Check SSL settings for HTTPS

### Dashboard won't load
- Configure User Token or use Admin Token
- Check browser console for errors
- Verify CORS settings in Matomo

## 📝 Changelog

### Version 1.2.2
- Dashboard domain configuration
- Overview page with statistics
- User Token support
- Extended GDPR options
- Complete multi-language support

## Credits

**Project Lead**  
[Daniel Springer](https://github.com/danspringer)

**Project Initiator**  
[Thomas Skerbis](https://github.com/skerbis)

## 🤝 Support

- **GitHub**: https://github.com/FriendsOfREDAXO/matomo
- **REDAXO Community**: https://redaxo.org/forum/
- **Matomo Documentation**: https://matomo.org/docs/

## 📄 License

This AddOn is available under the MIT License. Matomo itself is available under the GPL v3 License.

---

**Developed by Friends Of REDAXO**  
For REDAXO 5.7+ | Matomo 4.x/5.x compatible
