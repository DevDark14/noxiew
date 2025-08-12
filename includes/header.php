<?php
// Incluir el archivo de configuración si aún no ha sido incluido
// Esto asegura que la sesión y las funciones isLoggedIn() y otras estén disponibles
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php'; 

// Cargar los datos completos del usuario en la sesión si está logueado
// Asegúrate de que loadUserData exista en config.php y que cargue todos los campos necesarios.
if (isLoggedIn() && (!isset($_SESSION['premium_start_date']) || !isset($_SESSION['rank']))) {
    loadUserData($_SESSION['user_id'], $mysqli);
}

// Obtener el número de notificaciones no leídas si el usuario está logueado
$unread_notifications_count = 0;
if (isLoggedIn()) {
    $notifications = getNotifications($_SESSION['user_id'], $mysqli, true); // Solo no leídas
    $unread_notifications_count = count($notifications);
}

// Determinar el tema actual para aplicar la clase 'dark' al body si es necesario
$current_theme = $_SESSION['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo htmlspecialchars($current_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    
    <!-- Iconos minimalistas (versión 6.4.0) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>styles.css?v=<?php echo time(); ?>">
    
    <!-- Meta tags para SEO y redes sociales -->
    <meta name="description" content="Sistema de membresías moderno">
    <meta property="og:title" content="<?php echo SITE_NAME; ?>">
    <meta property="og:description" content="Sistema de membresías moderno">
    <meta property="og:type" content="website">
</head>
<body data-theme="<?php echo htmlspecialchars($current_theme); ?>">
    <header class="header">
        <div class="header-container">
            <a href="<?php echo BASE_URL; ?>home.php" class="logo">
                <?php echo SITE_NAME; ?>
            </a>
            
            <nav>
                <ul class="nav-links">
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo BASE_URL; ?>home.php">
                            <i class="fas fa-home"></i><span>Inicio</span>
                        </a></li>
                        
                        <li><a href="<?php echo BASE_URL; ?>membership.php">
                            <i class="fas fa-crown"></i><span>Membresía</span>
                        </a></li>

                        <!-- Notificaciones Bell Icon -->
                        <li class="notifications-container">
                            <a href="#" class="notification-bell" onclick="toggleNotificationsDropdown(event)">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_notifications_count > 0): ?>
                                    <span class="notification-badge" id="notificationCount"><?php echo $unread_notifications_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="notifications-dropdown" id="notificationsDropdown">
                                <div class="dropdown-header">
                                    <h3>Notificaciones</h3>
                                    <button id="markAllReadBtn" class="mark-all-read-btn" style="display: <?php echo ($unread_notifications_count > 0) ? 'inline-block' : 'none'; ?>;">Marcar todo como leído</button>
                                </div>
                                <div class="dropdown-content" id="notificationList">
                                    <!-- Notifications will be loaded here by JavaScript -->
                                    <p class="no-notifications" style="display: <?php echo ($unread_notifications_count == 0) ? 'block' : 'none'; ?>;">No tienes notificaciones.</p>
                                </div>
                                <div class="dropdown-footer">
                                    <a href="#" id="viewAllNotifications">Ver todas las notificaciones</a>
                                </div>
                            </div>
                        </li>
                        
                        <li class="user-profile" onclick="toggleProfileDropdown()">
                            <?php 
                            $avatar_src = '';
                            $default_avatar_initial = '';
                            $username_display = '';
                            $badge_html = ''; // Variable para almacenar el HTML del badge

                            // Asegurarse de que las variables de sesión existan antes de usarlas
                            if (isset($_SESSION['username'])) {
                                $username_display = htmlspecialchars($_SESSION['username']);
                                $default_avatar_initial = strtoupper(htmlspecialchars(substr($_SESSION['username'], 0, 1)));
                            }

                            if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar'])) {
                                $avatar_src = BASE_URL . 'uploads/' . htmlspecialchars($_SESSION['avatar']);
                            }

                            // Lógica para mostrar el badge: Owner si es owner, sino Premium si es premium, sino Free si es free.
                            if (isset($_SESSION['rank']) && $_SESSION['rank'] === 'owner') {
                                $badge_html = '<span class="user-rank-badge">' . ucfirst(htmlspecialchars($_SESSION['rank'])) . '</span>';
                            } elseif (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'premium') {
                                $badge_html = '<span class="user-rank-badge">Premium</span>'; 
                            } elseif (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'free') {
                                $badge_html = '<span class="membership-badge membership-free">Free</span>'; 
                            }
                            ?>

                            <?php if (!empty($avatar_src)): ?>
                                <img src="<?php echo $avatar_src; ?>" alt="Avatar" class="avatar">
                            <?php else: ?>
                                <div class="default-avatar">
                                    <?php echo $default_avatar_initial; ?>
                                </div>
                            <?php endif; ?>
                            <span class="user-name">
                                <?php echo $username_display; ?>
                                <?php echo $badge_html; // Mostrar el badge aquí, dentro del span del nombre de usuario para alineación original ?>
                            </span>
                            <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: var(--text-secondary);"></i>
                            
                            <div class="profile-dropdown" id="profileDropdown">
                                <a href="<?php echo BASE_URL; ?>profile.php" class="profile-dropdown-item">
                                    <i class="fas fa-user"></i>
                                    Mi Perfil
                                </a>
                                <a href="<?php echo BASE_URL; ?>settings.php" class="profile-dropdown-item">
                                    <i class="fas fa-cog"></i>
                                    Configuración
                                </a>
                                <?php if (isset($_SESSION['rank']) && $_SESSION['rank'] === 'owner'): ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="<?php echo BASE_URL; ?>admin_panel.php" class="profile-dropdown-item">
                                        <i class="fas fa-user-shield"></i>
                                        Panel Owner
                                    </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo BASE_URL; ?>logout.php" class="profile-dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Cerrar Sesión
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>login.php">
                            <i class="fas fa-sign-in-alt"></i><span>Iniciar Sesión</span>
                        </a></li>
                        <li><a href="<?php echo BASE_URL; ?>register.php" class="primary">
                            <i class="fas fa-user-plus"></i><span>Registrarse</span>
                        </a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
