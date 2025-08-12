<?php
require_once 'includes/config.php';

// Verificar si está logueado y si tiene el rango de 'owner'
if (!isLoggedIn() || (isset($_SESSION['rank']) && $_SESSION['rank'] !== 'owner')) {
    redirect('home.php'); // Redirige a la página de inicio si no es owner o no está logueado
}

$message = ''; // Para mensajes de éxito o error
$command_input = ''; // Para mantener el último comando en el input

// Lógica para generar una key única
function generateUniqueKey($prefix = 'noxiew-premium-', $length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $prefix . $random_string;
}

// Lógica para procesar comandos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_command'])) {
    $command_input = trim($_POST['admin_command']);
    
    if (empty($command_input)) {
        $message = 'error:Por favor, introduce un comando.';
    } elseif (strpos($command_input, ':') !== 0) {
        $message = 'error:Los comandos deben empezar con dos puntos (:).';
    } else {
        // Eliminar los dos puntos iniciales y dividir el comando
        $command_parts = explode(' ', substr($command_input, 1));
        $action = strtolower($command_parts[0] ?? '');
        $target_id = intval($command_parts[1] ?? 0); // Este será el ID de usuario o el ID de la key

        switch ($action) {
            case 'ban':
            case 'unban':
            case 'delete':
            case 'removepremium':
            case 'grantpremium':
                if ($target_id <= 0) {
                    $message = 'error:ID de usuario no válido. Usa un número positivo.';
                    break;
                }
                $user_id_to_manage = $target_id; // Renombrar para claridad en estos casos
                
                if ($action === 'ban') {
                    $days = intval($command_parts[2] ?? 0);
                    if ($days === 0) {
                        $message = 'error:Para banear, el comando debe ser: :ban ID días (>0, >1000 para permanente).';
                        break;
                    }

                    if ($days > 1000) { // Ban permanente
                        $stmt = $mysqli->prepare("UPDATE usuarios SET status = 'banned', unban_date = NULL WHERE id = ?");
                        $stmt->bind_param("i", $user_id_to_manage);
                        if ($stmt->execute()) {
                            $message = "success:Usuario con ID " . htmlspecialchars($user_id_to_manage) . " ha sido baneado permanentemente por vDanier Owner.";
                            if ($user_id_to_manage == $_SESSION['user_id']) {
                                session_destroy();
                                redirect('login.php?banned=true');
                            }
                        } else {
                            $message = "error:Error al banear al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                        }
                    } else { // Ban temporal (suspensión)
                        $unban_date = date('Y-m-d H:i:s', strtotime("+" . $days . " days"));
                        $stmt = $mysqli->prepare("UPDATE usuarios SET status = 'suspended', unban_date = ? WHERE id = ?");
                        $stmt->bind_param("si", $unban_date, $user_id_to_manage);
                        if ($stmt->execute()) {
                            $message = "success:Usuario con ID " . htmlspecialchars($user_id_to_manage) . " ha sido suspendido por " . $days . " días por vDanier Owner. Se activará el " . date('d/m/Y H:i', strtotime($unban_date)) . ".";
                            if ($user_id_to_manage == $_SESSION['user_id']) {
                                session_destroy();
                                redirect('login.php?suspended=true');
                            }
                        } else {
                            $message = "error:Error al suspender al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                        }
                    }
                } elseif ($action === 'unban') {
                    $stmt = $mysqli->prepare("UPDATE usuarios SET status = 'active', unban_date = NULL WHERE id = ?");
                    $stmt->bind_param("i", $user_id_to_manage);
                    if ($stmt->execute()) {
                        $message = "success:Usuario con ID " . htmlspecialchars($user_id_to_manage) . " ha sido activado (desbaneado) por vDanier Owner.";
                    } else {
                        $message = "error:Error al desbanear al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                    }
                } elseif ($action === 'delete') {
                    // Primero, borramos su avatar si existe para evitar archivos huérfanos
                    $stmt_avatar = $mysqli->prepare("SELECT avatar FROM usuarios WHERE id = ?");
                    $stmt_avatar->bind_param("i", $user_id_to_manage);
                    $stmt_avatar->execute();
                    $avatar_result = $stmt_avatar->get_result()->fetch_assoc();
                    if ($avatar_result && !empty($avatar_result['avatar'])) {
                        $avatar_path = 'uploads/' . $avatar_result['avatar'];
                        if (file_exists($avatar_path)) {
                            unlink($avatar_path); // Eliminar el archivo del avatar
                        }
                    }
                    
                    $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->bind_param("i", $user_id_to_manage);
                    if ($stmt->execute()) {
                        $message = "success:Usuario con ID " . htmlspecialchars($user_id_to_manage) . " ha sido borrado permanentemente por vDanier Owner.";
                        if ($user_id_to_manage == $_SESSION['user_id']) {
                            session_destroy();
                            redirect('login.php?deleted=true');
                        }
                    } else {
                        $message = "error:Error al borrar al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                    }
                } elseif ($action === 'removepremium') {
                    // Limpia las fechas de premium y el admin que la otorgó
                    $stmt = $mysqli->prepare("UPDATE usuarios SET membership_type = 'free', premium_start_date = NULL, premium_end_date = NULL, premium_granted_by_admin_id = NULL, premium_key_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $user_id_to_manage);
                    if ($stmt->execute()) {
                        $message = "success:Membresía Premium retirada al usuario con ID " . htmlspecialchars($user_id_to_manage) . ". Ahora es Free.";
                    } else {
                        $message = "error:Error al quitar la membresía Premium al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                    }
                } elseif ($action === 'grantpremium') {
                    $premium_days = intval($command_parts[2] ?? 0); // Días para membresía temporal
                    $admin_id = $_SESSION['user_id']; // ID del admin que ejecuta el comando

                    $premium_start_date = date('Y-m-d H:i:s');
                    $premium_end_date = NULL; // Por defecto permanente

                    if ($premium_days > 0 && $premium_days <= 1000) { // Premium temporal si >0 y <=1000 días
                        $premium_end_date = date('Y-m-d H:i:s', strtotime("+" . $premium_days . " days"));
                    }

                    // Asegura que se active y desbanee, además de setear las fechas de premium
                    // premium_key_id se establece a NULL si es otorgado directamente por admin
                    $stmt = $mysqli->prepare("UPDATE usuarios SET membership_type = 'premium', status = 'active', unban_date = NULL, premium_start_date = ?, premium_end_date = ?, premium_granted_by_admin_id = ?, premium_key_id = NULL WHERE id = ?");
                    $stmt->bind_param("ssii", $premium_start_date, $premium_end_date, $admin_id, $user_id_to_manage);
                    
                    if ($stmt->execute()) {
                        if ($premium_end_date) {
                            $message = "success:Membresía Premium otorgada al usuario con ID " . htmlspecialchars($user_id_to_manage) . " por " . htmlspecialchars($premium_days) . " días. Vence el " . date('d/m/Y H:i', strtotime($premium_end_date)) . ".";
                            // Insert notification for the user who received premium
                            $notification_msg = "Tu membresía Premium ha sido otorgada por " . htmlspecialchars($premium_days) . " días. ¡Disfruta!";
                            insertNotification($user_id_to_manage, $notification_msg, $mysqli);
                        } else {
                            $message = "success:Membresía Premium otorgada permanentemente al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                            // Insert notification for the user who received permanent premium
                            $notification_msg = "Tu membresía Premium ha sido otorgada permanentemente. ¡Disfruta!";
                            insertNotification($user_id_to_manage, $notification_msg, $mysqli);
                        }
                    } else {
                        $message = "error:Error al otorgar membresía Premium al usuario con ID " . htmlspecialchars($user_id_to_manage) . ".";
                    }
                }
                break;

            case 'genkey': // Comando para generar keys
                $key_days = intval($target_id ?? 0); // target_id es los días aquí
                $key_string = generateUniqueKey();
                $created_by_admin_id = $_SESSION['user_id'];

                $stmt = $mysqli->prepare("INSERT INTO premium_keys (key_string, days, created_by_admin_id) VALUES (?, ?, ?)");
                $stmt->bind_param("sii", $key_string, $key_days, $created_by_admin_id);
                
                if ($stmt->execute()) {
                    $key_duration_text = ($key_days > 0) ? "por " . htmlspecialchars($key_days) . " días" : "permanente";
                    $message = "success:Key Premium generada: <code style='background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px; color: var(--text-primary);'>" . htmlspecialchars($key_string) . "</code> " . $key_duration_text . ".";
                } else {
                    $message = "error:Error al generar la key Premium. Inténtalo de nuevo.";
                }
                break;

            case 'deletekey': // Nuevo comando para borrar keys y revocar premium
                if ($target_id <= 0) {
                    $message = 'error:ID de Key no válido. Usa un número positivo.';
                    break;
                }
                $key_id_to_delete = $target_id;

                // 1. Obtener información de la key antes de borrarla
                $stmt_key_info = $mysqli->prepare("SELECT key_string, used, used_by_user_id FROM premium_keys WHERE id = ?");
                $stmt_key_info->bind_param("i", $key_id_to_delete);
                $stmt_key_info->execute();
                $key_data = $stmt_key_info->get_result()->fetch_assoc();

                if ($key_data) {
                    // 2. Borrar la key
                    $stmt_delete_key = $mysqli->prepare("DELETE FROM premium_keys WHERE id = ?");
                    $stmt_delete_key->bind_param("i", $key_id_to_delete);
                    
                    if ($stmt_delete_key->execute()) {
                        $message = "success:Key '" . htmlspecialchars($key_data['key_string']) . "' (ID: " . htmlspecialchars($key_id_to_delete) . ") ha sido borrada.";

                        // 3. Si la key estaba en uso, revocar membresía Premium al usuario
                        if ($key_data['used'] == 1 && !empty($key_data['used_by_user_id'])) {
                            $user_id_to_revoke = $key_data['used_by_user_id'];
                            $stmt_revoke_premium = $mysqli->prepare("UPDATE usuarios SET membership_type = 'free', premium_start_date = NULL, premium_end_date = NULL, premium_granted_by_admin_id = NULL, premium_key_id = NULL WHERE id = ?");
                            $stmt_revoke_premium->bind_param("i", $user_id_to_revoke);
                            if ($stmt_revoke_premium->execute()) {
                                $message .= " Membresía Premium revocada al usuario con ID " . htmlspecialchars($user_id_to_revoke) . ".";
                                // If the revoked user is logged in, update their session
                                if ($user_id_to_revoke == $_SESSION['user_id']) {
                                    $_SESSION['membership_type'] = 'free';
                                    $_SESSION['premium_start_date'] = NULL;
                                    $_SESSION['premium_end_date'] = NULL;
                                    $_SESSION['premium_granted_by_admin_id'] = NULL;
                                    $_SESSION['premium_key_id'] = NULL;
                                }
                                // Insert notification for the user who had premium revoked
                                $notification_msg = "Tu membresía Premium ha sido revocada.";
                                insertNotification($user_id_to_revoke, $notification_msg, $mysqli);
                            } else {
                                $message .= " Error al revocar la membresía Premium del usuario con ID " . htmlspecialchars($user_id_to_revoke) . ".";
                            }
                        }
                    } else {
                        $message = "error:Error al borrar la key con ID " . htmlspecialchars($key_id_to_delete) . ".";
                    }
                } else {
                    $message = "error:Key con ID " . htmlspecialchars($key_id_to_delete) . " no encontrada.";
                }
                break;

            default:
                $message = 'error:Comando desconocido. Comandos válidos: :ban ID días, :unban ID, :delete ID, :removepremium ID, :grantpremium ID [días], :genkey [días], :deletekey ID.';
                break;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="hero">
        <h1 class="hero-title"><i class="fas fa-user-shield"></i> Panel de Administración</h1>
        <p class="hero-subtitle">Gestión de usuarios y configuraciones del sistema (Solo para Owners)</p>
    </div>

    <?php if ($message): ?>
        <?php
        $message_parts = explode(':', $message, 2); 
        $type = $message_parts[0];
        $text = $message_parts[1] ?? '';
        ?>
        <div class="message message-<?php echo $type; ?>">
            <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $text; ?>
        </div>
    <?php endif; ?>
    
    <!-- Sección de Comandos de Administración -->
    <div class="settings-section">
        <h2><i class="fas fa-terminal"></i> Ejecutar Comandos</h2>
        
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Introduce un comando para gestionar usuarios o generar keys. Ejemplos:</p>
        <ul style="list-style-type: disc; margin-left: 20px; color: var(--text-secondary); margin-bottom: 1.5rem;">
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:ban 2 10</code> - Banea al usuario con ID 2 por 10 días.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:ban 5 100000</code> - Banea permanentemente al usuario con ID 5 (cualquier número >= 100000 es permanente).</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:unban 2</code> - Desbanea/activa al usuario con ID 2.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:delete 3</code> - Borra la cuenta del usuario con ID 3 (¡irreversible!).</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:removepremium 4</code> - Quita la membresía Premium al usuario con ID 4.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:grantpremium 6</code> - Otorga membresía Premium permanente al usuario con ID 6.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:grantpremium 7 30</code> - Otorga membresía Premium al usuario con ID 7 por 30 días.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:genkey</code> - Genera una key Premium permanente.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:genkey 90</code> - Genera una key Premium por 90 días.</li>
            <li><code style="background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;">:deletekey 1</code> - Borra la key con ID 1 y revoca la membresía si está en uso.</li>
        </ul>

        <form method="POST" class="admin-action-form">
            <div class="form-group">
                <label for="admin_command" class="form-label">Comando de Administración</label>
                <input type="text" id="admin_command" name="admin_command" class="form-input" placeholder="Ej: :genkey 30" required value="<?php echo htmlspecialchars($command_input); ?>" autocomplete="off">
                <div id="command-suggestions" class="command-suggestions"></div> <!-- Nuevo div para sugerencias -->
            </div>
            <button type="submit" class="btn btn-primary" style="background-color: var(--accent-primary);">
                <i class="fas fa-play-circle"></i> Ejecutar Comando
            </button>
        </form>
    </div>
    
    <!-- Sección para ver la lista de usuarios -->
    <div class="settings-section" style="margin-top: 2rem;">
        <h2><i class="fas fa-list"></i> Ver Lista de Usuarios</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Aquí puedes ver un listado de todos los usuarios y su estado actual.</p>
        <div class="table-container-custom-scroll"> <!-- Nuevo contenedor con barra de desplazamiento personalizada -->
            <table style='width:100%; border-collapse: collapse; margin-top: 1rem;'>
                <thead>
                    <tr style='background-color: var(--bg-tertiary);'>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>ID</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Usuario</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Email</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Membresía</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Rango</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Estado</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Tiempo Restante / Fecha Desbaneo</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Registro</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Premium Inicio</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Premium Fin</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Admin Premium</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Key Premium ID</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $users_query = $mysqli->query("SELECT id, username, email, membership_type, `rank`, status, unban_date, created_at, premium_start_date, premium_end_date, premium_granted_by_admin_id, premium_key_id FROM usuarios ORDER BY id ASC");
                if ($users_query->num_rows > 0) {
                    while ($user_row = $users_query->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . htmlspecialchars($user_row['id']) . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-primary);'>" . htmlspecialchars($user_row['username']) . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . htmlspecialchars($user_row['email']) . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color);'><span class='membership-badge membership-" . htmlspecialchars($user_row['membership_type']) . "'>" . ucfirst(htmlspecialchars($user_row['membership_type'])) . "</span></td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color);'><span class='user-rank-badge' style='background-color: " . ($user_row['rank'] === 'owner' ? 'var(--rank-owner-bg)' : 'var(--text-tertiary)') . "; color: " . ($user_row['rank'] === 'owner' ? 'var(--rank-owner-text)' : 'white') . ";'>" . ucfirst(htmlspecialchars($user_row['rank'])) . "</span></td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-primary); font-weight: 600; text-transform: capitalize;'>" . htmlspecialchars($user_row['status']) . "</td>";
                        
                        // Calculate and display remaining time
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>";
                        if ($user_row['status'] === 'suspended' && !empty($user_row['unban_date'])) {
                            $unban_datetime = new DateTime($user_row['unban_date']);
                            $current_datetime = new DateTime();
                            if ($unban_datetime > $current_datetime) {
                                $interval = $current_datetime->diff($unban_datetime);
                                $remaining_time = [];
                                if ($interval->y > 0) $remaining_time[] = $interval->y . ' año' . ($interval->y > 1 ? 's' : '');
                                if ($interval->m > 0) $remaining_time[] = $interval->m . ' mes' . ($interval->m > 1 ? 'es' : '');
                                if ($interval->d > 0) $remaining_time[] = $interval->d . ' día' . ($interval->d > 1 ? 's' : '');
                                if ($interval->h > 0) $remaining_time[] = $interval->h . ' hora' . ($interval->h > 1 ? 's' : '');
                                if ($interval->i > 0) $remaining_time[] = $interval->i . ' minuto' . ($interval->i > 1 ? 's' : '');
                                
                                echo empty($remaining_time) ? 'Menos de 1 minuto' : implode(', ', $remaining_time);
                            } else {
                                echo 'Expirado (necesita loguearse para actualizar)'; 
                            }
                        } elseif ($user_row['status'] === 'banned') {
                            echo 'Permanente';
                        } else {
                            echo 'N/A'; // Not applicable for active users
                        }
                        echo "</td>";

                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . date('d/m/Y', strtotime($user_row['created_at'])) . "</td>";
                        
                        // Display Premium Start Date
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>";
                        echo !empty($user_row['premium_start_date']) ? date('d/m/Y H:i', strtotime($user_row['premium_start_date'])) : 'N/A';
                        echo "</td>";

                        // Display Premium End Date
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>";
                        echo !empty($user_row['premium_end_date']) ? date('d/m/Y H:i', strtotime($user_row['premium_end_date'])) : 'N/A';
                        echo "</td>";

                        // Display Premium Granted by Admin (fetch username)
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>";
                        if (!empty($user_row['premium_granted_by_admin_id'])) {
                            $admin_id_display = htmlspecialchars($user_row['premium_granted_by_admin_id']);
                            // Try to fetch admin username
                            $admin_stmt = $mysqli->prepare("SELECT username FROM usuarios WHERE id = ?");
                            $admin_stmt->bind_param("i", $user_row['premium_granted_by_admin_id']);
                            $admin_stmt->execute();
                            $admin_result = $admin_stmt->get_result()->fetch_assoc();
                            if ($admin_result) {
                                echo htmlspecialchars($admin_result['username']) . " (ID: " . $admin_id_display . ")";
                            } else {
                                echo "ID: " . $admin_id_display;
                            }
                        } else {
                            echo "N/A";
                        }
                        echo "</td>";

                        // Display Premium Key ID
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>";
                        echo !empty($user_row['premium_key_id']) ? htmlspecialchars($user_row['premium_key_id']) : 'N/A';
                        echo "</td>";

                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='12' style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary); text-align: center;'>No hay usuarios registrados.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Nueva Sección para ver la lista de Keys Premium -->
    <div class="settings-section" style="margin-top: 2rem;">
        <h2><i class="fas fa-key"></i> Lista de Keys Premium</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Aquí puedes ver todas las keys Premium generadas y su estado.</p>
        <div class="table-container-custom-scroll"> <!-- Nuevo contenedor con barra de desplazamiento personalizada -->
            <table style='width:100%; border-collapse: collapse; margin-top: 1rem;'>
                <thead>
                    <tr style='background-color: var(--bg-tertiary);'>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>ID Key</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Key</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Días</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Usada</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Usada por ID</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Fecha Uso</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Fecha Creación</th>
                        <th style='padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; color: var(--text-primary);'>Creada por Admin ID</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $keys_query = $mysqli->query("SELECT id, key_string, days, used, used_by_user_id, used_at, created_at, created_by_admin_id FROM premium_keys ORDER BY created_at DESC");
                if ($keys_query->num_rows > 0) {
                    while ($key_row = $keys_query->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . htmlspecialchars($key_row['id']) . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-primary);'><code style='background-color: var(--bg-tertiary); padding: 2px 4px; border-radius: 4px;'>" . htmlspecialchars($key_row['key_string']) . "</code></td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . htmlspecialchars($key_row['days']) . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-primary); font-weight: 600;'>" . ($key_row['used'] ? 'Sí' : 'No') . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . (!empty($key_row['used_by_user_id']) ? htmlspecialchars($key_row['used_by_user_id']) : 'N/A') . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . (!empty($key_row['used_at']) ? date('d/m/Y H:i', strtotime($key_row['used_at'])) : 'N/A') . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>" . date('d/m/Y H:i', strtotime($key_row['created_at'])) . "</td>";
                        echo "<td style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary);'>";
                        if (!empty($key_row['created_by_admin_id'])) {
                            $creator_admin_id = htmlspecialchars($key_row['created_by_admin_id']);
                            $creator_admin_stmt = $mysqli->prepare("SELECT username FROM usuarios WHERE id = ?");
                            $creator_admin_stmt->bind_param("i", $key_row['created_by_admin_id']);
                            $creator_admin_stmt->execute();
                            $creator_admin_result = $creator_admin_stmt->get_result()->fetch_assoc();
                            if ($creator_admin_result) {
                                echo htmlspecialchars($creator_admin_result['username']) . " (ID: " . $creator_admin_id . ")";
                            } else {
                                echo "ID: " . $creator_admin_id;
                            }
                        } else {
                            echo "N/A";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' style='padding: 0.75rem; border: 1px solid var(--border-color); color: var(--text-secondary); text-align: center;'>No hay keys Premium generadas.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Estilos para la barra de desplazamiento personalizada */
.table-container-custom-scroll {
    overflow-x: auto; /* Permite el desplazamiento horizontal */
    width: 100%; /* Asegura que ocupe el ancho disponible */
    /* Personalización de la barra de desplazamiento para navegadores basados en WebKit (Chrome, Safari) */
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: var(--accent-primary) var(--bg-tertiary); /* Firefox */
}

/* Para WebKit (Chrome, Safari, Edge) */
.table-container-custom-scroll::-webkit-scrollbar {
    height: 8px; /* Altura de la barra de desplazamiento horizontal */
    background-color: var(--bg-secondary); /* Fondo de la pista de la barra de desplazamiento */
    border-radius: 4px; /* Bordes redondeados de la pista */
}

.table-container-custom-scroll::-webkit-scrollbar-thumb {
    background-color: var(--accent-primary); /* Color del "pulgar" (el control deslizante) */
    border-radius: 4px; /* Bordes redondeados del pulgar */
    border: 1px solid var(--bg-tertiary); /* Borde alrededor del pulgar */
}

.table-container-custom-scroll::-webkit-scrollbar-thumb:hover {
    background-color: var(--accent-hover); /* Color del pulgar al pasar el ratón */
}

/* Opcional: Para ocultar la barra de desplazamiento completamente si se desea,
   pero manteniendo la funcionalidad de desplazamiento, esto es solo un ejemplo:
.table-container-custom-scroll::-webkit-scrollbar {
    display: none;
}
.table-container-custom-scroll {
    -ms-overflow-style: none; /* IE and Edge */
    /* scrollbar-width: none; /* Firefox */
/* } */

/* Estilos para las sugerencias de comandos */
.command-suggestions {
    border: 1px solid var(--border-color);
    border-top: none;
    max-height: 150px;
    overflow-y: auto;
    background-color: var(--bg-secondary);
    border-radius: 0 0 8px 8px;
    position: absolute; /* Para que aparezca debajo del input */
    width: calc(100% - 2px); /* Ajusta al ancho del input */
    z-index: 100; /* Asegura que esté por encima de otros elementos */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    display: none; /* Oculto por defecto */
    left: 0; /* Asegura que el cuadro de sugerencias empiece en el mismo punto que el input */
    right: 0; /* Asegura que el cuadro de sugerencias se extienda hasta el mismo punto que el input */
}

.command-suggestions div {
    padding: 8px 12px; /* Reducido el padding */
    cursor: pointer;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9em; /* Reducido el tamaño de fuente */
    line-height: 1.3; /* Ajustado para un espaciado más compacto */
    display: flex; /* Para alinear el comando y la descripción */
    justify-content: space-between; /* Para empujar la descripción a la derecha */
    align-items: center;
}

.command-suggestions div:last-child {
    border-bottom: none;
}

.command-suggestions div:hover {
    background-color: var(--bg-tertiary);
    color: var(--accent-primary);
}

.command-suggestions div strong {
    flex-shrink: 0; /* Evita que el comando se encoja */
}

.command-suggestions div span {
    flex-grow: 1; /* Permite que la descripción ocupe el espacio restante */
    text-align: right; /* Alinea la descripción a la derecha */
    margin-left: 10px; /* Espacio entre el comando y la descripción */
    color: var(--text-secondary); /* Color más sutil para la descripción */
    font-size: 0.8em; /* Tamaño de fuente más pequeño para la descripción */
    white-space: nowrap; /* Evita que la descripción se rompa en varias líneas */
    overflow: hidden; /* Oculta el contenido que se desborde */
    text-overflow: ellipsis; /* Añade puntos suspensivos si el texto es demasiado largo */
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const commandInput = document.getElementById('admin_command');
    const suggestionsBox = document.getElementById('command-suggestions');

    // Lista de comandos válidos con una pequeña descripción
    const validCommands = [
        { cmd: ':ban', desc: 'ID días' }, // Descripción más corta
        { cmd: ':unban', desc: 'ID' },
        { cmd: ':delete', desc: 'ID' },
        { cmd: ':removepremium', desc: 'ID' },
        { cmd: ':grantpremium', desc: 'ID [días]' },
        { cmd: ':genkey', desc: '[días]' },
        { cmd: ':deletekey', desc: 'ID' }
    ];

    commandInput.addEventListener('input', function() {
        const inputText = commandInput.value.trim().toLowerCase();
        suggestionsBox.innerHTML = ''; // Limpiar sugerencias anteriores

        if (inputText.startsWith(':') && inputText.length > 1) {
            const currentCommand = inputText.substring(1); // Comando sin el ':'
            const filteredSuggestions = validCommands.filter(command => 
                command.cmd.substring(1).startsWith(currentCommand)
            );

            if (filteredSuggestions.length > 0) {
                filteredSuggestions.forEach(suggestion => {
                    const suggestionElement = document.createElement('div');
                    // Ahora la descripción se muestra entre paréntesis y con estilo sutil
                    suggestionElement.innerHTML = `<strong>${suggestion.cmd}</strong> <span class="suggestion-desc">(${suggestion.desc})</span>`;
                    suggestionElement.addEventListener('click', function() {
                        commandInput.value = suggestion.cmd + ' '; // Autocompleta y añade un espacio
                        suggestionsBox.style.display = 'none'; // Oculta las sugerencias
                        commandInput.focus(); // Vuelve el foco al input
                    });
                    suggestionsBox.appendChild(suggestionElement);
                });
                suggestionsBox.style.display = 'block'; // Mostrar sugerencias
            } else {
                suggestionsBox.style.display = 'none'; // Ocultar si no hay coincidencias
            }
        } else {
            suggestionsBox.style.display = 'none'; // Ocultar si el input está vacío o no empieza con ':'
        }
    });

    // Ocultar sugerencias cuando se pierde el foco del input
    commandInput.addEventListener('blur', function() {
        // Usar un pequeño retardo para permitir el clic en una sugerencia
        setTimeout(() => {
            suggestionsBox.style.display = 'none';
        }, 150); 
    });

    // Asegurarse de que las sugerencias se oculten si se hace clic fuera
    document.addEventListener('click', function(event) {
        if (!commandInput.contains(event.target) && !suggestionsBox.contains(event.target)) {
            suggestionsBox.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
