{{--
    قالب صفحات الأخطاء (T20). مستقل تمامًا: لا قاعدة بيانات ولا Vite ولا جلسة،
    فيعمل حين يتعطل أيٌّ منها (500 و503). الأنماط مضمَّنة بألوان docs/DESIGN-TOKENS.md.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('errors.'.$code.'.title') }}</title>
        <style>
            :root { --bg:#F2F5F1; --surface:#fff; --ink:#10302C; --muted:#586C67; --line:#D3DED8; --pri:#0F4C45; --pri-ink:#fff; --brass:#B58A2A; }
            @media (prefers-color-scheme: dark) {
                :root { --bg:#0B1A18; --surface:#122624; --ink:#E7F0EC; --muted:#9BB1AB; --line:#27413C; --pri:#3FA593; --pri-ink:#062320; --brass:#D6AC54; }
            }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
                   background: var(--bg); color: var(--ink); font-family: "IBM Plex Sans Arabic", Tahoma, "Segoe UI", sans-serif; line-height: 1.7; }
            main { width: 100%; max-width: 28rem; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 24px; text-align: center; }
            .code { display: inline-block; margin: 0 0 8px; padding: 2px 12px; border-radius: 999px; border: 1px solid var(--brass); color: var(--ink); font-size: .875rem; direction: ltr; }
            h1 { margin: 0 0 8px; font-size: 1.5rem; }
            p { margin: 0 0 20px; color: var(--muted); }
            a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 8px 16px; border-radius: 10px;
                background: var(--pri); color: var(--pri-ink); font-weight: 600; text-decoration: none; }
            a:focus-visible { outline: 2px solid var(--pri); outline-offset: 2px; }
        </style>
    </head>
    <body>
        <main>
            <p class="code">{{ __('errors.code', ['code' => $code]) }}</p>
            <h1>{{ __('errors.'.$code.'.title') }}</h1>
            <p>{{ __('errors.'.$code.'.message') }}</p>
            <a href="{{ url('/') }}">{{ __('errors.home') }}</a>
        </main>
    </body>
</html>
