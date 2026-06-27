<?php
$trackingId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!empty($trackingId)) {
    header('Location: ' . trackingResultUrl($trackingId));
    exit;
}

include __DIR__ . '/includes/header.php';
$companyName = getSetting('company_name', 'FedEx');
?>

<section class="page-hero-bg text-white py-12 lg:py-16">
    <div class="container mx-auto px-4 md:px-12 text-center">
        <div class="w-16 h-1 bg-yellow-400 mx-auto mb-4"></div>
        <h1 class="text-white text-4xl lg:text-5xl font-extrabold mb-4 tracking-tight">
            Track your <?php echo htmlspecialchars($companyName); ?> shipments
        </h1>
        <p class="text-gray-200 text-lg max-w-2xl mx-auto">
            Enter your tracking numbers (one per line) to get real-time shipment updates.
        </p>
    </div>
</section>

<section class="py-16 px-4 lg:px-10 dot-pattern dot-pattern-gray">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-sm shadow-2xl border-t-4 border-yellow-400 overflow-hidden">
            <div class="bg-slate-900 text-white px-6 py-4">
                <h2 class="text-lg font-bold uppercase tracking-widest border-l-4 border-yellow-400 pl-3">Track Shipment</h2>
            </div>
            <div class="p-8">
                <form id="track-form" class="max-w-2xl mx-auto">
                    <label class="block text-sm font-bold text-slate-800 mb-2" for="tracking-number">
                        Tracking number*
                    </label>
                    <textarea class="w-full border border-gray-300 rounded p-4 focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 bg-white text-slate-800 resize-none shadow-sm"
                              id="tracking-number"
                              name="tracking-number"
                              rows="4"
                              placeholder="Enter tracking numbers (one per line)"
                              required><?php echo htmlspecialchars($trackingId); ?></textarea>
                    <a class="inline-block mt-3 text-sm font-bold text-yellow-600 hover:underline uppercase tracking-wide" href="/faq">
                        NEED HELP?
                    </a>
                    <div class="mt-8 text-center">
                        <button type="submit" id="track-button" class="bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-10 rounded text-base shadow-lg transition-all uppercase tracking-widest">
                            Track Now
                        </button>
                    </div>
                </form>
                <div id="error-message" class="mt-4 text-red-600 hidden text-center"></div>
            </div>
        </div>
    </div>
</section>

<script src="/js/tracking.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
