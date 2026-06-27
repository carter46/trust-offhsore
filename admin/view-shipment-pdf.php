<?php
/**
 * View/Download Shipment PDF
 * View and download PDF document for a shipment
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';

$shipmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$shipmentId) {
    header('Location: /admin/manage-shipments.php?error=' . urlencode('Shipment ID is required'));
    exit;
}

// Get shipment data
$stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
$stmt->bind_param("i", $shipmentId);
$stmt->execute();
$result = $stmt->get_result();
$shipment = $result->fetch_assoc();
$stmt->close();

if (!$shipment) {
    header('Location: /admin/manage-shipments.php?error=' . urlencode('Shipment not found'));
    exit;
}

// Viewer mode: render admin chrome + embed the document via iframe.
// Download headers are handled by /api/generate-shipment-pdf.php to avoid "headers already sent".
include __DIR__ . '/includes/admin-header.php';

$docUrl = '/api/generate-shipment-pdf.php?id=' . $shipmentId;
$downloadUrl = $docUrl . '&download=1';
?>

<div class="mb-8 no-print">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Shipment Document</h1>
            <p class="text-gray-600 dark:text-gray-400">Tracking #<?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tip: Print → “Save as PDF” to get an actual PDF file.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" id="btnPrintDoc"
                    class="flex items-center justify-center gap-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-bold py-2 px-4 rounded transition-colors">
                <span class="material-icons-outlined">print</span>
                <span class="hidden sm:inline">Print</span>
            </button>
            <a href="<?php echo htmlspecialchars($downloadUrl); ?>"
               class="flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded transition-colors shadow-sm">
                <span class="material-icons-outlined">download</span>
                <span>Download</span>
            </a>
            <a href="<?php echo htmlspecialchars($docUrl); ?>" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 font-bold py-2 px-4 rounded transition-colors">
                <span class="material-icons-outlined">open_in_new</span>
                <span>Open</span>
            </a>
            <button type="button" id="btnCopyLink"
                    class="flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 font-bold py-2 px-4 rounded transition-colors">
                <span class="material-icons-outlined">share</span>
                <span>Copy Link</span>
            </button>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-surface-dark rounded-lg shadow overflow-hidden">
    <iframe id="shipmentDocFrame"
            src="<?php echo htmlspecialchars($docUrl); ?>"
            class="w-full"
            style="height: 1000px; border: 0; background: #fff;"
            title="Shipment Document"></iframe>
</div>

<script>
(function () {
  const frame = document.getElementById('shipmentDocFrame');
  const btnPrint = document.getElementById('btnPrintDoc');
  const btnCopy = document.getElementById('btnCopyLink');
  const docUrl = <?php echo json_encode($docUrl); ?>;

  if (btnPrint) {
    btnPrint.addEventListener('click', function () {
      try {
        if (frame && frame.contentWindow) frame.contentWindow.print();
        else window.open(docUrl, '_blank')?.print();
      } catch (e) {
        window.open(docUrl, '_blank');
      }
    });
  }

  if (btnCopy && navigator.clipboard) {
    btnCopy.addEventListener('click', async function () {
      try {
        const full = window.location.origin + docUrl;
        await navigator.clipboard.writeText(full);
        const label = btnCopy.querySelector('span:last-child');
        if (label) {
          const old = label.textContent;
          label.textContent = 'Copied';
          setTimeout(() => (label.textContent = old), 1200);
        }
      } catch (e) {
        alert('Could not copy link. You can copy it from the Open button.');
      }
    });
  }
})();
</script>

<style>
@media print { .no-print { display: none !important; } }
</style>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

