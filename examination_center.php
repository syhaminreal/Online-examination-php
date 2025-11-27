<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('master/Examination.php');
$exam = new Examination;
include('header.php');

if (!isset($_SESSION["user_id"])) {
    header('location:login.php');
    exit();
}

// Exam center coordinates
$examCenter = [
    "name" => "Kathmandu Examination Center",
    "lat" => 27.7172,
    "lng" => 85.3240,
    "opening_time" => "08:00",
    "closing_time" => "17:00"
];

// OpenRouteService API key
$apiKey = "5b3ce3597851110001cf6248";

if (isset($_POST['action']) && $_POST['action'] === 'calculate') {
    $userLat = floatval($_POST['lat'] ?? 0);
    $userLng = floatval($_POST['lng'] ?? 0);

    if (!$userLat || !$userLng) {
        echo json_encode(["status" => "ERROR", "message" => "Invalid coordinates"]);
        exit;
    }

    // Enhanced Haversine calculation server-side
    function calculateHaversine($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;
        
        return [
            'km' => round($distance, 2),
            'meters' => round($distance * 1000),
            'miles' => round($distance * 0.621371, 2)
        ];
    }

    // Calculate straight-line distance using Haversine
    $straightDistance = calculateHaversine($userLat, $userLng, $examCenter['lat'], $examCenter['lng']);

    function getORSData($mode, $start, $end, $apiKey) {
        $url = "https://api.openrouteservice.org/v2/directions/$mode";
        
        try {
            $body = [
                "coordinates" => [
                    [$start[1], $start[0]], // ORS uses [lng, lat]
                    [$end[1], $end[0]]
                ]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 15
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception("API returned status code: " . $httpCode);
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Failed to parse JSON response");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("OpenRouteService API error: " . $e->getMessage());
            return ["error" => $e->getMessage()];
        }
    }

    // Get routing data
    $driving = getORSData("driving-car", [$userLat, $userLng], [$examCenter['lat'], $examCenter['lng']], $apiKey);
    $walking = getORSData("foot-walking", [$userLat, $userLng], [$examCenter['lat'], $examCenter['lng']], $apiKey);

    // Enhanced time estimation with traffic factors
    function estimateTravelTime($distanceKm, $mode, $apiData = null) {
        $baseSpeeds = [
            'driving' => 30, // km/h - average city driving speed
            'walking' => 5,  // km/h - average walking speed
            'cycling' => 15   // km/h - average cycling speed
        ];
        
        $trafficFactor = 1.2; // 20% extra time for traffic/lights
        
        if ($apiData && isset($apiData['routes'][0]['summary']['duration'])) {
            // Use API data if available
            $apiDuration = round($apiData['routes'][0]['summary']['duration'] / 60); // Convert to minutes
            return $apiDuration;
        } else {
            // Fallback calculation
            $speed = $baseSpeeds[$mode] ?? 5;
            $timeHours = $distanceKm / $speed;
            $timeMinutes = round($timeHours * 60 * $trafficFactor);
            return max(1, $timeMinutes); // At least 1 minute
        }
    }

    // Calculate times
    $drivingTime = estimateTravelTime($straightDistance['km'], 'driving', $driving);
    $walkingTime = estimateTravelTime($straightDistance['km'], 'walking', $walking);
    
    // Add buffer times
    $drivingTimeWithBuffer = $drivingTime + 10; // 10 min buffer for parking/traffic
    $walkingTimeWithBuffer = $walkingTime + 5;  // 5 min buffer for walking

    // Calculate arrival times
    $currentTime = time();
    $drivingArrival = $currentTime + ($drivingTimeWithBuffer * 60);
    $walkingArrival = $currentTime + ($walkingTimeWithBuffer * 60);

    // Prepare response
    $response = [
        "status" => "OK",
        "straight_distance" => $straightDistance,
        "routing" => [
            "driving" => [
                "distance_km" => isset($driving['routes'][0]['summary']['distance']) ? 
                    round($driving['routes'][0]['summary']['distance'] / 1000, 2) : $straightDistance['km'],
                "time_minutes" => $drivingTimeWithBuffer,
                "arrival_time" => date('H:i', $drivingArrival),
                "depart_by" => date('H:i', $currentTime - 300) // 5 minutes ago for "now"
            ],
            "walking" => [
                "distance_km" => isset($walking['routes'][0]['summary']['distance']) ? 
                    round($walking['routes'][0]['summary']['distance'] / 1000, 2) : $straightDistance['km'],
                "time_minutes" => $walkingTimeWithBuffer,
                "arrival_time" => date('H:i', $walkingArrival),
                "depart_by" => date('H:i', $currentTime - 300)
            ]
        ],
        "exam_center_info" => [
            "opening_time" => $examCenter['opening_time'],
            "closing_time" => $examCenter['closing_time'],
            "current_time" => date('H:i')
        ]
    ];

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Examination Center Distance & Travel Time Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f8f9fa; padding: 20px; font-family: 'Segoe UI', sans-serif; }
        #map { height: 500px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .info-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .transport-card { border-left: 4px solid #007bff; margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .transport-card.walking { border-left-color: #28a745; }
        .time-badge { background: #17a2b8; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.9em; }
        .distance-badge { background: #6c757d; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.9em; }
        .arrival-time { font-size: 1.2em; font-weight: bold; color: #28a745; }
        .exam-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="exam-info text-center">
                <h2>🏫 Examination Center Navigator</h2>
                <p class="mb-0">Calculate your travel time and distance to the exam center</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="info-card">
                <h5><i class="fas fa-location-crosshairs me-2"></i>Live Location Tracking</h5>
                <p class="text-muted small">Enable location access to calculate real-time travel information</p>
                <button onclick="getCurrentLocation()" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-location-dot me-2"></i> Start Live Tracking
                </button>
                <div id="locationStatus" class="alert" style="display:none;"></div>
            </div>

            <div id="routeDetails" style="display:none;">
                <div class="info-card">
                    <h5><i class="fas fa-route me-2"></i>Travel Information</h5>
                    
                    <!-- Straight Line Distance -->
                    <div class="mb-3 p-3 border rounded">
                        <h6><i class="fas fa-ruler-combined me-2"></i>Direct Distance</h6>
                        <div id="straightDistance" class="fw-bold text-primary"></div>
                    </div>

                    <!-- Driving Information -->
                    <div class="transport-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-car me-2"></i>By Vehicle</h6>
                            <span class="time-badge" id="drivingTime"></span>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Distance</small>
                                <div id="drivingDistance" class="fw-bold"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Arrival</small>
                                <div class="arrival-time" id="drivingArrival"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Walking Information -->
                    <div class="transport-card walking">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-walking me-2"></i>Walking</h6>
                            <span class="time-badge" id="walkingTime"></span>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Distance</small>
                                <div id="walkingDistance" class="fw-bold"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Arrival</small>
                                <div class="arrival-time" id="walkingArrival"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Exam Center Info -->
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6><i class="fas fa-building me-2"></i>Exam Center Hours</h6>
                        <div class="small">
                            <div>Open: <strong id="openingTime"></strong></div>
                            <div>Close: <strong id="closingTime"></strong></div>
                            <div>Current: <strong id="currentTime"></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div id="map"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let map, userMarker, routingLine;
let distanceMarker = null;
const examCenter = { 
    lat: <?php echo $examCenter['lat']; ?>, 
    lng: <?php echo $examCenter['lng']; ?>, 
    name: "<?php echo $examCenter['name']; ?>"
};

// Enhanced Haversine calculation client-side
function calculateHaversine(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const distance = R * c;
    
    return {
        km: Math.round(distance * 100) / 100,
        meters: Math.round(distance * 1000),
        miles: Math.round(distance * 0.621371 * 100) / 100
    };
}

function initMap() {
    map = L.map('map').setView([examCenter.lat, examCenter.lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    // Exam center marker
    L.marker([examCenter.lat, examCenter.lng], {
        icon: L.icon({
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/1001/1001371.png',
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        })
    }).addTo(map).bindPopup(`<b>${examCenter.name}</b><br>Your Exam Center`).openPopup();
}

let locationWatcher = null;

function getCurrentLocation() {
    const status = $('#locationStatus');
    status.removeClass().addClass('alert alert-info').html('<i class="fas fa-sync fa-spin me-2"></i>Tracking your live location...').show();

    if (!navigator.geolocation) {
        status.removeClass().addClass('alert alert-danger').text('Geolocation not supported by your browser.');
        return;
    }

    if (locationWatcher !== null) {
        navigator.geolocation.clearWatch(locationWatcher);
    }

    locationWatcher = navigator.geolocation.watchPosition(
        position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            updateUserLocation(lat, lng, accuracy, status);
        },
        error => {
            handleLocationError(error, status);
        },
        {
            enableHighAccuracy: true,
            maximumAge: 30000, // 30 seconds
            timeout: 15000
        }
    );
}

function updateUserLocation(lat, lng, accuracy, status) {
    // Update or create user marker
    if (userMarker) {
        map.removeLayer(userMarker);
    }
    
    userMarker = L.marker([lat, lng], {
        icon: L.icon({
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png',
            iconSize: [28, 28],
            iconAnchor: [14, 28]
        })
    }).addTo(map).bindPopup('<b>Your Current Location</b>').openPopup();

    // Update routing line
    if (routingLine) {
        map.removeLayer(routingLine);
    }
    routingLine = L.polyline([[lat, lng], [examCenter.lat, examCenter.lng]], {
        color: '#007bff',
        weight: 4,
        opacity: 0.7,
        dashArray: '5, 10'
    }).addTo(map);
    
    map.fitBounds(routingLine.getBounds(), { padding: [30, 30] });

    // Calculate and display distances
    calculateAndDisplayDistances(lat, lng, accuracy, status);
}

function calculateAndDisplayDistances(lat, lng, accuracy, status) {
    // Immediate Haversine calculation
    const straightDistance = calculateHaversine(lat, lng, examCenter.lat, examCenter.lng);
    
    // Update straight distance display immediately
    $('#straightDistance').html(`${straightDistance.km} km<br><small class="text-muted">${straightDistance.meters} meters</small>`);

    // Update distance marker
    updateDistanceMarker(lat, lng, straightDistance);

    // Get detailed routing from server
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'calculate', lat, lng },
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            if (response.status === 'OK') {
                displayRouteDetails(response);
                status.removeClass().addClass('alert alert-success')
                    .html('<i class="fas fa-check-circle me-2"></i>Location updated! Travel information calculated.');
            } else {
                throw new Error(response.message || 'Server error');
            }
        },
        error: function(xhr, statusObj, error) {
            console.error('Routing API error:', error);
            // Fallback to Haversine-only estimates
            displayFallbackEstimates(straightDistance);
            status.removeClass().addClass('alert alert-warning')
                .html('<i class="fas fa-exclamation-triangle me-2"></i>Using estimated travel times (routing service unavailable)');
        }
    });
}

function updateDistanceMarker(lat, lng, distance) {
    const midLat = (lat + examCenter.lat) / 2;
    const midLng = (lng + examCenter.lng) / 2;
    
    if (distanceMarker) {
        map.removeLayer(distanceMarker);
    }
    
    distanceMarker = L.marker([midLat, midLng], {
        icon: L.divIcon({
            className: 'distance-marker',
            html: `<div style="background: white; padding: 8px 12px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); border: 2px solid #007bff; font-weight: bold;">
                    ${distance.km} km
                  </div>`,
            iconSize: [80, 40],
            iconAnchor: [40, 20]
        })
    }).addTo(map).bindPopup(`<b>Direct Distance:</b><br>${distance.km} kilometers<br>${distance.meters} meters`);
}

function displayRouteDetails(data) {
    $('#routeDetails').show();
    
    // Update driving information
    $('#drivingDistance').text(data.routing.driving.distance_km + ' km');
    $('#drivingTime').text(data.routing.driving.time_minutes + ' min');
    $('#drivingArrival').text(data.routing.driving.arrival_time);
    
    // Update walking information
    $('#walkingDistance').text(data.routing.walking.distance_km + ' km');
    $('#walkingTime').text(data.routing.walking.time_minutes + ' min');
    $('#walkingArrival').text(data.routing.walking.arrival_time);
    
    // Update exam center info
    $('#openingTime').text(data.exam_center_info.opening_time);
    $('#closingTime').text(data.exam_center_info.closing_time);
    $('#currentTime').text(data.exam_center_info.current_time);
}

function displayFallbackEstimates(distance) {
    $('#routeDetails').show();
    
    // Estimate times based on distance only
    const drivingTime = Math.round((distance.km / 30) * 60) + 10; // 30 km/h + buffer
    const walkingTime = Math.round((distance.km / 5) * 60) + 5;   // 5 km/h + buffer
    
    const now = new Date();
    const drivingArrival = new Date(now.getTime() + drivingTime * 60000);
    const walkingArrival = new Date(now.getTime() + walkingTime * 60000);
    
    $('#drivingDistance').text(distance.km + ' km');
    $('#drivingTime').text(drivingTime + ' min');
    $('#drivingArrival').text(drivingArrival.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
    
    $('#walkingDistance').text(distance.km + ' km');
    $('#walkingTime').text(walkingTime + ' min');
    $('#walkingArrival').text(walkingArrival.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
    
    // Exam center info
    $('#openingTime').text('08:00');
    $('#closingTime').text('17:00');
    $('#currentTime').text(now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
}

function handleLocationError(error, status) {
    let message = 'Unknown location error';
    switch(error.code) {
        case error.PERMISSION_DENIED:
            message = 'Location access denied. Please enable location permissions.';
            break;
        case error.POSITION_UNAVAILABLE:
            message = 'Location information unavailable.';
            break;
        case error.TIMEOUT:
            message = 'Location request timed out.';
            break;
    }
    status.removeClass().addClass('alert alert-danger').html(`<i class="fas fa-exclamation-circle me-2"></i>${message}`);
}

// Initialize map when document is ready
$(document).ready(initMap);
</script>
</body>
</html>