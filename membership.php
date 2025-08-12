<?php
require_once 'includes/config.php';

// Verificar si está logueado
if (!isLoggedIn()) {
    redirect('login.php');
}

// Cargar los datos completos del usuario en la sesión para tener la info de premium_start_date, etc.
loadUserData($_SESSION['user_id'], $mysqli);

$message = '';

// Procesar canje de key
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_key'])) {
    $user_id = $_SESSION['user_id'];
    $key_input = sanitize_input($_POST['premium_key']);

    if (empty($key_input)) {
        $message = 'error:Por favor, ingresa una key de canje.';
    } else {
        // 1. Buscar la key en la base de datos y verificar si está usada
        $stmt_key = $mysqli->prepare("SELECT id, key_string, days, used, used_by_user_id FROM premium_keys WHERE key_string = ?");
        $stmt_key->bind_param("s", $key_input);
        $stmt_key->execute();
        $key_result = $stmt_key->get_result();

        if ($key_result->num_rows === 1) {
            $key_data = $key_result->fetch_assoc();

            if ($key_data['used'] == 0) { // Key no usada
                $premium_days = $key_data['days'];
                $premium_start_date = date('Y-m-d H:i:s');
                $premium_end_date = NULL;

                if ($premium_days > 0) {
                    $premium_end_date = date('Y-m-d H:i:s', strtotime("+" . $premium_days . " days"));
                }
                
                // Si el usuario ya era premium y canjea otra key, se extiende su membresía
                // Si ya era premium y permanente, seguirá siendo permanente.
                // Si la membresía actual es temporal y la nueva key tiene una duración,
                // extiende desde el final de la membresía actual o desde ahora si ya expiró.
                if ($_SESSION['membership_type'] === 'premium' && !empty($_SESSION['premium_end_date'])) {
                    $current_end_datetime = new DateTime($_SESSION['premium_end_date']);
                    $now = new DateTime();
                    
                    // Extender desde la fecha de fin actual si es futura, o desde ahora si ya expiró
                    $base_date_for_extension = ($current_end_datetime > $now) ? $current_end_datetime : $now;
                    
                    if ($premium_days > 0) { // Si la nueva key es temporal
                         $premium_end_date = date('Y-m-d H:i:s', strtotime($base_date_for_extension->format('Y-m-d H:i:s') . " +" . $premium_days . " days"));
                    } else { // Si la nueva key es permanente
                         $premium_end_date = NULL; // Se vuelve permanente
                    }
                }


                // 2. Actualizar la membresía del usuario, registrando la premium_key_id
                $stmt_update_user = $mysqli->prepare("UPDATE usuarios SET membership_type = 'premium', premium_start_date = ?, premium_end_date = ?, premium_granted_by_admin_id = NULL, premium_key_id = ? WHERE id = ?");
                $stmt_update_user->bind_param("ssii", $premium_start_date, $premium_end_date, $key_data['id'], $user_id);
                
                if ($stmt_update_user->execute()) {
                    // 3. Marcar la key como usada
                    $stmt_mark_key_used = $mysqli->prepare("UPDATE premium_keys SET used = 1, used_by_user_id = ?, used_at = ? WHERE id = ?");
                    $current_datetime = date('Y-m-d H:i:s');
                    $stmt_mark_key_used->bind_param("isi", $user_id, $current_datetime, $key_data['id']);
                    $stmt_mark_key_used->execute();

                    // Actualizar variables de sesión
                    $_SESSION['membership_type'] = 'premium';
                    $_SESSION['premium_start_date'] = $premium_start_date;
                    $_SESSION['premium_end_date'] = $premium_end_date;
                    $_SESSION['premium_granted_by_admin_id'] = NULL; // Otorgada por key, no por admin directo
                    $_SESSION['premium_key_id'] = $key_data['id']; // Guarda el ID de la key usada en la sesión

                    $message = 'success:¡Felicidades! Has canjeado una key. Tu membresía ahora es Premium.';
                    if ($premium_end_date) {
                         $message .= ' Vence el ' . date('d/m/Y H:i', strtotime($premium_end_date)) . '.';
                    } else {
                         $message .= ' Es permanente.';
                    }

                } else {
                    $message = 'error:Error al actualizar tu membresía. Inténtalo de nuevo.';
                }

            } else {
                $message = 'error:Esta key ya ha sido canjeada.';
            }
        } else {
            $message = 'error:Key de Premium inválida o no encontrada.';
        }
    }
}


// Procesar downgrade a free (permanece la opción para usuarios Premium)
// ESTE BLOQUE HA SIDO ELIMINADO PARA IMPEDIR QUE LOS USUARIOS PREMIUM CAMBIEN A FREE MANUALMENTE.
// Solo el owner puede quitar la membresía Premium.
/*
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['downgrade_free'])) {
    $user_id = $_SESSION['user_id'];
    
    // Al hacer downgrade, limpiar las fechas de premium y el ID de la key
    $stmt = $mysqli->prepare("UPDATE usuarios SET membership_type = 'free', premium_start_date = NULL, premium_end_date = NULL, premium_granted_by_admin_id = NULL, premium_key_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['membership_type'] = 'free';
        $_SESSION['premium_start_date'] = NULL;
        $_SESSION['premium_end_date'] = NULL;
        $_SESSION['premium_granted_by_admin_id'] = NULL;
        $_SESSION['premium_key_id'] = NULL; // Limpia también el ID de la key en sesión
        $message = 'success:Tu membresía ha sido cambiada a Free.';
    } else {
        $message = 'error:Error al cambiar la membresía. Inténtalo de nuevo.';
    }
}
*/

require_once 'includes/header.php';
?>

<div class="container">
    <div class="hero">
        <h1 class="hero-title">Planes de Membresía</h1>
        <p class="hero-subtitle">Elige el plan que mejor se adapte a tus necesidades</p>
    </div>
    
    <?php if ($message): ?>
        <?php $type = explode(':', $message)[0]; $text = explode(':', $message)[1]; ?>
        <div class="message message-<?php echo $type; ?>">
            <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($text); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'premium'): ?>
        <!-- Sección de Gestión de Membresía Premium -->
        <div class="settings-section" style="margin-top: 2rem;">
            <h2><i class="fas fa-gem"></i> Tu Membresía Premium</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Aquí puedes gestionar los detalles de tu suscripción Premium.</p>

            <div class="user-info">
                <div class="user-detail">
                    <span>Estado:</span>
                    <span>
                        <span class="membership-badge membership-premium">
                            <i class="fas fa-crown"></i> Premium
                        </span>
                    </span>
                </div>

                <?php
                $premium_start_date_display = 'N/A';
                if (!empty($_SESSION['premium_start_date'])) {
                    $premium_start_date_display = date('d/m/Y H:i', strtotime($_SESSION['premium_start_date']));
                }

                $premium_end_date_display = 'Permanente';
                $remaining_premium_time = 'N/A';
                if (!empty($_SESSION['premium_end_date'])) {
                    $premium_end_date_display = date('d/m/Y H:i', strtotime($_SESSION['premium_end_date']));
                    $premium_end_datetime = new DateTime($_SESSION['premium_end_date']);
                    $current_datetime = new DateTime();

                    if ($premium_end_datetime > $current_datetime) {
                        $interval = $current_datetime->diff($premium_end_datetime);
                        $remaining_parts = [];
                        if ($interval->y > 0) $remaining_parts[] = $interval->y . ' año' . ($interval->y > 1 ? 's' : '');
                        if ($interval->m > 0) $remaining_parts[] = $interval->m . ' mes' . ($interval->m > 1 ? 'es' : '');
                        if ($interval->d > 0) $remaining_parts[] = $interval->d . ' día' . ($interval->d > 1 ? 's' : '');
                        if ($interval->h > 0) $remaining_parts[] = $interval->h . ' hora' . ($interval->h > 1 ? 's' : '');
                        if ($interval->i > 0) $remaining_parts[] = $interval->i . ' minuto' . ($interval->i > 1 ? 's' : '');
                        
                        $remaining_premium_time = empty($remaining_parts) ? 'menos de 1 minuto' : implode(', ', $remaining_parts);
                    } else {
                        $remaining_premium_time = 'Expirado (necesita contactar soporte o el admin)';
                    }
                }
                
                // Modificación para que siempre ponga "vDanier Owner" si fue otorgado por admin o por key
                $admin_granted_info = 'N/A';
                if (!empty($_SESSION['premium_granted_by_admin_id']) || !empty($_SESSION['premium_key_id'])) {
                    $admin_granted_info = 'vDanier Owner'; // Según la solicitud del usuario
                }
                
                $premium_key_info_display = 'N/A'; // Cambiado de _id_display a _info_display para ser más descriptivo
                if (!empty($_SESSION['premium_key_id'])) {
                    $key_id_for_query = $_SESSION['premium_key_id'];
                    // Buscamos la key_string real si el ID está presente
                    $stmt_key_string = $mysqli->prepare("SELECT key_string FROM premium_keys WHERE id = ?");
                    $stmt_key_string->bind_param("i", $key_id_for_query);
                    $stmt_key_string->execute();
                    $key_string_result = $stmt_key_string->get_result()->fetch_assoc();
                    if ($key_string_result) {
                        $premium_key_info_display = htmlspecialchars($key_string_result['key_string']) . " (ID: " . htmlspecialchars($key_id_for_query) . ")";
                    } else {
                        $premium_key_info_display = "ID: " . htmlspecialchars($key_id_for_query); // Fallback si la key no se encuentra (borrada, etc.)
                    }
                }
                ?>

                <div class="user-detail">
                    <span>Tiempo Restante:</span>
                    <span><?php echo $remaining_premium_time; ?></span>
                </div>

                <div class="user-detail">
                    <span>Fecha de Inicio:</span>
                    <span><?php echo $premium_start_date_display; ?></span>
                </div>

                <div class="user-detail">
                    <span>Fecha de Vencimiento:</span>
                    <span><?php echo $premium_end_date_display; ?></span>
                </div>

                <div class="user-detail">
                    <span>Otorgado por Admin:</span>
                    <span><?php echo $admin_granted_info; ?></span>
                </div>

                <div class="user-detail">
                    <span>Key Canjeada:</span>
                    <span><?php echo $premium_key_info_display; ?></span>
                </div>
            </div>

            <!-- Sección de "Cambiar de Plan" para Premium, eliminando la opción de downgrade directo -->
            <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: var(--text-primary);">Cambiar de Plan</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                Si deseas dejar tu suscripción Premium, por favor, contacta con el administrador (vDanier Owner).
            </p>
            <!-- Eliminado el formulario de downgrade directo para usuarios premium -->
            <button class="btn btn-primary" disabled style="background-color: var(--text-tertiary);">
                <i class="fas fa-user-shield"></i>
                Solo el Owner puede cambiar tu plan
            </button>
        </div>

    <?php else: ?>
        <!-- Sección de Planes de Membresía (solo si es Free) -->
        <div class="membership-plans">
            <!-- Plan Free -->
            <div class="plan-card <?php echo (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'free') ? 'current-plan' : ''; ?>">
                <div class="plan-name">Free</div>
                <div class="plan-price">$0 <span>/mes</span></div>
                
                <ul class="plan-features">
                    <li>Acceso básico a la plataforma</li>
                    <li>Hasta 5 proyectos</li>
                    <li>Soporte por email</li>
                    <li>1GB de almacenamiento</li>
                </ul>
                
                <?php if (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'free'): ?>
                    <button class="btn btn-primary" disabled>
                        <i class="fas fa-check"></i>
                        Plan Actual
                    </button>
                <?php else: ?>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="downgrade_free" class="btn btn-primary" 
                                onclick="return confirm('¿Estás seguro de que quieres cambiar a plan Free?')">
                            <i class="fas fa-arrow-alt-circle-down"></i>
                            Cambiar a Free
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Plan Premium - Se elimina la compra directa -->
            <div class="plan-card featured">
                <div class="plan-name">Premium</div>
                <div class="plan-price">$19 <span>/mes</span></div>
                
                <ul class="plan-features">
                    <li>Acceso completo a todas las funciones</li>
                    <li>Proyectos ilimitados</li>
                    <li>Soporte prioritario 24/7</li>
                    <li>100GB de almacenamiento</li>
                    <li>Analytics avanzados</li>
                    <li>Exportar datos</li>
                    <li>API access</li>
                </ul>
                
                <!-- Eliminado el botón de "Upgrade a Premium" -->
                <button class="btn btn-primary" disabled style="background-color: var(--text-tertiary);">
                    <i class="fas fa-lock"></i>
                    Solo por Key
                </button>
            </div>
        </div>

        <!-- Nueva sección para canjear key -->
        <div class="settings-section" style="margin-top: 2rem;">
            <h2><i class="fas fa-key"></i> Canjear Key Premium</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Introduce tu key Premium para activar o extender tu membresía.</p>
            <form method="POST" class="admin-action-form">
                <div class="form-group">
                    <label for="premium_key" class="form-label">Tu Key de Premium</label>
                    <input type="text" id="premium_key" name="premium_key" class="form-input" placeholder="Ej: noxiew-premium-ABC123XYZ" required>
                </div>
                <button type="submit" name="redeem_key" class="btn btn-primary">
                    <i class="fas fa-ticket-alt"></i> Canjear Key
                </button>
            </form>
        </div>
        
        <div class="user-info">
            <h3>Estado Actual de tu Membresía</h3>
            
            <div class="user-detail">
                <span>Plan actual:</span>
                <span>
                    <span class="membership-badge membership-<?php echo (isset($_SESSION['membership_type']) ? htmlspecialchars($_SESSION['membership_type']) : 'free'); ?>">
                        <?php if (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'premium'): ?>
                            <i class="fas fa-crown"></i>
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                        <?php echo ucfirst((isset($_SESSION['membership_type']) ? htmlspecialchars($_SESSION['membership_type']) : 'Free')); ?>
                    </span>
                </span>
            </div>
            
            <div class="user-detail">
                <span>Usuario:</span>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
            
            <div class="user-detail">
                <span>Email:</span>
                <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* ... (existing CSS from membership.php, ensure this is in your styles.css for global application) ... */
.current-plan {
    border-color: var(--success-border) !important; /* Usar una variable de éxito */
    background-color: var(--success-bg) !important; /* Usar una variable de éxito */
}

.current-plan .plan-name {
    color: var(--success-color) !important; /* Usar una variable de éxito */
}
</style>

<?php require_once 'includes/footer.php'; ?>
