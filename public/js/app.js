document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-pressed', String(show));
    });
});

document.querySelectorAll('[data-submit-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        const button = form.querySelector('[type="submit"]');
        button.dataset.originalText = button.textContent;
        button.disabled = true;
        button.textContent = button.dataset.loadingText;
        form.setAttribute('aria-busy', 'true');
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-original-text]').forEach((button) => {
        button.disabled = false;
        button.textContent = button.dataset.originalText;
        button.closest('form').removeAttribute('aria-busy');
    });
});
document.querySelector('[data-error-summary]')?.focus();
