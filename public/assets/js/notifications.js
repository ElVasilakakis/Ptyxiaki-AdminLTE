/**
 * Notification System JavaScript
 * Handles real-time notifications in the topbar
 */

class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.refreshInterval = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadNotifications();
        this.startAutoRefresh();
    }

    bindEvents() {
        // Mark all as read button
        document.getElementById('markAllReadBtn').addEventListener('click', () => {
            this.markAllAsRead();
        });

        // Clear read notifications button
        document.getElementById('clearReadBtn').addEventListener('click', () => {
            this.clearReadNotifications();
        });

        // Refresh notifications when dropdown is opened
        document.getElementById('notificationDropdown').addEventListener('click', () => {
            this.loadNotifications();
        });
    }

    async loadNotifications() {
        try {
            const response = await fetch('/api/notifications?limit=15');
            const data = await response.json();
            
            if (data.success) {
                this.notifications = data.notifications;
                this.unreadCount = data.unread_count;
                this.updateUI();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    async getUnreadCount() {
        try {
            const response = await fetch('/api/notifications/unread-count');
            const data = await response.json();
            
            if (data.success) {
                this.unreadCount = data.count;
                this.updateBadge();
            }
        } catch (error) {
            console.error('Error getting unread count:', error);
        }
    }

    updateUI() {
        this.updateBadge();
        this.updateNotificationsList();
        this.updateButtons();
    }

    updateBadge() {
        const badge = document.getElementById('notificationBadge');
        if (this.unreadCount > 0) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }

    updateNotificationsList() {
        const listContainer = document.getElementById('notificationsList');
        const noNotifications = document.getElementById('noNotifications');

        if (this.notifications.length === 0) {
            noNotifications.style.display = 'block';
            return;
        }

        noNotifications.style.display = 'none';
        
        const notificationsHTML = this.notifications.map(notification => {
            return this.createNotificationHTML(notification);
        }).join('');

        listContainer.innerHTML = notificationsHTML;

        // Bind click events for individual notifications
        this.bindNotificationEvents();
    }

    createNotificationHTML(notification) {
        const isUnread = !notification.is_read;
        const unreadClass = isUnread ? 'bg-light' : '';
        const unreadIndicator = isUnread ? '<span class="badge bg-primary rounded-pill ms-2">New</span>' : '';

        return `
            <div class="dropdown-item notification-item ${unreadClass}" data-id="${notification.id}" data-read="${notification.is_read}">
                <div class="d-flex align-items-start">
                    <div class="me-3">
                        <i class="${notification.severity_icon} text-${notification.severity_color}" style="font-size: 1.2rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-1 fw-semibold">${notification.title}${unreadIndicator}</h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted p-0" data-bs-toggle="dropdown">
                                    <i class="ph-dots-three-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    ${!notification.is_read ? `<button class="dropdown-item mark-read-btn" data-id="${notification.id}">Mark as read</button>` : ''}
                                    <button class="dropdown-item delete-notification-btn text-danger" data-id="${notification.id}">Delete</button>
                                </div>
                            </div>
                        </div>
                        <p class="mb-1 text-muted small">${notification.message}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                ${notification.device_name ? `<i class="ph-device-mobile me-1"></i>${notification.device_name}` : ''}
                                ${notification.sensor_name ? ` • ${notification.sensor_name}` : ''}
                            </small>
                            <small class="text-muted">${notification.time_ago}</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    bindNotificationEvents() {
        // Mark individual notification as read
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                this.markAsRead(id);
            });
        });

        // Delete individual notification
        document.querySelectorAll('.delete-notification-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                this.deleteNotification(id);
            });
        });

        // Mark as read when clicking on unread notification
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = item.getAttribute('data-id');
                const isRead = item.getAttribute('data-read') === 'true';
                
                if (!isRead) {
                    this.markAsRead(id);
                }
            });
        });
    }

    updateButtons() {
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationsDivider = document.getElementById('notificationsDivider');
        const notificationsFooter = document.getElementById('notificationsFooter');

        if (this.unreadCount > 0) {
            markAllReadBtn.style.display = 'block';
        } else {
            markAllReadBtn.style.display = 'none';
        }

        if (this.notifications.length > 0) {
            notificationsDivider.style.display = 'block';
            notificationsFooter.style.display = 'block';
        } else {
            notificationsDivider.style.display = 'none';
            notificationsFooter.style.display = 'none';
        }
    }

    async markAsRead(id) {
        try {
            const response = await fetch(`/api/notifications/${id}/mark-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();
            if (data.success) {
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();
            if (data.success) {
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }

    async deleteNotification(id) {
        try {
            const response = await fetch(`/api/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();
            if (data.success) {
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    }

    async clearReadNotifications() {
        try {
            const response = await fetch('/api/notifications/clear-read', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();
            if (data.success) {
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error clearing read notifications:', error);
        }
    }

    startAutoRefresh() {
        // Refresh unread count every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.getUnreadCount();
        }, 30000);
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.notificationManager = new NotificationManager();
});
