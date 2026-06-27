<?php
/**
 * Helper Functions
 * Courier Tracking Site
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate a unique tracking number
 * Supports: 10, 12, 15, 20, 22, or 24-28 digits (no letters)
 * Default: 12 digits (most common format)
 */
function generateTrackingNumber($length = 12) {
    global $conn;
    
    // Validate length - must be one of supported lengths
    $validLengths = [10, 12, 15, 20, 22, 24, 25, 26, 27, 28];
    if (!in_array($length, $validLengths)) {
        $length = 12; // Default to 12 digits
    }
    
    do {
        $number = '';
        for ($i = 0; $i < $length; $i++) {
            $number .= rand(0, 9);
        }
        
        // Format with spaces for readability (every 4 digits, except last group)
        $formatted = '';
        for ($i = 0; $i < $length; $i += 4) {
            if ($i > 0) {
                $formatted .= ' ';
            }
            $remaining = $length - $i;
            $groupLength = min(4, $remaining);
            $formatted .= substr($number, $i, $groupLength);
        }
        
        // Check if exists (check both formatted and unformatted)
        $stmt = $conn->prepare("SELECT id FROM shipments WHERE tracking_number = ? OR REPLACE(tracking_number, ' ', '') = ?");
        $cleanNumber = str_replace(' ', '', $formatted);
        $stmt->bind_param("ss", $formatted, $cleanNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    
    return $formatted;
}

/**
 * Generate a unique reference number
 * Format: REF-YYYYMMDD-XXXXX (date + 5 random digits)
 */
function generateReferenceNumber() {
    global $conn;
    
    do {
        $datePart = date('Ymd');
        $randomPart = '';
        for ($i = 0; $i < 5; $i++) {
            $randomPart .= rand(0, 9);
        }
        $referenceNumber = 'REF-' . $datePart . '-' . $randomPart;
        
        // Check if exists
        $stmt = $conn->prepare("SELECT id FROM shipments WHERE reference_number = ?");
        $stmt->bind_param("s", $referenceNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    
    return $referenceNumber;
}

/**
 * Check if tracking number exists
 */
function checkTrackingExists($trackingNumber) {
    global $conn;
    
    // Remove spaces for search
    $cleanNumber = str_replace(' ', '', $trackingNumber);
    
    $stmt = $conn->prepare("SELECT id FROM shipments WHERE REPLACE(tracking_number, ' ', '') = ?");
    $stmt->bind_param("s", $cleanNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Get setting value
 */
function getSetting($key, $default = '') {
    global $conn;
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if (!$stmt) {
        error_log('getSetting prepare failed: ' . $conn->error);
        return $default;
    }
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $value = $row['setting_value'];
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }
        return $value;
    }
    
    $stmt->close();
    return $default;
}

/**
 * CSS class for active/inactive nav links (used in header).
 */
function navLinkClass($href, $currentPage, $cleanUri, $cleanScript, $homepageNav = false) {
    $active = false;
    if ($href === '/' && ($currentPage === 'homepage' || $cleanUri === '' || $cleanUri === '/')) {
        $active = true;
    } elseif ($href === '/track' && in_array($currentPage, ['track', 'track-result'], true)) {
        $active = true;
    } elseif ($href !== '/' && strpos($cleanUri, $href) !== false) {
        $active = true;
    }
    $base = 'nav-link text-sm font-bold uppercase tracking-wider transition-colors ';
    if ($homepageNav) {
        return $base . 'text-white hover:text-yellow-200';
    }
    if ($active) {
        return $base . 'text-yellow-500';
    }
    return $base . 'text-gray-700 hover:text-yellow-500';
}

/**
 * Get logo path based on background type
 * @param string $backgroundType 'dark' for dark backgrounds, 'light' for light backgrounds
 * @return string Logo path
 */
function getLogo($backgroundType = 'light') {
    $customLogo = getSetting('company_logo', '');
    if (!empty(trim($customLogo))) {
        return $customLogo;
    }
    // Fallback when no logo uploaded in admin
    return $backgroundType === 'dark' ? '/asset/logo2.png' : '/asset/logo1.png';
}

/**
 * Update setting value
 */
function updateSetting($key, $value) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $value, $value);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get page content by slug
 * @param string $slug Page slug
 * @return array|null Page data with content as array, or null if not found
 */
function getPageContent($slug) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM pages WHERE page_slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $page = $result->fetch_assoc();
    $stmt->close();
    
    if ($page) {
        // Decode JSON content
        $decodedContent = json_decode($page['content'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $page['content'] = $decodedContent;
        } else {
            // If JSON decode fails, return null to indicate error
            return null;
        }
        return $page;
    }
    
    return null;
}

/**
 * Update page content
 * @param string $slug Page slug
 * @param string $title Page title
 * @param array $content Content array (will be encoded as JSON)
 * @return bool Success
 */
function updatePageContent($slug, $title, $content) {
    global $conn;
    
    $jsonContent = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    $stmt = $conn->prepare("INSERT INTO pages (page_slug, page_title, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE page_title = ?, content = ?");
    $stmt->bind_param("sssss", $slug, $title, $jsonContent, $title, $jsonContent);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Replace placeholders in text (e.g., {company}, {date})
 * @param string $text Text with placeholders
 * @return string Text with placeholders replaced
 */
function replacePlaceholders($text) {
    $companyName = getSetting('company_name', 'FedEx');
    $text = str_replace('{company}', $companyName, $text);
    $text = str_replace('{date}', date('m/d/Y'), $text);
    return $text;
}

/**
 * Darken a hex color by a percentage
 * @param string $hexColor Hex color code (e.g., "#4D148C")
 * @param int $percent Percentage to darken (0-100, default 30)
 * @return string Darkened hex color
 */
function darkenColor($hexColor, $percent = 30) {
    // Remove # if present
    $hexColor = ltrim($hexColor, '#');
    
    // Validate hex color
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hexColor)) {
        return $hexColor; // Return original if invalid
    }
    
    // Convert to RGB
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    
    // Darken each component
    $r = max(0, min(255, round($r * (1 - $percent / 100))));
    $g = max(0, min(255, round($g * (1 - $percent / 100))));
    $b = max(0, min(255, round($b * (1 - $percent / 100))));
    
    // Convert back to hex
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

/**
 * Get shipment by tracking number
 */
function getShipmentByTracking($trackingNumber) {
    global $conn;
    
    // #region agent log
    $logFile = __DIR__ . '/../.cursor/debug.log';
    $t0 = microtime(true);
    $logLine = json_encode([
        'sessionId' => 'track-result-504-debug',
        'runId' => 'pre-fix',
        'hypothesisId' => 'C',
        'location' => 'functions.php:getShipmentByTracking:entry',
        'message' => 'Function entry',
        'data' => [
            'trackingNumber' => $trackingNumber,
            'cleanNumber' => str_replace(' ', '', $trackingNumber),
            'cleanLength' => strlen(str_replace(' ', '', $trackingNumber)),
        ],
        'timestamp' => (int) round($t0 * 1000),
    ]) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    // #endregion
    
    // Remove spaces for search
    $cleanNumber = str_replace(' ', '', $trackingNumber);
    
    // #region agent log
    $t1 = microtime(true);
    $logLine = json_encode([
        'sessionId' => 'track-result-504-debug',
        'runId' => 'pre-fix',
        'hypothesisId' => 'C',
        'location' => 'functions.php:getShipmentByTracking:before-query',
        'message' => 'About to execute query',
        'data' => [
            'cleanNumber' => $cleanNumber,
            'prepTimeMs' => (int) round(($t1 - $t0) * 1000),
        ],
        'timestamp' => (int) round($t1 * 1000),
    ]) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    // #endregion
    
    $stmt = $conn->prepare("SELECT * FROM shipments WHERE REPLACE(tracking_number, ' ', '') = ?");
    $stmt->bind_param("s", $cleanNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $shipment = $result->fetch_assoc();
    $stmt->close();
    
    // #region agent log
    $t2 = microtime(true);
    $logLine = json_encode([
        'sessionId' => 'track-result-504-debug',
        'runId' => 'pre-fix',
        'hypothesisId' => 'C',
        'location' => 'functions.php:getShipmentByTracking:after-query',
        'message' => 'Query completed',
        'data' => [
            'found' => $shipment !== null && $shipment !== false,
            'queryTimeMs' => (int) round(($t2 - $t1) * 1000),
            'totalTimeMs' => (int) round(($t2 - $t0) * 1000),
        ],
        'timestamp' => (int) round($t2 * 1000),
    ]) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    // #endregion
    
    return $shipment;
}

/**
 * Get tracking events for a shipment
 */
function getTrackingEvents($shipmentId) {
    global $conn;
    
    // #region agent log
    $logFile = __DIR__ . '/../.cursor/debug.log';
    $t0 = microtime(true);
    $logLine = json_encode([
        'sessionId' => 'track-result-504-debug',
        'runId' => 'pre-fix',
        'hypothesisId' => 'D',
        'location' => 'functions.php:getTrackingEvents:entry',
        'message' => 'Function entry',
        'data' => [
            'shipmentId' => $shipmentId,
        ],
        'timestamp' => (int) round($t0 * 1000),
    ]) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    // #endregion
    
    $stmt = $conn->prepare("SELECT * FROM tracking_events WHERE shipment_id = ? ORDER BY event_date DESC");
    $stmt->bind_param("i", $shipmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    $stmt->close();
    
    // #region agent log
    $t1 = microtime(true);
    $logLine = json_encode([
        'sessionId' => 'track-result-504-debug',
        'runId' => 'pre-fix',
        'hypothesisId' => 'D',
        'location' => 'functions.php:getTrackingEvents:exit',
        'message' => 'Function exit',
        'data' => [
            'eventCount' => count($events),
            'queryTimeMs' => (int) round(($t1 - $t0) * 1000),
        ],
        'timestamp' => (int) round($t1 * 1000),
    ]) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    // #endregion
    
    return $events;
}

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require admin login (redirect if not logged in)
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Format a monetary amount for display (USD).
 */
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return null;
    }
    return number_format((float) $amount, 2);
}

/**
 * Total amount due: shipping cost + clearance cost.
 */
function getShipmentTotalDue($shipment) {
    if (!empty($shipment['total_cost']) && $shipment['total_cost'] !== '') {
        return (float) $shipment['total_cost'];
    }
    $shipping = isset($shipment['base_cost']) && $shipment['base_cost'] !== '' ? (float) $shipment['base_cost'] : 0;
    $clearance = isset($shipment['clearance_cost']) && $shipment['clearance_cost'] !== '' ? (float) $shipment['clearance_cost'] : 0;
    if ($shipping === 0.0 && $clearance === 0.0) {
        return null;
    }
    return $shipping + $clearance;
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    $statusLower = strtolower($status);
    
    if (strpos($statusLower, 'delivered') !== false) {
        return 'bg-green-100 text-green-700';
    } elseif (strpos($statusLower, 'cancel') !== false || strpos($statusLower, 'returned') !== false || strpos($statusLower, 'exception') !== false) {
        return 'bg-red-100 text-red-700';
    } elseif (strpos($statusLower, 'hold') !== false) {
        return 'bg-purple-100 text-purple-700';
    } elseif (strpos($statusLower, 'out for delivery') !== false) {
        return 'bg-orange-100 text-orange-700';
    } elseif (strpos($statusLower, 'transit') !== false) {
        return 'bg-blue-100 text-blue-700';
    } elseif (strpos($statusLower, 'picked') !== false) {
        return 'bg-yellow-100 text-yellow-700';
    } elseif (strpos($statusLower, 'pending') !== false || strpos($statusLower, 'label') !== false) {
        return 'bg-gray-100 text-gray-700';
    } else {
        return 'bg-gray-100 text-gray-700';
    }
}

/**
 * Send email using PHPMailer
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML or plain text)
 * @param bool $isHTML Whether the body is HTML (default: true)
 * @param string|null $fromEmail Override from email (uses config default if null)
 * @param string|null $fromName Override from name (uses config default if null)
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body, $isHTML = true, $fromEmail = null, $fromName = null) {
    try {
        // Get email settings from database or use config defaults
        $smtpHost = getSetting('smtp_host', defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
        $smtpPort = intval(getSetting('smtp_port', defined('SMTP_PORT') ? SMTP_PORT : 587));
        $smtpUsername = getSetting('smtp_username', defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
        $smtpPassword = getSetting('smtp_password', defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
        $smtpEncryption = getSetting('smtp_encryption', defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls');
        $defaultFromEmail = getSetting('smtp_from_email', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@example.com');
        $defaultFromName = getSetting('smtp_from_name', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Shipping Company');
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = !empty($smtpUsername) && !empty($smtpPassword);
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        
        // Set encryption (PHPMailer uses string values: 'ssl', 'tls', or empty string)
        if ($smtpEncryption === 'ssl') {
            $mail->SMTPSecure = 'ssl';
        } elseif ($smtpEncryption === 'none') {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        } else {
            // Default to TLS
            $mail->SMTPSecure = 'tls';
        }
        
        $mail->Port = $smtpPort;
        $mail->CharSet = 'UTF-8';
        
        // Set timeouts to prevent hanging
        $mail->Timeout = 15; // Connection timeout in seconds
        $mail->SMTPKeepAlive = false;
        $mail->SMTPDebug = 0; // Set to 2 for debugging, 0 for production
        $mail->Debugoutput = function($str, $level) {
            // Log debug output to error log instead of displaying
            error_log("PHPMailer Debug: " . $str);
        };
        
        // Recipients
        $mail->setFrom($fromEmail ?: $defaultFromEmail, $fromName ?: $defaultFromName);
        error_log("sendEmail - From: " . ($fromEmail ?: $defaultFromEmail));
        error_log("sendEmail - To: $to");
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        if ($isHTML) {
            $mail->AltBody = strip_tags($body);
        }
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email could not be sent. Error: ' . $e->getMessage()];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Email could not be sent. Error: ' . $e->getMessage()];
    } catch (\Error $e) {
        return ['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()];
    }
}

/**
 * Generate shipment notification email HTML template
 * @param array $shipment Shipment data from database
 * @param array $trackingEvents Array of tracking events
 * @return string HTML email content
 */
function generateShipmentEmailTemplate($shipment, $trackingEvents = []) {
    $companyName = getSetting('company_name', 'Shipping Company');
    $siteUrl = getSetting('site_url', 'https://' . $_SERVER['HTTP_HOST']);
    $supportPhone = getSetting('contact_phone_number', '+1 (800) 555-0199');
    
    // Get logo path and make it absolute URL
    $logoPath = getLogo('light');
    $showTextLogo = getSetting('show_text_logo', '0'); // Default to 0 (hide text logo when image exists)
    $logoUrl = '';
    if (!empty($logoPath)) {
        // If logo path is relative, make it absolute
        if (strpos($logoPath, 'http') !== 0) {
            $logoUrl = rtrim($siteUrl, '/') . '/' . ltrim($logoPath, '/');
        } else {
            $logoUrl = $logoPath;
        }
    }
    
    // Format delivery address
    $deliveryAddress = trim(implode(', ', array_filter([
        $shipment['recipient_address'] ?? '',
        $shipment['recipient_city'] ?? '',
        $shipment['recipient_state'] ?? '',
        $shipment['recipient_zip'] ?? '',
        $shipment['recipient_country'] ?? ''
    ])));
    
    // Format estimated delivery
    $estimatedDelivery = '';
    if (!empty($shipment['estimated_delivery'])) {
        $deliveryDate = new DateTime($shipment['estimated_delivery']);
        $estimatedDelivery = $deliveryDate->format('F j, Y') . ' by 5:00 PM';
    } else {
        $estimatedDelivery = 'To be determined';
    }
    
    // Determine status badge
    $statusLower = strtolower($shipment['status'] ?? 'Pending');
    $statusBadge = 'ON TRACK';
    $statusColor = 'bg-signal-yellow';
    if (strpos($statusLower, 'delivered') !== false) {
        $statusBadge = 'DELIVERED';
        $statusColor = 'bg-green-500';
    } elseif (strpos($statusLower, 'out for delivery') !== false) {
        $statusBadge = 'OUT FOR DELIVERY';
        $statusColor = 'bg-orange-500';
    }
    
    // Build timeline from tracking events using table layout for email compatibility
    $timelineHTML = '';
    if (!empty($trackingEvents)) {
        $eventCount = count($trackingEvents);
        foreach ($trackingEvents as $index => $event) {
            $isLast = ($index === $eventCount - 1);
            $eventDate = new DateTime($event['event_date']);
            $formattedDate = $eventDate->format('M j, g:i A');
            
            $isActive = $isLast && (strpos(strtolower($event['event_type']), 'delivery') !== false || strpos(strtolower($event['event_type']), 'out for delivery') !== false);
            
            // Dot styles
            if ($isActive) {
                $dotBg = '#ffffff';
                $dotBorder = '2px solid #0f49bd';
                $dotContent = '<div style="width: 8px; height: 8px; border-radius: 50%; background-color: #0f49bd; margin: 0 auto;"></div>';
            } else {
                $dotBg = '#0f49bd';
                $dotBorder = 'none';
                $dotContent = '<span style="color: white; font-size: 14px;">✓</span>';
            }
            
            $textColor = $isActive ? '#0f49bd' : '#0d121b';
            $connectorLine = !$isLast ? '<div style="width: 2px; height: 24px; background-color: #e7ebf3; margin: 0 auto;"></div>' : '';
            
            $timelineHTML .= '
            <tr>
                <td style="padding-bottom: ' . (!$isLast ? '24px' : '0') . ';">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                        <tr>
                            <td style="width: 24px; padding-right: 16px; vertical-align: top;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                    <tr>
                                        <td align="center" style="width: 24px; height: 24px; border-radius: 50%; background-color: ' . $dotBg . '; border: ' . $dotBorder . '; text-align: center; vertical-align: middle;">
                                            ' . $dotContent . '
                                        </td>
                                    </tr>
                                    ' . ($connectorLine ? '<tr><td align="center">' . $connectorLine . '</td></tr>' : '') . '
                                </table>
                            </td>
                            <td style="vertical-align: top;">
                                <p style="font-size: 14px; font-weight: bold; color: ' . $textColor . '; margin: 0; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($event['event_type']) . '</p>
                                <p style="font-size: 12px; color: #4c669a; margin: 4px 0 0 0; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($formattedDate) . '</p>
                                ' . (!empty($event['location']) ? '<p style="font-size: 12px; color: #4c669a; margin: 4px 0 0 0; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($event['location']) . '</p>' : '') . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
        }
    } else {
        // Default timeline if no events
        $timelineHTML = '
        <tr>
            <td>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td style="width: 24px; padding-right: 16px; vertical-align: top;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                <tr>
                                    <td align="center" style="width: 24px; height: 24px; border-radius: 50%; background-color: #0f49bd; text-align: center; vertical-align: middle;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background-color: #0f49bd; margin: 0 auto;"></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td style="vertical-align: top;">
                            <p style="font-size: 14px; font-weight: bold; color: #0f49bd; margin: 0; font-family: Arial, Helvetica, sans-serif;">Shipment Created</p>
                            <p style="font-size: 12px; color: #4c669a; margin: 4px 0 0 0; font-family: Arial, Helvetica, sans-serif;">' . date('M j, g:i A') . '</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
    }
    
    // Reference number
    $referenceNumber = $shipment['reference_number'] ?? 'N/A';
    
    // Tracking URL
    $trackingUrl = $siteUrl . '/track-result?id=' . urlencode($shipment['tracking_number']);
    
    // Generate QR code URL using a QR code API service
    $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($trackingUrl);
    
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Shipment Notification - ' . htmlspecialchars($shipment['tracking_number']) . '</title>
        <style type="text/css">
            /* Mobile Responsive Styles */
            @media only screen and (max-width: 600px) {
                .email-container {
                    width: 100% !important;
                    max-width: 100% !important;
                }
                .email-padding {
                    padding: 20px 16px !important;
                }
                .email-padding-small {
                    padding: 0 16px 20px !important;
                }
                .email-headline {
                    font-size: 28px !important;
                    line-height: 1.3 !important;
                    padding: 30px 16px 12px !important;
                }
                .email-header {
                    padding: 20px 16px !important;
                }
                .email-logo {
                    height: 28px !important;
                    max-width: 150px !important;
                }
                .email-company-name {
                    font-size: 18px !important;
                }
                .email-badge-container {
                    display: block !important;
                }
                .email-badge {
                    display: block !important;
                    margin-bottom: 8px !important;
                    width: 100% !important;
                }
                .email-card {
                    padding: 0 16px 20px !important;
                }
                .email-card-inner {
                    padding: 20px !important;
                }
                .email-card-image {
                    height: 150px !important;
                }
                .email-info-row {
                    display: block !important;
                    padding-bottom: 16px !important;
                }
                .email-icon-cell {
                    padding-bottom: 8px !important;
                }
                .email-button {
                    width: 100% !important;
                    display: block !important;
                    padding: 14px 20px !important;
                    font-size: 14px !important;
                    text-align: center !important;
                }
                .email-button-container {
                    text-align: center !important;
                }
                .email-button-container table {
                    width: 100% !important;
                    margin: 0 auto !important;
                }
                .email-button-container td {
                    text-align: center !important;
                }
                .email-qr-code {
                    width: 140px !important;
                    height: 140px !important;
                }
                .email-qr-container {
                    padding: 12px !important;
                }
                .email-timeline {
                    padding-left: 8px !important;
                    padding-bottom: 24px !important;
                }
                .email-footer {
                    padding: 30px 16px !important;
                }
                .email-footer-links {
                    display: block !important;
                    text-align: center !important;
                    padding-bottom: 16px !important;
                }
                .email-footer-link {
                    display: block !important;
                    padding: 4px 0 !important;
                }
                .email-footer-separator {
                    display: none !important;
                }
                .email-progress-title {
                    padding: 0 16px 20px !important;
                    font-size: 16px !important;
                }
            }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f6f6f8; font-family: Arial, Helvetica, sans-serif;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f6f6f8; padding: 20px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="640" class="email-container" style="max-width: 640px; width: 100%; background-color: #ffffff; border-radius: 12px; border: 1px solid #e7ebf3;">
                        <!-- Header -->
                        <tr>
                            <td class="email-header" style="padding: 24px 32px; border-bottom: 1px solid #e7ebf3;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td>
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    ' . (!empty($logoUrl) && $showTextLogo !== '1' ? '
                                                    <!-- Show logo only -->
                                                    <td style="vertical-align: middle;">
                                                        <img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($companyName) . '" class="email-logo" style="height: 32px; width: auto; max-width: 200px; display: block;">
                                                    </td>
                                                    ' : '
                                                    <!-- Show text logo (no logo image or show_text_logo is enabled) -->
                                                    <td style="vertical-align: middle;">
                                                        <h2 class="email-company-name" style="color: #0d121b; font-size: 20px; font-weight: bold; margin: 0; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($companyName) . '</h2>
                                                    </td>
                                                    ') . '
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Headline -->
                        <tr>
                            <td class="email-headline" style="padding: 40px 32px 16px;">
                                <h1 style="color: #0f49bd; font-size: 36px; font-weight: bold; margin: 0 0 16px 0; line-height: 1.2; font-family: Arial, Helvetica, sans-serif;">' . 
                                (strpos(strtolower($shipment['status'] ?? ''), 'delivered') !== false ? 'Your Shipment Has Been Delivered' : 
                                 (strpos(strtolower($shipment['status'] ?? ''), 'out for delivery') !== false ? 'Your Shipment is Out for Delivery' : 
                                  'Your Shipment Update - ' . htmlspecialchars($shipment['status'] ?? 'In Transit'))) . '</h1>
                                <!-- Status Badges -->
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" class="email-badge-container" style="margin: 16px 0;">
                                    <tr>
                                        <td class="email-badge" style="padding-right: 12px; padding-bottom: 8px; display: inline-block;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="background-color: #fbbf24; border-radius: 9999px; border: 1px solid rgba(251, 191, 36, 0.2);">
                                                <tr>
                                                    <td style="padding: 0 16px; height: 32px; vertical-align: middle;">
                                                        <span style="font-size: 14px; margin-right: 4px;">🚚</span>
                                                        <span style="color: #1a1a1a; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">' . htmlspecialchars($statusBadge) . '</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="email-badge" style="padding-bottom: 8px; display: inline-block;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="background-color: rgba(15, 73, 189, 0.1); border-radius: 9999px; border: 1px solid rgba(15, 73, 189, 0.2);">
                                                <tr>
                                                    <td style="padding: 0 16px; height: 32px; vertical-align: middle;">
                                                        <span style="color: #0f49bd; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">' . htmlspecialchars($shipment['service_type'] ?? 'Standard') . '</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Shipment Summary Card -->
                        <tr>
                            <td class="email-card" style="padding: 0 32px 24px;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-radius: 12px; border: 2px solid rgba(15, 73, 189, 0.1); background-color: #f8f9fc;">
                                    <tr>
                                        <td>
                                            <div class="email-card-image" style="width: 100%; height: 192px; background-image: url(\'https://lh3.googleusercontent.com/aida-public/AB6AXuAGyFrFeQZ-WUwfrOeIAeigcym2vsdKmrfKeA1ISdwYpXx3QqleBzklojVlSWH1kWLWsYOat6VOYCi6NJyu3CuZj27umtDctZlu9XgFd8Vvi2nhj0U8wMe-Mfp8pUp33c3qEzD2SVty4xjcfIoCFvK6MOvsHXr5rKgjBdzHUDSIuNCkAUmxsAMKbvZprrvMqP2qga9UkZpIxg4_AVmmmOMp1Kt10pWdvJJOykSij1p5GfnsePj0_6xkmnv_4H2eDb8txLEO95mel4iv\'); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="email-card-inner" style="padding: 24px;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="padding-bottom: 16px;">
                                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                            <tr>
                                                                <td>
                                                                    <p style="color: #0f49bd; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; margin: 0;">Shipment Summary</p>
                                                                </td>
                                                                <td align="right">
                                                                    <span style="color: #4c669a; font-size: 12px; font-weight: 500;">Ref: ' . htmlspecialchars($referenceNumber) . '</span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="email-info-row" style="padding-bottom: 16px;">
                                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                            <tr>
                                                                <td class="email-icon-cell" style="padding-right: 12px; vertical-align: top; padding-top: 2px; width: 32px;">
                                                                    <span style="font-size: 20px; color: #0f49bd;">📦</span>
                                                                </td>
                                                                <td style="vertical-align: top;">
                                                                    <p style="color: #0d121b; font-size: 16px; font-weight: bold; margin: 0;">Tracking: ' . htmlspecialchars($shipment['tracking_number']) . '</p>
                                                                    <p style="color: #4c669a; font-size: 14px; margin: 4px 0 0 0;">Estimated Arrival: ' . htmlspecialchars($estimatedDelivery) . '</p>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="email-info-row" style="padding-bottom: 16px;">
                                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                            <tr>
                                                                <td class="email-icon-cell" style="padding-right: 12px; vertical-align: top; padding-top: 2px; width: 32px;">
                                                                    <span style="font-size: 20px; color: #0f49bd;">📍</span>
                                                                </td>
                                                                <td style="vertical-align: top;">
                                                                    <p style="color: #0d121b; font-size: 16px; font-weight: bold; margin: 0;">Delivery Address</p>
                                                                    <p style="color: #4c669a; font-size: 14px; margin: 4px 0 0 0; line-height: 1.5;">' . nl2br(htmlspecialchars($deliveryAddress)) . '</p>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="email-button-container" style="padding-top: 16px; text-align: center;">
                                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 auto;">
                                                            <tr>
                                                                <td align="center" style="background-color: #0f49bd; border-radius: 8px; text-align: center;">
                                                                    <a href="' . htmlspecialchars($trackingUrl) . '" class="email-button" style="display: inline-block; padding: 12px 24px; color: #ffffff; font-size: 14px; font-weight: bold; text-decoration: none; border-radius: 8px; text-align: center;">Track Package Details</a>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td align="center" style="padding-top: 24px;">
                                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                            <tr>
                                                                <td align="center" style="padding: 16px; background-color: #f8f9fc; border-radius: 12px; border: 1px solid #e7ebf3;">
                                                                    <img src="' . htmlspecialchars($qrCodeUrl) . '" alt="QR Code - Scan to track package" style="width: 160px; height: 160px; display: block; margin: 0 auto;">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td align="center" style="padding-top: 12px;">
                                                                    <span style="font-size: 11px; font-weight: bold; color: #4c669a; text-transform: uppercase; letter-spacing: 1.5px;">Scan for POD</span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Shipment Progress -->
                        <tr>
                            <td class="email-padding-small" style="padding: 0 32px;">
                                <h3 class="email-progress-title" style="color: #0d121b; font-size: 18px; font-weight: bold; padding-bottom: 24px; padding-top: 8px; margin: 0; font-family: Arial, Helvetica, sans-serif;">Shipment Progress</h3>
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-timeline" style="padding-left: 16px; padding-bottom: 32px;">
                                    ' . $timelineHTML . '
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td class="email-footer" style="background-color: #f8f9fc; padding: 40px 32px; border-top: 1px solid #e7ebf3;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td align="center" class="email-footer-links" style="padding-bottom: 24px;">
                                            <a href="' . htmlspecialchars($siteUrl) . '/contact" class="email-footer-link" style="color: #0f49bd; font-size: 14px; font-weight: bold; text-decoration: underline; padding: 0 8px; display: inline-block;">Manage Delivery</a>
                                            <span class="email-footer-separator" style="color: #d1d5db; padding: 0 8px;">|</span>
                                            <a href="' . htmlspecialchars($siteUrl) . '/contact" class="email-footer-link" style="color: #0f49bd; font-size: 14px; font-weight: bold; text-decoration: underline; padding: 0 8px; display: inline-block;">Support Center</a>
                                            <span class="email-footer-separator" style="color: #d1d5db; padding: 0 8px;">|</span>
                                            <a href="#" class="email-footer-link" style="color: #0f49bd; font-size: 14px; font-weight: bold; text-decoration: underline; padding: 0 8px; display: inline-block;">Privacy Policy</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center">
                                            <p style="color: #4c669a; font-size: 12px; margin: 8px 0;">© ' . date('Y') . ' ' . htmlspecialchars($companyName) . ' Enterprise Solutions. All rights reserved.</p>
                                            <p style="color: #4c669a; font-size: 10px; line-height: 1.6; max-width: 448px; margin: 8px auto;">
                                                This is an automated notification. If you have any questions regarding your shipment, please contact our 24/7 priority support line at ' . htmlspecialchars($supportPhone) . '.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Secondary Info -->
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 24px;">
                        <tr>
                            <td align="center">
                                <p style="color: #4c669a; font-size: 12px; margin: 0;">Having trouble viewing this email? <a href="' . htmlspecialchars($trackingUrl) . '" style="text-decoration: underline; color: #0f49bd;">View in Browser</a></p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send shipment notification email to recipient
 * @param int $shipmentId Shipment ID
 * @return array ['success' => bool, 'message' => string]
 */
function sendShipmentNotificationEmail($shipmentId) {
    global $conn;
    
    // Get shipment data
    $stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
    $stmt->bind_param("i", $shipmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Shipment not found'];
    }
    
    $shipment = $result->fetch_assoc();
    $stmt->close();
    
    // Debug: Log email addresses
    error_log("sendShipmentNotificationEmail - Shipment ID: $shipmentId");
    error_log("sendShipmentNotificationEmail - Recipient Email: " . ($shipment['recipient_email'] ?? 'NOT SET'));
    error_log("sendShipmentNotificationEmail - Sender Email: " . ($shipment['sender_email'] ?? 'NOT SET'));
    
    // Check if recipient email exists
    if (empty($shipment['recipient_email'])) {
        error_log("sendShipmentNotificationEmail - ERROR: Recipient email is empty for shipment ID $shipmentId");
        return ['success' => false, 'message' => 'Recipient email not provided'];
    }
    
    // Get tracking events
    $eventsStmt = $conn->prepare("SELECT * FROM tracking_events WHERE shipment_id = ? ORDER BY event_date ASC");
    $eventsStmt->bind_param("i", $shipmentId);
    $eventsStmt->execute();
    $eventsResult = $eventsStmt->get_result();
    
    $trackingEvents = [];
    while ($row = $eventsResult->fetch_assoc()) {
        $trackingEvents[] = $row;
    }
    $eventsStmt->close();
    
    // Generate email content
    $emailBody = generateShipmentEmailTemplate($shipment, $trackingEvents);
    
    // Determine subject based on status
    $statusLower = strtolower($shipment['status']);
    $companyName = getSetting('company_name', 'Shipping Company');
    
    if (strpos($statusLower, 'delivered') !== false) {
        $subject = 'Your Shipment Has Been Delivered - ' . htmlspecialchars($shipment['tracking_number']);
    } elseif (strpos($statusLower, 'out for delivery') !== false) {
        $subject = 'Your Shipment is Out for Delivery - ' . htmlspecialchars($shipment['tracking_number']);
    } else {
        $subject = 'Shipment Update - ' . htmlspecialchars($shipment['tracking_number']) . ' - ' . htmlspecialchars($shipment['status']);
    }
    
    // Send email to recipient
    $recipientEmail = $shipment['recipient_email'];
    error_log("sendShipmentNotificationEmail - Sending email to recipient: $recipientEmail");
    
    $emailResult = sendEmail(
        $recipientEmail,
        $subject,
        $emailBody,
        true
    );
    
    error_log("sendShipmentNotificationEmail - Email result: " . ($emailResult['success'] ? 'SUCCESS' : 'FAILED - ' . $emailResult['message']));
    
    return $emailResult;
}

