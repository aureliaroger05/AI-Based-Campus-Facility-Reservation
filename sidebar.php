<?php
require_once __DIR__ . '/auth.php';

$items = [
    ['label' => 'Home', 'icon' => 'bi bi-house', 'href' => 'index.php'],
    ['label' => 'Facilities', 'icon' => 'bi bi-building', 'href' => 'index.php?tab=available'],
    ['label' => 'Reserved', 'icon' => 'bi bi-bookmarks', 'href' => 'index.php?tab=reserved'],
    ['label' => 'Profile', 'icon' => 'bi bi-person', 'href' => 'profile.php'],
    ['label' => 'Forecast', 'icon' => 'bi bi-graph-up', 'href' => 'ai-forecast.php'],
];

if (isAdmin()) {
    $items[] = ['label' => 'Admin', 'icon' => 'bi bi-gear', 'href' => 'admin/'];
}

$logoutHref = 'logout.php';
?>

<div class="bb-sidebar" id="bbSidebar">
    <div class="bb-sidebar__top">
        <div class="bb-sidebar__brand">
            <i class="bi bi-building"></i>
            <span>AI Reservation</span>
        </div>
        <button class="bb-sidebar__toggle" type="button" aria-label="Toggle sidebar" onclick="window.bbSidebar?.toggle();">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <nav class="bb-sidebar__nav">
        <?php foreach ($items as $it): ?>
            <a class="bb-sidebar__link" href="<?php echo htmlspecialchars($it['href']); ?>">
                <i class="<?php echo htmlspecialchars($it['icon']); ?>"></i>
                <span><?php echo htmlspecialchars($it['label']); ?></span>
            </a>
        <?php endforeach; ?>

        <?php if (isLoggedIn()): ?>
            <a class="bb-sidebar__link bb-sidebar__logout" href="<?php echo htmlspecialchars($logoutHref); ?>">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</span>
            </a>
        <?php else: ?>
            <a class="bb-sidebar__link bb-sidebar__logout" href="login.php">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Login</span>
            </a>
        <?php endif; ?>
    </nav>
</div>

<script>
    window.bbSidebar = window.bbSidebar || (function () {
        const el = document.getElementById('bbSidebar');
        return {
            toggle: function () {
                if (!el) return;
                el.classList.toggle('is-collapsed');
            }
        };
    })();
</script>

