<?php

declare(strict_types=1);

namespace App\Support;

/**
 * مطابقة اسم المصرف المُدخل مع رمز المصرف في الآيبان وفق config/banks.php.
 */
final class SaudiBanks
{
    private function __construct()
    {
        //
    }

    /**
     * أسماء المصارف المقترحة في حقل اسم المصرف.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(array_unique(array_column(self::codes(), 'name')));
    }

    /**
     * اسم المصرف الذي يخص رمز الآيبان إن لم يطابقه الاسم المُدخل، وإلا null.
     * لا تنبيه إن كان الآيبان غير صالح أو رمزه غير معروف أو الاسم فارغًا.
     */
    public static function mismatchFor(?string $bankName, ?string $iban): ?string
    {
        $code = SaudiIban::isValid($iban) ? SaudiIban::bankCode($iban) : null;
        $bank = $code === null ? null : (self::codes()[$code] ?? null);
        $name = self::simplify((string) $bankName);

        if ($bank === null || $name === '') {
            return null;
        }

        foreach ([$bank['name'], ...$bank['aliases']] as $alias) {
            if (str_contains($name, self::simplify($alias))) {
                return null;
            }
        }

        return $bank['name'];
    }

    /**
     * @return array<string, array{name: string, aliases: list<string>}>
     */
    private static function codes(): array
    {
        /** @var array<string, array{name: string, aliases: list<string>}> */
        return config('banks.iban_codes', []);
    }

    /**
     * توحيد الهمزات والتاء المربوطة والألف المقصورة والمسافات وحالة الحروف قبل المقارنة.
     */
    private static function simplify(string $value): string
    {
        $value = str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $value);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
