<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SystemHeartbeat;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * نبض عامل الطوابير: يرسله المجدول كل دقيقة، ولا يُسجَّل إلا إن نفّذه عامل فعلًا.
 * فريد خلال مهلته فلا تتراكم نسخه إن توقف العامل طويلًا.
 */
class RecordQueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(): void
    {
        SystemHeartbeat::beat(SystemHeartbeat::QUEUE);
    }
}
