/**
 * Google Maps Animation
 * Route: Pickup → Current Location → Delivery, with labeled endpoints.
 * Always draws a straight geodesic line when driving directions are unavailable
 * (e.g. UK → US), so the shipment route is visible at all times.
 */

let map;
let directionsService;
let directionsRenderer;
let truckMarker;
let routePolyline;
let endpointOverlays = [];

function toNumber(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = typeof value === 'number' ? value : parseFloat(value);
    return Number.isFinite(n) ? n : null;
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function getRouteEndpointLabels() {
    const fb = window.__shipmentRouteFallback || {};
    return {
        pickup: (fb.pickup && fb.pickup.name) ? String(fb.pickup.name) : 'Pickup Address',
        dropoff: (fb.dropoff && fb.dropoff.name) ? String(fb.dropoff.name) : 'Delivery Address'
    };
}

function getFallbackEndpoints() {
    const fb = window.__shipmentRouteFallback || {};
    const pickupLat = toNumber(fb?.pickup?.lat);
    const pickupLng = toNumber(fb?.pickup?.lng);
    const dropoffLat = toNumber(fb?.dropoff?.lat);
    const dropoffLng = toNumber(fb?.dropoff?.lng);
    const labels = getRouteEndpointLabels();

    if (pickupLat === null || pickupLng === null || dropoffLat === null || dropoffLng === null) {
        return null;
    }
    // Reject 0,0 ghost points
    if (pickupLat === 0 && pickupLng === 0) return null;
    if (dropoffLat === 0 && dropoffLng === 0) return null;

    return {
        pickup: { lat: pickupLat, lng: pickupLng, name: labels.pickup },
        dropoff: { lat: dropoffLat, lng: dropoffLng, name: labels.dropoff }
    };
}

function geocodeAddress(query) {
    return new Promise((resolve) => {
        if (!query || !window.google || !google.maps || !google.maps.Geocoder) {
            resolve(null);
            return;
        }
        const trimmed = String(query).trim();
        if (trimmed.length < 2 || trimmed === 'Pickup Address' || trimmed === 'Delivery Address') {
            resolve(null);
            return;
        }
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ address: trimmed }, (results, status) => {
            if (status === 'OK' && results && results[0] && results[0].geometry) {
                resolve({
                    lat: results[0].geometry.location.lat(),
                    lng: results[0].geometry.location.lng(),
                    name: results[0].formatted_address || trimmed
                });
            } else {
                resolve(null);
            }
        });
    });
}

/** Try full address → city+country → country so a line can always be drawn. */
async function geocodeWithFallbacks(queries) {
    const seen = new Set();
    for (const raw of queries) {
        const q = String(raw || '').trim();
        if (!q || seen.has(q.toLowerCase())) continue;
        seen.add(q.toLowerCase());
        const geo = await geocodeAddress(q);
        if (geo) return geo;
    }
    return null;
}

function endpointGeocodeQueries(side, displayLabel) {
    const fb = window.__shipmentRouteFallback || {};
    const ep = (fb && fb[side]) || {};
    const city = String(ep.city || '').trim();
    const country = String(ep.country || '').trim();
    const cityCountry = [city, country].filter(Boolean).join(', ');
    return [
        ep.query,
        ep.name,
        displayLabel,
        cityCountry,
        city,
        country
    ];
}

/**
 * Ensure we have pickup/dropoff coords. If lat/lng missing, geocode the label
 * (city name, country, or full address) so a route can always be drawn.
 * Display labels stay as the entered city/address — only coordinates are resolved.
 */
async function resolveFallbackEndpoints() {
    let fallback = getFallbackEndpoints();
    if (fallback) return fallback;

    const labels = getRouteEndpointLabels();
    const fb = window.__shipmentRouteFallback || {};

    let pickupLat = toNumber(fb?.pickup?.lat);
    let pickupLng = toNumber(fb?.pickup?.lng);
    let dropoffLat = toNumber(fb?.dropoff?.lat);
    let dropoffLng = toNumber(fb?.dropoff?.lng);

    const pickupName = labels.pickup;
    const dropoffName = labels.dropoff;

    if (pickupLat === null || pickupLng === null || (pickupLat === 0 && pickupLng === 0)) {
        const geo = await geocodeWithFallbacks(endpointGeocodeQueries('pickup', pickupName));
        if (geo) {
            pickupLat = geo.lat;
            pickupLng = geo.lng;
        }
    }
    if (dropoffLat === null || dropoffLng === null || (dropoffLat === 0 && dropoffLng === 0)) {
        const geo = await geocodeWithFallbacks(endpointGeocodeQueries('dropoff', dropoffName));
        if (geo) {
            dropoffLat = geo.lat;
            dropoffLng = geo.lng;
        }
    }

    if (pickupLat === null || pickupLng === null || dropoffLat === null || dropoffLng === null) {
        return null;
    }

    return {
        pickup: { lat: pickupLat, lng: pickupLng, name: pickupName },
        dropoff: { lat: dropoffLat, lng: dropoffLng, name: dropoffName }
    };
}

/** Latest tracking event that has coordinates = current package position. */
function getCurrentLocationFromEvents(events) {
    if (!Array.isArray(events) || events.length === 0) return null;

    for (let i = 0; i < events.length; i++) {
        const e = events[i];
        const lat = toNumber(e.latitude);
        const lng = toNumber(e.longitude);
        if (lat === null || lng === null) continue;
        if (lat === 0 && lng === 0) continue;

        const name =
            (e.location && String(e.location).trim()) ||
            (e.description && String(e.description).trim()) ||
            (e.event_type && String(e.event_type).trim()) ||
            'Current Location';

        return { lat: lat, lng: lng, name: name, event: e };
    }

    return null;
}

function clearEndpointOverlays() {
    endpointOverlays.forEach((overlay) => {
        try {
            overlay.setMap(null);
        } catch (e) {}
    });
    endpointOverlays = [];
}

function addEndpointAddressLabel(mapInstance, position, heading, address, variant) {
    class AddressLabelOverlay extends google.maps.OverlayView {
        constructor(pos, head, addr, variantName) {
            super();
            this.position = pos;
            this.heading = head;
            this.address = addr;
            this.variant = variantName || '';
            this.div = null;
        }

        onAdd() {
            const div = document.createElement('div');
            div.className =
                'map-endpoint-address-label' +
                (this.variant ? ' map-endpoint-address-label--' + this.variant : '');
            div.innerHTML =
                '<div class="map-endpoint-address-label__inner">' +
                '<strong>' + escapeHtml(this.heading) + '</strong>' +
                '<span>' + escapeHtml(this.address) + '</span>' +
                '</div>';
            this.div = div;
            this.getPanes().floatPane.appendChild(div);
        }

        draw() {
            if (!this.div) return;
            const projection = this.getProjection();
            if (!projection) return;
            const point = projection.fromLatLngToDivPixel(
                new google.maps.LatLng(this.position.lat, this.position.lng)
            );
            if (!point) return;
            this.div.style.left = point.x + 'px';
            this.div.style.top = point.y + 'px';
        }

        onRemove() {
            if (this.div && this.div.parentNode) {
                this.div.parentNode.removeChild(this.div);
            }
            this.div = null;
        }
    }

    const overlay = new AddressLabelOverlay(position, heading, address, variant);
    overlay.setMap(mapInstance);
    endpointOverlays.push(overlay);
    return overlay;
}

function placeCircleMarker(mapInstance, position, fillColor, title, zIndex) {
    return new google.maps.Marker({
        position: position,
        map: mapInstance,
        title: title,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 8,
            fillColor: fillColor,
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2
        },
        zIndex: zIndex || 10
    });
}

function placeTruckMarker(mapInstance, position, title) {
    return new google.maps.Marker({
        position: position,
        map: mapInstance,
        title: title || 'Current Location',
        icon: {
            path: 'M 0,0 C -2,-20 -10,-22 -10,-30 A 10,10 0 1,1 10,-30 C 10,-22 2,-20 0,0 z M -12,-30 L -12,-40 L 12,-40 L 12,-30',
            fillColor: '#FF6200',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2,
            scale: 1.5,
            anchor: new google.maps.Point(0, -20)
        },
        zIndex: 30
    });
}

function placeRouteLabels(mapInstance, pickup, dropoff, current) {
    clearEndpointOverlays();

    placeCircleMarker(mapInstance, pickup, '#4D148C', pickup.name || 'Pickup', 10);
    addEndpointAddressLabel(mapInstance, pickup, 'Pickup', pickup.name || 'Pickup Address');

    placeCircleMarker(mapInstance, dropoff, '#FF6200', dropoff.name || 'Delivery', 10);
    addEndpointAddressLabel(mapInstance, dropoff, 'Delivery', dropoff.name || 'Delivery Address');

    if (current) {
        truckMarker = placeTruckMarker(mapInstance, current, current.name);
        addEndpointAddressLabel(
            mapInstance,
            current,
            'Current Location',
            current.name,
            'current'
        );
    }
}

function drawStraightMultiPoint(mapInstance, points) {
    if (directionsRenderer) {
        directionsRenderer.setMap(null);
    }
    if (routePolyline) {
        routePolyline.setMap(null);
    }

    const path = points.map((p) => ({ lat: p.lat, lng: p.lng }));
    routePolyline = new google.maps.Polyline({
        path: path,
        geodesic: true,
        strokeColor: '#4D148C',
        strokeOpacity: 0.9,
        strokeWeight: 5
    });
    routePolyline.setMap(mapInstance);

    const bounds = new google.maps.LatLngBounds();
    path.forEach((p) => bounds.extend(p));
    mapInstance.fitBounds(bounds);
}

function animateTruckToCurrent(path, current) {
    if (!path || path.length === 0 || !truckMarker || !current) return;

    let closestIndex = 0;
    let closestDist = Infinity;
    path.forEach((point, index) => {
        const lat = typeof point.lat === 'function' ? point.lat() : point.lat;
        const lng = typeof point.lng === 'function' ? point.lng() : point.lng;
        const d = Math.pow(lat - current.lat, 2) + Math.pow(lng - current.lng, 2);
        if (d < closestDist) {
            closestDist = d;
            closestIndex = index;
        }
    });

    const targetIndex = Math.max(1, closestIndex);
    let step = 0;
    const animationSpeed = 40;

    function moveTruck() {
        if (step < targetIndex) {
            truckMarker.setPosition(path[step]);
            step++;
            setTimeout(moveTruck, animationSpeed);
        } else {
            truckMarker.setPosition({ lat: current.lat, lng: current.lng });
        }
    }

    moveTruck();
}

async function initMap() {
    try {
        const response = await fetch('/api/settings.php?key=google_maps_api_key');
        const data = await response.json();
        const apiKey = data.value;

        if (!apiKey) {
            document.getElementById('map-container').innerHTML =
                '<div class="p-8 text-center text-gray-500">Google Maps API key not configured. Please add it in <a href="/admin/settings.php" class="text-primary hover:underline">Admin Settings</a>.</div>';
            return;
        }

        if (!window.google) {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=geometry,places`;
            script.async = true;
            script.defer = true;
            script.onload = () => setupMap();
            document.head.appendChild(script);
        } else {
            setupMap();
        }
    } catch (error) {
        console.error('Error loading map:', error);
        document.getElementById('map-container').innerHTML =
            '<div class="p-8 text-center text-red-500">Error loading map. Please check your API key.</div>';
    }
}

function setupMap() {
    const urlParams = new URLSearchParams(window.location.search);
    const trackingId = urlParams.get('id');
    if (!trackingId) return;

    fetch(`/api/tracking.php?id=${encodeURIComponent(trackingId)}`)
        .then((response) => response.json())
        .then(async (data) => {
            const labels = getRouteEndpointLabels();
            const events = (data.success && Array.isArray(data.events) ? data.events : [])
                .map((e) => ({
                    ...e,
                    latitude: toNumber(e.latitude),
                    longitude: toNumber(e.longitude)
                }));
            const eventsWithCoords = events.filter(
                (e) => e.latitude !== null && e.longitude !== null && !(e.latitude === 0 && e.longitude === 0)
            );
            const current = getCurrentLocationFromEvents(eventsWithCoords);

            const fallback = await resolveFallbackEndpoints();

            if (fallback) {
                renderShipmentRoute(fallback, labels, current);
                return;
            }

            if (eventsWithCoords.length >= 2) {
                renderEventOnlyRoute(eventsWithCoords, labels, current);
                return;
            }

            showSimpleMap(eventsWithCoords, current);
        })
        .catch((error) => {
            console.error('Error fetching tracking data:', error);
        });
}

/**
 * Main route: Pickup → (Current Location waypoint) → Delivery.
 * On overseas / ZERO_RESULTS, falls back to a labeled straight line.
 */
function renderShipmentRoute(fallback, labels, current) {
    const pickup = {
        lat: fallback.pickup.lat,
        lng: fallback.pickup.lng,
        name: fallback.pickup.name || labels.pickup
    };
    const dropoff = {
        lat: fallback.dropoff.lat,
        lng: fallback.dropoff.lng,
        name: fallback.dropoff.name || labels.dropoff
    };

    map = new google.maps.Map(document.getElementById('map-container'), {
        zoom: 6,
        center: current
            ? { lat: current.lat, lng: current.lng }
            : { lat: pickup.lat, lng: pickup.lng },
        mapTypeId: 'roadmap'
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#4D148C',
            strokeWeight: 5,
            strokeOpacity: 0.8
        }
    });

    const request = {
        origin: { lat: pickup.lat, lng: pickup.lng },
        destination: { lat: dropoff.lat, lng: dropoff.lng },
        travelMode: google.maps.TravelMode.DRIVING
    };

    if (current) {
        request.waypoints = [
            {
                location: { lat: current.lat, lng: current.lng },
                stopover: true
            }
        ];
        request.optimizeWaypoints = false;
    }

    directionsService.route(request, (result, status) => {
        placeRouteLabels(map, pickup, dropoff, current);

        if (status === 'OK') {
            directionsRenderer.setDirections(result);
            const path = result.routes[0].overview_path;

            const bounds = new google.maps.LatLngBounds();
            path.forEach((point) => bounds.extend(point));
            if (current) bounds.extend({ lat: current.lat, lng: current.lng });
            map.fitBounds(bounds);

            if (current && truckMarker) {
                animateTruckToCurrent(path, current);
            }
        } else {
            // Always show a route line between cities/countries when driving directions fail
            const points = current ? [pickup, current, dropoff] : [pickup, dropoff];
            drawStraightMultiPoint(map, points);
        }
    });
}

function renderEventOnlyRoute(events, labels, current) {
    map = new google.maps.Map(document.getElementById('map-container'), {
        zoom: 6,
        center: { lat: events[0].latitude, lng: events[0].longitude },
        mapTypeId: 'roadmap'
    });

    // events from API are typically newest-first
    const origin = {
        lat: events[events.length - 1].latitude,
        lng: events[events.length - 1].longitude,
        name: labels.pickup
    };
    const destination = {
        lat: events[0].latitude,
        lng: events[0].longitude,
        name: labels.dropoff
    };

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#4D148C',
            strokeWeight: 5,
            strokeOpacity: 0.8
        }
    });

    const waypoints = events.slice(1, -1).map((e) => ({
        location: { lat: e.latitude, lng: e.longitude },
        stopover: true
    }));

    directionsService.route(
        {
            origin: { lat: origin.lat, lng: origin.lng },
            destination: { lat: destination.lat, lng: destination.lng },
            waypoints: waypoints,
            travelMode: google.maps.TravelMode.DRIVING
        },
        (result, status) => {
            placeRouteLabels(map, origin, destination, current);
            if (status === 'OK') {
                directionsRenderer.setDirections(result);
                const path = result.routes[0].overview_path;
                const bounds = new google.maps.LatLngBounds();
                path.forEach((point) => bounds.extend(point));
                map.fitBounds(bounds);
                if (current && truckMarker) {
                    animateTruckToCurrent(path, current);
                }
            } else {
                drawStraightMultiPoint(
                    map,
                    current ? [origin, current, destination] : [origin, destination]
                );
            }
        }
    );
}

function showSimpleMap(events, current) {
    const fallback = getFallbackEndpoints();
    const labels = getRouteEndpointLabels();
    const center = current
        ? { lat: current.lat, lng: current.lng }
        : events.length > 0
          ? { lat: events[0].latitude, lng: events[0].longitude }
          : fallback
            ? { lat: fallback.pickup.lat, lng: fallback.pickup.lng }
            : { lat: 39.8283, lng: -98.5795 };

    map = new google.maps.Map(document.getElementById('map-container'), {
        zoom: 6,
        center: center,
        mapTypeId: 'roadmap'
    });

    if (fallback) {
        const pickup = {
            lat: fallback.pickup.lat,
            lng: fallback.pickup.lng,
            name: labels.pickup
        };
        const dropoff = {
            lat: fallback.dropoff.lat,
            lng: fallback.dropoff.lng,
            name: labels.dropoff
        };
        placeRouteLabels(map, pickup, dropoff, current);
        drawStraightMultiPoint(
            map,
            current ? [pickup, current, dropoff] : [pickup, dropoff]
        );
        return;
    }

    if (current) {
        truckMarker = placeTruckMarker(map, current, current.name);
        addEndpointAddressLabel(map, current, 'Current Location', current.name, 'current');
        map.setCenter({ lat: current.lat, lng: current.lng });
        map.setZoom(10);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
} else {
    initMap();
}
