<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// #region agent log
$logFile = __DIR__ . '/.cursor/debug.log';
$t0 = microtime(true);
$sessionId = 'track-result-504-debug';
$logLine = json_encode([
    'sessionId' => $sessionId,
    'runId' => 'pre-fix',
    'hypothesisId' => 'A',
    'location' => 'track-result.php:entry',
    'message' => 'Page entry',
    'data' => [
        'trackingId' => $_GET['id'] ?? null,
        'trackingIdLength' => isset($_GET['id']) ? strlen($_GET['id']) : 0,
        'hasPlusSign' => isset($_GET['id']) && strpos($_GET['id'], '+') !== false,
        'sessionStatus' => session_status(),
        'memoryUsage' => memory_get_usage(true),
    ],
    'timestamp' => (int) round($t0 * 1000),
]) . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);
// #endregion

$trackingId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($trackingId)) {
    header('Location: /track');
    exit;
}

// #region agent log
$t1 = microtime(true);
$logLine = json_encode([
    'sessionId' => $sessionId,
    'runId' => 'pre-fix',
    'hypothesisId' => 'B',
    'location' => 'track-result.php:before-getShipmentByTracking',
    'message' => 'About to call getShipmentByTracking',
    'data' => [
        'trackingId' => $trackingId,
        'cleanTrackingId' => str_replace(' ', '', $trackingId),
        'elapsedMs' => (int) round(($t1 - $t0) * 1000),
        'sessionStatus' => session_status(),
    ],
    'timestamp' => (int) round($t1 * 1000),
]) . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);
// #endregion

// Fetch tracking data directly from database
$shipment = getShipmentByTracking($trackingId);

// #region agent log
$t2 = microtime(true);
$logLine = json_encode([
    'sessionId' => $sessionId,
    'runId' => 'pre-fix',
    'hypothesisId' => 'B',
    'location' => 'track-result.php:after-getShipmentByTracking',
    'message' => 'getShipmentByTracking completed',
    'data' => [
        'found' => $shipment !== null && $shipment !== false,
        'shipmentId' => $shipment['id'] ?? null,
        'queryTimeMs' => (int) round(($t2 - $t1) * 1000),
        'elapsedMs' => (int) round(($t2 - $t0) * 1000),
        'memoryUsage' => memory_get_usage(true),
    ],
    'timestamp' => (int) round($t2 * 1000),
]) . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);
// #endregion

if (!$shipment) {
    // #region agent log
    $logLine = json_encode([
        'sessionId' => $sessionId,
        'runId' => 'pre-fix',
        'hypothesisId' => 'B',
        'location' => 'track-result.php:shipment-not-found',
        'message' => 'Shipment not found, exiting',
        'data' => [
            'trackingId' => $trackingId,
            'totalElapsedMs' => (int) round((microtime(true) - $t0) * 1000),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ]) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    // #endregion
    $error = 'Shipment not found';
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="flex-grow flex flex-col items-center justify-center pt-12 pb-24 px-4 bg-gray-50 min-h-screen">
        <div class="text-center">
            <h1 class="text-3xl font-light text-gray-800 mb-4">Shipment Not Found</h1>
            <p class="text-gray-600 mb-8"><?php echo htmlspecialchars($error); ?></p>
            <a href="/track" class="bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-8 rounded-full uppercase tracking-wide transition-colors">
                Track Another Shipment
            </a>
        </div>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// #region agent log
$t3 = microtime(true);
$logLine = json_encode([
    'sessionId' => $sessionId,
    'runId' => 'pre-fix',
    'hypothesisId' => 'D',
    'location' => 'track-result.php:before-getTrackingEvents',
    'message' => 'About to call getTrackingEvents',
    'data' => [
        'shipmentId' => $shipment['id'],
        'elapsedMs' => (int) round(($t3 - $t0) * 1000),
    ],
    'timestamp' => (int) round($t3 * 1000),
]) . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);
// #endregion

$events = getTrackingEvents($shipment['id']);

// #region agent log
$t4 = microtime(true);
$logLine = json_encode([
    'sessionId' => $sessionId,
    'runId' => 'pre-fix',
    'hypothesisId' => 'D',
    'location' => 'track-result.php:after-getTrackingEvents',
    'message' => 'getTrackingEvents completed',
    'data' => [
        'eventCount' => count($events),
        'queryTimeMs' => (int) round(($t4 - $t3) * 1000),
        'elapsedMs' => (int) round(($t4 - $t0) * 1000),
        'memoryUsage' => memory_get_usage(true),
    ],
    'timestamp' => (int) round($t4 * 1000),
]) . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);
// #endregion

// Separate remarks (Admin Note) from public Travel History
$latestRemark = null;
$publicEvents = [];
foreach ($events as $ev) {
    if (($ev['event_type'] ?? '') === 'Admin Note') {
        if ($latestRemark === null && !empty($ev['description'])) {
            $latestRemark = $ev; // events are DESC, first Admin Note is latest
        }
        continue; // do not show remarks inside the timeline
    }
    $publicEvents[] = $ev;
}

// Group public events by date
$eventsByDate = [];
foreach ($publicEvents as $event) {
    $date = date('Y-m-d', strtotime($event['event_date']));
    if (!isset($eventsByDate[$date])) {
        $eventsByDate[$date] = [];
    }
    $eventsByDate[$date][] = $event;
}

// Calculate progress
$progress = 0;
$status = strtolower($shipment['status']);
if (strpos($status, 'delivered') !== false) {
    $progress = 100;
} elseif (strpos($status, 'transit') !== false || strpos($status, 'delivery') !== false) {
    $progress = 75;
} elseif (strpos($status, 'picked') !== false) {
    $progress = 50;
} else {
    $progress = 25;
}

// #region agent log
$t5 = microtime(true);
$logLine = json_encode([
    'sessionId' => $sessionId,
    'runId' => 'pre-fix',
    'hypothesisId' => 'A',
    'location' => 'track-result.php:before-header-include',
    'message' => 'About to include header',
    'data' => [
        'totalElapsedMs' => (int) round(($t5 - $t0) * 1000),
        'sessionStatus' => session_status(),
        'memoryUsage' => memory_get_usage(true),
    ],
    'timestamp' => (int) round($t5 * 1000),
]) . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);
// #endregion

include __DIR__ . '/includes/header.php';
?>
<main class="flex-grow bg-gray-50 pb-16 track-result-page">
    <div class="bg-white border-b border-gray-200 py-4">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between">
            <a href="/track.php" class="text-yellow-600 font-bold text-sm uppercase tracking-wide cursor-pointer flex items-center hover:underline">
                <span class="material-symbols-outlined mr-1 !text-lg">arrow_back</span> Back to Tracking
            </a>
            <div class="hidden md:block text-sm text-gray-500">
                Need help? <a class="text-yellow-600 hover:underline" href="#">Contact Support</a>
            </div>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 pt-8">
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center">
            <h1 class="text-3xl font-light text-gray-800">
                Tracking results for <span class="font-bold whitespace-nowrap text-slate-900"><?php echo htmlspecialchars($shipment['tracking_number']); ?></span>
            </h1>
            <div class="mt-4 md:mt-0 flex gap-3">
                <a href="/api/generate-pdf.php?id=<?php echo urlencode($trackingId); ?>" target="_blank" class="flex items-center text-sm font-bold text-yellow-600 hover:bg-gray-200 px-3 py-2 rounded transition-colors">
                    <span class="material-symbols-outlined mr-2 !text-lg">print</span> Print
                </a>
                <button class="flex items-center text-sm font-bold text-yellow-600 hover:bg-gray-200 px-3 py-2 rounded transition-colors">
                    <span class="material-symbols-outlined mr-2 !text-lg">share</span> Share
                </button>
            </div>
        </div>
        <div class="bg-white shadow-sm rounded-sm overflow-hidden mb-8 border border-gray-200">
            <div class="h-2 bg-secondary w-full"></div>
            <div class="p-6 md:p-10">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-8">
                    <div class="flex-1">
                        <div class="inline-flex items-center gap-2 mb-3 text-gray-600">
                            <span class="<?php echo getStatusBadgeClass($shipment['status']); ?> p-1 rounded-full flex items-center justify-center">
                                <span class="material-symbols-outlined !text-lg">local_shipping</span>
                            </span>
                            <span class="text-sm font-bold uppercase tracking-wider text-slate-800"><?php echo htmlspecialchars($shipment['status']); ?></span>
                        </div>
                        <h2 class="text-4xl md:text-5xl font-light text-gray-800 mb-2">
                            <?php echo formatDate($shipment['estimated_delivery'] ?: 'now', 'l, m/d/Y'); ?>
                        </h2>
                        <p class="text-lg text-gray-600 mb-6 font-light">
                            <?php if ($shipment['estimated_delivery']): ?>
                                Estimated delivery by <span class="font-medium"><?php echo date('g:i A', strtotime($shipment['estimated_delivery'])); ?></span>
                            <?php else: ?>
                                Delivery information will be updated soon
                            <?php endif; ?>
                        </p>
                        <div class="flex items-center text-sm text-gray-500 font-medium">
                            <span><?php echo htmlspecialchars($shipment['sender_city'] ?: 'Origin'); ?><?php echo $shipment['sender_state'] ? ', ' . htmlspecialchars($shipment['sender_state']) : ''; ?></span>
                            <span class="mx-2 text-gray-300">—</span>
                            <span class="material-symbols-outlined !text-sm mx-1 text-gray-400">arrow_forward</span>
                            <span class="mx-2 text-gray-300">—</span>
                            <span><?php echo htmlspecialchars($shipment['recipient_city'] ?: 'Destination'); ?><?php echo $shipment['recipient_state'] ? ', ' . htmlspecialchars($shipment['recipient_state']) : ''; ?></span>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto mt-4 lg:mt-0">
                        <a href="#shipment-route-map" class="border-2 border-yellow-400 text-yellow-600 hover:bg-yellow-50 font-bold py-3 px-8 rounded-full text-sm uppercase tracking-wide transition-colors whitespace-nowrap text-center">
                            Jump to Map
                        </a>
                        <button type="button" class="border-2 border-yellow-400 text-yellow-600 hover:bg-yellow-50 font-bold py-3 px-8 rounded-full text-sm uppercase tracking-wide transition-colors whitespace-nowrap">
                            Get Updates
                        </button>
                    </div>
                </div>
                <!-- Progress Timeline -->
                <div class="mt-14 mb-8">
                    <div class="relative px-4 md:px-0">
                        <div class="absolute top-1/2 left-0 w-full h-1 bg-gray-200 -translate-y-1/2 z-0 rounded"></div>
                        <div class="absolute top-1/2 left-0 h-1 bg-green-600 -translate-y-1/2 z-0 rounded transition-all duration-1000" style="width: <?php echo $progress; ?>%"></div>
                        <div class="relative z-10 flex justify-between w-full">
                            <div class="flex flex-col items-center group cursor-default">
                                <div class="w-8 h-8 rounded-full <?php echo $progress >= 25 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500'; ?> flex items-center justify-center shadow-sm ring-4 ring-white">
                                    <span class="material-symbols-outlined !text-sm">done</span>
                                </div>
                                <div class="absolute top-10 flex flex-col items-center w-32 text-center">
                                    <div class="mt-1 text-xs md:text-sm font-bold text-gray-800">Label Created</div>
                                </div>
                            </div>
                            <div class="flex flex-col items-center group cursor-default">
                                <div class="w-8 h-8 rounded-full <?php echo $progress >= 50 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500'; ?> flex items-center justify-center shadow-sm ring-4 ring-white">
                                    <span class="material-symbols-outlined !text-sm">done</span>
                                </div>
                                <div class="absolute top-10 flex flex-col items-center w-32 text-center">
                                    <div class="mt-1 text-xs md:text-sm font-bold text-gray-800">Picked Up</div>
                                </div>
                            </div>
                            <div class="flex flex-col items-center group cursor-default">
                                <div class="w-10 h-10 -mt-1 rounded-full <?php echo $progress >= 75 ? 'bg-yellow-400 text-black' : 'bg-gray-200 text-gray-500'; ?> flex items-center justify-center shadow-md ring-4 ring-white <?php echo $progress >= 75 ? 'animate-pulse' : ''; ?>">
                                    <span class="material-symbols-outlined !text-xl">local_shipping</span>
                                </div>
                                <div class="absolute top-10 flex flex-col items-center w-32 text-center">
                                    <div class="mt-1 text-xs md:text-sm font-bold <?php echo $progress >= 75 ? 'text-yellow-600' : 'text-gray-400'; ?>">In Transit</div>
                                </div>
                            </div>
                            <div class="flex flex-col items-center group cursor-default">
                                <div class="w-8 h-8 rounded-full <?php echo $progress >= 100 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-400'; ?> flex items-center justify-center shadow-sm ring-4 ring-white">
                                    <span class="material-symbols-outlined !text-sm">home</span>
                                </div>
                                <div class="absolute top-10 flex flex-col items-center w-32 text-center">
                                    <div class="mt-1 text-xs md:text-sm font-bold <?php echo $progress >= 100 ? 'text-gray-800' : 'text-gray-400'; ?>">Delivered</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-wrap gap-x-8 gap-y-3">
                <a href="/api/generate-pdf.php?id=<?php echo urlencode($trackingId); ?>" target="_blank" class="text-yellow-600 font-bold text-xs md:text-sm uppercase hover:underline flex items-center group">
                    <span class="material-symbols-outlined mr-2 !text-lg text-gray-400 group-hover:text-yellow-600 transition-colors">receipt_long</span> Obtain Proof of Delivery
                </a>
                <button class="text-yellow-600 font-bold text-xs md:text-sm uppercase hover:underline flex items-center group">
                    <span class="material-symbols-outlined mr-2 !text-lg text-gray-400 group-hover:text-yellow-600 transition-colors">help</span> Delivery FAQ
                </button>
            </div>
        </div>
        <?php
        $totalDue = getShipmentTotalDue($shipment);
        $hasCostBand = !empty($shipment['base_cost']) || !empty($shipment['clearance_cost']) || $totalDue !== null;
        if ($hasCostBand):
        ?>
        <section class="track-cost-band mb-8 rounded-lg border border-stone-200/90 bg-gradient-to-br from-white via-stone-50 to-amber-50/40 px-4 py-4 md:px-5 md:py-5 shadow-sm" aria-label="Shipment costs">
            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-stone-200/70">
                <span class="material-symbols-outlined text-amber-600/80 text-lg">payments</span>
                <h2 class="text-stone-600 font-semibold uppercase tracking-[0.12em] text-[11px]">Payment Summary</h2>
            </div>
            <div class="track-cost-band-grid grid grid-cols-2 lg:grid-cols-4 gap-2.5 md:gap-3">
                <?php if (!empty($shipment['base_cost'])): ?>
                <div class="track-cost-card bg-white/90 rounded-md border border-stone-100 px-3 py-2.5 flex flex-col gap-0.5 shadow-sm">
                    <span class="track-cost-label text-[10px] font-semibold uppercase tracking-wider text-stone-400">Shipping Cost</span>
                    <span class="track-cost-value text-base md:text-lg font-semibold text-stone-800 tabular-nums">$<?php echo htmlspecialchars(formatMoney($shipment['base_cost'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($shipment['clearance_cost'])): ?>
                <div class="track-cost-card bg-white/90 rounded-md border border-stone-100 px-3 py-2.5 flex flex-col gap-0.5 shadow-sm">
                    <span class="track-cost-label text-[10px] font-semibold uppercase tracking-wider text-stone-400">Clearance Cost</span>
                    <span class="track-cost-value text-base md:text-lg font-semibold text-stone-800 tabular-nums">$<?php echo htmlspecialchars(formatMoney($shipment['clearance_cost'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($shipment['shipment_worth'])): ?>
                <div class="track-cost-card bg-white/90 rounded-md border border-stone-100 px-3 py-2.5 flex flex-col gap-0.5 shadow-sm">
                    <span class="track-cost-label text-[10px] font-semibold uppercase tracking-wider text-stone-400">Shipment Worth</span>
                    <span class="track-cost-value text-base md:text-lg font-semibold text-stone-800 tabular-nums">$<?php echo htmlspecialchars(formatMoney($shipment['shipment_worth'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($totalDue !== null): ?>
                <div class="track-cost-card track-cost-card-total bg-white rounded-md border border-amber-200/60 px-3 py-2.5 flex flex-col gap-0.5 shadow-sm col-span-2 lg:col-span-1">
                    <span class="track-cost-label text-[10px] font-semibold uppercase tracking-wider text-amber-700/70">Total Due</span>
                    <span class="track-cost-value track-cost-value-total text-lg md:text-xl font-bold text-stone-900 tabular-nums">$<?php echo htmlspecialchars(formatMoney($totalDue)); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-2">
                <div id="shipment-route-map" class="bg-white shadow-sm rounded-sm overflow-hidden border border-gray-200 mb-8 scroll-mt-24">
                    <div class="px-6 py-4 border-b border-gray-200 bg-slate-900 text-white flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <span class="material-symbols-outlined text-yellow-400">map</span>
                            Live Shipment Route
                        </h3>
                        <span class="text-xs uppercase tracking-wider text-yellow-200/80 hidden sm:inline">Updated from tracking events</span>
                    </div>
                    <div id="map-container" class="w-full h-[340px] md:h-[420px] bg-slate-200"></div>
                </div>
                <div class="bg-white shadow-sm rounded-sm overflow-hidden border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800">Travel History</h3>
                        <span class="text-xs text-gray-500 uppercase font-medium">Local Time</span>
                    </div>
                    <div class="p-6">
                        <?php if (empty($eventsByDate)): ?>
                            <p class="text-gray-500 text-center py-8">No tracking events available yet.</p>
                        <?php else: ?>
                            <?php foreach ($eventsByDate as $date => $dateEvents): ?>
                                <div class="mb-8 relative">
                                    <div class="sticky top-0 bg-white py-2 z-10 mb-4 border-b border-gray-100">
                                        <span class="font-bold text-lg text-gray-800"><?php echo formatDate($date, 'l, F j, Y'); ?></span>
                                    </div>
                                    <div class="space-y-8 pl-2 relative border-l-2 border-gray-200 ml-2">
                                        <?php foreach ($dateEvents as $index => $event): ?>
                                            <div class="relative pl-8">
                                                <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full border-4 border-white <?php echo $index === 0 ? 'bg-yellow-400' : 'bg-gray-300'; ?> shadow-sm"></div>
                                                <div class="flex flex-col sm:flex-row sm:gap-6">
                                                    <div class="text-sm font-bold text-gray-500 w-20 shrink-0"><?php echo formatDateTime($event['event_date'], 'g:i A'); ?></div>
                                                    <div>
                                                        <div class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($event['description']); ?></div>
                                                        <?php if ($event['location']): ?>
                                                            <div class="text-sm text-gray-600 mt-1 uppercase"><?php echo htmlspecialchars($event['location']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($latestRemark && !empty($latestRemark['description'])): ?>
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">Remark</div>
                                <div class="bg-gray-50 rounded p-4">
                                    <div class="text-sm text-gray-800 font-medium">
                                        <?php echo nl2br(htmlspecialchars($latestRemark['description'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-2">
                                        Updated: <?php echo htmlspecialchars(formatDateTime($latestRemark['event_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white shadow-sm rounded-sm overflow-hidden border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-800">Sender</h3>
                    </div>
                    <div class="p-6 space-y-2 text-sm">
                        <div class="font-bold text-gray-800"><?php echo htmlspecialchars($shipment['sender_name']); ?></div>
                        <div class="text-gray-600">
                            <?php echo nl2br(htmlspecialchars($shipment['sender_address'])); ?>
                            <?php if (!empty($shipment['sender_city']) || !empty($shipment['sender_state']) || !empty($shipment['sender_zip'])): ?>
                                <br><?php echo htmlspecialchars(trim(($shipment['sender_city'] ?? '') . (empty($shipment['sender_city']) ? '' : ', ') . ($shipment['sender_state'] ?? '') . ' ' . ($shipment['sender_zip'] ?? ''))); ?>
                            <?php endif; ?>
                            <?php if (!empty($shipment['sender_country'])): ?>
                                <br><?php echo htmlspecialchars($shipment['sender_country']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($shipment['sender_phone'])): ?>
                            <div class="text-gray-700"><span class="font-bold">Phone:</span> <?php echo htmlspecialchars($shipment['sender_phone']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['sender_email'])): ?>
                            <div class="text-gray-700"><span class="font-bold">Email:</span> <?php echo htmlspecialchars($shipment['sender_email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-sm overflow-hidden border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-800">Receiver</h3>
                    </div>
                    <div class="p-6 space-y-2 text-sm">
                        <div class="font-bold text-gray-800"><?php echo htmlspecialchars($shipment['recipient_name']); ?></div>
                        <div class="text-gray-600">
                            <?php echo nl2br(htmlspecialchars($shipment['recipient_address'])); ?>
                            <?php if (!empty($shipment['recipient_city']) || !empty($shipment['recipient_state']) || !empty($shipment['recipient_zip'])): ?>
                                <br><?php echo htmlspecialchars(trim(($shipment['recipient_city'] ?? '') . (empty($shipment['recipient_city']) ? '' : ', ') . ($shipment['recipient_state'] ?? '') . ' ' . ($shipment['recipient_zip'] ?? ''))); ?>
                            <?php endif; ?>
                            <?php if (!empty($shipment['recipient_country'])): ?>
                                <br><?php echo htmlspecialchars($shipment['recipient_country']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($shipment['recipient_phone'])): ?>
                            <div class="text-gray-700"><span class="font-bold">Phone:</span> <?php echo htmlspecialchars($shipment['recipient_phone']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['recipient_email'])): ?>
                            <div class="text-gray-700"><span class="font-bold">Email:</span> <?php echo htmlspecialchars($shipment['recipient_email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-sm overflow-hidden border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-800">Shipment Facts</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Tracking Number</div>
                            <div class="text-base font-medium text-yellow-600"><?php echo htmlspecialchars($shipment['tracking_number']); ?></div>
                        </div>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Service</div>
                            <div class="text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($shipment['service_type']); ?></div>
                        </div>
                        <?php if ($shipment['weight']): ?>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Weight</div>
                            <div class="text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($shipment['weight']); ?> lbs</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($shipment['dimensions']): ?>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Dimensions</div>
                            <div class="text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($shipment['dimensions']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($shipment['reference_number']): ?>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Reference</div>
                            <div class="text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($shipment['reference_number']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['pickup_location']) || (!empty($shipment['pickup_latitude']) && !empty($shipment['pickup_longitude']))): ?>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Pickup</div>
                            <div class="text-sm text-gray-800 font-medium">
                                <?php echo htmlspecialchars($shipment['pickup_location'] ?: '—'); ?>
                                <?php if (!empty($shipment['pickup_latitude']) && !empty($shipment['pickup_longitude'])): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($shipment['pickup_latitude']); ?>, <?php echo htmlspecialchars($shipment['pickup_longitude']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['dropoff_location']) || (!empty($shipment['dropoff_latitude']) && !empty($shipment['dropoff_longitude']))): ?>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-1 tracking-wider">Dropoff</div>
                            <div class="text-sm text-gray-800 font-medium">
                                <?php echo htmlspecialchars($shipment['dropoff_location'] ?: '—'); ?>
                                <?php if (!empty($shipment['dropoff_latitude']) && !empty($shipment['dropoff_longitude'])): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($shipment['dropoff_latitude']); ?>, <?php echo htmlspecialchars($shipment['dropoff_longitude']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['item_image'])): ?>
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">Item Image</div>
                            <a href="<?php echo htmlspecialchars($shipment['item_image']); ?>" target="_blank" class="block">
                                <img src="<?php echo htmlspecialchars($shipment['item_image']); ?>" alt="Shipment item" class="w-full h-40 object-cover rounded border border-gray-200">
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
window.__shipmentRouteFallback = {
    pickup: {
        name: <?php echo json_encode($shipment['pickup_location'] ?? ''); ?>,
        lat: <?php echo json_encode($shipment['pickup_latitude'] ?? null); ?>,
        lng: <?php echo json_encode($shipment['pickup_longitude'] ?? null); ?>
    },
    dropoff: {
        name: <?php echo json_encode($shipment['dropoff_location'] ?? ''); ?>,
        lat: <?php echo json_encode($shipment['dropoff_latitude'] ?? null); ?>,
        lng: <?php echo json_encode($shipment['dropoff_longitude'] ?? null); ?>
    }
};
</script>
<script src="/js/map-animation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>

