<?php

declare(strict_types=1);

namespace App\Support;

/**
 * إيصال حُفظ على القرص الخاص: مساره العشوائي وبصمة SHA-256 للملف كما رفعه المبادر.
 */
final readonly class StoredReceipt
{
    public function __construct(
        public string $path,
        public string $hash,
    ) {}
}
