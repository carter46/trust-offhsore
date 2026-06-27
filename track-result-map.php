<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$trackingId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($trackingId)) {
    header('Location: /track.php');
    exit;
}

// Fetch tracking data directly from database
$shipment = getShipmentByTracking($trackingId);

if (!$shipment) {
    header('Location: /track-result.php?id=' . urlencode($trackingId));
    exit;
}

$events = getTrackingEvents($shipment['id']);

// Calculate progress (same logic as track-result.php)
$progress = 0;
$status = strtolower($shipment['status']);
if (strpos($status, 'delivered') !== false) {
    $progress = 100;
} elseif (strpos($status, 'out for delivery') !== false || strpos($status, 'delivery') !== false) {
    $progress = 75;
} elseif (strpos($status, 'transit') !== false) {
    $progress = 70;
} elseif (strpos($status, 'picked') !== false || strpos($status, 'pickup') !== false) {
    $progress = 50;
} else {
    $progress = 25;
}

$eventsWithLocation = array_values(array_filter($events, function($e) {
    return !empty($e['latitude']) && !empty($e['longitude']);
}));

include __DIR__ . '/includes/header.php';
?>
<script>
    // Fallback route endpoints (used when there are not yet 2 tracking events with coordinates)
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
<style>
    #map-container {
        height: 380px;
        width: 100%;
    }
</style>
<main class="flex-grow flex flex-col pt-8 pb-12 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-light text-gray-800 dark:text-white">Tracking Results</h1>
            <a class="text-yellow-600 font-bold text-sm flex items-center hover:underline" href="/track">
                <span class="material-symbols-outlined mr-1 text-sm">arrow_back_ios</span> TRACK ANOTHER SHIPMENT
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <!-- Tracking Summary Card -->
                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom p-6 border-l-[6px] border-secondary relative overflow-hidden">
                    <div class="flex flex-col sm:flex-row justify-between items-start mb-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wide">Tracking Number</p>
                            <p class="text-xl font-bold text-primary dark:text-white tracking-wide"><?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
                        </div>
                        <div class="mt-2 sm:mt-0 text-right">
                            <span class="inline-flex items-center <?php echo getStatusBadgeClass($shipment['status']); ?> text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wider">
                                <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                                <?php echo htmlspecialchars($shipment['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-3xl md:text-4xl font-light text-gray-800 dark:text-white mb-2">
                            <?php echo htmlspecialchars(formatDate($shipment['estimated_delivery'] ?: 'now', 'l, M j')); ?>
                        </h2>
                        <p class="text-lg text-gray-600 dark:text-gray-300 font-light">
                            <?php if ($shipment['estimated_delivery']): ?>
                                Estimated delivery by end of day
                            <?php else: ?>
                                Delivery information will be updated soon
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Progress Timeline -->
                    <div class="relative flex items-center justify-between mb-2 px-2">
                        <div class="absolute left-0 top-[15px] w-full h-1 bg-gray-200 dark:bg-gray-600 -z-10"></div>
                        <div class="absolute left-0 top-[15px] h-1 bg-yellow-400 dark:bg-blue-500 -z-10 transition-all duration-1000" style="width: <?php echo (int) $progress; ?>%"></div>

                        <div class="flex flex-col items-center group">
                            <div class="w-8 h-8 rounded-full <?php echo $progress >= 25 ? 'bg-yellow-400 text-white' : 'bg-gray-300 dark:bg-gray-600'; ?> flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-sm z-10">
                                <span class="material-symbols-outlined text-[16px]">inventory_2</span>
                            </div>
                            <span class="mt-2 text-xs font-bold <?php echo $progress >= 25 ? 'text-yellow-600' : 'text-gray-500 dark:text-gray-400'; ?>">Label</span>
                        </div>

                        <div class="flex flex-col items-center group">
                            <div class="w-8 h-8 rounded-full <?php echo $progress >= 50 ? 'bg-yellow-400 text-white' : 'bg-gray-300 dark:bg-gray-600'; ?> flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-sm z-10">
                                <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                            </div>
                            <span class="mt-2 text-xs font-bold <?php echo $progress >= 50 ? 'text-yellow-600' : 'text-gray-500 dark:text-gray-400'; ?>">Picked up</span>
                        </div>

                        <div class="flex flex-col items-center group relative">
                            <div class="w-10 h-10 rounded-full <?php echo $progress >= 75 ? 'bg-secondary text-white' : 'bg-gray-300 dark:bg-gray-600'; ?> flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-lg z-20 scale-110">
                                <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                            </div>
                            <span class="mt-2 text-xs font-bold <?php echo $progress >= 75 ? 'text-secondary dark:text-orange-400' : 'text-gray-500 dark:text-gray-400'; ?>">In transit</span>
                        </div>

                        <div class="flex flex-col items-center group opacity-70">
                            <div class="w-8 h-8 rounded-full <?php echo $progress >= 100 ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600'; ?> border-4 border-white dark:border-gray-800 z-10"></div>
                            <span class="mt-2 text-xs font-bold <?php echo $progress >= 100 ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'; ?>">Delivered</span>
                        </div>
                    </div>
                </div>

                <!-- Live Shipment Route -->
                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom overflow-hidden flex flex-col border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-600 flex justify-between items-center bg-white dark:bg-gray-800">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center text-sm uppercase tracking-wide">
                            <span class="material-symbols-outlined mr-2 text-yellow-600 text-[20px]">map</span>
                            Live Shipment Route
                        </h3>
                        <a class="text-xs font-bold text-yellow-600 uppercase hover:underline flex items-center" href="/track-result.php?id=<?php echo urlencode($trackingId); ?>">
                            View Details <span class="material-symbols-outlined ml-1 text-[16px]">open_in_new</span>
                        </a>
                    </div>

                    <div class="relative w-full bg-[#E5E7EB] dark:bg-gray-700 overflow-hidden">
                        <div id="map-container" class="w-full"></div>
                    </div>
                </div>

                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom">
                    <a href="/track-result.php?id=<?php echo urlencode($trackingId); ?>" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors rounded">
                        <span class="font-bold text-gray-700 dark:text-gray-200 uppercase text-sm tracking-wide">Travel History</span>
                        <span class="material-symbols-outlined text-gray-500">open_in_new</span>
                    </a>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom p-6">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-4 uppercase text-sm border-b pb-3 border-gray-100 dark:border-gray-600 tracking-wide">Shipment Facts</h3>
                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400 text-xs uppercase font-medium">Service</span>
                            <span class="font-bold text-gray-800 dark:text-gray-200 text-right"><?php echo htmlspecialchars($shipment['service_type']); ?></span>
                        </div>
                        <?php if (!empty($shipment['weight'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400 text-xs uppercase font-medium">Weight</span>
                            <span class="font-bold text-gray-800 dark:text-gray-200 text-right"><?php echo htmlspecialchars($shipment['weight']); ?> lbs</span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['dimensions'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400 text-xs uppercase font-medium">Dimensions</span>
                            <span class="font-bold text-gray-800 dark:text-gray-200 text-right"><?php echo htmlspecialchars($shipment['dimensions']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($shipment['reference_number'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400 text-xs uppercase font-medium">Reference</span>
                            <span class="font-bold text-gray-800 dark:text-gray-200 text-right"><?php echo htmlspecialchars($shipment['reference_number']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom p-6">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-4 uppercase text-sm border-b pb-3 border-gray-100 dark:border-gray-600 tracking-wide">Manage Delivery</h3>
                    <div class="space-y-3">
                        <button type="button" class="flex items-center justify-center w-full py-3 px-4 border-2 border-primary dark:border-blue-400 text-yellow-600 font-bold text-sm rounded hover:bg-purple-50 dark:hover:bg-gray-700 transition-colors uppercase">
                            <span class="material-symbols-outlined mr-2 text-[18px]">notifications</span> Get Updates
                        </button>
                        <a class="flex items-center justify-center w-full py-3 px-4 border-2 border-primary dark:border-blue-400 text-yellow-600 font-bold text-sm rounded hover:bg-purple-50 dark:hover:bg-gray-700 transition-colors uppercase" href="/track">
                            <span class="material-symbols-outlined mr-2 text-[18px]">search</span> Track Another
                        </a>
                    </div>
                </div>

                <div class="bg-[#E6E6E6] dark:bg-gray-700 rounded shadow-inner p-4 text-center">
                    <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">Want updates on this shipment?</p>
                    <button type="button" class="text-sm font-bold text-yellow-600 hover:underline">TURN ON NOTIFICATIONS</button>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="/js/map-animation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>

