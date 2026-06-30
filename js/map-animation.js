/**
 * Google Maps Animation
 * Animated route and moving truck marker
 */

let map;
let directionsService;
let directionsRenderer;
let truckMarker;
let routePolyline;

function toNumber(value) {
    if (value === null || value === undefined) return null;
    const n = typeof value === 'number' ? value : parseFloat(value);
    return Number.isFinite(n) ? n : null;
}

function getFallbackEndpoints() {
    const fb = window.__shipmentRouteFallback || {};
    const pickupLat = toNumber(fb?.pickup?.lat);
    const pickupLng = toNumber(fb?.pickup?.lng);
    const dropoffLat = toNumber(fb?.dropoff?.lat);
    const dropoffLng = toNumber(fb?.dropoff?.lng);

    if (pickupLat === null || pickupLng === null || dropoffLat === null || dropoffLng === null) {
        return null;
    }

    return {
        pickup: { lat: pickupLat, lng: pickupLng, name: fb?.pickup?.name || 'Pickup' },
        dropoff: { lat: dropoffLat, lng: dropoffLng, name: fb?.dropoff?.name || 'Dropoff' }
    };
}

function sameCoord(a, b) {
    return Math.abs(a.lat - b.lat) < 0.0001 && Math.abs(a.lng - b.lng) < 0.0001;
}

function getEndpointsFromShipment(shipment) {
    const fb = getFallbackEndpoints() || {};
    const pickupLat = toNumber(shipment?.pickup_latitude) ?? toNumber(fb?.pickup?.lat);
    const pickupLng = toNumber(shipment?.pickup_longitude) ?? toNumber(fb?.pickup?.lng);
    const dropoffLat = toNumber(shipment?.dropoff_latitude) ?? toNumber(fb?.dropoff?.lat);
    const dropoffLng = toNumber(shipment?.dropoff_longitude) ?? toNumber(fb?.dropoff?.lng);

    return {
        pickup: {
            lat: pickupLat,
            lng: pickupLng,
            name: shipment?.pickup_location || fb?.pickup?.name || 'Pickup'
        },
        dropoff: {
            lat: dropoffLat,
            lng: dropoffLng,
            name: shipment?.dropoff_location || fb?.dropoff?.name || 'Dropoff'
        }
    };
}

function getGeoTrackingEvents(events) {
    return (events || [])
        .filter((e) => (e.event_type || '') !== 'Admin Note')
        .map((e) => ({
            lat: toNumber(e.latitude),
            lng: toNumber(e.longitude),
            name: e.location || e.description || 'Stop',
            event_date: e.event_date
        }))
        .filter((e) => e.lat !== null && e.lng !== null)
        .sort((a, b) => new Date(a.event_date) - new Date(b.event_date));
}

function buildRoutePoints(shipment, events) {
    const endpoints = getEndpointsFromShipment(shipment);
    const geoEvents = getGeoTrackingEvents(events);
    const points = [];

    const push = (lat, lng, name) => {
        if (lat === null || lng === null) return;
        const last = points[points.length - 1];
        if (last && sameCoord(last, { lat, lng })) return;
        points.push({ lat, lng, name: name || 'Location' });
    };

    push(endpoints.pickup.lat, endpoints.pickup.lng, endpoints.pickup.name);
    geoEvents.forEach((e) => push(e.lat, e.lng, e.name));
    push(endpoints.dropoff.lat, endpoints.dropoff.lng, endpoints.dropoff.name);

    return { points, geoEvents, endpoints };
}

function clearRouteLayers() {
    if (directionsRenderer) {
        directionsRenderer.setMap(null);
    }
    if (routePolyline) {
        routePolyline.setMap(null);
        routePolyline = null;
    }
}

function drawStraightRoute(mapInstance, points, options = {}) {
    clearRouteLayers();

    if (!points.length) return;

    const origin = points[0];
    const destination = points[points.length - 1];

    new google.maps.Marker({
        position: { lat: origin.lat, lng: origin.lng },
        map: mapInstance,
        title: options.originTitle || origin.name || 'Origin',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 8,
            fillColor: '#4D148C',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2
        }
    });

    if (points.length > 1) {
        new google.maps.Marker({
            position: { lat: destination.lat, lng: destination.lng },
            map: mapInstance,
            title: options.destinationTitle || destination.name || 'Destination',
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: '#FF6200',
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2
            }
        });

        routePolyline = new google.maps.Polyline({
            path: points.map((p) => ({ lat: p.lat, lng: p.lng })),
            geodesic: true,
            strokeColor: '#4D148C',
            strokeOpacity: 0.9,
            strokeWeight: 5
        });
        routePolyline.setMap(mapInstance);
    }

    const bounds = new google.maps.LatLngBounds();
    points.forEach((p) => bounds.extend({ lat: p.lat, lng: p.lng }));
    mapInstance.fitBounds(bounds);
}

function placeTruckMarker(mapInstance, position, title) {
    if (!position) return;

    if (truckMarker) {
        truckMarker.setMap(null);
    }

    truckMarker = new google.maps.Marker({
        position: { lat: position.lat, lng: position.lng },
        map: mapInstance,
        icon: {
            path: 'M 0,0 C -2,-20 -10,-22 -10,-30 A 10,10 0 1,1 10,-30 C 10,-22 2,-20 0,0 z M -12,-30 L -12,-40 L 12,-40 L 12,-30',
            fillColor: '#FF6200',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2,
            scale: 1.5,
            anchor: new google.maps.Point(0, -20)
        },
        title: title || 'Current Location'
    });
}

function renderShipmentMap(shipment, events) {
    const { points, geoEvents } = buildRoutePoints(shipment, events);
    const currentPosition = geoEvents.length
        ? geoEvents[geoEvents.length - 1]
        : (points.length ? points[points.length - 1] : null);

    const center = currentPosition
        ? { lat: currentPosition.lat, lng: currentPosition.lng }
        : (points.length ? { lat: points[0].lat, lng: points[0].lng } : { lat: 39.8283, lng: -98.5795 });

    map = new google.maps.Map(document.getElementById('map-container'), {
        zoom: 6,
        center,
        mapTypeId: 'roadmap'
    });

    if (points.length < 2) {
        if (points.length === 1) {
            drawStraightRoute(map, points);
            placeTruckMarker(map, points[0], points[0].name);
        }
        return;
    }

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#4D148C',
            strokeWeight: 5,
            strokeOpacity: 0.8
        }
    });

    const origin = points[0];
    const destination = points[points.length - 1];
    const waypoints = points.slice(1, -1).map((p) => ({
        location: { lat: p.lat, lng: p.lng },
        stopover: true
    }));

    directionsService.route({
        origin: { lat: origin.lat, lng: origin.lng },
        destination: { lat: destination.lat, lng: destination.lng },
        waypoints,
        travelMode: google.maps.TravelMode.DRIVING
    }, (result, status) => {
        if (status === 'OK') {
            directionsRenderer.setDirections(result);

            new google.maps.Marker({
                position: { lat: origin.lat, lng: origin.lng },
                map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: '#4D148C',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2
                },
                title: origin.name || 'Origin'
            });

            new google.maps.Marker({
                position: { lat: destination.lat, lng: destination.lng },
                map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: '#FF6200',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2
                },
                title: destination.name || 'Destination'
            });

            const path = result.routes[0].overview_path;
            placeTruckMarker(map, currentPosition, currentPosition?.name || 'Current Location');
            animateTruck(path);

            const bounds = new google.maps.LatLngBounds();
            path.forEach((point) => bounds.extend(point));
            map.fitBounds(bounds);
        } else {
            drawStraightRoute(map, points, {
                originTitle: origin.name,
                destinationTitle: destination.name
            });
            placeTruckMarker(map, currentPosition, currentPosition?.name || 'Current Location');
        }
    });
}

/**
 * Initialize map with Google Maps API
 */
async function initMap() {
    try {
        const response = await fetch('/api/settings.php?key=google_maps_api_key');
        const data = await response.json();
        const apiKey = data.value;

        if (!apiKey) {
            document.getElementById('map-container').innerHTML = '<div class="p-8 text-center text-gray-500">Google Maps API key not configured. Please add it in <a href="/admin/settings.php" class="text-primary hover:underline">Admin Settings</a>.</div>';
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
        document.getElementById('map-container').innerHTML = '<div class="p-8 text-center text-red-500">Error loading map. Please check your API key.</div>';
    }
}

/**
 * Setup map with route from pickup, tracking events, and dropoff
 */
function setupMap() {
    const urlParams = new URLSearchParams(window.location.search);
    const trackingId = urlParams.get('id');

    if (!trackingId) {
        return;
    }

    fetch(`/api/tracking.php?id=${encodeURIComponent(trackingId)}`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) return;
            renderShipmentMap(data.shipment || {}, data.events || []);
        })
        .catch((error) => {
            console.error('Error fetching tracking data:', error);
        });
}

/**
 * Animate truck along route
 */
function animateTruck(path) {
    if (!path || path.length === 0 || !truckMarker) return;

    let step = 0;
    const totalSteps = path.length;
    const animationSpeed = 50;

    function moveTruck() {
        if (step < totalSteps) {
            truckMarker.setPosition(path[step]);
            step++;
            setTimeout(moveTruck, animationSpeed);
        } else {
            step = 0;
            setTimeout(moveTruck, 1000);
        }
    }

    moveTruck();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
} else {
    initMap();
}
