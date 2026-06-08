<?php
// API endpoint for kiosk to verify intern QR code and fetch face data.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/db.php';
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid intern ID']);
    exit;
}

$stmt = $db->prepare("SELECT id, first_name, last_name, profile_photo, face_embedding FROM interns WHERE id = ? AND status = 'Active'");
$stmt->bind_param('i', $id);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$intern) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Active intern not found']);
    exit;
}

if (empty($intern['face_embedding'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Intern face profile not registered']);
    exit;
}

// Stored embedding is already a JSON array string
$faceEmbedding = json_decode($intern['face_embedding'], true);

$profilePhotoUrl = null;
if (!empty($intern['profile_photo'])) {
    $profilePhotoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/uploads/photos/' . $intern['profile_photo'];
}

echo json_encode([
    'ok' => true,
    'id' => $intern['id'],
    'first_name' => $intern['first_name'],
    'last_name' => $intern['last_name'],
    'name' => $intern['first_name'] . ' ' . $intern['last_name'],
    'profile_photo' => $profilePhotoUrl,
    'face_embedding' => $faceEmbedding
]);
