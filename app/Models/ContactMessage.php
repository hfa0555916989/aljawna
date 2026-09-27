<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * رسالة "اتصل بنا" (docs/SPEC.md §9 contact_messages، FR-55). تُقرأ بصلاحية
 * messages.view فقط، وتُنشأ عبر App\Actions\Contact\SendContactMessage بعد
 * التحقق من Turnstile وحقل الفخ وحدّ المعدل لكل IP.
 *
 * @property string $name
 * @property string $phone
 * @property string $body
 * @property string $ip
 * @property Carbon $created_at
 * @property Carbon|null $read_at
 */
#[Fillable(['name', 'phone', 'body', 'ip', 'read_at'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
