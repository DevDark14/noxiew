<?php
require_once 'includes/config.php';

// Verificar si está logueado
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Asegúrate de que los datos del usuario estén completamente cargados, incluyendo 'rank'
// Esto recargará todos los datos del usuario desde la base de datos en la sesión
loadUserData($user_id, $mysqli);

// Los datos del usuario ahora deben estar en $_SESSION, así que los usamos directamente
$user_data = [
    'id' => $_SESSION['user_id'], // El ID de usuario ya está en la sesión
    'username' => $_SESSION['username'],
    'email' => $_SESSION['email'],
    'avatar' => $_SESSION['avatar'],
    'theme' => $_SESSION['theme'],
    'membership_type' => $_SESSION['membership_type'],
    'first_name' => $_SESSION['first_name'] ?? '', // Usar operador null coalescing para valores que pueden ser nulos
    'last_name' => $_SESSION['last_name'] ?? '',
    'rank' => $_SESSION['rank'] ?? 'user', // Asegurar un valor por defecto
    'created_at' => $_SESSION['created_at'] ?? date('Y-m-d H:i:s') // Asegurar que created_at esté disponible
];

require_once 'includes/header.php';
?>

<div class="container">
    <div class="hero">
        <h1 class="hero-title">Mi Perfil</h1>
        <p class="hero-subtitle">Información de tu cuenta</p>
    </div>
    
    <!-- Tarjeta de Perfil -->
    <div class="user-info">
        <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem;">
            <?php if (!empty($user_data['avatar'])): ?>
                <!-- Utiliza la ruta base para el avatar -->
                <img src="<?php echo htmlspecialchars(BASE_URL . 'uploads/' . $user_data['avatar']); ?>" alt="Avatar" class="avatar-preview">
            <?php else: ?>
                <div class="default-avatar-large">
                    <?php echo strtoupper(htmlspecialchars(substr($user_data['username'], 0, 1))); ?>
                </div>
            <?php endif; ?>
            
            <div>
                <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <?php if (!empty($user_data['first_name']) || !empty($user_data['last_name'])): ?>
                        <?php echo htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($user_data['username']); ?>
                    <?php endif; ?>
                    <?php 
                    $display_badge_profile_h2 = '';
                    if (isset($user_data['rank']) && $user_data['rank'] === 'owner') {
                        $display_badge_profile_h2 = '<span class="user-rank-badge">' . ucfirst(htmlspecialchars($user_data['rank'])) . '</span>';
                    } elseif (isset($user_data['membership_type']) && $user_data['membership_type'] === 'premium') {
                        // Si es premium y no es owner, mostrar "Premium" con estilo de rango
                        $display_badge_profile_h2 = '<span class="user-rank-badge">Premium</span>'; 
                    }
                    echo $display_badge_profile_h2;
                    ?>
                </h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-secondary);">@<?php echo htmlspecialchars($user_data['username']); ?></p>
                
                <?php if (isset($user_data['membership_type'])): // Aseguramos que la variable exista ?>
                    <span class="membership-badge membership-<?php echo htmlspecialchars($user_data['membership_type']); ?>" style="margin-top: 0.5rem;">
                        <?php if ($user_data['membership_type'] === 'premium'): ?>
                            <i class="fas fa-crown"></i>
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                        <?php echo ucfirst(htmlspecialchars($user_data['membership_type'])); ?>
                    </span>
                <?php else: ?>
                    <span class="membership-badge membership-free" style="margin-top: 0.5rem;">
                        <i class="fas fa-user"></i>
                        Free
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="user-detail">
            <span>Email:</span>
            <span><?php echo htmlspecialchars($user_data['email']); ?></span>
        </div>
        
        <?php if (!empty($user_data['first_name'])): ?>
            <div class="user-detail">
                <span>Nombre:</span>
                <span><?php echo htmlspecialchars($user_data['first_name']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($user_data['last_name'])): ?>
            <div class="user-detail">
                <span>Apellido:</span>
                <span><?php echo htmlspecialchars($user_data['last_name']); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="user-detail">
            <span>Miembro desde:</span>
            <span><?php echo date('d F Y', strtotime($user_data['created_at'])); ?></span>
        </div>
        
        <div class="user-detail">
            <span>Tema:</span>
            <span><?php echo $user_data['theme'] === 'dark' ? 'Oscuro' : 'Claro'; ?></span>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <a href="settings.php" class="btn btn-primary">
                <i class="fas fa-cog"></i>
                Editar Perfil
            </a>
            
            <?php if (isset($user_data['membership_type']) && $user_data['membership_type'] === 'free'): ?>
                <a href="membership.php" class="btn btn-primary">
                    <i class="fas fa-crown"></i>
                    Upgrade a Premium
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estadísticas rápidas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                <?php echo (isset($user_data['membership_type']) && $user_data['membership_type'] === 'premium') ? '∞' : '5'; ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.875rem;">Proyectos Disponibles</div>
        </div>
        
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                <?php echo (isset($user_data['membership_type']) && $user_data['membership_type'] === 'premium') ? '100GB' : '1GB'; ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.875rem;">Almacenamiento</div>
        </div>
        
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                <?php echo (isset($user_data['membership_type']) && $user_data['membership_type'] === 'premium') ? '24/7' : 'Email'; ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.875rem;">Soporte</div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
