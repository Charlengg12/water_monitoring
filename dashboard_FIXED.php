<?php
// ------------------------------------------------------------------
// XAMPP DATABASE CONNECTION
// ------------------------------------------------------------------
$servername = "localhost";
$username = "root";           // Default XAMPP MySQL username
$password = "";               // Default XAMPP MySQL password (empty)
$database = "water_monitoring"; // Change this to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Get station_id from query string
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : 0;

/*----------------------------
  Handle ESP32 POST (JSON Data)
  ESP32 posts JSON payload to: dashboard.php?station_id=NNN
----------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['station_id']) && isset($_SERVER['CONTENT_TYPE'])) {
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]));

    if ($contentType === 'application/json' || $contentType === 'text/json') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if ($data && is_array($data)) {
            $sid = (int)$_GET['station_id'];

            // Map and sanitize values
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

            // Prepare INSERT
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
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
                    http_response_code(200);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'DB Insert failed: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'DB Prepare failed: ' . $conn->error]);
                http_response_code(500);
            }
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            http_response_code(400);
            exit;
        }
    }
}

// Fetch station info
$station = null;
if ($station_id) {
    $stmt = $conn->prepare("SELECT * FROM refilling_stations WHERE station_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $station_id);
        $stmt->execute();
        $station = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fetch latest water data
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Monitoring Dashboard - XAMPP</title>
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

        .station-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .station-info div {
            flex: 1;
            min-width: 200px;
        }

        .station-info strong {
            color: #555;
            display: block;
            margin-bottom: 5px;
        }

        .status-indicator {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-indicator.online {
            background-color: #10b981;
            color: white;
        }

        .status-indicator.offline {
            background-color: #ef4444;
            color: white;
        }

        .status-indicator.connecting {
            background-color: #f59e0b;
            color: white;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .gauge-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .gauge {
            background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #e5e7eb;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .gauge:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .gauge h3 {
            margin: 0 0 15px 0;
            color: #374151;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .gauge-value {
            font-size: 36px;
            font-weight: bold;
            color: #1f2937;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
        }

        .gauge-status {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            font-size: 14px;
            text-transform: uppercase;
        }

        .gauge-status.safe {
            background-color: #10b981;
            color: white;
        }

        .gauge-status.neutral {
            background-color: #3b82f6;
            color: white;
        }

        .gauge-status.warning {
            background-color: #f59e0b;
            color: white;
        }

        .gauge-status.failed {
            background-color: #ef4444;
            color: white;
        }

        .gauge-status.connecting,
        .gauge-status.loading {
            background-color: #6b7280;
            color: white;
        }

        #last-update-display {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
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
        </div>

        <?php if ($station): ?>
            <div class="station-info">
                <div>
                    <strong>Station Name:</strong>
                    <?php echo htmlspecialchars($station['station_name'] ?? 'Unknown Station'); ?>
                </div>
                <div>
                    <strong>Sensor ID:</strong>
                    <?php echo htmlspecialchars($station['device_sensor_id'] ?? 'N/A'); ?>
                </div>
                <div>
                    <strong>Connection Status:</strong>
                    <span id="overall-connection-status" class="status-indicator connecting">CONNECTING...</span>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                <strong>⚠️ Error:</strong> Please select a valid station ID in the URL (e.g., ?station_id=41)
            </div>
        <?php endif; ?>

        <div class="gauge-container">
            <div class="gauge">
                <h3>💧 TDS</h3>
                <div id="tdsValueDisplay" class="gauge-value">---</div>
                <div style="color: #6b7280; font-size: 12px; margin: 5px 0;">mg/L</div>
                <span id="tdsStatusDisplay" class="gauge-status loading">Loading...</span>
                <div id="tdsIndicator" style="display:none;"></div>
            </div>

            <div class="gauge">
                <h3>🧪 pH Level</h3>
                <div id="phValueDisplay" class="gauge-value">---</div>
                <div style="color: #6b7280; font-size: 12px; margin: 5px 0;">pH Units</div>
                <span id="phStatusDisplay" class="gauge-status loading">Loading...</span>
                <div id="phIndicator" style="display:none;"></div>
            </div>

            <div class="gauge">
                <h3>🌊 Turbidity</h3>
                <div id="turbidityValueDisplay" class="gauge-value">---</div>
                <div style="color: #6b7280; font-size: 12px; margin: 5px 0;">NTU</div>
                <span id="turbidityStatusDisplay" class="gauge-status loading">Loading...</span>
                <div id="turbidityIndicator" style="display:none;"></div>
            </div>

            <div class="gauge">
                <h3>⚠️ Lead</h3>
                <div id="leadValueDisplay" class="gauge-value">---</div>
                <div style="color: #6b7280; font-size: 12px; margin: 5px 0;">mg/L</div>
                <span id="leadStatusDisplay" class="gauge-status loading">Loading...</span>
                <div id="leadIndicator" style="display:none;"></div>
            </div>

            <div class="gauge">
                <h3>🎨 Color</h3>
                <div id="colorValueDisplay" class="gauge-value">---</div>
                <div style="color: #6b7280; font-size: 12px; margin: 5px 0;">Result</div>
                <span id="colorStatusDisplay" class="gauge-status loading">Loading...</span>
                <div id="colorIndicator" style="display:none;"></div>
            </div>
        </div>

        <div id="last-update-display">⏳ Waiting for ESP32 data...</div>

        <div class="footer">
            <p>📡 Powered by ESP32 IoT Sensors | Local XAMPP Server</p>
            <p style="margin-top: 5px; font-size: 12px;">Data refreshes every 5 seconds</p>
        </div>
    </div>

    <script src="scrip.js"></script>
</body>
</html>
<?php $conn->close(); ?>