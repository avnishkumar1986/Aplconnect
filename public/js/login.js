document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('password-toggle');
    const input = document.getElementById('password');
    if (!button || !input) return;
    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});
