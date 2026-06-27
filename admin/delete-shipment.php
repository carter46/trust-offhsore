<?php
/**
 * Delete Shipment (Admin)
 * Deletes a shipment and its related tracking events.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/manage-shipments.php');
    exit;
}

$shipmentId = isset($_POST['id']) ? intval($_POST['id']) : 0;
$returnTo = isset($_POST['return_to']) ? trim($_POST['return_to']) : '';

function safeAdminReturnUrl($returnTo) {
    // Only allow same-host absolute paths (prevents open redirect)
    if (!$returnTo) return '/admin/manage-shipments.php';
    $parsed = parse_url($returnTo);
    if ($parsed === false) return '/admin/manage-shipments.php';
    if (isset($parsed['scheme']) || isset($parsed['host'])) return '/admin/manage-shipments.php';
    if (!isset($parsed['path']) || $parsed['path'] === '') return '/admin/manage-shipments.php';
    // Only allow returning to admin pages
    if (strpos($parsed['path'], '/admin/') !== 0) return '/admin/manage-shipments.php';
    return $parsed['path'] . (isset($parsed['query']) ? ('?' . $parsed['query']) : '');
}

$redirectBase = safeAdminReturnUrl($returnTo);

if ($shipmentId <= 0) {
    header('Location: ' . $redirectBase . (strpos($redirectBase, '?') === false ? '?' : '&') . 'delete_error=Invalid+shipment+ID');
    exit;
}

// Fetch shipment for optional image cleanup and to ensure it exists
$stmt = $conn->prepare("SELECT id, item_image FROM shipments WHERE id = ?");
$stmt->bind_param("i", $shipmentId);
$stmt->execute();
$res = $stmt->get_result();
$shipment = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$shipment) {
    header('Location: ' . $redirectBase . (strpos($redirectBase, '?') === false ? '?' : '&') . 'delete_error=Shipment+not+found');
    exit;
}

// Delete from DB (events first for safety even if FK is missing)
$conn->begin_transaction();
try {
    $evt = $conn->prepare("DELETE FROM tracking_events WHERE shipment_id = ?");
    $evt->bind_param("i", $shipmentId);
    $evt->execute();
    $evt->close();

    $del = $conn->prepare("DELETE FROM shipments WHERE id = ?");
    $del->bind_param("i", $shipmentId);
    $ok = $del->execute();
    $delErr = $del->error;
    $del->close();

    if (!$ok) {
        throw new Exception($delErr ?: 'Unknown delete error');
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $msg = urlencode('Failed to delete: ' . $e->getMessage());
    header('Location: ' . $redirectBase . (strpos($redirectBase, '?') === false ? '?' : '&') . 'delete_error=' . $msg);
    exit;
}

// Optional: remove uploaded image file if it’s inside /asset/shipment_images/
$img = $shipment['item_image'] ?? '';
if ($img && strpos($img, '/asset/shipment_images/') === 0) {
    $candidate = realpath(__DIR__ . '/..' . $img);
    $baseDir = realpath(__DIR__ . '/../asset/shipment_images');
    if ($candidate && $baseDir && strpos($candidate, $baseDir) === 0 && is_file($candidate)) {
        @unlink($candidate);
    }
}

header('Location: ' . $redirectBase . (strpos($redirectBase, '?') === false ? '?' : '&') . 'deleted=1');
exit;


