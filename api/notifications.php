<?php
error_log("DEBUG: api/notifications.php accessed.");
require_once '../includes/config.php'; // Ajusta la ruta si es necesario

header('Content-Type: application/json');

if (!isLoggedIn()) {
    error_log("DEBUG: User not logged in. Exiting.");
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$user_id = $_SESSION['user_id'];
// Para obtener la acción, verifica tanto POST (para mark_read/all_read) como GET (para get)
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? null);

error_log("DEBUG: Received action: " . ($action ?? 'NULL'));
error_log("DEBUG: Received input (from JSON POST body): " . print_r($input, true));
error_log("DEBUG: Received GET parameters: " . print_r($_GET, true));


// Funciones de notificaciones (usan datos reales de la base de datos)
function getNotificationsFromDB($userId, $mysqli, $unreadOnly = false, $offset = 0, $limit = 100, $searchQuery = null) {
    error_log("DEBUG: getNotificationsFromDB called with userId: $userId, unreadOnly: " . ($unreadOnly ? 'true' : 'false') . ", offset: $offset, limit: $limit, searchQuery: " . ($searchQuery ?? 'NULL'));
    $notifications = [];
    
    $query = "SELECT id, message, details, is_read, created_at FROM notifications WHERE user_id = ?";
    $params_types = "i";
    $params_values = [$userId];

    if ($unreadOnly) {
        $query .= " AND is_read = 0";
    }
    
    if ($searchQuery) {
        $query .= " AND message LIKE ?";
        $params_types .= "s";
        // No es necesario real_escape_string si se usa bind_param, ya que bind_param lo escapa.
        $params_values[] = "%" . $searchQuery . "%"; 
    }

    $query .= " ORDER BY created_at DESC LIMIT ?, ?";
    $params_types .= "ii";
    $params_values[] = $offset;
    $params_values[] = $limit;
    
    $stmt = $mysqli->prepare($query);

    // Bind parameters dinámicamente
    // Usar una referencia a los elementos de $params_values para bind_param
    $stmt_bind_params = [];
    foreach ($params_values as $key => $value) {
        $stmt_bind_params[$key] = &$params_values[$key];
    }
    // call_user_func_array es necesario porque bind_param no acepta arrays directamente en versiones antiguas de PHP,
    // y requiere referencias para los valores de los parámetros.
    call_user_func_array([$stmt, 'bind_param'], array_merge([$params_types], $stmt_bind_params));

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['details'])) {
            $row['details'] = json_decode($row['details'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("ERROR: JSON decoding error for notification ID " . $row['id'] . ": " . json_last_error_msg());
                $row['details'] = null;
            }
        } else {
            $row['details'] = null;
        }
        $notifications[] = $row;
    }
    $stmt->close();
    error_log("DEBUG: Real DB notifications fetched: " . count($notifications));
    
    return array_values($notifications); 
}

/**
 * Añade una nueva notificación a la base de datos.
 * @param int $userId ID del usuario al que va dirigida la notificación.
 * @param string $message Mensaje principal de la notificación.
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 * @param array|null $details Array asociativo con detalles adicionales (se guardará como JSON).
 * @return bool True si la notificación se añadió correctamente, False en caso contrario.
 */
function createNotification($userId, $message, $mysqli, $details = null) {
    error_log("DEBUG: createNotification called for userId: $userId, message: $message");
    
    $details_json = null;
    if ($details !== null && is_array($details)) {
        $details_json = json_encode($details);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERROR: JSON encoding error for notification details: " . json_last_error_msg());
            $details_json = null; 
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, details, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $userId, $message, $details_json);
    $success = $stmt->execute();
    $stmt->close();
    error_log("DEBUG: createNotification success: " . ($success ? 'true' : 'false'));
    return $success;
}


function markNotificationAsReadInDB($notificationId, $userId, $mysqli) {
    error_log("DEBUG: markNotificationAsReadInDB called for notificationId: $notificationId, userId: $userId");
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
    $success = $stmt->execute();
    $stmt->close();
    error_log("DEBUG: markNotificationAsReadInDB real DB update success: " . ($success ? 'true' : 'false'));
    return $success;
}

function markAllNotificationsAsReadInDB($userId, $mysqli) {
    error_log("DEBUG: markAllNotificationsAsReadInDB called for userId: $userId");
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $success = $stmt->execute();
    $stmt->close();
    error_log("DEBUG: markAllNotificationsAsReadInDB real DB update success: " . ($success ? 'true' : 'false'));
    return $success;
}


switch ($action) {
    case 'get':
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search_query = isset($_GET['q']) ? sanitize_input($_GET['q']) : null; // Nuevo parámetro de búsqueda
        
        $unread_only_param = isset($_GET['unreadOnly']) && $_GET['unreadOnly'] === 'true';
        
        $notifications_to_return = getNotificationsFromDB($user_id, $mysqli, $unread_only_param, $offset, $limit, $search_query);
        
        // Para obtener el conteo total de notificaciones no leídas (sin paginación, para el badge)
        // Aquí necesitamos obtener TODAS las no leídas para el conteo, sin límite de offset/limit
        $query_unread_count = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt_unread_count = $mysqli->prepare($query_unread_count);
        $stmt_unread_count->bind_param("i", $user_id);
        $stmt_unread_count->execute();
        $result_unread_count = $stmt_unread_count->get_result()->fetch_assoc();
        $unread_count = $result_unread_count['unread_count'];
        $stmt_unread_count->close();

        error_log("DEBUG: 'get' action - Returning " . count($notifications_to_return) . " notifications, unread count: $unread_count");
        echo json_encode(['success' => true, 'notifications' => $notifications_to_return, 'unread_count' => $unread_count]);
        break;

    case 'mark_read':
        $notification_id = $input['id'] ?? null;
        if ($notification_id && markNotificationAsReadInDB($notification_id, $user_id, $mysqli)) {
            error_log("DEBUG: 'mark_read' action - Success for ID: $notification_id");
            echo json_encode(['success' => true]);
        } else {
            error_log("ERROR: 'mark_read' action - Failed for ID: $notification_id");
            echo json_encode(['success' => false, 'message' => 'No se pudo marcar la notificación como leída.']);
        }
        break;

    case 'mark_all_read':
        if (markAllNotificationsAsReadInDB($user_id, $mysqli)) {
            error_log("DEBUG: 'mark_all_read' action - Success.");
            echo json_encode(['success' => true]);
        } else {
            error_log("ERROR: 'mark_all_read' action - Failed.");
            echo json_encode(['success' => false, 'message' => 'No se pudieron marcar todas las notificaciones como leídas.']);
        }
        break;

    // Nuevo caso para añadir notificaciones (si se llama desde otras partes del sistema)
    case 'create':
        $message_content = $input['message'] ?? null;
        $notification_details = $input['details'] ?? null;
        if ($message_content && createNotification($user_id, $message_content, $mysqli, $notification_details)) {
            error_log("DEBUG: 'create' action - Notification created for user $user_id.");
            echo json_encode(['success' => true, 'message' => 'Notificación creada con éxito.']);
        } else {
            error_log("ERROR: 'create' action - Failed to create notification for user $user_id.");
            echo json_encode(['success' => false, 'message' => 'Error al crear la notificación.']);
        }
        break;

    default:
        error_log("ERROR: Invalid action received: " . ($action ?? 'NULL'));
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
        break;
}
?>
