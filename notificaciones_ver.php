<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

loadUserData($_SESSION['user_id'], $mysqli);

require_once 'includes/header.php';
?>

<div class="container">
    <div class="hero">
        <h1 class="hero-title">Todas tus Notificaciones</h1>
        <p class="hero-subtitle">Aquí puedes ver el historial completo de tus notificaciones y buscar por palabras clave.</p>
    </div>

    <div class="user-info">
        <h3>Buscar Notificaciones</h3>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label for="searchQuery" class="form-label">Buscar por palabra clave:</label>
            <input 
                type="text" 
                id="searchQuery" 
                class="form-input" 
                placeholder="Ej. membresía, clave, suspendido"
                value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
            >
        </div>
        <button id="searchNotificationsBtn" class="btn btn-primary" style="max-width: 200px; margin-bottom: 1rem;">
            <i class="fas fa-search"></i> Buscar
        </button>
        <button id="resetSearchBtn" class="btn" style="max-width: 200px; background-color:var(--border-color); color:var(--text-primary); margin-left: 0.5rem;">
            <i class="fas fa-redo"></i> Restablecer
        </button>
    </div>

    <div class="user-info" style="margin-top: 1rem;">
        <h3>Historial de Notificaciones</h3>
        <div id="allNotificationsList">
            <p class="no-notifications" style="text-align: center; color: var(--text-secondary);">Cargando notificaciones...</p>
        </div>
        <div style="text-align: center; margin-top: 1.5rem;">
            <button id="loadMoreNotificationsBtn" class="btn btn-primary" style="display: none; max-width: 200px; margin: auto;">Cargar más</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const allNotificationsList = document.getElementById('allNotificationsList');
    const loadMoreNotificationsBtn = document.getElementById('loadMoreNotificationsBtn');
    const searchQueryInput = document.getElementById('searchQuery');
    const searchNotificationsBtn = document.getElementById('searchNotificationsBtn');
    const resetSearchBtn = document.getElementById('resetSearchBtn');
    let offset = 0;
    const limit = 10; 

    // Helper para htmlspecialchars en JS
    function htmlspecialchars(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Función para formatear la fecha/hora
    function formatNotificationTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleDateString('es-ES', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    async function loadAllNotifications(isNewSearch = false) {
        if (isNewSearch) {
            offset = 0;
            allNotificationsList.innerHTML = '<p class="no-notifications" style="text-align: center; color: var(--text-secondary);">Cargando notificaciones...</p>';
        }
        
        const query = searchQueryInput.value.trim();
        const apiUrl = `<?php echo BASE_URL; ?>api/notifications.php?action=get&offset=${offset}&limit=${limit}&unreadOnly=false${query ? '&q=' + encodeURIComponent(query) : ''}`;

        try {
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (isNewSearch) { 
                allNotificationsList.innerHTML = ''; 
            }

            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    const notificationItem = document.createElement('div');
                    notificationItem.classList.add('notification-item'); 
                    notificationItem.classList.add(notification.is_read == 1 ? 'read' : 'unread');
                    notificationItem.dataset.id = notification.id;
                    notificationItem.dataset.notificationData = JSON.stringify(notification);

                    notificationItem.innerHTML = `
                        <i class="icon fas fa-info-circle"></i>
                        <div class="message-text">${htmlspecialchars(notification.message)}</div>
                        <span class="timestamp">${formatNotificationTime(notification.created_at)}</span>
                    `;
                    allNotificationsList.appendChild(notificationItem);
                    
                    notificationItem.addEventListener('click', async function(event) {
                        event.preventDefault(); 
                        const clickedNotification = JSON.parse(this.dataset.notificationData);

                        let detailsContent = `<p>${htmlspecialchars(clickedNotification.message)}</p>`;
                        if (clickedNotification.details) {
                            detailsContent += '<h4 style="margin-top: 1rem; margin-bottom: 0.5rem; color: var(--text-primary);">Detalles Adicionales:</h4>';
                            detailsContent += '<pre style="background: var(--bg-tertiary); padding: 0.75rem; border-radius: 6px; text-align: left; overflow-x: auto; font-size: 0.8em; color: var(--text-primary);"><code style="white-space: pre-wrap; word-wrap: break-word;">' + htmlspecialchars(JSON.stringify(clickedNotification.details, null, 2)) + '</code></pre>';
                        }
                        window.showCustomAlert(detailsContent); // Usar showCustomAlert global


                        if (this.classList.contains('unread')) {
                            await markNotificationAsRead(this.dataset.id);
                            this.classList.remove('unread');
                            this.classList.add('read');
                        }
                    });
                });
                offset += data.notifications.length; 
                
                if (data.notifications.length === limit) {
                    loadMoreNotificationsBtn.style.display = 'block';
                } else {
                    loadMoreNotificationsBtn.style.display = 'none';
                }
                
                const loadingMessage = allNotificationsList.querySelector('.no-notifications');
                if (loadingMessage) loadingMessage.remove();

            } else if (offset === 0) { 
                allNotificationsList.innerHTML = '<p class="no-notifications" style="text-align: center; color: var(--text-secondary);">No tienes notificaciones en tu historial.</p>';
                loadMoreNotificationsBtn.style.display = 'none';
            } else { 
                loadMoreNotificationsBtn.style.display = 'none';
            }
        }

        async function markNotificationAsRead(notificationId) {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>api/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_read', id: notificationId })
                });
                const data = await response.json();
                if (!data.success) {
                    console.error('Failed to mark notification as read:', data.message);
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }

        // Cargar notificaciones al cargar la página
        loadAllNotifications();

        // Event listener para el botón "Cargar más"
        if (loadMoreNotificationsBtn) {
            loadMoreNotificationsBtn.addEventListener('click', () => loadAllNotifications(false));
        }

        // Event listener para el botón de búsqueda
        if (searchNotificationsBtn) {
            searchNotificationsBtn.addEventListener('click', () => {
                loadAllNotifications(true); // Iniciar una nueva búsqueda
            });
        }

        // Event listener para el campo de búsqueda (enter key)
        if (searchQueryInput) {
            searchQueryInput.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // Prevenir el envío del formulario si existe
                    loadAllNotifications(true);
                }
            });
        }

        // Event listener para el botón de restablecer búsqueda
        if (resetSearchBtn) {
            resetSearchBtn.addEventListener('click', () => {
                searchQueryInput.value = ''; // Limpiar el campo de búsqueda
                loadAllNotifications(true); // Recargar sin filtro de búsqueda
            });
        }

        // MODAL PERSONALIZADO (reutilizado del footer.php)
        function createModal(message, type, callback) {
            const existingModal = document.getElementById('customModal');
            if (existingModal) existingModal.remove();

            const modalHtml = `
                <div id="customModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; z-index: 9999;">
                    <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 12px; text-align: center; box-shadow: var(--shadow-lg); max-width: 400px; width: 90%; color: var(--text-primary);">
                        <div style="margin-bottom: 1.5rem; font-size: 1.1rem; text-align: left;">${message}</div>
                        <div style="display: flex; justify-content: center; gap: 1rem;">
                            ${type === 'confirm' ? `
                                <button id="modalConfirmBtn" class="btn btn-primary" style="width: auto;">Aceptar</button>
                                <button id="modalCancelBtn" class="btn" style="width: auto; background-color:var(--border-color); color:var(--text-primary);">Cancelar</button>
                            ` : `
                                <button id="modalOkBtn" class="btn btn-primary" style="width: auto;">OK</button>
                            `}
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const customModal = document.getElementById('customModal');

            if (type === 'confirm') {
                document.getElementById('modalConfirmBtn').addEventListener('click', () => {
                    customModal.remove();
                    if (callback) callback(true);
                });
                document.getElementById('modalCancelBtn').addEventListener('click', () => {
                    customModal.remove();
                    if (callback) callback(false);
                });
            } else { // type === 'alert'
                document.getElementById('modalOkBtn').addEventListener('click', () => {
                    customModal.remove();
                    if (callback) callback();
                });
            }
        }

        // Hacer las funciones de modal accesibles globalmente para esta página
        window.showCustomAlert = function(message, callback) {
            createModal(message, 'alert', callback);
        };

        window.showCustomConfirm = function(message, callback) {
            createModal(message, 'confirm', callback);
        };
    });
    </script>

    <?php require_once 'includes/footer.php'; ?>
