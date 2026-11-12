(function () {
    const widget = document.querySelector('.ai-chat-widget');
    if (!widget) return;

    const launcher = widget.querySelector('.ai-chat-launcher');
    const panel = widget.querySelector('.ai-chat-panel');
    const closeButton = widget.querySelector('.ai-chat-close');
    const messages = widget.querySelector('.ai-chat-messages');
    const form = widget.querySelector('.ai-chat-form');
    const input = widget.querySelector('#ai-chat-input');
    const sendButton = widget.querySelector('.ai-chat-send');
    const history = [];
    let busy = false;

    function setOpen(open) {
        panel.hidden = !open;
        widget.classList.toggle('is-open', open);
        launcher.setAttribute('aria-expanded', String(open));
        if (open) input.focus();
    }

    function addMessage(content, role) {
        const message = document.createElement('div');
        message.className = 'ai-chat-message ai-chat-message-' + role;
        message.textContent = content;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
        return message;
    }

    async function sendMessage(text) {
        const message = text.trim();
        if (!message || busy) return;

        busy = true;
        sendButton.disabled = true;
        input.value = '';
        input.style.height = '';
        addMessage(message, 'user');

        const pending = addMessage('Thinking...', 'assistant ai-chat-pending');
        try {
            const response = await fetch(widget.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': widget.dataset.csrf
                },
                body: JSON.stringify({ message, history })
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || 'The assistant could not respond. Please try again.');
            }

            pending.classList.remove('ai-chat-pending');
            pending.textContent = result.reply;
            history.push(
                { role: 'user', content: message },
                { role: 'assistant', content: result.reply }
            );
            if (history.length > 8) history.splice(0, history.length - 8);
        } catch (error) {
            pending.classList.remove('ai-chat-pending');
            pending.classList.add('ai-chat-error');
            pending.textContent = error instanceof Error ? error.message : 'The assistant could not respond. Please try again.';
        } finally {
            busy = false;
            sendButton.disabled = false;
            input.focus();
        }
    }

    launcher.addEventListener('click', function () {
        setOpen(panel.hidden);
    });
    closeButton.addEventListener('click', function () {
        setOpen(false);
        launcher.focus();
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });
    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    widget.querySelectorAll('[data-prompt]').forEach(function (button) {
        button.addEventListener('click', function () {
            setOpen(true);
            sendMessage(button.dataset.prompt || '');
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            launcher.focus();
        }
    });
})();
