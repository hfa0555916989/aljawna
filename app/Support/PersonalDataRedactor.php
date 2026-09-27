<?php

declare(strict_types=1);

namespace App\Support;

use Error;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use PDOException;
use ReflectionProperty;
use Stringable;
use Throwable;

/**
 * يحجب أرقام الجوال السعودية والآيبانات من كل سجل قبل كتابته (docs/SPEC.md §12.12، T20):
 * في نص الرسالة، والسياق والإضافات بكل عمقها، ورسائل الاستثناءات المرفقة وأسبابها
 * (كرسالة قيد فريد من PostgreSQL تحمل قيمة الجوال). يُطبَّق على كل قناة سجل
 * (App\Support\RedactingLogManager)، ومنها قناة Laravel Cloud التي تُنشأ عند الإقلاع.
 */
final class PersonalDataRedactor implements ProcessorInterface
{
    public const string PHONE_MASK = '[phone]';

    public const string IBAN_MASK = '[iban]';

    /**
     * الأرقام اللاتينية والعربية والهندية، كما يقبلها App\Support\SaudiPhone.
     */
    private const string DIGIT = '[0-9٠-٩۰-۹]';

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $record->message;

        foreach ($record->context as $value) {
            if ($value instanceof Throwable) {
                $original = $value->getMessage();
                self::redactThrowable($value);

                // معالج الاستثناءات ينسخ رسالة الاستثناء نصًا للسجل قبل هذه الخطوة.
                $message = $message === $original ? $value->getMessage() : $message;
            }
        }

        return $record->with(
            message: self::redact($message),
            context: self::redactValue($record->context),
            extra: self::redactValue($record->extra),
        );
    }

    public static function redact(string $text): string
    {
        $d = self::DIGIT;

        $text = preg_replace('/(?<![A-Za-z0-9])SA\s?'.$d.'{2}(?:\s?'.$d.'){20}(?!'.$d.')/iu', self::IBAN_MASK, $text) ?? $text;

        return preg_replace('/(?<!'.$d.')(?:(?:\+|00)\s?966|966|'.'[0٠۰]'.')?\s?[5٥۵]'.$d.'(?:[\s\-]?'.$d.'){7}(?!'.$d.')/u', self::PHONE_MASK, $text) ?? $text;
    }

    /**
     * @template TValue
     *
     * @param  TValue  $value
     * @return TValue|string|array<mixed>
     */
    private static function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::redact($value);
        }

        if (is_array($value)) {
            return array_map(self::redactValue(...), $value);
        }

        if ($value instanceof Stringable && ! $value instanceof Throwable) {
            return self::redact((string) $value);
        }

        return $value;
    }

    /**
     * يحجب رسالة الاستثناء وأسبابه في مكانها، لأن منسّقات Monolog تقرؤها منه مباشرة.
     * استثناء الاستعلام يفقد قيم معاملاته كلها (أسماء وتجزئات كلمات مرور وعناوين)،
     * ويبقى نص SQL بعلامات ? فقط.
     */
    private static function redactThrowable(Throwable $throwable): void
    {
        for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
            $message = $current instanceof QueryException
                ? Str::before($current->getMessage(), ' (Connection:').' (SQL: '.$current->getSql().')'
                : $current->getMessage();

            if ($current instanceof PDOException && is_array($current->errorInfo)) {
                $current->errorInfo = array_map(
                    fn (mixed $item): mixed => is_string($item) ? self::redact($item) : $item,
                    $current->errorInfo,
                );
            }

            $redacted = self::redact($message);

            if ($redacted === $current->getMessage()) {
                continue;
            }

            $property = new ReflectionProperty($current instanceof Error ? Error::class : Exception::class, 'message');
            $property->setValue($current, $redacted);
        }
    }
}
