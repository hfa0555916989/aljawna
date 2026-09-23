# هوية التصميم والرموز

مرجع بصري: `docs/prototype/ajawna-initiative.html` (الشكل فقط).

## الخطوط (مستضافة محليًا)
- النص: **IBM Plex Sans Arabic** 400/500/600/700، مع بدائل `Tahoma, "Segoe UI", sans-serif`.
- الشعار واسم المبادرة في الهيرو: **Aref Ruqaa** 700.
- الأرقام اللاتينية في الجوال والآيبان، وخط أحادي `ui-monospace` للآيبان.

## الألوان (فاتح)
| الاسم | القيمة |
|---|---|
| `--bg` | `#F2F5F1` |
| `--surface` | `#FFFFFF` |
| `--ink` | `#10302C` |
| `--muted` | `#586C67` |
| `--line` | `#D3DED8` |
| `--pri` (أخضر عميق) | `#0F4C45` |
| `--pri-ink` | `#FFFFFF` |
| `--brass` (نحاسي) | `#B58A2A` |
| `--brass-soft` | `#F3EACF` |
| `--bad` | `#A33A2B` |
| `--good` | `#2C7A54` |
| خلفية الهيرو | `#0D3B36` مع نمط نجمة ثمانية شفاف |

## الألوان (داكن)
`--bg #0B1A18` · `--surface #122624` · `--ink #E7F0EC` · `--muted #9BB1AB` · `--line #27413C` · `--pri #3FA593` · `--pri-ink #062320` · `--brass #D6AC54` · `--brass-soft #3A3117` · `--bad #E07A6B` · `--good #5CC08F`.

> `--bad` و`--good` في الوضع الداكن أفتح من الفاتح لتحقيق تباين AA على `--surface` و`--bg` (معتمد في T02).

## الأشكال
- بطاقات: نصف قطر 14px وحد 1px بلون `--line` بلا ظل.
- أزرار: 10px، وأقراص/شارات: 999px.
- حلقة التقدّم (Ring) في الصفحة الرئيسية بلون `--brass` على خلفية شفافة فوق الهيرو.
- شريط التقدّم: ارتفاع 14px، تعبئة `--brass`.

## Tailwind 4
```css
@import "tailwindcss";

@theme inline {
  --font-sans: "IBM Plex Sans Arabic", Tahoma, "Segoe UI", sans-serif;
  --font-brand: "Aref Ruqaa", serif;
  --color-bg: var(--bg);
  --color-surface: var(--surface);
  --color-ink: var(--ink);
  --color-muted: var(--muted);
  --color-line: var(--line);
  --color-pri: var(--pri);
  --color-pri-ink: var(--pri-ink);
  --color-brass: var(--brass);
  --color-bad: var(--bad);
  --color-good: var(--good);
}

:root { --bg:#F2F5F1; --surface:#fff; --ink:#10302C; --muted:#586C67; --line:#D3DED8;
        --pri:#0F4C45; --pri-ink:#fff; --brass:#B58A2A; --bad:#A33A2B; --good:#2C7A54; }
@media (prefers-color-scheme: dark) {
  :root { --bg:#0B1A18; --surface:#122624; --ink:#E7F0EC; --muted:#9BB1AB; --line:#27413C;
          --pri:#3FA593; --pri-ink:#062320; --brass:#D6AC54; --bad:#E07A6B; --good:#5CC08F; }
}
```
> عند تفعيل وحدة الهوية (T16) تُستبدل `--pri` و`--brass` من `site_branding` عبر متغيرات CSS تُحقن في الصفحة، بعد فحص التباين.

## قواعد الأداء والوصولية
- تباين WCAG AA، وحالة تركيز ظاهرة، وأهداف لمس 44px.
- `viewport-fit=cover` مع `env(safe-area-inset-*)`.
- لا رسوم متحركة ثقيلة، واحترم `prefers-reduced-motion`.
