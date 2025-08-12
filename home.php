<?php
require_once 'includes/config.php';

// Verificar si está logueado
if (!isLoggedIn()) {
    redirect('login.php');
}

// Cargar los datos completos del usuario en la sesión
loadUserData($_SESSION['user_id'], $mysqli);

// Verificar el status del usuario al cargar home.php
if (isset($_SESSION['status']) && ($_SESSION['status'] === 'banned' || $_SESSION['status'] === 'suspended')) {
    // Si el usuario está baneado o suspendido, cerrar sesión y redirigir
    $status_message = '';
    if ($_SESSION['status'] === 'banned') {
        $status_message = 'Tu cuenta ha sido baneada permanentemente por vDanier Owner.';
    } elseif ($_SESSION['status'] === 'suspended') {
        $status_message = 'Tu cuenta ha sido suspendida temporalmente por vDanier Owner. Por favor, contacta al soporte.';
    }
    session_destroy();
    redirect('login.php?message=' . urlencode($status_message) . '&type=error');
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="hero">
        <h1 class="hero-title">Bienvenido a <?php echo htmlspecialchars(SITE_NAME); ?></h1>
        <p class="hero-subtitle" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
            Hola, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            <?php 
            $display_badge_hero = '';
            if (isset($_SESSION['rank']) && $_SESSION['rank'] === 'owner') {
                $display_badge_hero = '<span class="user-rank-badge">' . ucfirst(htmlspecialchars($_SESSION['rank'])) . '</span>';
            } elseif (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'premium') {
                // Si es premium y no es owner, mostrar "Premium" con estilo de rango
                $display_badge_hero = '<span class="user-rank-badge">Premium</span>'; 
            } elseif (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'free') {
                // Si es free, mostrar "Free" con su propio estilo de membresía
                $display_badge_hero = '<span class="membership-badge membership-free">Free</span>';
            }
            echo $display_badge_hero;
            ?>
        </p>
    </div>
    
    <div class="user-info">
        <h3>Información de tu cuenta</h3>
        
        <!-- Mostrar avatar si existe -->
        <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem;">
            <?php if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar'])): ?>
                <img src="<?php echo htmlspecialchars(BASE_URL . 'uploads/' . $_SESSION['avatar']); ?>" alt="Avatar" class="avatar-preview">
            <?php else: ?>
                <div class="default-avatar-large">
                    <?php echo strtoupper(htmlspecialchars(substr($_SESSION['username'], 0, 1))); ?>
                </div>
            <?php endif; ?>
            <div>
                <div class="user-detail">
                    <span>Usuario:</span>
                    <span>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                        <?php 
                        $display_badge_username = '';
                        if (isset($_SESSION['rank']) && $_SESSION['rank'] === 'owner') {
                            $display_badge_username = '<span class="user-rank-badge">' . ucfirst(htmlspecialchars($_SESSION['rank'])) . '</span>';
                        } elseif (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'premium') {
                            // Si es premium y no es owner, mostrar "Premium" con estilo de rango
                            $display_badge_username = '<span class="user-rank-badge">Premium</span>';
                        } elseif (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'free') {
                            // Si es free, mostrar "Free" con su propio estilo de membresía
                            $display_badge_username = '<span class="membership-badge membership-free">Free</span>';
                        }
                        echo $display_badge_username;
                        ?>
                    </span>
                </div>
                
                <div class="user-detail">
                    <span>Email:</span>
                    <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
                </div>
                
                <div class="user-detail">
                    <span>Tipo de membresía:</span>
                    <span>
                        <?php if (isset($_SESSION['membership_type'])): // Aseguramos que la variable exista ?>
                            <span class="membership-badge membership-<?php echo htmlspecialchars($_SESSION['membership_type']); ?>">
                                <?php if ($_SESSION['membership_type'] === 'premium'): ?>
                                    <i class="fas fa-crown"></i>
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                                <?php echo ucfirst(htmlspecialchars($_SESSION['membership_type'])); ?>
                            </span>
                        <?php else: ?>
                            <!-- Fallback si la membresía no está definida -->
                            <span class="membership-badge membership-free">
                                <i class="fas fa-user"></i>
                                Free
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'free'): ?>
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="membership.php" class="btn btn-primary" style="max-width: 200px; display: inline-flex;">
                    <i class="fas fa-crown"></i>
                    Upgrade a Premium
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['membership_type']) && $_SESSION['membership_type'] === 'premium'): ?>
        <div class="user-info">
            <h3>Contenido Premium</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">¡Gracias por ser miembro Premium! Aquí tienes acceso a contenido exclusivo.</p>
            
            <div style="display: grid; gap: 1rem;">
                <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; border-left: 4px solid var(--accent-primary);">
                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">📊 Analytics Avanzados</h4>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.875rem;">Acceso completo a estadísticas detalladas.</p>
                </div>
                
                <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; border-left: 4px solid var(--accent-primary);">
                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">🎯 Funciones Premium</h4>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.875rem;">Herramientas exclusivas para miembros premium.</p>
                </div>
                
                <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; border-left: 4px solid var(--accent-primary);">
                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">🏆 Soporte Prioritario</h4>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.875rem;">Soporte técnico con respuesta prioritaria.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
