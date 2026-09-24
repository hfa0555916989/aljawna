<?php

declare(strict_types=1);

use App\Models\Transfer;
use App\Models\User;
use App\Services\ReceiptStorage;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| عرض الإيصال لصاحبه ولمن يملك transfers.view فقط (T07 — §12.6)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake('receipts');
    Storage::disk('receipts')->put('Abc123receipt.jpg', jpegBytes());

    $this->owner = User::factory()->create();
    $this->transfer = Transfer::factory()->for($this->owner)->create(['receipt_path' => 'Abc123receipt.jpg']);
    $this->url = app(ReceiptStorage::class)->temporaryUrl($this->transfer);
});

test('صاحب الحوالة يرى إيصاله برابط موقّع مع رؤوس الحماية', function (): void {
    $response = $this->actingAs($this->owner)->get($this->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('receipt-'.$this->transfer->id.'.jpg')
        ->and($response->streamedContent())->toBe(Storage::disk('receipts')->get('Abc123receipt.jpg'));
});

test('مبادر آخر يُمنع من إيصال غيره بـ 403 ولو كان الرابط موقّعًا صحيحًا', function (): void {
    $this->actingAs(User::factory()->create())->get($this->url)->assertForbidden();
});

test('المشرف بلا transfers.view يُمنع بـ 403', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['stats.view', 'transfers.review'])->create())
        ->get($this->url)
        ->assertForbidden();
});

test('المشرف الممنوح transfers.view والمدير يريان الإيصال', function (User $user): void {
    $this->actingAs($user)->get($this->url)->assertOk();
})->with([
    'مشرف transfers.view' => fn (): User => User::factory()->supervisor()->withPermissions(['transfers.view'])->create(),
    'مدير' => fn (): User => User::factory()->admin()->create(),
]);

test('صاحب الحوالة المعطَّل يُمنع بـ 403', function (): void {
    $this->owner->forceFill(['is_active' => false])->save();

    $this->actingAs($this->owner)->get($this->url)->assertForbidden();
});

test('الرابط بلا توقيع أو بتوقيع معدَّل يُرفض بـ 403 حتى لصاحبه', function (): void {
    $this->actingAs($this->owner)
        ->get(route('transfers.receipt', $this->transfer))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->get($this->url.'0')
        ->assertForbidden();

    $other = Transfer::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->get(str_replace('/receipts/'.$this->transfer->id.'?', '/receipts/'.$other->id.'?', $this->url))
        ->assertForbidden();
});

test('الرابط ينتهي بعد مدته', function (): void {
    $this->travel((int) config('security.receipts.link_minutes') + 1)->minutes();

    $this->actingAs($this->owner)->get($this->url)->assertForbidden();
});

test('الزائر يُحوَّل إلى الدخول ولو كان الرابط موقّعًا', function (): void {
    $this->get($this->url)->assertRedirect(route('login'));
});

test('إيصال PDF يُقدَّم تنزيلًا لا عرضًا داخل الصفحة', function (): void {
    Storage::disk('receipts')->put('Pdf123receipt.pdf', pdfBytes());
    $transfer = Transfer::factory()->for($this->owner)->create(['receipt_path' => 'Pdf123receipt.pdf']);

    $response = $this->actingAs($this->owner)->get(app(ReceiptStorage::class)->temporaryUrl($transfer));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('الإيصال المفقود من القرص يعطي 404 لصاحبه', function (): void {
    Storage::disk('receipts')->delete('Abc123receipt.jpg');

    $this->actingAs($this->owner)->get($this->url)->assertNotFound();
});
