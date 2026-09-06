<?php
/**
 * Shared professional shipping receipt (admin + public tracking).
 * Expects $shipment. Optional: $events, $autoprint.
 */

if (!isset($shipment) || !$shipment) {
    die('Shipment data is required');
}

if (!defined('DB_CONFIG_LOADED') && file_exists(__DIR__ . '/../config.php')) {
    // config may already be loaded; ignore if constant unused
}
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/../includes/functions.php';
}

if (!isset($events) || !is_array($events)) {
    $events = !empty($shipment['id']) ? getTrackingEvents((int) $shipment['id']) : [];
}

$publicEvents = array_values(array_filter($events, static function ($ev) {
    return ($ev['event_type'] ?? '') !== 'Admin Note';
}));

$companyName = getSetting('company_name', 'Shipping Company');
$companyTagline = getSetting('company_tagline', 'Global Logistics Solutions');
$primaryColor = getSetting('primary_color', '#152E56');
$secondaryColor = getSetting('secondary_color', '#F9BA34');
$logoSrc = getPrintableLogoSrc('light');
$supportPhone = getSetting('support_phone', getSetting('company_phone', ''));
$supportEmail = getSetting('support_email', getSetting('company_email', ''));

$generatedDate = date('M j, Y');
$generatedTime = date('g:i A');
$shipmentCreated = getShipmentCreatedAt($shipment);
$createdDisplay = $shipmentCreated ? date('M j, Y g:i A', strtotime($shipmentCreated)) : $generatedDate;
$estimatedDelivery = !empty($shipment['estimated_delivery'])
    ? date('M j, Y g:i A', strtotime($shipment['estimated_delivery']))
    : 'TBD';

$shippingCost = !empty($shipment['base_cost']) ? number_format((float) $shipment['base_cost'], 2) : null;
$clearanceCost = !empty($shipment['clearance_cost']) ? number_format((float) $shipment['clearance_cost'], 2) : null;
$totalDue = getShipmentTotalDue($shipment);
$totalCost = $totalDue !== null ? number_format($totalDue, 2) : null;
$shipmentWorth = !empty($shipment['shipment_worth']) ? number_format((float) $shipment['shipment_worth'], 2) : null;

$pickupLocation = $shipment['pickup_location']
    ?: trim(($shipment['sender_city'] ?? '') . (!empty($shipment['sender_state']) ? ', ' . $shipment['sender_state'] : ''))
    ?: 'Origin';
$dropoffLocation = $shipment['dropoff_location']
    ?: trim(($shipment['recipient_city'] ?? '') . (!empty($shipment['recipient_state']) ? ', ' . $shipment['recipient_state'] : ''))
    ?: 'Destination';

$weight = !empty($shipment['weight'])
    ? number_format((float) $shipment['weight'], 2) . ' lbs (' . number_format((float) $shipment['weight'] * 0.453592, 1) . ' kg)'
    : 'N/A';

$trackingClean = preg_replace('/\s+/', '', (string) $shipment['tracking_number']);
$autoprint = !empty($autoprint) || (isset($_GET['autoprint']) && $_GET['autoprint'] == '1');

$siteUrl = rtrim((string) getSetting('site_url', ''), '/');
if ($siteUrl === '') {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $siteUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$trackingUrl = $siteUrl . trackingResultUrl($shipment['tracking_number']);
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($trackingUrl);

$statusLower = strtolower((string) ($shipment['status'] ?? ''));
$statusTone = '#166534';
$statusBg = '#dcfce7';
if (strpos($statusLower, 'cancel') !== false || strpos($statusLower, 'exception') !== false || strpos($statusLower, 'returned') !== false) {
    $statusTone = '#991b1b';
    $statusBg = '#fee2e2';
} elseif (strpos($statusLower, 'hold') !== false) {
    $statusTone = '#854d0e';
    $statusBg = '#fef9c3';
} elseif (strpos($statusLower, 'transit') !== false || strpos($statusLower, 'out for delivery') !== false) {
    $statusTone = '#1e40af';
    $statusBg = '#dbeafe';
} elseif (strpos($statusLower, 'pending') !== false || strpos($statusLower, 'label') !== false) {
    $statusTone = '#374151';
    $statusBg = '#f3f4f6';
}

$itemImageSrc = '';
if (!empty($shipment['item_image'])) {
    $imgPath = '/' . ltrim(str_replace('\\', '/', (string) $shipment['item_image']), '/');
    $localImg = dirname(__DIR__) . $imgPath;
    if (is_file($localImg) && is_readable($localImg)) {
        $mime = function_exists('mime_content_type') ? (@mime_content_type($localImg) ?: 'image/jpeg') : 'image/jpeg';
        $bytes = @file_get_contents($localImg);
        if ($bytes !== false) {
            $itemImageSrc = 'data:' . $mime . ';base64,' . base64_encode($bytes);
        }
    }
}

if (!function_exists('receipt_h')) {
    function receipt_h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Shipping Receipt — <?php echo receipt_h($shipment['tracking_number']); ?></title>
    <style>
        :root {
            --brand: <?php echo receipt_h($primaryColor); ?>;
            --accent: <?php echo receipt_h($secondaryColor); ?>;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --paper: #ffffff;
            --wash: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background: #e8eef5;
            line-height: 1.45;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.92);
            color: #fff;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 0;
            border-radius: 6px;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
            background: #fff;
        }
        .toolbar .primary {
            background: var(--accent);
            color: #111;
        }
        .sheet-wrap {
            padding: 24px 16px 48px;
        }
        .sheet {
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
            background: var(--paper);
            border: 1px solid #dbe3ee;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .brand-bar {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            padding: 28px 32px;
            border-bottom: 4px solid var(--brand);
            background: linear-gradient(180deg, #fff 0%, var(--wash) 100%);
        }
        .brand-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            flex: 1;
        }
        .brand-logo {
            max-height: 64px;
            max-width: 160px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .brand-text {
            min-width: 0;
        }
        .brand-text h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -0.02em;
            color: var(--brand);
            word-break: break-word;
        }
        .brand-text p {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .doc-meta {
            text-align: right;
            flex-shrink: 0;
        }
        .doc-meta .label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .doc-meta .title {
            margin-top: 4px;
            font-size: 20px;
            font-weight: 800;
            color: var(--ink);
        }
        .doc-meta .sub {
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted);
        }
        .body {
            padding: 28px 32px 8px;
        }
        .hero {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }
        .panel {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--wash);
            padding: 16px 18px;
        }
        .panel h2 {
            margin: 0 0 10px;
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .tracking-number {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: var(--brand);
            word-break: break-word;
        }
        .status-pill {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: <?php echo receipt_h($statusTone); ?>;
            background: <?php echo receipt_h($statusBg); ?>;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .kv {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 6px 10px;
            font-size: 13px;
        }
        .kv .k { color: var(--muted); font-weight: 600; }
        .kv .v { font-weight: 700; color: var(--ink); word-break: break-word; }
        .qr-block {
            text-align: center;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
        }
        .qr-block img {
            width: 140px;
            height: 140px;
            max-width: 100%;
            display: block;
            margin: 0 auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .qr-caption {
            margin-top: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .qr-tracking {
            margin-top: 4px;
            font-size: 11px;
            font-family: ui-monospace, monospace;
            letter-spacing: 0.08em;
            color: var(--ink);
            word-break: break-all;
        }
        .section {
            margin: 0 0 22px;
        }
        .section-title {
            margin: 0 0 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--brand);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand);
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .card {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px;
            min-height: 100%;
        }
        .card h3 {
            margin: 0 0 8px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .card .name {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .card .addr, .card .contact {
            font-size: 13px;
            color: #334155;
        }
        .card .contact { margin-top: 10px; }
        .card .contact div { margin-top: 2px; }
        table.details {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table.details th,
        table.details td {
            border: 1px solid var(--line);
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        table.details th {
            width: 28%;
            background: var(--wash);
            color: var(--muted);
            font-weight: 700;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.details td { font-weight: 600; }
        .route {
            display: grid;
            grid-template-columns: 1fr 40px 1fr;
            gap: 8px;
            align-items: stretch;
            margin-top: 12px;
        }
        .route-box {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px;
            background: #fff;
        }
        .route-box .eyebrow {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .route-box .place {
            margin-top: 4px;
            font-size: 14px;
            font-weight: 800;
        }
        .route-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--brand);
            font-weight: 800;
        }
        .item-photo {
            margin-top: 12px;
            max-width: 220px;
            max-height: 160px;
            border: 1px solid var(--line);
            border-radius: 8px;
            object-fit: cover;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .costs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        .cost-box {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px;
            background: var(--wash);
        }
        .cost-box.total {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .cost-box .lbl {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            opacity: 0.8;
        }
        .cost-box .amt {
            margin-top: 4px;
            font-size: 18px;
            font-weight: 800;
        }
        .timeline {
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }
        .timeline table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .timeline th {
            background: var(--brand);
            color: #fff;
            text-align: left;
            padding: 10px 12px;
            font-weight: 700;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .timeline td {
            padding: 10px 12px;
            border-top: 1px solid var(--line);
            vertical-align: top;
        }
        .timeline tr:nth-child(even) td {
            background: #fafbfc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .footer {
            margin-top: 8px;
            padding: 20px 32px 28px;
            border-top: 1px solid var(--line);
            background: var(--wash);
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--muted);
        }
        .footer strong { color: var(--ink); }
        .muted { color: var(--muted); }
        @media (max-width: 720px) {
            .sheet-wrap { padding: 12px 8px 32px; }
            .sheet { border-radius: 0; box-shadow: none; }
            .brand-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
                padding: 18px 16px;
            }
            .brand-left {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
                gap: 10px;
            }
            .brand-logo {
                max-height: 52px;
                max-width: min(140px, 70vw);
            }
            .brand-text { width: 100%; }
            .brand-text h1 { font-size: 18px; }
            .brand-text p { white-space: normal; }
            .doc-meta {
                text-align: left;
                width: 100%;
                padding-top: 8px;
                border-top: 1px solid var(--line);
            }
            .doc-meta .title { font-size: 16px; }
            .body, .footer { padding-left: 16px; padding-right: 16px; }
            .body { padding-top: 18px; }
            .hero, .two-col, .route, .costs { display: grid; grid-template-columns: 1fr; gap: 12px; }
            .panel { padding: 14px; }
            .route-arrow { transform: rotate(90deg); padding: 4px 0; }
            .tracking-number { font-size: 18px; }
            .kv { grid-template-columns: 88px 1fr; font-size: 12px; gap: 4px 8px; }
            .party .name { word-break: break-word; }
            table.details, .timeline table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table.details th { width: auto; }
            .timeline { overflow-x: auto; }
            .timeline th, .timeline td { white-space: nowrap; }
            .timeline td:nth-child(3) { white-space: normal; min-width: 140px; }
            .cost-box .amt { font-size: 16px; }
            .footer { flex-direction: column; text-align: left; }
            .footer > div[style*="text-align:right"] { text-align: left !important; }
            .qr-block { margin-top: 12px; padding-top: 12px; }
            .qr-block img { width: 120px; height: 120px; }
            .item-photo { max-width: 100%; height: auto; }
        }
        @media print {
            @page { margin: 10mm; }
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet {
                max-width: none;
                border: 0;
                box-shadow: none;
            }
            a { color: inherit; text-decoration: none; }
            .brand-logo, .item-photo, .status-pill, .cost-box.total, .timeline th, .qr-block img {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
        <a href="<?php echo receipt_h(trackingResultUrl($shipment['tracking_number'])); ?>">Open tracking</a>
    </div>

    <div class="sheet-wrap">
        <article class="sheet">
            <header class="brand-bar">
                <div class="brand-left">
                    <img class="brand-logo" src="<?php echo receipt_h($logoSrc); ?>" alt="<?php echo receipt_h($companyName); ?> logo"/>
                    <div class="brand-text">
                        <h1><?php echo receipt_h($companyName); ?></h1>
                        <p><?php echo receipt_h($companyTagline); ?></p>
                    </div>
                </div>
                <div class="doc-meta">
                    <div class="label">Official document</div>
                    <div class="title">Shipping Receipt</div>
                    <div class="sub">Issued <?php echo receipt_h($generatedDate); ?> · <?php echo receipt_h($generatedTime); ?></div>
                </div>
            </header>

            <div class="body">
                <section class="hero">
                    <div class="panel">
                        <h2>Tracking number</h2>
                        <div class="tracking-number"><?php echo receipt_h($shipment['tracking_number']); ?></div>
                        <span class="status-pill"><?php echo receipt_h($shipment['status']); ?></span>
                        <div class="qr-block">
                            <img src="<?php echo receipt_h($qrCodeUrl); ?>" width="140" height="140" alt="QR code to track shipment"/>
                            <div class="qr-caption">Scan to track</div>
                            <div class="qr-tracking"><?php echo receipt_h($shipment['tracking_number']); ?></div>
                        </div>
                    </div>
                    <div class="panel">
                        <h2>Shipment summary</h2>
                        <div class="kv">
                            <div class="k">Service</div>
                            <div class="v"><?php echo receipt_h($shipment['service_type'] ?: 'N/A'); ?></div>
                            <div class="k">Created</div>
                            <div class="v"><?php echo receipt_h($createdDisplay); ?></div>
                            <div class="k">Est. delivery</div>
                            <div class="v"><?php echo receipt_h($estimatedDelivery); ?></div>
                            <div class="k">Reference</div>
                            <div class="v"><?php echo receipt_h($shipment['reference_number'] ?: '—'); ?></div>
                        </div>
                    </div>
                </section>

                <section class="section">
                    <h2 class="section-title">Parties</h2>
                    <div class="two-col">
                        <div class="card">
                            <h3>Shipper / Sender</h3>
                            <div class="name"><?php echo receipt_h($shipment['sender_name']); ?></div>
                            <div class="addr">
                                <?php echo receipt_h($shipment['sender_address']); ?><br/>
                                <?php
                                $senderLine = trim(($shipment['sender_city'] ?? '') . (!empty($shipment['sender_state']) ? ', ' . $shipment['sender_state'] : '') . (!empty($shipment['sender_zip']) ? ' ' . $shipment['sender_zip'] : ''));
                                if ($senderLine !== '') {
                                    echo receipt_h($senderLine) . '<br/>';
                                }
                                echo receipt_h($shipment['sender_country'] ?? '');
                                ?>
                            </div>
                            <div class="contact">
                                <?php if (!empty($shipment['sender_phone'])): ?>
                                    <div>Phone: <?php echo receipt_h($shipment['sender_phone']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($shipment['sender_email'])): ?>
                                    <div>Email: <?php echo receipt_h($shipment['sender_email']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card">
                            <h3>Consignee / Recipient</h3>
                            <div class="name"><?php echo receipt_h($shipment['recipient_name']); ?></div>
                            <div class="addr">
                                <?php echo receipt_h($shipment['recipient_address']); ?><br/>
                                <?php
                                $recipientLine = trim(($shipment['recipient_city'] ?? '') . (!empty($shipment['recipient_state']) ? ', ' . $shipment['recipient_state'] : '') . (!empty($shipment['recipient_zip']) ? ' ' . $shipment['recipient_zip'] : ''));
                                if ($recipientLine !== '') {
                                    echo receipt_h($recipientLine) . '<br/>';
                                }
                                echo receipt_h($shipment['recipient_country'] ?? '');
                                ?>
                            </div>
                            <div class="contact">
                                <?php if (!empty($shipment['recipient_phone'])): ?>
                                    <div>Phone: <?php echo receipt_h($shipment['recipient_phone']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($shipment['recipient_email'])): ?>
                                    <div>Email: <?php echo receipt_h($shipment['recipient_email']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section">
                    <h2 class="section-title">Package &amp; route</h2>
                    <table class="details">
                        <tr>
                            <th>Weight</th>
                            <td><?php echo receipt_h($weight); ?></td>
                            <th>Dimensions</th>
                            <td><?php echo receipt_h($shipment['dimensions'] ?: 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Service type</th>
                            <td><?php echo receipt_h($shipment['service_type'] ?: 'N/A'); ?></td>
                            <th>Current status</th>
                            <td><?php echo receipt_h($shipment['status']); ?></td>
                        </tr>
                    </table>
                    <div class="route">
                        <div class="route-box">
                            <div class="eyebrow">Origin / Pickup</div>
                            <div class="place"><?php echo receipt_h($pickupLocation); ?></div>
                        </div>
                        <div class="route-arrow">→</div>
                        <div class="route-box">
                            <div class="eyebrow">Destination / Drop-off</div>
                            <div class="place"><?php echo receipt_h($dropoffLocation); ?></div>
                        </div>
                    </div>
                    <?php if ($itemImageSrc): ?>
                        <div style="margin-top:14px;">
                            <div class="eyebrow muted" style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Shipment photo</div>
                            <img class="item-photo" src="<?php echo receipt_h($itemImageSrc); ?>" alt="Shipment item"/>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($shippingCost !== null || $clearanceCost !== null || $shipmentWorth !== null || $totalCost !== null): ?>
                <section class="section">
                    <h2 class="section-title">Charges</h2>
                    <div class="costs">
                        <div class="cost-box">
                            <div class="lbl">Shipment worth</div>
                            <div class="amt"><?php echo $shipmentWorth !== null ? '$' . receipt_h($shipmentWorth) : '—'; ?></div>
                        </div>
                        <div class="cost-box">
                            <div class="lbl">Shipping</div>
                            <div class="amt"><?php echo $shippingCost !== null ? '$' . receipt_h($shippingCost) : '—'; ?></div>
                        </div>
                        <div class="cost-box">
                            <div class="lbl">Clearance</div>
                            <div class="amt"><?php echo $clearanceCost !== null ? '$' . receipt_h($clearanceCost) : '—'; ?></div>
                        </div>
                        <div class="cost-box total">
                            <div class="lbl">Total due</div>
                            <div class="amt"><?php echo $totalCost !== null ? '$' . receipt_h($totalCost) : '—'; ?></div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <section class="section">
                    <h2 class="section-title">Travel history</h2>
                    <?php if (empty($publicEvents)): ?>
                        <p class="muted" style="font-size:13px;">No public tracking events recorded yet.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:22%;">Date &amp; time</th>
                                        <th style="width:22%;">Event</th>
                                        <th>Description</th>
                                        <th style="width:22%;">Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($publicEvents as $event): ?>
                                    <tr>
                                        <td><?php echo receipt_h(formatDateTime($event['event_date'] ?? '')); ?></td>
                                        <td><?php echo receipt_h($event['event_type'] ?? ''); ?></td>
                                        <td><?php echo receipt_h($event['description'] ?? ''); ?></td>
                                        <td><?php echo receipt_h($event['location'] ?? '—'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <footer class="footer">
                <div>
                    <strong><?php echo receipt_h($companyName); ?></strong><br/>
                    Official shipping receipt · Keep for your records<br/>
                    Track anytime with the tracking number above.
                </div>
                <div style="text-align:right;">
                    <?php if ($supportPhone): ?>Support: <?php echo receipt_h($supportPhone); ?><br/><?php endif; ?>
                    <?php if ($supportEmail): ?>Email: <?php echo receipt_h($supportEmail); ?><br/><?php endif; ?>
                    Generated <?php echo receipt_h($generatedDate . ' ' . $generatedTime); ?>
                </div>
            </footer>
        </article>
    </div>

    <?php if ($autoprint): ?>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
    <?php endif; ?>
</body>
</html>
