<?php
session_start();

// Configuración de la base de datos (Adaptable para local y Heroku)
// Detecta si está en un entorno Heroku (normalmente definido por la variable 'DYNO')
if (getenv('DATABASE_URL')) {
    // Estamos en Heroku, usa la URL de la base de datos proporcionada por Heroku (Heroku Postgres)
    $db_url = parse_url(getenv("DATABASE_URL"));

    define('DB_HOST', $db_url["host"]);
    define('DB_USER', $db_url["user"]);
    define('DB_PASS', $db_url["pass"]);
    define('DB_NAME', substr($db_url["path"], 1)); // Elimina la barra inicial del path
    define('DB_PORT', isset($db_url["port"]) ? $db_url["port"] : 5432); // 5432 es el puerto por defecto de PostgreSQL

    // Conexión PDO para PostgreSQL en Heroku
    try {
        $mysqli = new PDO(
            "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $mysqli->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("Error de conexión a la base de datos Heroku: " . $e->getMessage());
        die("Error de conexión a la base de datos (Heroku): " . $e->getMessage());
    }

} else {
    // Estamos en desarrollo local, usa tus credenciales de MySQL (XAMPP/WAMP/MAMP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', 'Dan19060..'); // Tu contraseña de MySQL
    define('DB_NAME', 'prueba_db'); // Nombre correcto de tu base de datos local
    define('DB_PORT', 3306); // Puerto por defecto de MySQL

    // Configurar reporte de errores para desarrollo
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Conexión MySQL con mysqli
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        // Verificar conexión
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        // Establecer charset UTF-8
        $mysqli->set_charset("utf8mb4"); // Usa utf8mb4 para compatibilidad completa con emojis y otros caracteres
        
    } catch (Exception $e) {
        // Registro del error (en producción, enviar a log file)
        error_log("Database connection error (local): " . $e->getMessage());
        
        // Mostrar mensaje amigable al usuario
        die("
            <div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px; border: 1px solid #f5c6cb;'>
                <h3>Error de conexión a la base de datos local</h3>
                <p><strong>Mensaje:</strong> {$e->getMessage()}</p>
                <h4>Posibles soluciones:</h4>
                <ul>
                    <li>Verificar que XAMPP esté ejecutándose</li>
                    <li>Comprobar que MySQL esté iniciado</li>
                    <li>Verificar credenciales de la base de datos en config.php</li>
                    <li>Asegurar que la base de datos '" . DB_NAME . "' existe</li>
                </ul>
            </div>
        ");
    }
}

// Configuración global del sitio
define('SITE_NAME', 'Noxiew'); // Nombre del sitio
define('BASE_URL', 'http://localhost/Noxiew/'); // URL base del sitio para entorno local

// Funciones de utilidad

/**
 * Redirige a una URL específica.
 * @param string $url La URL a la que redirigir.
 */
function redirect($url) {
    // En Heroku, BASE_URL podría no ser necesaria si los enlaces son relativos
    // o si el buildpack de Heroku maneja las redirecciones.
    // Para asegurar compatibilidad, usaremos la URL completa si está disponible.
    // Aunque para Heroku, los enlaces en HTML/JS deberían ser relativos o absolutos sin dominio.
    header("Location: " . $url); // A menudo la URL ya es completa o relativa al root
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
 * Sanitiza una entrada de usuario para prevenir XSS e inyección SQL.
 * @param string|array $data La cadena o array de entrada a sanitizar.
 * @return string|array La cadena o array sanitizada.
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    // Determinar qué objeto de conexión usar (mysqli o PDO)
    global $mysqli; 
    
    // Si $mysqli es un objeto PDO, no usar real_escape_string. Solo usar htmlspecialchars y strip_tags.
    if ($mysqli instanceof PDO) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    } else { // Asume que es un objeto mysqli
        return htmlspecialchars(strip_tags(trim($mysqli->real_escape_string($data))), ENT_QUOTES, 'UTF-8');
    }
}


/**
 * Carga los datos del usuario desde la base de datos a la sesión.
 * Compatible con mysqli y PDO.
 * @param int $user_id El ID del usuario.
 * @param mixed $mysqli_conn Objeto de conexión a la base de datos (mysqli o PDO).
 */
function loadUserData($user_id, $mysqli_conn) {
    $user = null;
    if ($mysqli_conn instanceof PDO) {
        $stmt = $mysqli_conn->prepare("SELECT id, username, email, avatar, theme, membership_type, first_name, last_name, `rank`, status, unban_date, created_at, premium_start_date, premium_end_date, premium_granted_by_admin_id FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else { // Asume mysqli
        $stmt = $mysqli_conn->prepare("SELECT id, username, email, avatar, theme, membership_type, first_name, last_name, `rank`, status, unban_date, created_at, premium_start_date, premium_end_date, premium_granted_by_admin_id FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }

    if ($user) {
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
        $_SESSION['premium_start_date'] = $user['premium_start_date'];
        $_SESSION['premium_end_date'] = $user['premium_end_date'];
        $_SESSION['premium_granted_by_admin_id'] = $user['premium_granted_by_admin_id'];

        // Comprobar y actualizar estado de suspensión si la fecha de desbaneo ha pasado
        if ($user['status'] === 'suspended' && !empty($user['unban_date'])) {
            $unban_datetime = new DateTime($user['unban_date']);
            $current_datetime = new DateTime();
            if ($current_datetime > $unban_datetime) {
                // Si la fecha de desbaneo ha pasado, actualiza a 'active'
                if ($mysqli_conn instanceof PDO) {
                    $update_status_stmt = $mysqli_conn->prepare("UPDATE usuarios SET status = 'active', unban_date = NULL WHERE id = ?");
                    $update_status_stmt->execute([$user_id]);
                } else { // Asume mysqli
                    $update_status_stmt = $mysqli_conn->prepare("UPDATE usuarios SET status = 'active', unban_date = NULL WHERE id = ?");
                    $update_status_stmt->bind_param("i", $user_id);
                    $update_status_stmt->execute();
                }
                $_SESSION['status'] = 'active';
                $_SESSION['unban_date'] = NULL;
            }
        }
    } else {
        // Si por alguna razón el usuario no se encuentra, destruir sesión
        session_destroy();
        // Redirige usando la URL base correcta si es posible.
        header("Location: http://localhost/Noxiew/login.php"); // O tu login.php
        exit();
    }
}

/**
 * Obtiene las notificaciones para un usuario.
 * ESTA FUNCIÓN SE USA EN header.php para el conteo de notificaciones no leídas.
 * @param int $user_id El ID del usuario.
 * @param mixed $mysqli_conn Objeto de conexión a la base de datos (mysqli o PDO).
 * @param bool $unread_only Si es true, solo obtiene notificaciones no leídas.
 * @return array Un array de notificaciones.
 */
function getNotifications($user_id, $mysqli_conn, $unread_only = false) {
    $query = "SELECT id, message, details, is_read, created_at FROM notifications WHERE user_id = ?";
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    // Limitamos a 10 para el dropdown, la página completa tiene su propia paginación.
    $query .= " ORDER BY created_at DESC, id DESC LIMIT 10"; 

    $notifications = [];
    if ($mysqli_conn instanceof PDO) {
        $stmt = $mysqli_conn->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else { // Asume mysqli
        $stmt = $mysqli_conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    foreach ($result as $row) {
        if (!empty($row['details'])) {
            $row['details'] = json_decode($row['details'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("ERROR: JSON decoding error in getNotifications: " . json_last_error_msg());
                $row['details'] = null;
            }
        } else {
            $row['details'] = null;
        }
        $notifications[] = $row;
    }
    return $notifications;
}
