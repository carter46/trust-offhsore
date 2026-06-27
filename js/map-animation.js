/**
 * Google Maps Animation
 * Animated route and moving truck marker
 */

let map;
let directionsService;
let directionsRenderer;
let truckMarker;
let routePolyline;
let animationPath = [];
let currentStep = 0;

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

function drawStraightLine(mapInstance, origin, destination, options = {}) {
    // Clear any previous renderer/polyline
    if (directionsRenderer) {
        directionsRenderer.setMap(null);
    }
    if (routePolyline) {
        routePolyline.setMap(null);
    }

    const originPos = { lat: origin.lat, lng: origin.lng };
    const destPos = { lat: destination.lat, lng: destination.lng };

    new google.maps.Marker({
        position: originPos,
        map: mapInstance,
        title: options.originTitle || 'Origin',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 8,
            fillColor: '#4D148C',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2
        }
    });

    new google.maps.Marker({
        position: destPos,
        map: mapInstance,
        title: options.destinationTitle || 'Destination',
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
        path: [originPos, destPos],
        geodesic: true,
        strokeColor: '#4D148C',
        strokeOpacity: 0.9,
        strokeWeight: 5
    });
    routePolyline.setMap(mapInstance);

    const bounds = new google.maps.LatLngBounds();
    bounds.extend(originPos);
    bounds.extend(destPos);
    mapInstance.fitBounds(bounds);
}

/**
 * Initialize map with Google Maps API
 */
async function initMap() {
    // Get API key from settings
    try {
        const response = await fetch('/api/settings.php?key=google_maps_api_key');
        const data = await response.json();
        const apiKey = data.value;
        
        if (!apiKey) {
            document.getElementById('map-container').innerHTML = '<div class="p-8 text-center text-gray-500">Google Maps API key not configured. Please add it in <a href="/admin/settings.php" class="text-primary hover:underline">Admin Settings</a>.</div>';
            return;
        }
        
        // Load Google Maps script
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
 * Setup map with route
 */
function setupMap() {
    // Get tracking data
    const urlParams = new URLSearchParams(window.location.search);
    const trackingId = urlParams.get('id');
    
    if (!trackingId) {
        return;
    }
    
    // Fetch tracking data
    fetch(`/api/tracking.php?id=${encodeURIComponent(trackingId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.events && data.events.length > 0) {
                const events = data.events.filter(e => {
                    const lat = toNumber(e.latitude);
                    const lng = toNumber(e.longitude);
                    return lat !== null && lng !== null;
                }).map(e => ({
                    ...e,
                    latitude: toNumber(e.latitude),
                    longitude: toNumber(e.longitude)
                }));
                
                if (events.length < 2) {
                    // Not enough event points yet: fall back to shipment pickup -> dropoff endpoints (straight-line)
                    showSimpleMap(data.shipment, events);
                    return;
                }
                
                // Initialize map
                map = new google.maps.Map(document.getElementById('map-container'), {
                    zoom: 6,
                    center: { lat: events[0].latitude, lng: events[0].longitude },
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
                
                // Create route from events
                const origin = { lat: events[events.length - 1].latitude, lng: events[events.length - 1].longitude };
                const destination = { lat: events[0].latitude, lng: events[0].longitude };
                
                // If we have waypoints
                const waypoints = events.slice(1, -1).map(e => ({
                    location: { lat: e.latitude, lng: e.longitude },
                    stopover: true
                }));
                
                directionsService.route({
                    origin: origin,
                    destination: destination,
                    waypoints: waypoints,
                    travelMode: google.maps.TravelMode.DRIVING
                }, (result, status) => {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(result);
                        
                        // Get route path for animation
                        const route = result.routes[0];
                        const path = route.overview_path;
                        
                        // Create origin marker
                        new google.maps.Marker({
                            position: origin,
                            map: map,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 8,
                                fillColor: '#4D148C',
                                fillOpacity: 1,
                                strokeColor: '#fff',
                                strokeWeight: 2
                            },
                            title: 'Origin'
                        });
                        
                        // Create destination marker
                        new google.maps.Marker({
                            position: destination,
                            map: map,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 8,
                                fillColor: '#FF6200',
                                fillOpacity: 1,
                                strokeColor: '#fff',
                                strokeWeight: 2
                            },
                            title: 'Destination'
                        });
                        
                        // Create animated truck marker
                        const currentLocation = events[0];
                        truckMarker = new google.maps.Marker({
                            position: { lat: currentLocation.latitude, lng: currentLocation.longitude },
                            map: map,
                            icon: {
                                path: 'M 0,0 C -2,-20 -10,-22 -10,-30 A 10,10 0 1,1 10,-30 C 10,-22 2,-20 0,0 z M -12,-30 L -12,-40 L 12,-40 L 12,-30',
                                fillColor: '#FF6200',
                                fillOpacity: 1,
                                strokeColor: '#fff',
                                strokeWeight: 2,
                                scale: 1.5,
                                anchor: new google.maps.Point(0, -20)
                            },
                            title: 'Current Location'
                        });
                        
                        // Animate truck along route
                        animateTruck(path);
                        
                        // Fit bounds to show entire route
                        const bounds = new google.maps.LatLngBounds();
                        path.forEach(point => bounds.extend(point));
                        map.fitBounds(bounds);
                    } else {
                        // If Directions fails (common for some coordinates/regions), fall back to a straight-line preview
                        drawStraightLine(map, origin, destination, {
                            originTitle: 'Origin',
                            destinationTitle: 'Destination'
                        });
                    }
                });
            } else {
                showSimpleMap(data.shipment, []);
            }
        })
        .catch(error => {
            console.error('Error fetching tracking data:', error);
        });
}

/**
 * Animate truck along route
 */
function animateTruck(path) {
    if (!path || path.length === 0) return;
    
    let step = 0;
    const totalSteps = path.length;
    const animationSpeed = 50; // milliseconds per step
    
    function moveTruck() {
        if (step < totalSteps) {
            const position = path[step];
            truckMarker.setPosition(position);
            step++;
            setTimeout(moveTruck, animationSpeed);
        } else {
            // Loop animation
            step = 0;
            setTimeout(moveTruck, 1000);
        }
    }
    
    moveTruck();
}

/**
 * Show simple map without route (fallback)
 */
function showSimpleMap(shipment, events) {
    // Prefer event position; otherwise fall back to pickup -> dropoff endpoints injected by PHP.
    const fallback = getFallbackEndpoints();
    const center = events.length > 0
        ? { lat: events[0].latitude, lng: events[0].longitude }
        : (fallback ? { lat: fallback.pickup.lat, lng: fallback.pickup.lng } : { lat: 39.8283, lng: -98.5795 }); // Center of USA
    
    map = new google.maps.Map(document.getElementById('map-container'), {
        zoom: 6,
        center: center,
        mapTypeId: 'roadmap'
    });
    
    // If we have shipment endpoints, draw a straight line route immediately.
    if (fallback) {
        drawStraightLine(map, fallback.pickup, fallback.dropoff, {
            originTitle: fallback.pickup.name || 'Pickup',
            destinationTitle: fallback.dropoff.name || 'Dropoff'
        });
        return;
    }

    // Otherwise, just add markers for any available events.
    events.forEach((event, index) => {
        new google.maps.Marker({
            position: { lat: event.latitude, lng: event.longitude },
            map: map,
            title: event.description || 'Location',
            label: {
                text: (index + 1).toString(),
                color: '#fff'
            }
        });
    });
}

// Initialize map when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
} else {
    initMap();
}

