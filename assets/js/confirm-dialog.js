document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('action-confirm-dialog');
    if (!dialog || !window.bootstrap) {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(dialog);
    const message = dialog.querySelector('[data-confirm-message]');
    const acceptButton = dialog.querySelector('[data-confirm-accept]');
    let pendingAction = null;

    function askToConfirm(text, action) {
        message.textContent = text || 'Are you sure you want to continue?';
        pendingAction = action;
        modal.show();
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-confirm]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        askToConfirm(link.dataset.confirm, function () {
            window.location.assign(link.href);
        });
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;
        const submitter = event.submitter;
        const prompt = submitter && submitter.dataset.confirm
            ? submitter.dataset.confirm
            : form.dataset.confirm;

        if (!prompt || form.__confirmationApproved) {
            delete form.__confirmationApproved;
            return;
        }

        event.preventDefault();
        askToConfirm(prompt, function () {
            form.__confirmationApproved = true;
            if (form.requestSubmit) {
                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
            } else {
                form.submit();
            }
        });
    }, true);

    acceptButton.addEventListener('click', function () {
        const action = pendingAction;
        pendingAction = null;
        modal.hide();
        if (action) {
            action();
        }
    });

    dialog.addEventListener('hidden.bs.modal', function () {
        pendingAction = null;
    });
});
