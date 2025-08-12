<?php
session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Dan19060..');
define('DB_NAME', 'prueba_db'); // ¡CAMBIA ESTO POR EL NOMBRE REAL DE TU BASE DE DATOS!

// Nombre del sitio (para el footer, emails, etc.)
define('SITE_NAME', 'Noxiew');

// URL base del sitio (para enlaces y redirecciones)
// Asegúrate de que termina con un '/' si estás en el directorio raíz o en un subdirectorio
define('BASE_URL', 'http://localhost/Noxiew/'); // Actualizada según tu estructura

// Conexión a la base de datos
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if ($mysqli->connect_errno) {
    die("Error de conexión a la base de datos: " . $mysqli->connect_error);
}

// Establecer el juego de caracteres a UTF8
$mysqli->set_charset("utf8mb4");

// Funciones de utilidad

/**
 * Redirige a una URL específica.
 * @param string $url La URL a la que redirigir.
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

/**
 * Verifica si el usuario está logueado.
 * @return bool True si el usuario está logueado, false de lo contrario.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Sanitiza una entrada de usuario para prevenir XSS.
 * @param string $data La cadena de entrada a sanitizar.
 * @return string La cadena sanitizada.
 */
function sanitize_input($data) {
    global $mysqli;
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Carga los datos del usuario desde la base de datos a la sesión.
 * @param int $user_id El ID del usuario.
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 */
function loadUserData($user_id, $mysqli) {
    $stmt = $mysqli->prepare("SELECT id, username, email, avatar, theme, membership_type, first_name, last_name, `rank`, status, unban_date, created_at FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['avatar'] = $user['avatar'];
        $_SESSION['theme'] = $user['theme'];
        $_SESSION['membership_type'] = $user['membership_type'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['rank'] = $user['rank'];
        $_SESSION['status'] = $user['status'];
        $_SESSION['unban_date'] = $user['unban_date'];
        $_SESSION['created_at'] = $user['created_at'];

        // Comprobar y actualizar estado de suspensión si la fecha de desbaneo ha pasado
        if ($user['status'] === 'suspended' && !empty($user['unban_date'])) {
            $unban_datetime = new DateTime($user['unban_date']);
            $current_datetime = new DateTime();
            if ($current_datetime > $unban_datetime) {
                $update_status_stmt = $mysqli->prepare("UPDATE usuarios SET status = 'active', unban_date = NULL WHERE id = ?");
                $update_status_stmt->bind_param("i", $user_id);
                $update_status_stmt->execute();
                $_SESSION['status'] = 'active';
                $_SESSION['unban_date'] = NULL;
            }
        }
    } else {
        // Si por alguna razón el usuario no se encuentra, destruir sesión
        session_destroy();
        redirect('login.php');
    }
}

/**
 * Inserta una nueva notificación en la base de datos.
 * @param int $user_id El ID del usuario que recibirá la notificación.
 * @param string $message El contenido de la notificación.
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 * @return bool True si la notificación se insertó correctamente, false de lo contrario.
 */
function insertNotification($user_id, $message, $mysqli) {
    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $message);
        return $stmt->execute();
    }
    return false;
}

/**
 * Obtiene las notificaciones para un usuario.
 * @param int $user_id El ID del usuario.
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 * @param bool $unread_only Si es true, solo obtiene notificaciones no leídas.
 * @return array Un array de notificaciones.
 */
function getNotifications($user_id, $mysqli, $unread_only = false) {
    $query = "SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ?";
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    $query .= " ORDER BY created_at DESC, id DESC LIMIT 10"; // Obtener las 10 últimas

    $stmt = $mysqli->prepare($query);
    $notifications = [];
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    return $notifications;
}

/**
 * Marca una o todas las notificaciones de un usuario como leídas.
 * @param int $user_id El ID del usuario.
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 * @param int|null $notification_id El ID de la notificación a marcar como leída (si es null, marca todas).
 * @return bool True si se marcó correctamente, false de lo contrario.
 */
function markNotificationAsRead($user_id, $mysqli, $notification_id = null) {
    if ($notification_id) {
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $notification_id);
            return $stmt->execute();
        }
    } else {
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            return $stmt->execute();
        }
    }
    return false;
}

/**
 * Elimina una o todas las notificaciones de un usuario.
 * @param int $user_id El ID del usuario.
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 * @param int|null $notification_id El ID de la notificación a eliminar (si es null, elimina todas).
 * @return bool True si se eliminó correctamente, false de lo contrario.
 */
function deleteNotification($user_id, $mysqli, $notification_id = null) {
    if ($notification_id) {
        $stmt = $mysqli->prepare("DELETE FROM notifications WHERE user_id = ? AND id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $notification_id);
            return $stmt->execute();
        }
    } else {
        $stmt = $mysqli->prepare("DELETE FROM notifications WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            return $stmt->execute();
        }
    }
    return false;
}

// Puedes añadir más funciones de utilidad según necesites
?>
