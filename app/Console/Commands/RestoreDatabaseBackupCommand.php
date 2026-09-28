<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

/**
 * يسترجع نسخة قاعدة بيانات مشفّرة (docs/RUNBOOK.md "استرجاع نسخة احتياطية").
 *
 *     php artisan backup:restore-database --latest --verify
 *     php artisan backup:restore-database database/2026-09-28T000000Z.ajdb
 *     php artisan backup:restore-database --latest --wipe --force
 *
 * --verify يفك التشفير ويتحقق من اكتمال النسخة فقط، ولا يكتب شيئًا (لتجربة الاسترجاع
 * الدورية). الاسترجاع الفعلي إلى قاعدة فارغة، أو مع --wipe يحذف كل جداول القاعدة
 * الحالية أولًا، وكل ذلك في معاملة واحدة تُلغى كاملة عند أي خطأ.
 */
class RestoreDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:restore-database
        {backup? : مسار النسخة كما يعرضه backup:list}
        {--latest : أحدث نسخة}
        {--verify : تحقق من النسخة دون استرجاع}
        {--wipe : احذف كل جداول القاعدة الحالية قبل الاسترجاع}
        {--force : دون طلب تأكيد}';

    protected $description = 'استرجاع نسخة مشفّرة من قاعدة البيانات أو التحقق منها';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $path = $this->option('latest') ? $backup->latest() : $this->argument('backup');
        } catch (Throwable $exception) {
            $this->error('تعذّرت قراءة وجهة النسخ: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_string($path) || $path === '') {
            $this->error('حدّد النسخة بمسارها أو مرّر --latest (php artisan backup:list يعرض النسخ).');

            return self::FAILURE;
        }

        try {
            if ($this->option('verify')) {
                $summary = $backup->verify($path);
                $this->info('النسخة سليمة: '.$path);
                $this->line('أُنشئت: '.$summary['created_at'].' — الجداول: '.count($summary['tables']).' — الصفوف: '.array_sum($summary['rows']));

                return self::SUCCESS;
            }

            if (! $this->option('force') && ! $this->confirm(
                $this->option('wipe')
                    ? "سيُحذف كل ما في قاعدة البيانات الحالية ({$this->databaseName()}) ويُستبدل بالنسخة {$path}. متابعة؟"
                    : "استرجاع النسخة {$path} إلى قاعدة البيانات ({$this->databaseName()})؟",
            )) {
                $this->warn('أُلغي الاسترجاع.');

                return self::FAILURE;
            }

            $summary = $backup->restore($path, (bool) $this->option('wipe'));
        } catch (Throwable $exception) {
            $this->error('فشل: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('اكتمل الاسترجاع من '.$path.' (أُنشئت '.$summary['created_at'].').');
        $this->line('الصفوف المسترجعة: '.array_sum($summary['rows']).' في '.count($summary['tables']).' جدولًا.');
        $this->line('التالي: php artisan backup:restore-receipts ثم php artisan receipts:purge-expired (docs/RUNBOOK.md).');

        return self::SUCCESS;
    }

    private function databaseName(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }
}
