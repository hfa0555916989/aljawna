<?php

declare(strict_types=1);

use App\Support\SaudiIban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * بيانات وهمية صالحة لنموذج المستفيد في اللوحة (لا بيانات حقيقية).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function beneficiaryFormData(array $overrides = []): array
{
    return [
        'display_name' => 'سالم ماجد تركي العجاوني',
        'account_holder' => 'سالم ماجد تركي العجاوني',
        'bank_name' => 'مصرف الراجحي',
        'account_number' => '123456789012345',
        'iban' => SaudiIban::fromParts('80', '000000123456789012'),
        'target_amount' => '45000',
        'target_deadline' => '2026-12-01',
        'recommended_deadline' => '2026-11-01',
        'wedding_date' => '2027-01-15',
        ...$overrides,
    ];
}
