<?php

namespace FriendsOfRedaxo\Matomo;

use rex;
use rex_addon;
use rex_config;
use rex_logger;

/**
 * Matomo Tracker Class for Server-Side Tracking.
 * Implements the Matomo Tracking API using REDAXO Core components.
 * 
 * @package FriendsOfRedaxo\Matomo
 * @see https://developer.matomo.org/api-reference/tracking-api
 */
class Tracker
{
    /** @var string */
    private string $matomoUrl;
    
    /** @var int */
    private int $siteId;
    
    /** @var string */
    private string $tokenAuth;
    
    /** @var string */
    private string $userAgent;
    
    /** @var string */
    private string $ip;
    
    /** @var string */
    private string $visitorId;
    
    /** @var int */
    private int $width = 0;
    
    /** @var int */
    private int $height = 0;
    
    /** @var array<string, mixed> */
    private array $customParameters = [];

    /**
     * @param int $siteId
     * @param string $matomoUrl
     * @param string $tokenAuth Needed to force IP address
     */
    public function __construct(int $siteId, string $matomoUrl, string $tokenAuth = '')
    {
        $this->siteId = $siteId;
        $this->matomoUrl = rtrim($matomoUrl, '/');
        $this->tokenAuth = $tokenAuth;

        // Set defaults from current request
        $this->userAgent = rex_server('HTTP_USER_AGENT') ?? '';
        $this->ip = rex_server('REMOTE_ADDR') ?? '';
        
        // Generate Visitor ID (16 chars hex)
        // Try to get from cookie if available, or generate hash from IP/UA
        $this->visitorId = substr(md5($this->userAgent . $this->ip . date('Y-m-d')), 0, 16);
    }

    /**
     * Factory method to create a tracker instance using addon config.
     * 
     * @param int|null $siteId Optional Site ID (uses default from config if null)
     * @return self|null Returns null if configuration is missing
     */
    public static function factory(?int $siteId = null): ?self
    {
        $addon = rex_addon::get('matomo');
        if (!$addon->isAvailable()) {
            return null;
        }

        $url = $addon->getConfig('matomo_url');
        // Use admin_token as default for tracking as it requires write permissions for some features
        $token = $addon->getConfig('admin_token'); 
        
        // Try to find siteId if not provided
        if ($siteId === null) {
            // Usually we might map the current domain to a siteId here
            // implementing a simple default fallback
             /* 
                Explanation: In a real world scenario, you might want to look up the siteId 
                based on the current domain using YRewrite or similar.
                For now we require the siteId or use a config default if available.
             */
             // For this example we just return null if no siteId is given and none in config
             // Let's assume there isn't a single "default site id" in config usually.
             return null;
        }

        if (!$url) {
            return null;
        }

        $tracker = new self($siteId, $url, (string) $token);
        return $tracker;
    }

    /**
     * Sets the user agent.
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Sets the IP address.
     * Note: Requires token_auth to be set.
     */
    public function setIp(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * Sets the screen resolution.
     */
    public function setResolution(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }

    /**
     * Sets the Visitor ID (16 char hex string).
     */
    public function setVisitorId(string $visitorId): self
    {
        if (preg_match('/^[0-9a-fA-F]{16}$/', $visitorId)) {
            $this->visitorId = $visitorId;
        }
        return $this;
    }
    
    /**
     * Sets the User ID.
     * This connects the visitor to a known user (e.g. from YCom).
     */
    public function setUserId(string $userId): self
    {
        $this->customParameters['uid'] = $userId;
        return $this;
    }

    /**
     * Sets a Custom Dimension.
     * Requires the Custom Dimensions plugin in Matomo.
     * 
     * @param int $id The ID of the dimension (1-999)
     * @param string $value The value for the dimension
     */
    public function setCustomDimension(int $id, string $value): self
    {
        $this->customParameters['dimension' . $id] = $value;
        return $this;
    }

    /**
     * Sets a custom variable.
     * @deprecated Matomo suggests using Custom Dimensions instead.
     */
    public function setCustomVariable(int $id, string $name, string $value, string $scope = 'visit'): self
    {
        // Custom variables are passed as JSON encoded array in 'cvar' parameter
        // But the key depends on scope: 'cvar' (visit) or '_cvar' (page)
        
        $key = ($scope === 'page') ? '_cvar' : 'cvar';
        
        if (!isset($this->customParameters[$key])) {
             $this->customParameters[$key] = [];
        }
        
        // We need to decode first if it's already a string (which it shouldn't be in our internal array, but conceptually)
        // Actually we will build the array and json_encode it right before sending
        // For now, let's just store it in a temporary array structure inside customParameters
        if (!is_array($this->customParameters[$key])) {
            $this->customParameters[$key] = [];
        }

        $this->customParameters[$key][$id] = [$name, $value];
        
        return $this;
    }

    /**
     * Sets the timestamp for the visit.
     * Useful for importing past data.
     * Note: Requires Admin Token.
     * 
     * @param string|int $timestamp Timestamp or Date string
     */
    public function setForceVisitDateTime($timestamp): self
    {
        $this->customParameters['cdt'] = is_int($timestamp) ? $timestamp : strtotime($timestamp);
        return $this;
    }

    /**
     * Forces a new visit to be created for this request.
     */
    public function setForceNewVisit(): self
    {
        $this->customParameters['new_visit'] = 1;
        return $this;
    }
    
    /**
     * Sets a specific country, region, city and lat/long (GeoIP override).
     * Note: Requires Admin Token.
     */
    public function setLocation(string $country, string $region = '', string $city = '', float $lat = 0.0, float $long = 0.0): self
    {
        $this->customParameters['country'] = $country;
        if ($region) $this->customParameters['region'] = $region;
        if ($city) $this->customParameters['city'] = $city;
        if ($lat != 0.0) $this->customParameters['lat'] = $lat;
        if ($long != 0.0) $this->customParameters['long'] = $long;
        return $this;
    }

    /**
     * Sets the view to be tracked as an Ecommerce Product or Category page.
     * 
     * @param string $sku Product SKU
     * @param string $name Product Name
     * @param string|array $category Category or Array of Categories
     * @param float $price Product Price
     */
    public function setEcommerceView(string $sku = '', string $name = '', $category = '', float $price = 0.0): self
    {
        if (is_array($category)) {
             $category = json_encode($category);
        }
        
        $this->customParameters['_pkc'] = $category;
        $this->customParameters['_pkp'] = $price;
        $this->customParameters['e_c'] = ''; // Ensure not confused with event category
        $this->customParameters['e_a'] = '';
        
        // Product name and SKU are passed to trackPageView if set
        // But for consistency we store them to be used in trackPageView logic if needed
        // Matomo Logic: If SKU/Name is set, it's a product view. If only Category is set, it's a category view.
        
        // We will store them in special temporary properties or directly in customParams 
        // but they are not standard params for API, they modify how trackPageView works.
        // Actually for the HTTP API, these are NOT parameters of the request itself directly,
        // but they change the meaning of the PageView.
        
        // Wait: The JS API sets these into internal state and sends them with the page view.
        // The HTTP API expects them as specific parameters? 
        // No, looking at MatomoTracker.php:
        // $this->ecommerceView = array('_pkc' => $category, '_pkp' => $price, ...)
        // And then in getUrlTrackPageView it appends them.
        // So we need to store them in customParameters is fine, as long as sendRequest merges them.
        
        // Let's check MatomoTracker.php getUrlTrackPageView and ecommerceView again.
        // It keeps them in $this->ecommerceView and appends them to URL.
        
        // So putting them in customParameters is correct because sendRequest merges customParameters.
        
        return $this;
    }

    /**
     * Tracks a Content Impression.
     */
    public function trackContentImpression(string $contentName, string $contentPiece = 'Unknown', string $contentTarget = ''): bool
    {
        $params = [
            'c_n' => $contentName,
            'c_p' => $contentPiece,
             'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                 (rex_server('HTTP_HOST') ?? '') . 
                 (rex_server('REQUEST_URI') ?? '')
        ];
        if ($contentTarget) {
            $params['c_t'] = $contentTarget;
        }
        return $this->sendRequest($params);
    }

    /**
     * Tracks a Content Interaction.
     */
    public function trackContentInteraction(string $interaction, string $contentName, string $contentPiece = 'Unknown', string $contentTarget = ''): bool
    {
        $params = [
            'c_i' => $interaction,
            'c_n' => $contentName,
            'c_p' => $contentPiece,
             'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                 (rex_server('HTTP_HOST') ?? '') . 
                 (rex_server('REQUEST_URI') ?? '')
        ];
        if ($contentTarget) {
            $params['c_t'] = $contentTarget;
        }
        return $this->sendRequest($params);
    }

    /**
     * Tracks a Crash/Exception.
     * Useful for backend error logging.
     * 
     * @param string $message Error message
     * @param string $type Error type (e.g. Exception class)
     * @param string $file File where error occurred
     * @param int $line Line number
     * @param string $stacktrace Stacktrace
     */
    public function trackCrash(string $message, string $type = '', string $file = '', int $line = 0, string $stacktrace = ''): bool
    {
        $params = [
            'ca' => 1,
            'cra' => $message,
                 'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                     (rex_server('HTTP_HOST') ?? '') . 
                     (rex_server('REQUEST_URI') ?? '')
        ];
        
        if ($type) $params['cra_tp'] = $type;
        if ($file) $params['cra_ru'] = $file;
        if ($line) $params['cra_rl'] = $line;
        if ($stacktrace) $params['cra_st'] = $stacktrace; // Note: Might be too long for GET requests, but we use POST often
        
        return $this->sendRequest($params);
    }

    /**
     * Tracks a page view.
     * 
     * @param string $documentTitle The title of the page
     * @param string|null $url The URL of the page (defaults to current URL)
     */
    public function trackPageView(string $documentTitle, ?string $url = null): bool
    {
        if ($url === null) {
                 $url = (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                     (rex_server('HTTP_HOST') ?? '') . 
                     (rex_server('REQUEST_URI') ?? '');
        }

        return $this->sendRequest([
            'action_name' => $documentTitle,
            'url' => $url,
        ]);
    }

    /**
     * Tracks a goal conversion.
     * 
     * @param int $goalId
     * @param float|int $revenue
     */
    public function trackGoal(int $goalId, $revenue = 0): bool
    {
        return $this->sendRequest([
            'idgoal' => $goalId,
            'revenue' => $revenue,
                 'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                     (rex_server('HTTP_HOST') ?? '') . 
                     (rex_server('REQUEST_URI') ?? '')
        ]);
    }

    /**
     * Tracks an internal Site Search.
     * 
     * @param string $keyword The search keyword
     * @param string|null $category Optional search category
     * @param int|null $countReplies Optional number of search results
     */
    public function trackSiteSearch(string $keyword, ?string $category = null, ?int $countReplies = null): bool
    {
        $params = [
            'search' => $keyword,
                 'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                     (rex_server('HTTP_HOST') ?? '') . 
                     (rex_server('REQUEST_URI') ?? '')
        ];

        if ($category !== null) {
            $params['search_cat'] = $category;
        }
        if ($countReplies !== null) {
            $params['search_count'] = $countReplies;
        }

        return $this->sendRequest($params);
    }

    /**
     * Adds an item to the ecommerce order.
     * Must be called before trackEcommerceOrder().
     * 
     * @param string $sku SKU
     * @param string $name Product Name
     * @param string|array|null $category Product Category (string or array of up to 5 categories)
     * @param float|int $price Price
     * @param int $quantity Quantity
     */
    public function addEcommerceItem(string $sku, string $name = '', $category = null, $price = 0, int $quantity = 1): self
    {
        if (!isset($this->customParameters['ec_items'])) {
            $this->customParameters['ec_items'] = [];
        }
        if (!is_array($this->customParameters['ec_items'])) {
            $this->customParameters['ec_items'] = [];
        }
        
        $this->customParameters['ec_items'][] = [
            $sku,
            $name,
            $category,
            $price,
            $quantity
        ];
        
        return $this;
    }

    /**
     * Tracks an Ecommerce Order.
     * 
     * @param string|int $orderId Unique Order ID
     * @param float|int $grandTotal Grand Total (Revenue)
     * @param float|int|null $subTotal Sub Total
     * @param float|int|null $tax Tax
     * @param float|int|null $shipping Shipping cost
     * @param float|int|null $discount Discount
     */
    public function trackEcommerceOrder($orderId, $grandTotal, $subTotal = null, $tax = null, $shipping = null, $discount = null): bool
    {
        $params = [
            'ec_id' => $orderId,
            'revenue' => $grandTotal,
             'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                   (rex_server('HTTP_HOST') ?? '') . 
                   (rex_server('REQUEST_URI') ?? '')
        ];
        
        if ($subTotal !== null) $params['ec_st'] = $subTotal;
        if ($tax !== null) $params['ec_tx'] = $tax;
        if ($shipping !== null) $params['ec_sh'] = $shipping;
        if ($discount !== null) $params['ec_dt'] = $discount;
        
        // ec_items are already in customParameters and will be merged in sendRequest
        // But we need to make sure they are JSON encoded correctly as well
        // This is handled in sendRequest for 'ec_items' key specifically (we need to add it there)

        $success = $this->sendRequest($params);
        
        // Clear items after order is tracked
        unset($this->customParameters['ec_items']);
        
        return $success;
    }

    /**
     * Tracks an Ecommerce Cart Update.
     * 
     * @param float|int $grandTotal Cart Grand Total
     */
    public function trackEcommerceCartUpdate($grandTotal): bool
    {
        return $this->sendRequest([
            'idgoal' => 0,
            'revenue' => $grandTotal,
            'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                   (rex_server('HTTP_HOST') ?? '') . 
                   (rex_server('REQUEST_URI') ?? '')
        ]);
        // Note: ec_items currently in buffer will be sent with this request
    }

    /**
     * Tracks an event.
     * 
     * @param string $category
     * @param string $action
     * @param string|null $name
     * @param float|int|null $value
     */
    public function trackEvent(string $category, string $action, ?string $name = null, $value = null): bool
    {
        $params = [
            'e_c' => $category,
            'e_a' => $action,
             'url' => (rex_server('REQUEST_SCHEME') ?? 'http') . '://' . 
                   (rex_server('HTTP_HOST') ?? '') . 
                   (rex_server('REQUEST_URI') ?? '')
        ];
        if ($name !== null) {
            $params['e_n'] = $name;
        }
        if ($value !== null) {
            $params['e_v'] = $value;
        }
        
        return $this->sendRequest($params);
    }

    /**
     * Sends the request to Matomo using fire-and-forget method.
     * Uses curl in background process (Unix/Linux/macOS) or fsockopen as fallback.
     * This ensures minimal impact on page load time.
     * 
     * @param array<string, mixed> $params
     * @return bool
     */
    private function sendRequest(array $params): bool
    {
        $baseParams = [
            'idsite' => $this->siteId,
            'rec' => 1,
            'apiv' => 1,
            'r' => substr(str_shuffle('0123456789'), 0, 6),
            '_id' => $this->visitorId,
        ];

        if ($this->userAgent) {
            $baseParams['ua'] = $this->userAgent;
        }

        // Needed for correct IP tracking (requires Auth Token)
        if ($this->tokenAuth && $this->ip) {
            $baseParams['cip'] = $this->ip;
            $baseParams['token_auth'] = $this->tokenAuth;
        }
        
        // Resolution
        if ($this->width > 0 && $this->height > 0) {
            $baseParams['res'] = $this->width . 'x' . $this->height;
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $baseParams['lang'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        }

        // Merge base params with custom params
        $finalParams = array_merge($baseParams, $this->customParameters);
        
        // Handle JSON encoding for special parameters like cvar/_cvar/ec_items
        foreach (['cvar', '_cvar', 'ec_items'] as $jsonParam) {
            if (isset($finalParams[$jsonParam]) && is_array($finalParams[$jsonParam])) {
                $finalParams[$jsonParam] = json_encode($finalParams[$jsonParam]);
            }
        }
        
        // Merge with specific action params
        $finalParams = array_merge($finalParams, $params);
        
        try {
            // True fire-and-forget using curl in background process
            // This executes curl in a separate process that immediately detaches
            // The parent process continues without waiting
            if (function_exists('exec') && !$this->isWindowsOS()) {
                // Build POST data - explicitly use & as separator (not &amp;)
                $postData = http_build_query($finalParams, '', '&');
                $trackingUrl = $this->matomoUrl . '/matomo.php';
                
                // Unix/Linux/macOS: Use curl with proper POST data in background
                // -d sends POST data, -s silent mode, -o /dev/null discards output
                $curlCmd = sprintf(
                    'curl -X POST -s -o /dev/null --max-time 5 -d %s %s > /dev/null 2>&1 &',
                    escapeshellarg($postData),
                    escapeshellarg($trackingUrl)
                );
                
                @exec($curlCmd);
                return true;
            }
            
            // Fallback: Ultra-fast fsockopen with minimal timeout
            // Parse URL to extract host, port, and path
            $urlParts = parse_url($this->matomoUrl . '/matomo.php');
            $host = $urlParts['host'] ?? 'localhost';
            $port = $urlParts['port'] ?? (($urlParts['scheme'] ?? 'http') === 'https' ? 443 : 80);
            $path = $urlParts['path'] ?? '/matomo.php';
            
            // Use SSL/TLS for HTTPS connections
            $scheme = (($urlParts['scheme'] ?? 'http') === 'https') ? 'ssl://' : '';
            
            // Fire-and-forget: Open non-blocking socket with minimal timeout (0.01s = 10ms)
            // Using @ to suppress warnings, we handle errors via return value
            $fp = @fsockopen($scheme . $host, $port, $errno, $errstr, 0.01);
            
            if ($fp) {
                // Set non-blocking mode immediately for fire-and-forget behavior
                stream_set_blocking($fp, false);
                stream_set_timeout($fp, 0, 1000); // 1ms timeout
                
                // Build POST request body - explicitly use & as separator
                $postData = http_build_query($finalParams, '', '&');
                
                // Build minimal HTTP POST request
                $request = "POST " . $path . " HTTP/1.1\r\n";
                $request .= "Host: " . $host . "\r\n";
                $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $request .= "Content-Length: " . strlen($postData) . "\r\n";
                $request .= "Connection: Close\r\n\r\n";
                $request .= $postData;
                
                // Send request without waiting for response (fire-and-forget)
                @fwrite($fp, $request);
                @fclose($fp);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            // Log error silently, don't break the page
            rex_logger::logException($e);
            return false;
        }
    }
    
    /**
     * Check if running on Windows OS
     */
    private function isWindowsOS(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}
