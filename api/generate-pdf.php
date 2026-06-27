<?php
/**
 * Generate PDF Receipt
 * Generate PDF receipt for a shipment
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if FPDF is available, if not use simple PDF generation
$useFPDF = class_exists('FPDF');

$trackingNumber = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($trackingNumber)) {
    die('Tracking number is required');
}

// Get shipment
$shipment = getShipmentByTracking($trackingNumber);

if (!$shipment) {
    die('Shipment not found');
}

// Get tracking events
$events = getTrackingEvents($shipment['id']);

if ($useFPDF) {
    // Use FPDF if available
    require_once('fpdf/fpdf.php');
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Shipping Receipt', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Tracking Number: ' . $shipment['tracking_number'], 0, 1);
    $pdf->Cell(0, 10, 'Service Type: ' . $shipment['service_type'], 0, 1);
    $pdf->Cell(0, 10, 'Status: ' . $shipment['status'], 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Sender Information', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, $shipment['sender_name'], 0, 1);
    $pdf->Cell(0, 8, $shipment['sender_address'], 0, 1);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Recipient Information', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, $shipment['recipient_name'], 0, 1);
    $pdf->Cell(0, 8, $shipment['recipient_address'], 0, 1);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Tracking History', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    foreach ($events as $event) {
        $pdf->Cell(0, 7, formatDateTime($event['event_date']) . ' - ' . $event['description'], 0, 1);
        if ($event['location']) {
            $pdf->Cell(0, 5, 'Location: ' . $event['location'], 0, 1);
        }
        $pdf->Ln(2);
    }
    
    $pdf->Output('D', 'receipt-' . str_replace(' ', '-', $shipment['tracking_number']) . '.pdf');
} else {
    // Simple HTML to PDF using browser print
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Shipping Receipt - <?php echo htmlspecialchars($shipment['tracking_number']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            h1 { text-align: center; }
            .section { margin: 20px 0; }
            .label { font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4D148C; color: white; }
            @media print {
                body { padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <h1>Shipping Receipt</h1>
        
        <div class="section">
            <p><span class="label">Tracking Number:</span> <?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
            <p><span class="label">Service Type:</span> <?php echo htmlspecialchars($shipment['service_type']); ?></p>
            <p><span class="label">Status:</span> <?php echo htmlspecialchars($shipment['status']); ?></p>
            <p><span class="label">Weight:</span> <?php echo htmlspecialchars($shipment['weight'] ?? 'N/A'); ?></p>
            <p><span class="label">Dimensions:</span> <?php echo htmlspecialchars($shipment['dimensions'] ?? 'N/A'); ?></p>
        </div>
        
        <div class="section">
            <h2>Sender Information</h2>
            <p><?php echo htmlspecialchars($shipment['sender_name']); ?></p>
            <p><?php echo htmlspecialchars($shipment['sender_address']); ?></p>
            <?php if ($shipment['sender_city']): ?>
            <p><?php echo htmlspecialchars($shipment['sender_city'] . ', ' . $shipment['sender_state'] . ' ' . $shipment['sender_zip']); ?></p>
            <?php endif; ?>
            <?php if (!empty($shipment['sender_email'])): ?>
            <p><span class="label">Email:</span> <?php echo htmlspecialchars($shipment['sender_email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($shipment['sender_phone'])): ?>
            <p><span class="label">Phone:</span> <?php echo htmlspecialchars($shipment['sender_phone']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>Recipient Information</h2>
            <p><?php echo htmlspecialchars($shipment['recipient_name']); ?></p>
            <p><?php echo htmlspecialchars($shipment['recipient_address']); ?></p>
            <?php if ($shipment['recipient_city']): ?>
            <p><?php echo htmlspecialchars($shipment['recipient_city'] . ', ' . $shipment['recipient_state'] . ' ' . $shipment['recipient_zip']); ?></p>
            <?php endif; ?>
            <?php if (!empty($shipment['recipient_email'])): ?>
            <p><span class="label">Email:</span> <?php echo htmlspecialchars($shipment['recipient_email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($shipment['recipient_phone'])): ?>
            <p><span class="label">Phone:</span> <?php echo htmlspecialchars($shipment['recipient_phone']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>Tracking History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Event</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo formatDateTime($event['event_date']); ?></td>
                        <td><?php echo htmlspecialchars($event['description']); ?></td>
                        <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()">Print Receipt</button>
        </div>
    </body>
    </html>
    <?php
}

