<?php
require_once 'includes/config.php';

// Verificar si está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['theme']) && in_array($input['theme'], ['light', 'dark'])) {
    $theme = $input['theme'];
    $user_id = $_SESSION['user_id'];
    
    // Actualizar tema en la base de datos
    $stmt = $mysqli->prepare("UPDATE usuarios SET theme = ? WHERE id = ?");
    $stmt->bind_param("si", $theme, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['theme'] = $theme;
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid theme']);
}
?>