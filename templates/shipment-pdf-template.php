<?php
/**
 * Shipment PDF Template
 * HTML template for PDF generation based on provided design
 */

if (!isset($shipment) || !$shipment) {
    die('Shipment data is required');
}

// Only include if not already included (prevent double inclusion errors)
if (!defined('DB_CONFIG_LOADED')) {
    require_once __DIR__ . '/../config.php';
    define('DB_CONFIG_LOADED', true);
}
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$companyName = getSetting('company_name', 'FedEx');
$companyTagline = getSetting('company_tagline', 'Global Shipping Solutions');
$companyLogo = getLogo('light');
$generatedDate = date('M d, Y');
$generatedTime = date('H:i A');

// Format dates
$estimatedDelivery = $shipment['estimated_delivery'] ? date('F d, Y', strtotime($shipment['estimated_delivery'])) : 'TBD';
$createdDate = $shipment['created_at'] ? date('F d, Y', strtotime($shipment['created_at'])) : $generatedDate;

// Format currency
$shippingCost = !empty($shipment['base_cost']) ? number_format((float) $shipment['base_cost'], 2) : '0.00';
$clearanceCost = !empty($shipment['clearance_cost']) ? number_format((float) $shipment['clearance_cost'], 2) : '0.00';
$totalDue = getShipmentTotalDue($shipment);
$totalCost = $totalDue !== null ? number_format($totalDue, 2) : '0.00';
$shipmentWorth = $shipment['shipment_worth'] ? number_format($shipment['shipment_worth'], 2) : '0.00';

// Get pickup and dropoff locations
$pickupLocation = $shipment['pickup_location'] ?: ($shipment['sender_city'] ? $shipment['sender_city'] . ', ' . $shipment['sender_state'] : 'Origin');
$dropoffLocation = $shipment['dropoff_location'] ?: ($shipment['recipient_city'] ? $shipment['recipient_city'] . ', ' . $shipment['recipient_state'] : 'Destination');

// Format weight
$weight = $shipment['weight'] ? $shipment['weight'] . ' lbs (' . number_format($shipment['weight'] * 0.453592, 1) . ' kg)' : 'N/A';

// Get status badge class
$statusClass = 'bg-green-100 text-green-700';
if (stripos($shipment['status'], 'delivered') !== false) {
    $statusClass = 'bg-green-100 text-green-700';
} elseif (stripos($shipment['status'], 'transit') !== false || stripos($shipment['status'], 'delivery') !== false) {
    $statusClass = 'bg-blue-100 text-blue-700';
} elseif (stripos($shipment['status'], 'hold') !== false || stripos($shipment['status'], 'cancelled') !== false) {
    $statusClass = 'bg-red-100 text-red-700';
} elseif (stripos($shipment['status'], 'pending') !== false || stripos($shipment['status'], 'pickup') !== false) {
    $statusClass = 'bg-yellow-100 text-yellow-700';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Shipment Tracking Details - <?php echo htmlspecialchars($shipment['tracking_number']); ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f5f8;
            color: #140d1c;
        }
        .container {
            max-width: 1024px;
            margin: 0 auto;
            padding: 20px;
        }
        .pdf-container {
            background: white;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            aspect-ratio: 210/297;
            display: flex;
            flex-direction: column;
        }
        .pdf-header {
            padding: 32px 40px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: #7f0df2;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        .company-info h3 {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
        }
        .company-info p {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .header-right h2 {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            text-align: right;
        }
        .header-right p {
            font-size: 14px;
            color: #64748b;
            text-align: right;
        }
        .pdf-content {
            padding: 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .overview-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        .overview-left {
            display: flex;
            gap: 32px;
        }
        .overview-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .overview-label {
            font-size: 14px;
            color: #64748b;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: bold;
            width: fit-content;
        }
        .overview-value {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }
        .barcode-section {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .barcode-label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .barcode {
            font-family: 'Libre Barcode 39', cursive;
            font-size: 48px;
            line-height: 1;
            color: #1e293b;
            user-select: none;
        }
        .barcode-text {
            font-size: 12px;
            font-family: monospace;
            letter-spacing: 0.1em;
            color: #64748b;
            margin-top: 4px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        .info-section h4 {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .info-section .company-name {
            font-size: 14px;
            font-weight: 500;
            color: #7f0df2;
            margin-bottom: 8px;
        }
        .info-section .address {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
            margin-top: 8px;
        }
        .contact-info {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 14px;
            color: #475569;
        }
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .contact-icon {
            font-size: 16px;
            color: #94a3b8;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .details-table thead {
            background: #f8fafc;
        }
        .details-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        .details-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #1e293b;
            font-weight: 600;
        }
        .details-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
        }
        .location-row {
            display: flex;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .location-item {
            padding: 16px;
            width: 50%;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-right: 1px solid #e2e8f0;
        }
        .location-item:last-child {
            border-right: none;
        }
        .location-icon {
            color: #7f0df2;
            margin-top: 2px;
        }
        .location-label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .location-name {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }
        .location-date {
            font-size: 12px;
            color: #64748b;
        }
        .map-placeholder {
            width: 100%;
            height: 128px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 16px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .map-label {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
            padding: 8px 16px;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: bold;
            color: #475569;
        }
        .pdf-footer {
            background: #1e293b;
            color: white;
            padding: 40px;
            margin-top: auto;
        }
        .footer-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
        }
        .footer-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .footer-label {
            font-size: 14px;
            color: #94a3b8;
        }
        .footer-value {
            font-size: 14px;
            color: #e2e8f0;
            font-family: monospace;
        }
        .footer-total {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            padding-left: 32px;
            border-left: 1px solid #334155;
        }
        .footer-total-label {
            font-size: 14px;
            color: #94a3b8;
        }
        .footer-total-amount {
            font-size: 32px;
            font-weight: bold;
            color: #7f0df2;
        }
        @media print {
            body { background: white; }
            .container { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="pdf-container">
            <div class="pdf-header">
                <div class="logo-section">
                    <div class="logo-icon">
                        <span class="material-symbols-outlined" style="font-size: 24px;">package_2</span>
                    </div>
                    <div class="company-info">
                        <h3><?php echo htmlspecialchars($companyName); ?></h3>
                        <p><?php echo htmlspecialchars($companyTagline); ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <h2>SHIPMENT TRACKING DETAILS</h2>
                    <p>ID: #<?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
                </div>
            </div>
            
            <div class="pdf-content">
                <section>
                    <h4 class="section-title">Shipment Overview</h4>
                    <div class="overview-section">
                        <div class="overview-left">
                            <div class="overview-item">
                                <span class="overview-label">Current Status</span>
                                <div class="status-badge <?php echo $statusClass; ?>">
                                    <span class="material-symbols-outlined" style="font-size: 14px;">local_shipping</span>
                                    <span><?php echo htmlspecialchars($shipment['status']); ?></span>
                                </div>
                            </div>
                            <div class="overview-item">
                                <span class="overview-label">Estimated Delivery</span>
                                <span class="overview-value"><?php echo htmlspecialchars($estimatedDelivery); ?></span>
                            </div>
                            <div class="overview-item">
                                <span class="overview-label">Reference No.</span>
                                <span class="overview-value"><?php echo htmlspecialchars($shipment['reference_number'] ?: 'N/A'); ?></span>
                            </div>
                        </div>
                        <div class="barcode-section">
                            <span class="barcode-label">Scan to Track</span>
                            <div class="barcode">*<?php echo htmlspecialchars($shipment['tracking_number']); ?>*</div>
                            <div class="barcode-text"><?php echo htmlspecialchars($shipment['tracking_number']); ?></div>
                        </div>
                    </div>
                </section>
                
                <section class="info-grid">
                    <div class="info-section">
                        <h4 class="section-title">Sender Information</h4>
                        <div>
                            <h4><?php echo htmlspecialchars($shipment['sender_name']); ?></h4>
                            <p class="address">
                                <?php echo htmlspecialchars($shipment['sender_address']); ?><br/>
                                <?php if ($shipment['sender_city']): ?>
                                <?php echo htmlspecialchars($shipment['sender_city'] . ', ' . $shipment['sender_state'] . ' ' . $shipment['sender_zip']); ?><br/>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($shipment['sender_country']); ?>
                            </p>
                            <?php if ($shipment['sender_email'] || $shipment['sender_phone']): ?>
                            <div class="contact-info">
                                <?php if ($shipment['sender_phone']): ?>
                                <div class="contact-item">
                                    <span class="material-symbols-outlined contact-icon">call</span>
                                    <span><?php echo htmlspecialchars($shipment['sender_phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($shipment['sender_email']): ?>
                                <div class="contact-item">
                                    <span class="material-symbols-outlined contact-icon">mail</span>
                                    <span><?php echo htmlspecialchars($shipment['sender_email']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-section">
                        <h4 class="section-title">Receiver Information</h4>
                        <div>
                            <h4><?php echo htmlspecialchars($shipment['recipient_name']); ?></h4>
                            <p class="address">
                                <?php echo htmlspecialchars($shipment['recipient_address']); ?><br/>
                                <?php if ($shipment['recipient_city']): ?>
                                <?php echo htmlspecialchars($shipment['recipient_city'] . ', ' . $shipment['recipient_state'] . ' ' . $shipment['recipient_zip']); ?><br/>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($shipment['recipient_country']); ?>
                            </p>
                            <?php if ($shipment['recipient_email'] || $shipment['recipient_phone']): ?>
                            <div class="contact-info">
                                <?php if ($shipment['recipient_phone']): ?>
                                <div class="contact-item">
                                    <span class="material-symbols-outlined contact-icon">call</span>
                                    <span><?php echo htmlspecialchars($shipment['recipient_phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($shipment['recipient_email']): ?>
                                <div class="contact-item">
                                    <span class="material-symbols-outlined contact-icon">mail</span>
                                    <span><?php echo htmlspecialchars($shipment['recipient_email']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                
                <section>
                    <h4 class="section-title">Tracking & Destination Details</h4>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Service Type</th>
                                <th>Weight</th>
                                <th>Dimensions</th>
                                <th>Handling</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($shipment['service_type']); ?></td>
                                <td><?php echo htmlspecialchars($weight); ?></td>
                                <td><?php echo htmlspecialchars($shipment['dimensions'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($shipment['dimensions'] ? 'Standard' : 'N/A'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="location-row">
                        <div class="location-item">
                            <span class="material-symbols-outlined location-icon">trip_origin</span>
                            <div>
                                <span class="location-label">Origin</span>
                                <div class="location-name"><?php echo htmlspecialchars($pickupLocation); ?></div>
                                <div class="location-date"><?php echo htmlspecialchars($createdDate); ?> - <?php echo htmlspecialchars($generatedTime); ?></div>
                            </div>
                        </div>
                        <div class="location-item">
                            <span class="material-symbols-outlined location-icon">location_on</span>
                            <div>
                                <span class="location-label">Destination</span>
                                <div class="location-name"><?php echo htmlspecialchars($dropoffLocation); ?></div>
                                <div class="location-date">Est. <?php echo htmlspecialchars($estimatedDelivery); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="map-placeholder">
                        <div class="map-label">
                            <span class="material-symbols-outlined" style="font-size: 16px; color: #7f0df2;">route</span>
                            <span>Route Map Visualization</span>
                        </div>
                    </div>
                </section>
            </div>
            
            <div class="pdf-footer">
                <h4 class="footer-title">Financial Summary</h4>
                <div class="footer-content">
                    <div class="footer-item">
                        <span class="footer-label">Shipment Worth</span>
                        <span class="footer-value">$<?php echo htmlspecialchars($shipmentWorth); ?></span>
                    </div>
                    <div class="footer-item">
                        <span class="footer-label">Shipping Cost</span>
                        <span class="footer-value">$<?php echo htmlspecialchars($shippingCost); ?></span>
                    </div>
                    <div class="footer-item">
                        <span class="footer-label">Clearance Cost</span>
                        <span class="footer-value">$<?php echo htmlspecialchars($clearanceCost); ?></span>
                    </div>
                    <div class="footer-item">
                        <span class="footer-label">Due Date</span>
                        <span class="footer-value"><?php echo htmlspecialchars($estimatedDelivery); ?></span>
                    </div>
                    <div class="footer-total">
                        <span class="footer-total-label">Total Due</span>
                        <span class="footer-total-amount">$<?php echo htmlspecialchars($totalCost); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

