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
    header('Location: ' . trackingResultUrl($trackingId));
    exit;
}

$events = getTrackingEvents($shipment['id']);

$progressData = getTrackingProgressSteps($shipment['status'], $events);
$progressSteps = $progressData['steps'];
$progress = $progressData['progress'];
$progressActiveTone = 'green';
foreach ($progressSteps as $step) {
    if (($step['state'] ?? '') === 'active') {
        $progressActiveTone = $step['tone'] ?? 'green';
        break;
    }
}
$progressBarVisual = getTrackingStepVisual(['state' => 'active', 'tone' => $progressActiveTone]);
$progressBarClass = $progressBarVisual['bar'];

$eventsWithLocation = array_values(array_filter($events, function($e) {
    return hasUsableMapCoords($e['latitude'] ?? null, $e['longitude'] ?? null);
}));

include __DIR__ . '/includes/header.php';
?>
<script>
    <?php
    $mapPickupCoords = getShipmentMapEndpointCoords($shipment, 'pickup');
    $mapDropoffCoords = getShipmentMapEndpointCoords($shipment, 'dropoff');
    $mapPickupLabel = getShipmentMapEndpointLabel($shipment, 'pickup');
    $mapDropoffLabel = getShipmentMapEndpointLabel($shipment, 'dropoff');
    $mapPickupQuery = $mapPickupLabel;
    $mapDropoffQuery = $mapDropoffLabel;
    ?>
    window.__shipmentRouteFallback = {
        pickup: {
            name: <?php echo json_encode($mapPickupLabel); ?>,
            query: <?php echo json_encode($mapPickupQuery); ?>,
            city: <?php echo json_encode(trim((string) ($shipment['sender_city'] ?? ''))); ?>,
            country: <?php echo json_encode(trim((string) ($shipment['sender_country'] ?? ''))); ?>,
            lat: <?php echo json_encode($mapPickupCoords['lat']); ?>,
            lng: <?php echo json_encode($mapPickupCoords['lng']); ?>
        },
        dropoff: {
            name: <?php echo json_encode($mapDropoffLabel); ?>,
            query: <?php echo json_encode($mapDropoffQuery); ?>,
            city: <?php echo json_encode(trim((string) ($shipment['recipient_city'] ?? ''))); ?>,
            country: <?php echo json_encode(trim((string) ($shipment['recipient_country'] ?? ''))); ?>,
            lat: <?php echo json_encode($mapDropoffCoords['lat']); ?>,
            lng: <?php echo json_encode($mapDropoffCoords['lng']); ?>
        }
    };
</script>
<style>
    #map-container {
        height: 380px;
        width: 100%;
        overflow: visible;
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
                    <div class="relative flex items-center justify-between mb-2 px-2 pt-1 pb-8">
                        <div class="absolute left-0 top-[15px] w-full h-1 bg-gray-200 dark:bg-gray-600 -z-10"></div>
                        <div class="absolute left-0 top-[15px] h-1 <?php echo htmlspecialchars($progressBarClass); ?> -z-10 transition-all duration-1000" style="width: <?php echo (int) $progress; ?>%"></div>

                        <?php foreach ($progressSteps as $step):
                            $visual = getTrackingStepVisual($step);
                            $isActive = ($step['state'] ?? '') === 'active';
                            $circleSize = $isActive ? 'w-10 h-10' : 'w-8 h-8';
                        ?>
                        <div class="flex flex-col items-center group relative z-10">
                            <div class="<?php echo $circleSize; ?> rounded-full <?php echo htmlspecialchars($visual['circle']); ?> flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-sm">
                                <span class="material-symbols-outlined text-[16px]"><?php echo htmlspecialchars($step['icon'] ?? 'circle'); ?></span>
                            </div>
                            <span class="mt-2 text-[10px] md:text-xs font-bold text-center max-w-[4.5rem] md:max-w-none <?php echo htmlspecialchars($visual['label']); ?>"><?php echo htmlspecialchars($step['label']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live Shipment Route -->
                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom flex flex-col border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-600 flex justify-between items-center bg-white dark:bg-gray-800 rounded-t">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center text-sm uppercase tracking-wide">
                            <span class="material-symbols-outlined mr-2 text-yellow-600 text-[20px]">map</span>
                            Live Shipment Route
                        </h3>
                        <a class="text-xs font-bold text-yellow-600 uppercase hover:underline flex items-center" href="<?php echo htmlspecialchars(trackingResultUrl($trackingId)); ?>">
                            View Details <span class="material-symbols-outlined ml-1 text-[16px]">open_in_new</span>
                        </a>
                    </div>

                    <div class="relative w-full bg-[#E5E7EB] dark:bg-gray-700 overflow-visible">
                        <div id="map-container" class="w-full"></div>
                    </div>
                </div>

                <div class="bg-surface-light dark:bg-surface-dark rounded shadow-custom">
                    <a href="<?php echo htmlspecialchars(trackingResultUrl($trackingId)); ?>" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors rounded">
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
<script src="<?php echo htmlspecialchars(assetUrl('/js/map-animation.js')); ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>

