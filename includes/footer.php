</main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
        </div>
    </footer>
    
    <script>
        // Toggle del dropdown de perfil
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
            // Asegúrate de cerrar las notificaciones si están abiertas
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            if (notificationsDropdown && notificationsDropdown.classList.contains('active')) {
                notificationsDropdown.classList.remove('active');
            }
        }
        
        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            const userProfile = e.target.closest('.user-profile'); 
            const dropdown = document.getElementById('profileDropdown');
            
            if (!userProfile && dropdown) { 
                dropdown.classList.remove('active');
            }

            const notificationBell = e.target.closest('.notification-bell');
            const notificationsContainer = e.target.closest('.notifications-container');
            const notificationsDropdown = document.getElementById('notificationsDropdown');

            if (notificationsDropdown && !notificationBell && !notificationsContainer && notificationsDropdown.classList.contains('active')) {
                notificationsDropdown.classList.remove('active');
            }
        });
        
        // Toggle del tema
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            
            // Guardar tema en localStorage
            localStorage.setItem('theme', newTheme);
            
            // Actualizar en servidor
            fetch('<?php echo BASE_URL; ?>update_theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ theme: newTheme })
            });
        }
        
        // Cargar tema guardado
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.body.setAttribute('data-theme', savedTheme);
            }

            const notificationBell = document.querySelector('.notification-bell');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const notificationList = document.getElementById('notificationList');
            const notificationCountBadge = document.getElementById('notificationCount');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            const noNotificationsMessage = notificationList.querySelector('.no-notifications');

            // Función para formatear la fecha/hora
            function formatNotificationTime(timestamp) {
                const now = new Date();
                const date = new Date(timestamp);
                const diffMs = now - date;
                const diffMinutes = Math.round(diffMs / (1000 * 60));
                const diffHours = Math.round(diffMs / (1000 * 60 * 60));
                const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

                if (diffMinutes < 1) return 'Justo ahora';
                if (diffMinutes < 60) return `${diffMinutes} min. atrás`;
                if (diffHours < 24) return `${diffHours} hora${diffHours === 1 ? '' : 's'} atrás`;
                if (diffDays < 7) return `${diffDays} día${diffDays === 1 ? '' : 's'} atrás`;
                
                return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
            }

            // Función para cargar notificaciones
            async function loadNotifications() {
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>api/notifications.php?action=get&unreadOnly=true');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    notificationList.innerHTML = ''; 
                    if (data.success && data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationItem = document.createElement('div');
                            notificationItem.classList.add('notification-item');
                            notificationItem.classList.add(notification.is_read == 1 ? 'read' : 'unread');
                            notificationItem.dataset.id = notification.id;
                            // Almacenar el objeto completo de la notificación en el dataset
                            notificationItem.dataset.notificationData = JSON.stringify(notification);

                            notificationItem.innerHTML = `
                                <i class="icon fas fa-info-circle"></i>
                                <div class="message-text">${htmlspecialchars(notification.message)}</div>
                                <span class="timestamp">${formatNotificationTime(notification.created_at)}</span>
                            `;
                            notificationList.appendChild(notificationItem);

                            // Event listener para mostrar detalles y marcar como leído
                            notificationItem.addEventListener('click', async function(event) {
                                event.preventDefault(); // Prevenir cualquier acción de navegación por defecto
                                const clickedNotification = JSON.parse(this.dataset.notificationData);
                                
                                // Mostrar detalles de la notificación en un modal
                                let detailsContent = `<p>${htmlspecialchars(clickedNotification.message)}</p>`;
                                if (clickedNotification.details) {
                                    detailsContent += '<h4 style="margin-top: 1rem; margin-bottom: 0.5rem; color: var(--text-primary);">Detalles Adicionales:</h4>';
                                    detailsContent += '<pre style="background: var(--bg-tertiary); padding: 0.75rem; border-radius: 6px; text-align: left; overflow-x: auto; font-size: 0.8em; color: var(--text-primary);"><code style="white-space: pre-wrap; word-wrap: break-word;">' + htmlspecialchars(JSON.stringify(clickedNotification.details, null, 2)) + '</code></pre>';
                                }
                                showCustomAlert(detailsContent);

                                // Marcar como leída después de mostrar los detalles
                                if (this.classList.contains('unread')) {
                                    await markNotificationAsRead(this.dataset.id);
                                    this.classList.remove('unread');
                                    this.classList.add('read');
                                    if (notificationCountBadge && notificationCountBadge.style.display !== 'none') {
                                        let currentCount = parseInt(notificationCountBadge.textContent);
                                        updateNotificationCount(currentCount > 0 ? currentCount - 1 : 0);
                                    } else {
                                        updateNotificationCount(0); 
                                    }
                                    
                                    if (notificationList.querySelectorAll('.notification-item.unread').length === 0) {
                                        noNotificationsMessage.style.display = 'block';
                                    }
                                }
                            });
                        });
                        noNotificationsMessage.style.display = 'none';
                    } else {
                        noNotificationsMessage.style.display = 'block';
                    }
                    updateNotificationCount(data.unread_count); 
                } catch (error) {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<p class="no-notifications">Error al cargar notificaciones.</p>';
                    noNotificationsMessage.style.display = 'block';
                }
            }

            // Función para actualizar el contador de notificaciones
            function updateNotificationCount(count) {
                let currentBadge = document.getElementById('notificationCount');
                if (count > 0) {
                    if (!currentBadge) {
                        currentBadge = document.createElement('span');
                        currentBadge.id = 'notificationCount';
                        currentBadge.classList.add('notification-badge');
                        notificationBell.appendChild(currentBadge);
                    }
                    currentBadge.textContent = count;
                    currentBadge.style.display = 'inline-block';
                    if (markAllReadBtn) {
                        markAllReadBtn.style.display = 'inline-block';
                    }
                } else {
                    if (currentBadge) {
                        currentBadge.style.display = 'none';
                    }
                    if (markAllReadBtn) {
                        markAllReadBtn.style.display = 'none';
                    }
                }
                if (noNotificationsMessage) {
                    const unreadItemsCount = notificationList.querySelectorAll('.notification-item.unread').length;
                    if (count === 0 && unreadItemsCount === 0) {
                         noNotificationsMessage.style.display = 'block';
                    } else {
                        noNotificationsMessage.style.display = 'none';
                    }
                }
            }

            // Función para marcar una notificación específica como leída
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

            // Función para marcar todas las notificaciones como leídas
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async function(event) {
                    event.preventDefault();
                    showCustomConfirm('¿Estás seguro de que quieres marcar todas las notificaciones como leídas?', async function(confirmed) {
                        if (confirmed) {
                            try {
                                const response = await fetch('<?php echo BASE_URL; ?>api/notifications.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'mark_all_read' })
                                });
                                
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }

                                const data = await response.json();

                                if (data.success) {
                                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                                        item.classList.remove('unread');
                                        item.classList.add('read');
                                    });
                                    updateNotificationCount(0); 
                                    loadNotifications();
                                } else {
                                    showCustomAlert('Error al marcar todas las notificaciones como leídas: ' + data.message);
                                }
                            } catch (error) {
                                console.error('Error marking all notifications as read:', error);
                                showCustomAlert('Error de red al marcar notificaciones.');
                            }
                        }
                    });
                });
            }

            // Toggle del desplegable de notificaciones
            window.toggleNotificationsDropdown = async function(event) {
                event.preventDefault();
                notificationsDropdown.classList.toggle('active');
                const profileDropdown = document.getElementById('profileDropdown');
                if (profileDropdown && profileDropdown.classList.contains('active')) {
                    profileDropdown.classList.remove('active');
                }
                if (notificationsDropdown.classList.contains('active')) {
                    await loadNotifications(); 
                }
            };
            
            // Helper para htmlspecialchars en JS (para notificaciones)
            function htmlspecialchars(str) {
                const div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            // MODAL PERSONALIZADO para alert y confirm (en lugar de alert()/confirm())
            function createModal(message, type, callback) {
                const existingModal = document.getElementById('customModal');
                if (existingModal) existingModal.remove();

                const modalHtml = `
                    <div id="customModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; z-index: 9999;">
                        <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 12px; text-align: center; box-shadow: var(--shadow-lg); max-width: 400px; width: 90%; color: var(--text-primary);">
                            <p style="margin-bottom: 1.5rem; font-size: 1.1rem;">${message}</p>
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

            window.showCustomAlert = function(message, callback) {
                createModal(message, 'alert', callback);
            };

            window.showCustomConfirm = function(message, callback) {
                createModal(message, 'confirm', callback);
            };
        });
    </script>
</body>
</html>