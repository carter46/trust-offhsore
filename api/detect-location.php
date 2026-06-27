<?php
/**
 * IP Location Detection API
 * Detects user's location based on IP address
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Get user's IP address
function getUserIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

$userIP = getUserIP();

// Default to United States
$defaultLocation = [
    'country' => 'United States',
    'country_code' => 'US',
    'city' => '',
    'language' => 'English'
];

// Try to detect location using free IP geolocation service
try {
    // Using ip-api.com (free, no API key required)
    $url = "http://ip-api.com/json/{$userIP}?fields=status,message,country,countryCode,city,query";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            $detectedLocation = [
                'country' => $data['country'] ?? 'United States',
                'country_code' => $data['countryCode'] ?? 'US',
                'city' => $data['city'] ?? '',
                'language' => getLanguageForCountry($data['countryCode'] ?? 'US')
            ];
            
            // If detected location is not US, return it; otherwise return default US
            if ($detectedLocation['country_code'] !== 'US') {
                echo json_encode([
                    'success' => true,
                    'detected' => $detectedLocation,
                    'default' => $defaultLocation
                ]);
                exit;
            }
        }
    }
} catch (Exception $e) {
    // Fall through to default
}

// Return default United States
echo json_encode([
    'success' => true,
    'detected' => null,
    'default' => $defaultLocation
]);

/**
 * Get language name for country code
 */
function getLanguageForCountry($countryCode) {
    $languages = [
        'FI' => 'Suomi',
        'ES' => 'Español',
        'FR' => 'Français',
        'DE' => 'Deutsch',
        'IT' => 'Italiano',
        'PT' => 'Português',
        'NL' => 'Nederlands',
        'PL' => 'Polski',
        'RU' => 'Русский',
        'CN' => '中文',
        'JP' => '日本語',
        'KR' => '한국어',
        'AR' => 'العربية',
        'TR' => 'Türkçe',
        'SE' => 'Svenska',
        'NO' => 'Norsk',
        'DK' => 'Dansk',
    ];
    
    return $languages[$countryCode] ?? 'English';
}

