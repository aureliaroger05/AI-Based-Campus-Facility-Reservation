// Custom JS for Reservation System
document.addEventListener('DOMContentLoaded', function() {
    // Auto check availability on date/time change
    const forms = document.querySelectorAll('form input[type="date"], form input[type="time"]');
    forms.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            const btn = form.querySelector('button[name="reserve"]');
            // AJAX check avail if implemented
            btn.disabled = true;
        });
    });

    // Confirm before actions
    const confirms = document.querySelectorAll('[onclick]');
    // ...

    console.log('Reservation system JS loaded.');
});

