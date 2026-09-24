<?php

declare(strict_types=1);

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| صفحة إيصالات المبادرات في اللوحة (قرار T07 — §12.6)
|--------------------------------------------------------------------------
| كل مبادرة وتحتها إيصالاتها، لمن يملك transfers.view العامة لا لمنشئ المبادرة.
*/

const RECEIPTS_PAGE = '/admin/beneficiary-receipts';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake('receipts');
    $this->travelTo(CarbonImmutable::parse('2026-09-24 12:00', 'Asia/Riyadh'));

    $this->creator = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();
    $this->viewer = User::factory()->supervisor()->withPermissions(['transfers.view'])->create();
});

/**
 * معرّفات الحوالات المعروضة تحت كل مبادرة في HTML الصفحة كما تُرسم فعلًا.
 *
 * @return array<int, list<int>>
 */
function receiptsGroupedInPage(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);

    $groups = [];

    foreach ($xpath->query('//*[@data-beneficiary]') ?: [] as $section) {
        if (! $section instanceof DOMElement) {
            continue;
        }

        $transferIds = [];

        foreach ($xpath->query('.//*[@data-transfer]', $section) ?: [] as $item) {
            if ($item instanceof DOMElement) {
                $transferIds[] = (int) $item->getAttribute('data-transfer');
            }
        }

        sort($transferIds);
        $groups[(int) $section->getAttribute('data-beneficiary')] = $transferIds;
    }

    return $groups;
}

test('كل إيصال يظهر تحت مبادرته وحدها، والمبادرة بلا حوالات تظهر فارغة', function (): void {
    $first = Beneficiary::factory()->approved()->create(['created_by' => $this->creator->id]);
    $second = Beneficiary::factory()->approved()->create(['created_by' => $this->creator->id]);
    $empty = Beneficiary::factory()->approved()->create();

    $firstTransfers = Transfer::factory()->count(3)->for($first)->create();
    $secondTransfers = Transfer::factory()->count(2)->for($second)->create();

    $groups = receiptsGroupedInPage(
        $this->actingAs($this->viewer)->get(RECEIPTS_PAGE)->assertOk()->getContent() ?: '',
    );

    expect($groups)->toHaveCount(3)
        ->and($groups[$first->id])->toBe($firstTransfers->pluck('id')->sort()->values()->all())
        ->and($groups[$second->id])->toBe($secondTransfers->pluck('id')->sort()->values()->all())
        ->and($groups[$empty->id])->toBe([]);
});

test('رأس كل مبادرة يعرض عدد حوالاتها ومجموعها الخاص بها', function (): void {
    $first = Beneficiary::factory()->approved()->create();
    $second = Beneficiary::factory()->approved()->create();
    $empty = Beneficiary::factory()->approved()->closed()->create();
    Transfer::factory()->for($first)->create(['amount' => '1000.00']);
    Transfer::factory()->for($first)->create(['amount' => '250.50']);
    Transfer::factory()->for($second)->create(['amount' => '75.00']);

    $this->actingAs($this->viewer)->get(RECEIPTS_PAGE)
        ->assertOk()
        ->assertSeeInOrder([$first->display_name, 'حوالتان', '1,250.50'])
        ->assertSeeInOrder([$second->display_name, 'حوالة واحدة', '75'])
        ->assertSeeInOrder([$empty->display_name, 'لا حوالات', 'لم تصل حوالات إلى هذه المبادرة بعد.']);
});

test('المشرف الممنوح transfers.view يرى إيصالات كل المبادرات ولو لم ينشئ أيًّا منها', function (): void {
    $byCreator = Beneficiary::factory()->approved()->create(['created_by' => $this->creator->id]);
    $byAdmin = Beneficiary::factory()->approved()->create();
    Transfer::factory()->for($byCreator)->create();
    Transfer::factory()->for($byAdmin)->create();

    expect(Beneficiary::query()->where('created_by', $this->viewer->id)->exists())->toBeFalse();

    $groups = receiptsGroupedInPage($this->actingAs($this->viewer)->get(RECEIPTS_PAGE)->assertOk()->getContent() ?: '');

    expect(array_keys($groups))->toEqualCanonicalizing([$byCreator->id, $byAdmin->id])
        ->and($groups[$byCreator->id])->toHaveCount(1)
        ->and($groups[$byAdmin->id])->toHaveCount(1);
});

test('منشئ المبادرة بلا transfers.view يُمنع بـ 403 ولا يرى عنصر التنقل', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['created_by' => $this->creator->id]);
    Transfer::factory()->for($beneficiary)->create();

    $this->actingAs($this->creator)->get(RECEIPTS_PAGE)->assertForbidden();
    $this->actingAs($this->creator)->get('/admin')->assertOk()->assertDontSee('إيصالات المبادرات');
});

test('المدير يفتح الصفحة ويرى عنصر التنقل', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin')->assertSee('إيصالات المبادرات');
    $this->actingAs($admin)->get(RECEIPTS_PAGE)->assertOk();
    $this->actingAs($this->viewer)->get('/admin')->assertSee('إيصالات المبادرات');
});

test('سحب transfers.view يمنع الصفحة فورًا', function (): void {
    $this->actingAs($this->viewer)->get(RECEIPTS_PAGE)->assertOk();

    $this->viewer->revokePermissionTo('transfers.view');

    $this->actingAs($this->viewer->fresh())->get(RECEIPTS_PAGE)->assertForbidden();
});

test('المبادر لا يصل إلى الصفحة', function (): void {
    $this->actingAs(User::factory()->create())->get(RECEIPTS_PAGE)->assertRedirect(route('dashboard'));
});

test('رابط الإيصال موقّع وينتهي بعد 10 دقائق، ويفتحه صاحب transfers.view', function (): void {
    Storage::disk('receipts')->put('Receipt123.jpg', jpegBytes());
    $transfer = Transfer::factory()->create(['receipt_path' => 'Receipt123.jpg']);

    $html = $this->actingAs($this->viewer)->get(RECEIPTS_PAGE)->assertOk()->getContent() ?: '';

    preg_match('#href="([^"]*/receipts/'.$transfer->id.'\?[^"]+)"#', $html, $match);
    $url = html_entity_decode($match[1] ?? '');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->not->toBe('')
        ->and($query)->toHaveKeys(['expires', 'signature'])
        ->and((int) $query['expires'])->toBe(now()->addMinutes(10)->getTimestamp())
        ->and(config('security.receipts.link_minutes'))->toBe(10);

    $this->actingAs($this->viewer)->get($url)->assertOk();

    $this->travel(11)->minutes();

    $this->actingAs($this->viewer)->get($url)->assertForbidden();
});

test('تظهر للمبادر المحوِّل باسمه الأول فقط، ويُعلَّم المتكرر', function (): void {
    $initiator = User::factory()->create(['full_name' => 'مشعل عبدالله خالد العجاوني']);
    Transfer::factory()->for($initiator)->repeated()->create(['bank_reference' => 'REF-777']);

    $this->actingAs($this->viewer)->get(RECEIPTS_PAGE)
        ->assertOk()
        ->assertSee('مشعل')
        ->assertDontSee('مشعل عبدالله')
        ->assertDontSee($initiator->phone)
        ->assertSee('REF-777')
        ->assertSee('متكررة');
});

test('المبادرات الأحدث حوالةً أولًا، ثم التي بلا حوالات', function (): void {
    $empty = Beneficiary::factory()->approved()->create();
    $older = Beneficiary::factory()->approved()->create();
    $newer = Beneficiary::factory()->approved()->create();

    Transfer::factory()->for($older)->create(['created_at' => now()->subDays(2)]);
    Transfer::factory()->for($newer)->create(['created_at' => now()->subHour()]);

    $groups = receiptsGroupedInPage($this->actingAs($this->viewer)->get(RECEIPTS_PAGE)->getContent() ?: '');

    expect(array_keys($groups))->toBe([$newer->id, $older->id, $empty->id]);
});

test('الصفحة مقسّمة إلى 10 مبادرات في كل صفحة', function (): void {
    Beneficiary::factory()->approved()->count(11)->create();

    $firstPage = receiptsGroupedInPage($this->actingAs($this->viewer)->get(RECEIPTS_PAGE)->getContent() ?: '');
    $secondPage = receiptsGroupedInPage($this->actingAs($this->viewer)->get(RECEIPTS_PAGE.'?page=2')->getContent() ?: '');

    expect($firstPage)->toHaveCount(10)
        ->and($secondPage)->toHaveCount(1)
        ->and(array_intersect(array_keys($firstPage), array_keys($secondPage)))->toBe([]);
});
