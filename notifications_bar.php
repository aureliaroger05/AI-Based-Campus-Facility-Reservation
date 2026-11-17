<?php
require_once __DIR__ . '/notifications.php';

$notificationFeed = ['count' => 0, 'items' => []];
try {
    $notificationFeed = getUserNotifications((int)($_SESSION['user_id'] ?? 0));
} catch (Throwable $exception) {
    error_log('Could not render the notification bell: ' . $exception->getMessage());
}

$notificationItems = is_array($notificationFeed['items'] ?? null) ? $notificationFeed['items'] : [];
$notificationCount = (int)($notificationFeed['count'] ?? 0);
$notificationIcons = [
    'pending' => 'bi-hourglass-split',
    'confirmed' => 'bi-check2-circle',
    'completed' => 'bi-patch-check',
    'cancelled' => 'bi-x-circle',
];
$notificationSummary = $notificationCount === 0
    ? 'All caught up'
    : $notificationCount . ' need' . ($notificationCount === 1 ? 's' : '') . ' attention';
?>
<div class="navbar-tools" data-user-toolbar>
    

    <div class="navbar-notifications" data-user-notifications data-endpoint="user-notifications.php">
        <button class="navbar-icon-button navbar-notification-toggle" type="button" data-user-notifications-toggle aria-expanded="false" aria-controls="userNotificationPanel" aria-label="Notifications">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span class="navbar-notification-badge" data-user-notification-badge<?php echo $notificationCount > 0 ? '' : ' hidden'; ?>><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></span>
        </button>

        <section class="navbar-notification-panel" id="userNotificationPanel" aria-label="Notifications" hidden>
            <header class="navbar-notification-heading">
                <strong>Notifications</strong>
                <span data-user-notification-summary><?php echo htmlspecialchars($notificationSummary, ENT_QUOTES, 'UTF-8'); ?></span>
            </header>

            <div class="navbar-notification-list" data-user-notification-list>
                <?php foreach ($notificationItems as $notification): ?>
                    <a class="navbar-notification-item is-<?php echo htmlspecialchars($notification['status'], ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($notification['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="navbar-notification-item-icon" aria-hidden="true">
                            <i class="bi <?php echo htmlspecialchars($notificationIcons[$notification['status']] ?? 'bi-info-circle', ENT_QUOTES, 'UTF-8'); ?>"></i>
                        </span>
                        <span class="navbar-notification-item-body">
                            <strong><?php echo htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <small><?php echo htmlspecialchars($notification['meta'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <p class="navbar-notification-empty" data-user-notification-empty<?php echo $notificationItems ? ' hidden' : ''; ?>>
                No new reservation updates.
            </p>

            <a class="navbar-notification-footer" href="profile.php">
                View my reservations
                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
        </section>
    </div>
</div>
