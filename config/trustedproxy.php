<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | الوكلاء الموثوقون (Trusted Proxies)
    |--------------------------------------------------------------------------
    |
    | proxies: عناوين الوسطاء الذين تُقبل منهم ترويسات X-Forwarded-* (مفصولة
    | بفواصل)، أو * لقبول أي وسيط. الفارغ على Laravel Cloud يعني * تلقائيًا
    | (Illuminate\Http\Middleware\TrustProxies)، فالتطبيق هناك لا يُصل إليه إلا
    | عبر شبكة Cloudflare الطرفية. خارج Laravel Cloud يبقى الفارغ "لا وسيط".
    |
    | client_ip_header: ترويسة يضعها الوسيط الطرفي نفسه بعنوان الزائر ولا يقبلها
    | من الزائر، فتُقدَّم على X-Forwarded-For الذي يضيف إليه Cloudflare ولا
    | يستبدله (فيمكن للزائر أن يزوّر أوله). الفارغ على Laravel Cloud يعني
    | CF-Connecting-IP، وnone يعطّلها. لا تُقرأ إلا من طلب جاء عبر وسيط موثوق
    | (App\Http\Middleware\TrustProxies).
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

    'client_ip_header' => env('TRUSTED_PROXY_CLIENT_IP_HEADER'),

];
