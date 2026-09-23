/**
 * يرسم Cloudflare Turnstile صراحةً بعد تحميل سكربته، ويمرّر الرمز إلى Livewire.
 * يُستدعى من صفحة التسجيل فقط (resources/views/livewire/auth/register.blade.php).
 */
window.renderTurnstile = (element, sitekey, onToken) => {
    const render = () => {
        window.turnstile.render(element, {
            sitekey,
            language: 'ar',
            theme: 'auto',
            size: 'flexible',
            callback: onToken,
            'expired-callback': () => onToken(''),
            'error-callback': () => onToken(''),
        });
    };

    if (window.turnstile) {
        render();

        return;
    }

    const timer = setInterval(() => {
        if (window.turnstile) {
            clearInterval(timer);
            render();
        }
    }, 100);
};
