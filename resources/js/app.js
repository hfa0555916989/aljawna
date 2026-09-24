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

/**
 * زر نسخ رقم الحساب أو الآيبان (resources/views/components/copy-field.blade.php).
 * يجرّب واجهة الحافظة، ثم النسخ القديم، فإن رفض المتصفح كليهما يعرض الرقم محدّدًا في حقل ليُنسخ يدويًا.
 */
const legacyCopy = (text) => {
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    field.remove();

    return copied;
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('copyField', (text) => ({
        state: 'idle',
        timer: null,

        async copy() {
            let copied = false;

            try {
                await navigator.clipboard.writeText(text);
                copied = true;
            } catch {
                copied = legacyCopy(text);
            }

            clearTimeout(this.timer);

            if (copied) {
                this.state = 'copied';
                this.timer = setTimeout(() => (this.state = 'idle'), 2500);

                return;
            }

            this.state = 'manual';
            this.$nextTick(() => {
                this.$refs.manual.focus();
                this.$refs.manual.select();
            });
        },
    }));
});
