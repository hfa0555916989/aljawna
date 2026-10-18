<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ملف /robots.txt: على النطاق الأساسي يسمح بالزحف ويشير إلى خريطة الموقع، وعلى أي
 * نطاق آخر (مثل نطاق laravel.cloud الافتراضي) يمنع الزحف كله (App\Support\Seo).
 */
class RobotsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $lines = Seo::isCanonicalHost($request)
            ? ['User-agent: *', 'Disallow:', '', 'Sitemap: '.Seo::baseUrl().'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
