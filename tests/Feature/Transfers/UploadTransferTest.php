<?php

declare(strict_types=1);

use App\Actions\Transfers\CreateTransfer;
use App\BeneficiaryStatus;
use App\Livewire\Beneficiaries\Show;
use App\Livewire\Transfers\Create;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\Rules\ReceiptFile;
use App\TransferReviewState;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| رفع الحوالة والإيصال (T07 — FR-13, FR-14, §12.6)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('receipts');
    $this->travelTo(CarbonImmutable::parse('2026-09-24 12:00', 'Asia/Riyadh'));
    $this->initiator = User::factory()->create();
    $this->beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '45000.00']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function submitTransfer(User $user, Beneficiary $beneficiary, UploadedFile $receipt, array $overrides = []): Testable
{
    $component = Livewire::actingAs($user)->test(Create::class)
        ->set('beneficiary_id', (string) $beneficiary->id)
        ->set('amount', '4500')
        ->set('transferred_on', '2026-09-23')
        ->set('bank_reference', '');

    foreach ($overrides as $property => $value) {
        $component->set($property, $value);
    }

    return $component->set('receipt', $receipt)->call('save');
}

test('رفع ناجح: تُنشأ الحوالة محتسبة فورًا والإيصال على القرص الخاص باسم عشوائي', function (): void {
    $original = jpegBytes();

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('إيصال التحويل.jpg', $original))
        ->assertHasNoErrors()
        ->assertRedirect(route('transfers.index'));

    $transfer = Transfer::query()->sole();

    expect($transfer->user_id)->toBe($this->initiator->id)
        ->and($transfer->beneficiary_id)->toBe($this->beneficiary->id)
        ->and($transfer->amount)->toBe('4500.00')
        ->and($transfer->transferred_on->toDateString())->toBe('2026-09-23')
        ->and($transfer->bank_reference)->toBeNull()
        ->and($transfer->is_repeated)->toBeFalse()
        ->and($transfer->review_state)->toBe(TransferReviewState::NotReviewed)
        ->and($transfer->receipt_hash)->toBe(hash('sha256', $original))
        ->and($transfer->receipt_path)->toMatch('/^[A-Za-z0-9]{40}\.jpg$/');

    Storage::disk('receipts')->assertExists($transfer->receipt_path);
    expect(Storage::disk('receipts')->allFiles())->toHaveCount(1);
});

test('القرص الافتراضي للإيصالات خاص ولا يُخدم مباشرة', function (): void {
    expect(config('security.receipts.disk'))->toBe('receipts')
        ->and(config('filesystems.disks.receipts.visibility'))->toBe('private')
        ->and(config('filesystems.disks.receipts.serve'))->toBeFalse()
        ->and(config('filesystems.disks.receipts.root'))->toBe(storage_path('app/private/receipts'))
        ->and(config('filesystems.disks.receipts.url'))->toBeNull();
});

test('الحوالة تظهر في نسبة التقدّم مباشرة بعد الرفع بلا أي مراجعة', function (): void {
    Livewire::test(Show::class, ['beneficiary' => $this->beneficiary])->assertSeeHtml('value="0"');

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()))
        ->assertHasNoErrors();

    Livewire::test(Show::class, ['beneficiary' => $this->beneficiary])
        ->assertSeeHtml('value="10"')
        ->assertSee('10%');

    $this->get(route('beneficiaries.index'))->assertSee('10%');
});

test('يُقبل PNG وPDF ويُحفظ كلٌّ بامتداد نوعه الحقيقي', function (string $name, string $contents, string $extension): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent($name, $contents))
        ->assertHasNoErrors();

    expect(Transfer::query()->sole()->receipt_path)->toEndWith('.'.$extension);
})->with([
    'PNG' => fn (): array => ['receipt.png', pngBytes(), 'png'],
    'PDF' => fn (): array => ['receipt.pdf', pdfBytes(), 'pdf'],
    'JPEG بامتداد jpeg' => fn (): array => ['receipt.JPEG', jpegBytes(), 'jpg'],
]);

test('ملف PDF يُحفظ كما رُفع دون تعديل', function (): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.pdf', pdfBytes('abc')))
        ->assertHasNoErrors();

    expect(Storage::disk('receipts')->get(Transfer::query()->sole()->receipt_path))->toBe(pdfBytes('abc'));
});

test('ملف بامتداد مزيَّف يُرفض ولا يُحفظ شيء', function (string $name, string $contents): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent($name, $contents))
        ->assertHasErrors(['receipt'])
        ->assertSee(__('transfers.validation.receipt_type'));

    expect(Transfer::query()->count())->toBe(0)
        ->and(Storage::disk('receipts')->allFiles())->toBe([]);
})->with([
    'سكربت PHP باسم jpg' => fn (): array => ['receipt.jpg', '<?php echo "hacked"; ?>'],
    'صفحة HTML باسم pdf' => fn (): array => ['receipt.pdf', '<html><script>alert(1)</script></html>'],
    'نص عادي باسم png' => fn (): array => ['receipt.png', 'just some text'],
    'PDF باسم png' => fn (): array => ['receipt.png', pdfBytes()],
    'PNG باسم jpg' => fn (): array => ['receipt.jpg', pngBytes()],
    'JPEG باسم exe' => fn (): array => ['receipt.exe', jpegBytes()],
    'JPEG باسم مزدوج الامتداد' => fn (): array => ['receipt.jpg.php', jpegBytes()],
    'بداية JPEG ثم محتوى تالف' => fn (): array => ['receipt.jpg', "\xFF\xD8\xFF\xE0".str_repeat('x', 200)],
]);

test('قاعدة الإيصال ترفض الملف بلا امتداد ولو كان محتواه صورة صحيحة', function (): void {
    $validator = Validator::make(
        ['receipt' => UploadedFile::fake()->createWithContent('receipt', jpegBytes())],
        ['receipt' => [new ReceiptFile]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('receipt'))->toBe(__('transfers.validation.receipt_type'));
});

test('صورة GIF حقيقية ليست من الأنواع المسموحة', function (string $name): void {
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent($name, $gif))
        ->assertHasErrors(['receipt']);

    expect(Transfer::query()->count())->toBe(0);
})->with(['receipt.gif', 'receipt.png']);

test('الإيصال الذي يتجاوز 5 ميجابايت يُرفض، و5 ميجابايت تمامًا مقبولة', function (): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->image('big.jpg', 20, 20)->size(5121))
        ->assertHasErrors(['receipt' => 'max'])
        ->assertSee(__('transfers.validation.receipt_size'));

    expect(Transfer::query()->count())->toBe(0);

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->image('ok.jpg', 20, 20)->size(5120))
        ->assertHasNoErrors();

    expect(Transfer::query()->count())->toBe(1);
});

test('صورة تتجاوز حد الأبعاد تُرفض قبل فك ترميزها', function (): void {
    config(['security.receipts.max_pixels' => 1000]);

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('wide.jpg', jpegBytes(50, 30)))
        ->assertHasErrors(['receipt'])
        ->assertSee(__('transfers.validation.receipt_dimensions'));

    expect(Transfer::query()->count())->toBe(0);
});

test('الإيصال مطلوب', function (): void {
    Livewire::actingAs($this->initiator)->test(Create::class)
        ->set('beneficiary_id', (string) $this->beneficiary->id)
        ->set('amount', '100')
        ->call('save')
        ->assertHasErrors(['receipt' => 'required']);
});

test('تاريخ الحوالة المستقبلي يُرفض، وتاريخ اليوم بتوقيت الرياض مقبول', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 23:30', 'Asia/Riyadh'));

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['transferred_on' => '2026-09-25'])
        ->assertHasErrors(['transferred_on' => 'before_or_equal'])
        ->assertSee(__('transfers.validation.future_date'));

    expect(Transfer::query()->count())->toBe(0);

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['transferred_on' => '2026-09-24'])
        ->assertHasNoErrors();

    expect(Transfer::query()->count())->toBe(1);
});

test('الإجراء نفسه يرفض التاريخ المستقبلي ولو تجاوز المستدعي تحقق الواجهة', function (): void {
    expect(fn () => app(CreateTransfer::class)->handle(
        $this->initiator, $this->beneficiary->id, '100', '2026-09-25', null,
        UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()),
    ))->toThrow(ValidationException::class);

    expect(Transfer::query()->count())->toBe(0)
        ->and(Storage::disk('receipts')->allFiles())->toBe([]);
});

test('المبلغ يُقبل بالأرقام العربية وفواصل الآلاف ويُخزَّن عشريًا بدقة', function (string $input, string $stored): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['amount' => $input])
        ->assertHasNoErrors();

    expect(Transfer::query()->sole()->amount)->toBe($stored);
})->with([
    ['١٬٥٠٠٫٥٠', '1500.50'],
    ['2,000', '2000.00'],
    ['0.01', '0.01'],
    ['9999999999.99', '9999999999.99'],
]);

test('المبلغ غير الصالح يُرفض', function (string $input): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['amount' => $input])
        ->assertHasErrors(['amount']);

    expect(Transfer::query()->count())->toBe(0);
})->with(['0', '0.00', '-100', '100.555', 'abc', '1e5', '10000000000']);

test('رقم العملية يُطبَّع ويُرفض بالرموز غير المسموحة', function (): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['bank_reference' => '<script>'])
        ->assertHasErrors(['bank_reference']);

    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['bank_reference' => ' ft 12-٣٤ '])
        ->assertHasNoErrors();

    expect(Transfer::query()->sole()->bank_reference)->toBe('FT12-34');
});

test('لا حوالات لمبادرة مغلقة أو غير معتمدة أو غير موجودة', function (int $beneficiaryId): void {
    submitTransfer($this->initiator, $this->beneficiary, UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()), ['beneficiary_id' => (string) $beneficiaryId])
        ->assertHasErrors(['beneficiary_id'])
        ->assertSee(__('transfers.validation.beneficiary'));

    expect(Transfer::query()->count())->toBe(0)
        ->and(Storage::disk('receipts')->allFiles())->toBe([]);
})->with([
    'مغلقة معتمدة' => fn (): int => Beneficiary::factory()->approved()->closed()->create()->id,
    'غير معتمدة' => fn (): int => Beneficiary::factory()->create()->id,
    'مغلقة غير معتمدة' => fn (): int => Beneficiary::factory()->closed()->create()->id,
    'أُلغي اعتمادها' => fn (): int => Beneficiary::factory()->approvalRevoked()->create()->id,
    'غير موجودة' => fn (): int => 999999,
]);

test('إغلاق المبادرة بعد فتح الصفحة يمنع الحوالة عند الإرسال', function (): void {
    $component = Livewire::actingAs($this->initiator)->test(Create::class)
        ->set('beneficiary_id', (string) $this->beneficiary->id)
        ->set('amount', '100')
        ->set('receipt', UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()));

    $this->beneficiary->forceFill(['status' => BeneficiaryStatus::Closed])->save();

    $component->call('save')->assertHasErrors(['beneficiary_id']);

    expect(Transfer::query()->count())->toBe(0);
});

test('الإجراء يرفض المبادرة غير المتاحة ولو تجاوز المستدعي تحقق الواجهة، ولا يبقى ملف', function (): void {
    $closed = Beneficiary::factory()->approved()->closed()->create();

    expect(fn () => app(CreateTransfer::class)->handle(
        $this->initiator, $closed->id, '100', '2026-09-20', null,
        UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()),
    ))->toThrow(ValidationException::class);

    expect(Storage::disk('receipts')->allFiles())->toBe([]);
});

test('صفحة الرفع تعرض المبادرات المتاحة فقط بأسمائها كاملة دون بيانات بنكية', function (): void {
    $this->beneficiary->forceFill(['account_holder' => 'صاحب حساب وهمي أول للاختبار'])->save();
    $closed = Beneficiary::factory()->approved()->closed()->create(['display_name' => 'مستفيد مغلق وهمي للاختبار']);
    $pending = Beneficiary::factory()->create(['display_name' => 'مستفيد بانتظار الاعتماد وهمي']);
    $longName = str_repeat('عبدالرحمن ', 6).'العجاوني';
    $available = Beneficiary::factory()->approved()->create([
        'display_name' => $longName,
        'account_holder' => 'صاحب حساب وهمي ثان للاختبار',
    ]);

    $component = Livewire::actingAs($this->initiator)->test(Create::class)
        ->assertSee($this->beneficiary->display_name)
        ->assertSee($longName)
        ->assertDontSee($closed->display_name)
        ->assertDontSee($pending->display_name)
        ->assertDontSeeHtml('truncate');

    assertNoBankData($component, $this->beneficiary);
    assertNoBankData($component, $available);
});

test('يُختار المستفيد مسبقًا من الرابط إن كان متاحًا فقط', function (): void {
    $closed = Beneficiary::factory()->approved()->closed()->create();

    Livewire::withQueryParams(['beneficiary' => $this->beneficiary->id])
        ->actingAs($this->initiator)->test(Create::class)
        ->assertSet('beneficiary_id', (string) $this->beneficiary->id);

    Livewire::withQueryParams(['beneficiary' => $closed->id])
        ->actingAs($this->initiator)->test(Create::class)
        ->assertSet('beneficiary_id', '');
});

test('الزائر يُحوَّل إلى الدخول', function (): void {
    $this->get(route('transfers.create'))->assertRedirect(route('login'));
    $this->get(route('transfers.index'))->assertRedirect(route('login'));
});

test('المبادر يفتح صفحة الرفع', function (): void {
    $this->actingAs($this->initiator)->get(route('transfers.create'))
        ->assertOk()
        ->assertSee(__('transfers.create.title'));
});

test('المشرف والحساب المعطَّل يُمنعان من صفحة الرفع بـ 403', function (User $user): void {
    $this->actingAs($user)->get(route('transfers.create'))->assertForbidden();
})->with([
    'مشرف' => fn (): User => User::factory()->supervisor()->create(),
    'مبادر معطَّل' => fn (): User => User::factory()->inactive()->create(),
]);

test('الإجراء يرفض المشرف ولو استُدعي مباشرة', function (): void {
    expect(fn () => app(CreateTransfer::class)->handle(
        User::factory()->supervisor()->create(), $this->beneficiary->id, '100', '2026-09-20', null,
        UploadedFile::fake()->createWithContent('r.jpg', jpegBytes()),
    ))->toThrow(AuthorizationException::class);

    expect(Transfer::query()->count())->toBe(0);
});

test('صفحة المستفيد المتاح تربط المبادر بصفحة الرفع مع اختيار المستفيد', function (): void {
    $this->actingAs($this->initiator)
        ->get(route('beneficiaries.show', $this->beneficiary))
        ->assertSee(route('transfers.create', ['beneficiary' => $this->beneficiary->id]), false);

    $this->actingAs(User::factory()->supervisor()->create())
        ->get(route('beneficiaries.show', $this->beneficiary))
        ->assertDontSee(route('transfers.create', ['beneficiary' => $this->beneficiary->id]), false);
});
