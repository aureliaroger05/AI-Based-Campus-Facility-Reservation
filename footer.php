<?php
if (defined('HEADER_INCLUDED')):
    if (empty($_SESSION['ai_chat_csrf'])) {
        $_SESSION['ai_chat_csrf'] = bin2hex(random_bytes(32));
    }
    $isAdminPage = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
    $chatAssetPrefix = $isAdminPage ? '../' : '';
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="modal fade action-confirm-modal" id="action-confirm-dialog" tabindex="-1" aria-labelledby="action-confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="action-confirm-icon" aria-hidden="true"><i class="bi bi-question-lg"></i></div>
                <div class="modal-header border-0 justify-content-center pb-0">
                    <h2 class="modal-title fs-4" id="action-confirm-title">Please confirm</h2>
                </div>
                <div class="modal-body text-center px-4" data-confirm-message>Are you sure you want to continue?</div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No, go back</button>
                    <button type="button" class="btn btn-primary px-4" data-confirm-accept>Yes, continue</button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo $chatAssetPrefix; ?>assets/js/confirm-dialog.js?v=<?php echo (int)filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'confirm-dialog.js'); ?>"></script>
    <section class="ai-chat-widget" data-endpoint="<?php echo $chatAssetPrefix; ?>ai-chat.php" data-csrf="<?php echo htmlspecialchars($_SESSION['ai_chat_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <button class="ai-chat-launcher" type="button" aria-expanded="false" aria-controls="ai-chat-panel" aria-label="Open AI system assistant">
            <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
            <span class="ai-chat-launcher-label">Ask AI</span>
        </button>
        <div class="ai-chat-panel" id="ai-chat-panel" role="dialog" aria-label="AI system assistant" aria-modal="false" hidden>
            <header class="ai-chat-header">
                <span class="ai-chat-avatar"><i class="bi bi-stars" aria-hidden="true"></i></span>
                <div class="ai-chat-heading">
                    <strong>AI System Assistant</strong>
                    <span>Ask about reservations and features</span>
                </div>
                <button class="ai-chat-close" type="button" aria-label="Close chat">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>
            <div class="ai-chat-messages" role="log" aria-live="polite" aria-relevant="additions text">
                <div class="ai-chat-message ai-chat-message-assistant">
                    Hi! I can explain how facility reservations, QR codes, profiles, admin tools, and forecasts work.
                </div>
            </div>
            <div class="ai-chat-suggestions" aria-label="Suggested questions">
                <button type="button" data-prompt="How do I reserve a facility?">How do I reserve?</button>
                <button type="button" data-prompt="How does the reservation QR code work?">QR codes</button>
                <button type="button" data-prompt="What can an administrator do?">Admin features</button>
            </div>
            <form class="ai-chat-form">
                <label class="visually-hidden" for="ai-chat-input">Your message</label>
                <textarea id="ai-chat-input" class="form-control" rows="1" maxlength="1000" placeholder="Ask about the system..." required></textarea>
                <button class="ai-chat-send" type="submit" aria-label="Send message">
                    <i class="bi bi-send-fill" aria-hidden="true"></i>
                </button>
            </form>
            <p class="ai-chat-note">AI responses may be inaccurate. Don’t share passwords or sensitive information.</p>
        </div>
    </section>
    <script src="<?php echo $chatAssetPrefix; ?>assets/js/ai-chat.js?v=<?php echo (int)filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'ai-chat.js'); ?>"></script>
    <div class="mt-auto pt-5 pb-5"></div>
    <footer>
        <div class="container text-center py-4">
            <p class="small mb-0 opacity-75">&copy; <?php echo date('Y'); ?> AI Based Campus Facility Reservation System w/ QR & Forecasting.</p>
        </div>
    </footer>
    </body>
</html>
<?php endif; ?>
