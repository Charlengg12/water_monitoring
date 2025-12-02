<?php
// ------------------------------------------------------------------
// XAMPP DATABASE CONNECTION
// ------------------------------------------------------------------
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$servername = "localhost";
$username = "root";
$password = "";
$database = "water_monitoring";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    http_response_code(500);
    exit;
}

$conn->set_charset("utf8mb4");

// ------------------------------------------------------------------
// Get Latest Sensor Readings
// ------------------------------------------------------------------
if (!isset($_GET['station_id'])) {
    echo json_encode(['error' => 'No station_id provided']);
    http_response_code(400);
    exit;
}

$station_id = (int)$_GET['station_id'];

$stmt = $conn->prepare("
    SELECT
        tds_value, ph_value, turbidity_value, lead_value, color_value,
        tds_status, ph_status, turbidity_status, lead_status, color_status,
        color_result, timestamp
    FROM water_data
    WHERE station_id = ?
    ORDER BY timestamp DESC
    LIMIT 1
");

$stmt->bind_param('i', $station_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if ($data) {
    // Match ESP32 JSON format with uppercase keys
    echo json_encode([
        'TDS_Value' => (float)$data['tds_value'],
        'PH_Value' => (float)$data['ph_value'],
        'Turbidity_Value' => (float)$data['turbidity_value'],
        'Lead_Value' => (float)$data['lead_value'],
        'Color_Value' => (float)$data['color_value'],
        'TDS_Status' => $data['tds_status'],
        'PH_Status' => $data['ph_status'],
        'Turbidity_Status' => $data['turbidity_status'],
        'Lead_Status' => $data['lead_status'],
        'Color_Status' => $data['color_status'],
        'Color_Result' => $data['color_result'],
        'timestamp' => $data['timestamp']
    ]);
    http_response_code(200);
} else {
    echo json_encode(['error' => 'No data found']);
    http_response_code(404);
}

$conn->close();
?>