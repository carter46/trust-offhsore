<?php
/**
 * Shared 3-dot shipment actions + quick status/location modal.
 */

function renderShipmentActionMenu($shipment, $options = []) {
    $id = (int) ($shipment['id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    $returnTo = $options['return_to'] ?? '/admin/dashboard.php';
    $showPdf = !empty($options['show_pdf']);
    $menuId = 'actions-' . $id;
    $status = (string) ($shipment['status'] ?? '');
    $tracking = (string) ($shipment['tracking_number'] ?? '');
    $latest = $options['latest_event'] ?? [];
    $lat = $latest['latitude'] ?? '';
    $lng = $latest['longitude'] ?? '';
    $hasCoords = hasUsableMapCoords($lat, $lng);
    $location = $hasCoords ? (string) ($latest['location'] ?? '') : '';
    $lat = $hasCoords ? (string) $lat : '';
    $lng = $hasCoords ? (string) $lng : '';

    $viewUrl = htmlspecialchars(trackingResultUrl($tracking));
    $editUrl = '/admin/manage-shipments.php?id=' . $id;
    $receiptUrl = htmlspecialchars(shipmentReceiptUrl($tracking));
    $pdfUrl = '/admin/view-shipment-pdf.php?id=' . $id;
    ?>
    <div class="admin-actions-wrap relative inline-block text-left">
        <button type="button"
                class="admin-actions-toggle inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                aria-haspopup="true"
                aria-expanded="false"
                aria-label="Shipment actions"
                data-menu-id="<?php echo htmlspecialchars($menuId); ?>">
            <span class="material-icons-outlined">more_vert</span>
        </button>
        <div id="<?php echo htmlspecialchars($menuId); ?>"
             class="admin-actions-menu hidden fixed z-[80] w-52 rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-surface-dark py-1"
             role="menu">
            <button type="button"
                    class="admin-quick-update-btn w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    data-id="<?php echo $id; ?>"
                    data-tracking="<?php echo htmlspecialchars($tracking); ?>"
                    data-status="<?php echo htmlspecialchars($status); ?>"
                    data-location="<?php echo htmlspecialchars($location); ?>"
                    data-lat="<?php echo htmlspecialchars((string) $lat); ?>"
                    data-lng="<?php echo htmlspecialchars((string) $lng); ?>"
                    data-return="<?php echo htmlspecialchars($returnTo); ?>">
                Change status
            </button>
            <a href="<?php echo $viewUrl; ?>" target="_blank" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" role="menuitem">View tracking</a>
            <a href="<?php echo $receiptUrl; ?>" target="_blank" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" role="menuitem">Print receipt</a>
            <a href="<?php echo htmlspecialchars($editUrl); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" role="menuitem">Edit shipment</a>
            <?php if ($showPdf): ?>
            <a href="<?php echo htmlspecialchars($pdfUrl); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" role="menuitem">View document</a>
            <?php endif; ?>
            <form method="POST" action="/admin/delete-shipment.php" class="border-t border-gray-100 dark:border-gray-700"
                  onsubmit="return confirm('Delete this shipment permanently? This will erase it from the database and delete all tracking events.');">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo); ?>">
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30" role="menuitem">Delete</button>
            </form>
        </div>
    </div>
    <?php
}

function renderShipmentQuickUpdateModal() {
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    $statusOptions = getShipmentStatusOptions();
    ?>
    <div id="quick-update-modal" class="fixed inset-0 z-[90] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/50" data-quick-update-close></div>
        <div class="relative mx-auto mt-16 w-full max-w-lg px-4">
            <div class="bg-white dark:bg-surface-dark rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Change status</h2>
                        <p id="quick-update-tracking" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                    </div>
                    <button type="button" class="text-gray-500 hover:text-gray-800 dark:hover:text-white" data-quick-update-close aria-label="Close">
                        <span class="material-icons-outlined">close</span>
                    </button>
                </div>
                <form method="POST" action="/admin/quick-update-shipment.php" id="quick-update-form">
                    <input type="hidden" name="id" id="quick-update-id">
                    <input type="hidden" name="return_to" id="quick-update-return">
                    <input type="hidden" name="latitude" id="quick-update-lat">
                    <input type="hidden" name="longitude" id="quick-update-lng">

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="quick-update-status">Status *</label>
                        <select id="quick-update-status" name="status" required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                            <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="quick-update-location">Current location</label>
                        <input type="text" id="quick-update-location" name="location"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="Search and select a location"
                               autocomplete="off">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select from Google suggestions so the tracking map can update.</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="quick-update-note">Note</label>
                        <textarea id="quick-update-note" name="note" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                                  placeholder="Why was this status changed? Shown in travel history."></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" class="px-4 py-2 rounded font-bold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700" data-quick-update-close>Cancel</button>
                        <button type="submit" class="px-6 py-2 rounded font-bold text-white bg-primary hover:bg-primary-dark">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const modal = document.getElementById('quick-update-modal');
        const form = document.getElementById('quick-update-form');
        if (!modal || !form) return;

        const locationInput = document.getElementById('quick-update-location');
        const latInput = document.getElementById('quick-update-lat');
        const lngInput = document.getElementById('quick-update-lng');
        let placesReady = false;
        let autocomplete = null;

        function closeMenus() {
            document.querySelectorAll('.admin-actions-menu').forEach(function (menu) {
                menu.classList.add('hidden');
            });
            document.querySelectorAll('.admin-actions-toggle').forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
        }

        function positionMenu(btn, menu) {
            const rect = btn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 4) + 'px';
            menu.style.left = Math.max(8, rect.right - 208) + 'px';
        }

        document.addEventListener('click', function (e) {
            const toggle = e.target.closest('.admin-actions-toggle');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                const menu = document.getElementById(toggle.getAttribute('data-menu-id'));
                const wasOpen = menu && !menu.classList.contains('hidden');
                closeMenus();
                if (menu && !wasOpen) {
                    positionMenu(toggle, menu);
                    menu.classList.remove('hidden');
                    toggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }
            if (!e.target.closest('.admin-actions-menu')) {
                closeMenus();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenus();
                closeModal();
            }
        });

        function openModal(data) {
            document.getElementById('quick-update-id').value = data.id || '';
            document.getElementById('quick-update-return').value = data.returnTo || '/admin/dashboard.php';
            const statusEl = document.getElementById('quick-update-status');
            const currentStatus = data.status || '';
            if (currentStatus) {
                const exists = Array.prototype.some.call(statusEl.options, function (opt) {
                    return opt.value === currentStatus;
                });
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = currentStatus;
                    opt.textContent = currentStatus;
                    statusEl.appendChild(opt);
                }
                statusEl.value = currentStatus;
            }
            document.getElementById('quick-update-tracking').textContent = data.tracking ? ('Tracking ' + data.tracking) : '';
            document.getElementById('quick-update-note').value = '';
            locationInput.value = data.location || '';
            latInput.value = data.lat || '';
            lngInput.value = data.lng || '';
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            ensurePlaces();
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-quick-update-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.admin-quick-update-btn');
            if (!btn) return;
            e.preventDefault();
            closeMenus();
            openModal({
                id: btn.getAttribute('data-id'),
                tracking: btn.getAttribute('data-tracking'),
                status: btn.getAttribute('data-status'),
                location: btn.getAttribute('data-location'),
                lat: btn.getAttribute('data-lat'),
                lng: btn.getAttribute('data-lng'),
                returnTo: btn.getAttribute('data-return')
            });
        });

        locationInput.addEventListener('input', function () {
            latInput.value = '';
            lngInput.value = '';
        });

        form.addEventListener('submit', function (e) {
            if (locationInput.value.trim() && (!latInput.value || !lngInput.value)) {
                e.preventDefault();
                alert('Please select a location from the suggestions so latitude and longitude can be saved.');
            }
        });

        function bindAutocomplete() {
            if (!window.google || !google.maps || !google.maps.places || autocomplete) return;
            autocomplete = new google.maps.places.Autocomplete(locationInput, {
                fields: ['formatted_address', 'name', 'geometry']
            });
            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place || !place.geometry) return;
                latInput.value = place.geometry.location.lat();
                lngInput.value = place.geometry.location.lng();
                locationInput.value = place.formatted_address || place.name || locationInput.value;
            });
            placesReady = true;
        }

        function ensurePlaces() {
            if (placesReady) {
                bindAutocomplete();
                return;
            }
            fetch('/api/settings.php?key=google_maps_api_key')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const apiKey = data && data.value;
                    if (!apiKey) return;
                    if (window.google && google.maps && google.maps.places) {
                        bindAutocomplete();
                        return;
                    }
                    const existing = document.querySelector('script[data-admin-places], script[src*="maps.googleapis.com"]');
                    if (existing) {
                        if (window.google && google.maps && google.maps.places) {
                            bindAutocomplete();
                            return;
                        }
                        existing.addEventListener('load', bindAutocomplete);
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&libraries=places';
                    script.async = true;
                    script.defer = true;
                    script.setAttribute('data-admin-places', '1');
                    script.onload = bindAutocomplete;
                    document.head.appendChild(script);
                })
                .catch(function () {});
        }
    })();
    </script>
    <?php
}

function renderAdminFlashMessages() {
    if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
        <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
            Shipment update saved. Public tracking will use the latest status and location.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['email']) && $_GET['email'] === 'sent'): ?>
        <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
            Status-update email sent to the recipient.
        </div>
    <?php elseif (isset($_GET['email']) && $_GET['email'] === 'failed'): ?>
        <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
            The shipment was updated, but the recipient email could not be sent. Check SMTP settings.
        </div>
    <?php elseif (isset($_GET['email']) && $_GET['email'] === 'missing-recipient-email'): ?>
        <div class="bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-4 py-3 rounded mb-6">
            The shipment was updated, but no recipient email address is saved for this shipment.
        </div>
    <?php endif;
}
