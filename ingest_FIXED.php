<?php
// ------------------------------------------------------------------
// XAMPP DATABASE CONNECTION
// ------------------------------------------------------------------
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "water_monitoring";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    http_response_code(500);
    exit;
}

$conn->set_charset("utf8mb4");

// ------------------------------------------------------------------
// Handle ESP32 POST Request
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($_GET['station_id'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid data or missing station_id']);
        http_response_code(400);
        exit;
    }

    $station_id = (int)$_GET['station_id'];
    $sensorId = $data['sensorId'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO water_data
        (station_id, sensor_id, tds_value, ph_value, turbidity_value,
         lead_value, color_value, tds_status, ph_status, turbidity_status,
         lead_status, color_status, color_result, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "issddddssssss",
        $station_id,
        $sensorId,
        $data['tds_val'],
        $data['ph_val'],
        $data['turbidity_val'],
        $data['lead_val'],
        $data['color_val'],
        $data['tds_status'],
        $data['ph_status'],
        $data['turbidity_status'],
        $data['lead_status'],
        $data['color_status'],
        $data['color_result']
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data saved']);
        http_response_code(200);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
        http_response_code(500);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    http_response_code(405);
}

$conn->close();
?>