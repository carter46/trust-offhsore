<?php
include __DIR__ . '/includes/admin-header.php';

// Get statistics
$stats = [];

// Total shipments (safe - no user input)
$result = $conn->query("SELECT COUNT(*) as total FROM shipments");
if ($result) {
    $stats['total'] = $result->fetch_assoc()['total'];
    $result->free();
} else {
    $stats['total'] = 0;
}

// In transit (safe - hardcoded strings)
$result = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status LIKE '%Transit%' OR status LIKE '%Delivery%'");
if ($result) {
    $stats['in_transit'] = $result->fetch_assoc()['total'];
    $result->free();
} else {
    $stats['in_transit'] = 0;
}

// Delivered (safe - hardcoded strings)
$result = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status LIKE '%Delivered%'");
if ($result) {
    $stats['delivered'] = $result->fetch_assoc()['total'];
    $result->free();
} else {
    $stats['delivered'] = 0;
}

// Recent shipments (safe - hardcoded LIMIT)
$result = $conn->query("SELECT * FROM shipments ORDER BY created_at DESC LIMIT 10");
$recentShipments = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentShipments[] = $row;
    }
    $result->free();
}
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</p>
</div>

<?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
    <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
        Shipment deleted successfully.
    </div>
<?php endif; ?>
<?php if (!empty($_GET['delete_error'])): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($_GET['delete_error']); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Shipments</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2"><?php echo $stats['total']; ?></p>
            </div>
            <div class="bg-primary/10 p-3 rounded-full">
                <span class="material-icons-outlined text-primary text-3xl">inventory_2</span>
            </div>
        </div>
    </div>
    
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400 uppercase tracking-wide">In Transit</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2"><?php echo $stats['in_transit']; ?></p>
            </div>
            <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-3xl">local_shipping</span>
            </div>
        </div>
    </div>
    
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400 uppercase tracking-wide">Delivered</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2"><?php echo $stats['delivered']; ?></p>
            </div>
            <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                <span class="material-icons-outlined text-green-600 dark:text-green-400 text-3xl">check_circle</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6 mb-8">
    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="/admin/create-shipment.php" class="flex items-center p-4 border-2 border-primary text-primary rounded-lg hover:bg-primary hover:text-white transition-colors">
            <span class="material-icons-outlined mr-3">add_circle</span>
            <span class="font-bold">Create New Shipment</span>
        </a>
        <a href="/admin/manage-shipments.php" class="flex items-center p-4 border-2 border-secondary text-secondary rounded-lg hover:bg-secondary hover:text-white transition-colors">
            <span class="material-icons-outlined mr-3">list</span>
            <span class="font-bold">Manage Shipments</span>
        </a>
        <a href="/admin/settings.php" class="flex items-center p-4 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <span class="material-icons-outlined mr-3">settings</span>
            <span class="font-bold">Settings</span>
        </a>
    </div>
</div>

<!-- Recent Shipments -->
<div class="bg-white dark:bg-surface-dark rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Recent Shipments</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tracking Number</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (empty($recentShipments)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">No shipments yet. <a href="/admin/create-shipment.php" class="text-primary hover:underline">Create one</a></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentShipments as $shipment): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-primary"><?php echo htmlspecialchars($shipment['tracking_number']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($shipment['recipient_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($shipment['recipient_city'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?php echo getStatusBadgeClass($shipment['status']); ?>">
                                    <?php echo htmlspecialchars($shipment['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo formatDate($shipment['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="<?php echo htmlspecialchars(trackingResultUrl($shipment['tracking_number'])); ?>" target="_blank"
                                       class="inline-flex items-center px-3 py-1.5 rounded border border-primary text-primary hover:bg-primary hover:text-white transition-colors font-bold">
                                        View
                                    </a>
                                    <a href="/admin/manage-shipments.php?id=<?php echo $shipment['id']; ?>"
                                       class="inline-flex items-center px-3 py-1.5 rounded border border-secondary text-secondary hover:bg-secondary hover:text-white transition-colors font-bold">
                                        Edit
                                    </a>
                                    <form method="POST" action="/admin/delete-shipment.php"
                                          onsubmit="return confirm('Delete this shipment permanently? This will erase it from the database and delete all tracking events.');">
                                        <input type="hidden" name="id" value="<?php echo (int) $shipment['id']; ?>">
                                        <input type="hidden" name="return_to" value="/admin/dashboard.php">
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 rounded border border-red-600 text-red-600 hover:bg-red-600 hover:text-white transition-colors font-bold">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>

