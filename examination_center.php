<?php
include('header.php');  // This includes session start and user check

// ===============================
// Exam Center Configuration
// ===============================
$examCenter = [
    "name" => "Kathmandu Examination Center",
    "address" => "Kathmandu, Nepal",
    "lat" => 27.7172,
    "lng" => 85.3240
];

// Haversine Formula to calculate distance between two points
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $radius = 6371; // Earth's radius in kilometers

    $lat = deg2rad($lat2 - $lat1);
    $lon = deg2rad($lon2 - $lon1);
    
    $a = sin($lat/2) * sin($lat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($lon/2) * sin($lon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $radius * $c;
    
    return round($distance, 2);
}

// Calculate travel times based on distance
function calculateTravelTimes($distance) {
    // Average speeds (km/h)
    $carSpeed = 40;    // Average urban car speed
    $walkSpeed = 5;    // Average walking speed
    
    // Calculate times
    $carTime = $distance / $carSpeed;
    $walkTime = $distance / $walkSpeed;
    
    return [
        'car' => formatTime($carTime),
        'walk' => formatTime($walkTime)
    ];
}

// Format time from hours to hours and minutes
function formatTime($hours) {
    $hrs = floor($hours);
    $mins = round(($hours - $hrs) * 60);
    
    if ($hrs == 0) {
        return "{$mins} minutes";
    } else {
        return "{$hrs} hour" . ($hrs > 1 ? "s" : "") . 
               ($mins > 0 ? " {$mins} minutes" : "");
    }
}

// Handle AJAX request for distance calculation
if (isset($_POST['action']) && $_POST['action'] === 'calculate') {
    $user_lat = floatval($_POST['lat'] ?? 0);
    $user_lng = floatval($_POST['lng'] ?? 0);
    
    if ($user_lat && $user_lng) {
        $distance = calculateDistance($user_lat, $user_lng, $examCenter['lat'], $examCenter['lng']);
        $times = calculateTravelTimes($distance);
        
        $response = [
            'status' => 'OK',
            'distance' => $distance,
            'results' => [
                'driving' => [
                    'distance' => $distance . ' km',
                    'duration' => $times['car']
                ],
                'walking' => [
                    'distance' => $distance . ' km',
                    'duration' => $times['walk']
                ]
            ]
        ];
    } else {
        $response = [
            'status' => 'ERROR',
            'message' => 'Invalid coordinates provided'
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Examination Center Distance Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        .map-container {
            position: relative;
            height: 450px;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .map-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 12px;
        }
        .route-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .travel-mode {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .travel-mode i {
            font-size: 24px;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }
        .travel-info {
            flex-grow: 1;
        }
        #locationStatus {
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="my-4">🏫 Examination Center Distance Calculator</h2>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Calculate Travel Distance</h4>
                        <p class="text-muted">Use your current location to calculate distance and estimated travel time to the examination center.</p>
                        
                        <button onclick="getCurrentLocation()" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-location-dot me-2"></i> Use My Current Location
                        </button>
                        
                        <div id="locationStatus" class="alert" role="alert"></div>
                    </div>
                </div>

                <div id="routeDetails" class="route-card mt-3" style="display:none;">
                    <h4 class="mb-3">Travel Options</h4>
                    <div class="travel-mode">
                        <i class="fas fa-car"></i>
                        <div class="travel-info">
                            <h5>By Vehicle</h5>
                            <p class="mb-0" id="drivingDetails">Loading...</p>
                        </div>
                    </div>
                    <div class="travel-mode">
                        <i class="fas fa-walking"></i>
                        <div class="travel-info">
                            <h5>Walking</h5>
                            <p class="mb-0" id="walkingDetails">Loading...</p>
                        </div>
                    </div>
                    <div class="mt-3 text-muted small">
                        <p class="mb-0">
                            <i class="fas fa-info-circle"></i> 
                            These are approximate times. Actual travel time may vary based on traffic and route conditions.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="map-container">
                    <iframe class="map-iframe"
                        src="https://www.openstreetmap.org/export/embed.html?bbox=<?php 
                            echo ($examCenter['lng'] - 0.02) . '%2C' . 
                                 ($examCenter['lat'] - 0.02) . '%2C' . 
                                 ($examCenter['lng'] + 0.02) . '%2C' . 
                                 ($examCenter['lat'] + 0.02);
                        ?>&amp;layer=mapnik&amp;marker=<?php 
                            echo $examCenter['lat'] . '%2C' . $examCenter['lng'];
                        ?>"
                    ></iframe>
                </div>
                <div class="text-center">
                    <a href="https://www.openstreetmap.org/?mlat=<?php echo $examCenter['lat']; ?>&mlon=<?php echo $examCenter['lng']; ?>#map=15/<?php echo $examCenter['lat'] . '/' . $examCenter['lng']; ?>" 
                       class="btn btn-sm btn-outline-secondary" 
                       target="_blank">
                        View Larger Map
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const examCenter = {
            lat: <?php echo $examCenter['lat']; ?>,
            lng: <?php echo $examCenter['lng']; ?>
        };

        function getCurrentLocation() {
            const status = $('#locationStatus');
            status.removeClass().addClass('alert').show();

            if (!navigator.geolocation) {
                status.addClass('alert-danger')
                    .text('Geolocation is not supported by your browser');
                return;
            }

            status.addClass('alert-info')
                .html('<i class="fas fa-spinner fa-spin"></i> Getting your location...');

            navigator.geolocation.getCurrentPosition(position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                // Calculate distance and times
                $.post('examination_center.php', {
                    action: 'calculate',
                    lat: lat,
                    lng: lng
                }, function(response) {
                    if (response.status === 'OK') {
                        $('#routeDetails').show();
                        $('#drivingDetails').html(
                            `Distance: <strong>${response.results.driving.distance}</strong><br>` +
                            `Estimated time: <strong>${response.results.driving.duration}</strong>`
                        );
                        $('#walkingDetails').html(
                            `Distance: <strong>${response.results.walking.distance}</strong><br>` +
                            `Estimated time: <strong>${response.results.walking.duration}</strong>`
                        );
                        status.addClass('alert-success')
                            .text('Distance calculated successfully!');
                        setTimeout(() => status.fadeOut(), 3000);
                    } else {
                        status.addClass('alert-danger')
                            .text('Could not calculate distance: ' + (response.message || 'Unknown error'));
                    }
                }).fail(function() {
                    status.addClass('alert-danger')
                        .text('Server error while calculating distance');
                });

            }, error => {
                status.addClass('alert-danger')
                    .text('Could not get your location: ' + error.message);
            });
        }
    </script>
</body>
</html>

    <script>
        let map;
        const examCenter = { lat: <?= $examCenter['lat'] ?>, lng: <?= $examCenter['lng'] ?> };

        // Initialize Google Map
        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: examCenter,
                zoom: 14,
            });
            new google.maps.Marker({
                position: examCenter,
                map,
                title: "<?= $examCenter['name'] ?>",
            });
        }

        // Get user's current location and calculate distance
        function getUserLocation() {
            document.getElementById('status').textContent = "Getting your location...";
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const userLat = position.coords.latitude;
                    const userLng = position.coords.longitude;

                    fetch(`exam_center_page.php?lat=${userLat}&lng=${userLng}`)
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById('status').textContent = "✅ Distance calculated successfully!";
                            document.getElementById('distance').textContent = "Distance: " + data.distance;
                            document.getElementById('time').textContent = "Estimated Travel Time: " + data.time;

                            // Show user marker
                            new google.maps.Marker({
                                position: { lat: userLat, lng: userLng },
                                map,
                                title: "Your Location",
                                icon: {
                                    url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
                                }
                            });
                        });
                }, () => {
                    document.getElementById('status').textContent = "⚠️ Location permission denied.";
                });
            } else {
                document.getElementById('status').textContent = "Geolocation not supported by your browser.";
            }
        }
    </script>

    <!-- Load Google Maps JS (no API key required just for map display) -->
    <script async defer src="https://maps.googleapis.com/maps/api/js?callback=initMap"></script>

</body>
</html>
