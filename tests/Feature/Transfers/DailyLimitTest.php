<?php

declare(strict_types=1);

use App\Actions\Transfers\CreateTransfer;
use App\Livewire\Transfers\Create;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الحد اليومي لعدد الحوالات لكل مبادر (T07 — §12.3)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('receipts');
    config(['security.transfers.daily_limit' => 2]);
    $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00', 'Asia/Riyadh'));
    $this->initiator = User::factory()->create();
    $this->beneficiary = Beneficiary::factory()->approved()->create();
});

function uploadViaPage(User $user, Beneficiary $beneficiary, int $width): Testable
{
    return Livewire::actingAs($user)->test(Create::class)
        ->set('beneficiary_id', (string) $beneficiary->id)
        ->set('amount', (string) (100 + $width))
        ->set('transferred_on', '2026-09-24')
        ->set('receipt', UploadedFile::fake()->createWithContent('r.jpg', jpegBytes($width, 20)))
        ->call('save');
}

test('الحد الافتراضي مضبوط من الإعدادات', function (): void {
    expect(config('security.transfers.daily_limit'))->toBe(2)
        ->and(app(CreateTransfer::class)->dailyLimit())->toBe(2);
});

test('بعد بلوغ الحد اليومي تُرفض الحوالة التالية ولا يُحفظ إيصالها', function (): void {
    uploadViaPage($this->initiator, $this->beneficiary, 30)->assertHasNoErrors();
    uploadViaPage($this->initiator, $this->beneficiary, 31)->assertHasNoErrors();

    uploadViaPage($this->initiator, $this->beneficiary, 32)
        ->assertHasErrors(['form'])
        ->assertSee(__('transfers.validation.daily_limit', ['limit' => 2]));

    expect(Transfer::query()->count())->toBe(2)
        ->and(Storage::disk('receipts')->allFiles())->toHaveCount(2);
});

test('الإجراء يفرض الحد ولو استُدعي مباشرة', function (): void {
    Transfer::factory()->count(2)->for($this->initiator)->create();

    expect(fn () => app(CreateTransfer::class)->handle(
        $this->initiator, $this->beneficiary->id, '100', '2026-09-24', null,
        UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()),
    ))->toThrow(ValidationException::class);

    expect(Storage::disk('receipts')->allFiles())->toBe([]);
});

test('حوالات الأمس بتوقيت الرياض لا تُحتسب في حد اليوم', function (): void {
    Transfer::factory()->count(2)->for($this->initiator)->create([
        'created_at' => CarbonImmutable::parse('2026-09-23 23:59', 'Asia/Riyadh'),
    ]);

    uploadViaPage($this->initiator, $this->beneficiary, 30)->assertHasNoErrors();
});

test('الحد لكل مبادر على حدة', function (): void {
    Transfer::factory()->count(2)->for(User::factory())->create();

    uploadViaPage($this->initiator, $this->beneficiary, 30)->assertHasNoErrors();
});

test('يُحتسب الحد بتاريخ الرفع لا بتاريخ الحوالة', function (): void {
    Transfer::factory()->count(2)->for($this->initiator)->create(['transferred_on' => '2026-09-01']);

    uploadViaPage($this->initiator, $this->beneficiary, 30)->assertHasErrors(['form']);
});

test('صفحة الرفع تنبّه عند بلوغ الحد ولا تعرض النموذج', function (): void {
    Transfer::factory()->count(2)->for($this->initiator)->create();

    Livewire::actingAs($this->initiator)->test(Create::class)
        ->assertSee(__('transfers.create.daily_limit_reached', ['limit' => 2]))
        ->assertDontSeeHtml('wire:submit="save"');
});

test('يعود الرفع متاحًا في اليوم التالي', function (): void {
    Transfer::factory()->count(2)->for($this->initiator)->create();

    $this->travelTo(CarbonImmutable::parse('2026-09-25 00:01', 'Asia/Riyadh'));

    Livewire::actingAs($this->initiator)->test(Create::class)
        ->set('beneficiary_id', (string) $this->beneficiary->id)
        ->set('amount', '100')
        ->set('transferred_on', '2026-09-25')
        ->set('receipt', UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()))
        ->call('save')
        ->assertHasNoErrors();
});
