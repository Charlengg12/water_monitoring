<?php
// ============================================================================
// WATER MONITORING DASHBOARD - XAMPP Configuration
// Server IP: 10.140.22.16
// ============================================================================

// ------------------------------------------------------------------
// REQUIRED: Include database connection OR define it here
// ------------------------------------------------------------------
// Option 1: If you have a separate db_connect.php file, uncomment:
// require_once 'db_connect.php';

// Option 2: Direct XAMPP connection (recommended for local development)
$servername = "localhost";
$username = "root";           // Default XAMPP MySQL username
$password = "";               // Default XAMPP MySQL password (empty)
$database = "water_monitoring";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// ------------------------------------------------------------------
// Get station_id from query string
// ------------------------------------------------------------------
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : 0;

/* ------------------------------------------------------------------
   Save to DB
   ESP32 posts JSON payload to: http://10.140.22.16/water-monitoring/dashboard.php?station_id=NNN

   Example payload (from your ESP32 sketch):
   {
     "sensorId":"ISUIT-WQTAMS-0001",
     "tds_val":12.34,
     "ph_val":6.50,
     "turbidity_val":1.23,
     "lead_val":0.010,
     "color_val":9.25,
     "tds_status":"Safe",
     "ph_status":"Neutral",
     "turbidity_status":"Safe",
     "lead_status":"Neutral",
     "color_status":"Neutral",
     "color_result":"Clear"
   }
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['station_id']) && isset($_SERVER['CONTENT_TYPE'])) {
    // Only accept JSON or form posts intended for ESP32 uploads.
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]));

    if ($contentType === 'application/json' || $contentType === 'text/json') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if ($data && is_array($data)) {
            $sid = (int)$_GET['station_id'];

            // Map and sanitize values using real_escape_string for security
            $sensorId = isset($data['sensorId']) ? $conn->real_escape_string(trim($data['sensorId'])) : null;
            $tds_val = isset($data['tds_val']) ? (float)$data['tds_val'] : null;
            $ph_val = isset($data['ph_val']) ? (float)$data['ph_val'] : null;
            $turbidity_val = isset($data['turbidity_val']) ? (float)$data['turbidity_val'] : null;
            $lead_val = isset($data['lead_val']) ? (float)$data['lead_val'] : null;
            $color_val = isset($data['color_val']) ? (float)$data['color_val'] : null;
            $tds_status = isset($data['tds_status']) ? $conn->real_escape_string($data['tds_status']) : null;
            $ph_status = isset($data['ph_status']) ? $conn->real_escape_string($data['ph_status']) : null;
            $turbidity_status = isset($data['turbidity_status']) ? $conn->real_escape_string($data['turbidity_status']) : null;
            $lead_status = isset($data['lead_status']) ? $conn->real_escape_string($data['lead_status']) : null;
            $color_status = isset($data['color_status']) ? $conn->real_escape_string($data['color_status']) : null;
            $color_result = isset($data['color_result']) ? $conn->real_escape_string($data['color_result']) : null;

            // Prepare INSERT into water_data table
            // Ensure your water_data table has matching columns. Adjust column names if needed.
            $stmt = $conn->prepare("
                INSERT INTO water_data
                (station_id, sensor_id, tds_value, ph_value, turbidity_value, lead_value, color_value,
                 tds_status, ph_status, turbidity_status, lead_status, color_status, color_result, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "issddddssssss",
                    $sid, $sensorId, $tds_val, $ph_val, $turbidity_val, $lead_val, $color_val,
                    $tds_status, $ph_status, $turbidity_status, $lead_status, $color_status, $color_result
                );

                if ($stmt->execute()) {
                    // Optional: return JSON to ESP32
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
                    http_response_code(200);
                    $stmt->close();
                    exit;
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'DB Insert failed: ' . $stmt->error]);
                    http_response_code(500);
                    $stmt->close();
                    exit;
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'DB Prepare failed: ' . $conn->error]);
                http_response_code(500);
                exit;
            }
        } else {
            // Not JSON or invalid
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            http_response_code(400);
            exit;
        }
    }
}

/* ------------------------------------------------------------------
   Auto-test settings save (AJAX POST to this file)
   (This is reused by the front-end save button.)
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_autotest') {
    header('Content-Type: application/json; charset=utf-8');

    $sid = isset($_POST['station_id']) ? (int)$_POST['station_id'] : 0;
    $mode = $_POST['mode'] ?? 'hourly';
    $interval_hours = ($_POST['interval_hours'] !== '' && $_POST['interval_hours'] !== null) ? (int)$_POST['interval_hours'] : null;
    $interval_days = ($_POST['interval_days'] !== '' && $_POST['interval_days'] !== null) ? (int)$_POST['interval_days'] : null;
    $interval_months = ($_POST['interval_months'] !== '' && $_POST['interval_months'] !== null) ? (int)$_POST['interval_months'] : null;
    $day_of_month = ($_POST['day_of_month'] !== '' && $_POST['day_of_month'] !== null) ? (int)$_POST['day_of_month'] : null;
    $time_of_day = isset($_POST['time_of_day']) && $_POST['time_of_day'] !== '' ? $_POST['time_of_day'] : null;
    $enabled = isset($_POST['enabled']) && ($_POST['enabled'] === '1' || $_POST['enabled'] === 'true' || $_POST['enabled'] === 1) ? 1 : 0;

    if (!$sid) {
        echo json_encode(['success' => false, 'message' => 'No station id provided.']);
        exit;
    }

    // Upsert into station_autotest_settings
    $stmt = $conn->prepare("
        INSERT INTO station_autotest_settings
        (station_id, mode, interval_hours, interval_days, interval_months, day_of_month, time_of_day, enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            mode = VALUES(mode),
            interval_hours = VALUES(interval_hours),
            interval_days = VALUES(interval_days),
            interval_months = VALUES(interval_months),
            day_of_month = VALUES(day_of_month),
            time_of_day = VALUES(time_of_day),
            enabled = VALUES(enabled)
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
        exit;
    }

    // bind (use null-int safe binding)
    $stmt->bind_param(
        'isiiiisi',
        $sid, $mode, $interval_hours, $interval_days, $interval_months, $day_of_month, $time_of_day, $enabled
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

/* ------------------------------------------------------------------
   Defaults & fetch settings (for display)
------------------------------------------------------------------ */
$settings = [
    'mode' => 'hourly',
    'interval_hours' => 1,
    'interval_days' => 1,
    'daily_time' => '00:00',
    'monthly_time' => '00:00',
    'interval_months' => 1,
    'day_of_month' => 1,
    'enabled' => 0
];

$station = null;

if ($station_id) {
    // Fetch station info from DB
    $stmt = $conn->prepare("SELECT * FROM refilling_stations WHERE station_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $station_id);
        $stmt->execute();
        $station = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Fetch saved auto-test settings (if any)
    $stmt = $conn->prepare("SELECT * FROM station_autotest_settings WHERE station_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $station_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            // map DB columns to $settings names (account for different column names)
            $settings['mode'] = $row['mode'] ?? $settings['mode'];
            $settings['interval_hours'] = $row['interval_hours'] ?? $settings['interval_hours'];
            $settings['interval_days'] = $row['interval_days'] ?? $settings['interval_days'];
            $settings['daily_time'] = $row['time_of_day'] ?? $settings['daily_time'];
            $settings['interval_months'] = $row['interval_months'] ?? $settings['interval_months'];
            $settings['day_of_month'] = $row['day_of_month'] ?? $settings['day_of_month'];
            $settings['monthly_time'] = $row['time_of_day'] ?? $settings['monthly_time'];
            $settings['enabled'] = $row['enabled'] ?? $settings['enabled'];
        }
        $stmt->close();
    }
}

/* ------------------------------------------------------------------
   Fetch test runs from DB (real history)
------------------------------------------------------------------ */
$testRuns = [];
if ($station_id) {
    $stmt = $conn->prepare("SELECT waterdata_id, timestamp FROM water_data WHERE station_id = ? ORDER BY timestamp DESC LIMIT 200");
    if ($stmt) {
        $stmt->bind_param('i', $station_id);
        $stmt->execute();
        $testRuns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

/* ------------------------------------------------------------------
   Fetch latest water data for display
------------------------------------------------------------------ */
$latestData = null;
if ($station_id) {
    $stmt = $conn->prepare("SELECT * FROM water_data WHERE station_id = ? ORDER BY timestamp DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $station_id);
        $stmt->execute();
        $latestData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/* ------------------------------------------------------------------
   Default user fallback (if fetch_user didn't provide)
------------------------------------------------------------------ */
if (!isset($user) || !is_array($user)) {
    $user = ['profile_pic' => 'https://cdn-icons-png.flaticon.com/512/847/847969.png'];
}

/* ------------------------------------------------------------------
   End PHP header, begin HTML
------------------------------------------------------------------ */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Monitoring Dashboard - 10.140.22.16</title>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/3094/3094261.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }

        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .server-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .network-info {
            background: #e0e7ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 13px;
            color: #3730a3;
        }

        .network-info code {
            background: #c7d2fe;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        .station-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }

        .info-item strong {
            color: #555;
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .data-section {
            margin-top: 30px;
        }

        .data-section h3 {
            color: #374151;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .reading-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .reading-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }

        .reading-card h4 {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .reading-value {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin: 10px 0;
        }

        .reading-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .reading-status.safe {
            background: #d1fae5;
            color: #065f46;
        }

        .reading-status.neutral {
            background: #dbeafe;
            color: #1e40af;
        }

        .reading-status.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .reading-status.failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💧 Water Quality Monitoring Dashboard</h1>
            <p style="color: #6b7280; margin-top: 10px;">Real-time ESP32 Sensor Data - XAMPP Local Server</p>
            <span class="server-badge">🌐 Server: 10.140.22.16</span>
        </div>

        <div class="network-info">
            <strong>📡 Network Configuration:</strong><br>
            Server IP: <code>10.140.22.16</code> | 
            ESP32 IP: <code>10.140.22.250</code> | 
            ESP32 POST URL: <code>http://10.140.22.16/water-monitoring/dashboard.php?station_id=<?php echo $station_id; ?></code>
        </div>

        <?php if ($station): ?>
            <div class="station-info">
                <h2 style="color: #333; margin-bottom: 15px;">📍 Station Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Station Name</strong>
                        <span><?php echo htmlspecialchars($station['station_name'] ?? 'Unknown Station'); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Station ID</strong>
                        <span><?php echo htmlspecialchars($station_id); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Sensor ID</strong>
                        <span><?php echo htmlspecialchars($station['device_sensor_id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Status</strong>
                        <span><?php echo htmlspecialchars($station['status'] ?? 'Active'); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($latestData): ?>
                <div class="data-section">
                    <h3>📊 Latest Sensor Readings</h3>
                    <div style="background: #f9fafb; padding: 20px; border-radius: 8px;">
                        <div style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">
                            Last Updated: <strong><?php echo htmlspecialchars($latestData['timestamp']); ?></strong>
                        </div>
                        <div class="reading-grid">
                            <div class="reading-card">
                                <h4>💧 TDS</h4>
                                <div class="reading-value"><?php echo number_format($latestData['tds_value'], 2); ?></div>
                                <div style="color: #9ca3af; font-size: 12px;">mg/L</div>
                                <span class="reading-status <?php echo strtolower($latestData['tds_status']); ?>">
                                    <?php echo htmlspecialchars($latestData['tds_status']); ?>
                                </span>
                            </div>

                            <div class="reading-card">
                                <h4>🧪 pH Level</h4>
                                <div class="reading-value"><?php echo number_format($latestData['ph_value'], 2); ?></div>
                                <div style="color: #9ca3af; font-size: 12px;">pH</div>
                                <span class="reading-status <?php echo strtolower($latestData['ph_status']); ?>">
                                    <?php echo htmlspecialchars($latestData['ph_status']); ?>
                                </span>
                            </div>

                            <div class="reading-card">
                                <h4>🌊 Turbidity</h4>
                                <div class="reading-value"><?php echo number_format($latestData['turbidity_value'], 2); ?></div>
                                <div style="color: #9ca3af; font-size: 12px;">NTU</div>
                                <span class="reading-status <?php echo strtolower($latestData['turbidity_status']); ?>">
                                    <?php echo htmlspecialchars($latestData['turbidity_status']); ?>
                                </span>
                            </div>

                            <div class="reading-card">
                                <h4>⚠️ Lead</h4>
                                <div class="reading-value"><?php echo number_format($latestData['lead_value'], 3); ?></div>
                                <div style="color: #9ca3af; font-size: 12px;">mg/L</div>
                                <span class="reading-status <?php echo strtolower($latestData['lead_status']); ?>">
                                    <?php echo htmlspecialchars($latestData['lead_status']); ?>
                                </span>
                            </div>

                            <div class="reading-card">
                                <h4>🎨 Color</h4>
                                <div class="reading-value"><?php echo number_format($latestData['color_value'], 2); ?></div>
                                <div style="color: #9ca3af; font-size: 12px;"><?php echo htmlspecialchars($latestData['color_result']); ?></div>
                                <span class="reading-status <?php echo strtolower($latestData['color_status']); ?>">
                                    <?php echo htmlspecialchars($latestData['color_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>ℹ️ No Data Available:</strong> Waiting for ESP32 to send sensor data. Make sure ESP32 is connected and sending data to <code>http://10.140.22.16/water-monitoring/dashboard.php?station_id=<?php echo $station_id; ?></code>
                </div>
            <?php endif; ?>

            <?php if (!empty($testRuns)): ?>
                <div class="data-section">
                    <h3>📝 Recent Test History (<?php echo count($testRuns); ?> total tests)</h3>
                    <div style="max-height: 200px; overflow-y: auto; background: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <?php foreach (array_slice($testRuns, 0, 10) as $run): ?>
                            <div style="padding: 8px; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280;">Test ID:</span> 
                                <strong><?php echo htmlspecialchars($run['waterdata_id']); ?></strong>
                                <span style="color: #9ca3af; margin-left: 15px;">
                                    <?php echo htmlspecialchars($run['timestamp']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-error">
                <strong>⚠️ Error:</strong> Please select a valid station ID in the URL. 
                Example: <code>http://10.140.22.16/water-monitoring/dashboard.php?station_id=41</code>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>📡 Powered by ESP32 IoT Sensors | XAMPP Server @ 10.140.22.16</p>
            <p style="margin-top: 5px; font-size: 12px;">
                Database: <?php echo htmlspecialchars($database); ?> | 
                Total Tests: <?php echo count($testRuns); ?> | 
                Station ID: <?php echo htmlspecialchars($station_id); ?>
            </p>
        </div>
    </div>
</body>
</html>
<?php 
// Close database connection
$conn->close(); 
?>