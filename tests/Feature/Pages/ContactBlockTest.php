<?php

declare(strict_types=1);

use App\Actions\Content\CreatePage;
use App\Actions\Content\PublishPage;
use App\Models\Page;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| كتلة "اتصل بنا" في منشئ الصفحات (T18 — docs/SPEC.md FR-55)
|--------------------------------------------------------------------------
| رقم الجهة يُطبَّع ويُتحقق منه، والنموذج الاختياري يُضمَّن فقط عند تفعيله.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

function publishedContactPage(array $data, string $slug = 'about-us'): Page
{
    $actor = contentManager();
    $block = ['type' => PageBlocks::CONTACT, 'data' => $data];
    $page = app(CreatePage::class)->handle($actor, pageInput(['slug' => $slug, 'blocks' => [$block]]));
    app(PublishPage::class)->handle($actor, $page);

    return $page->refresh();
}

test('رقم الجهة يُطبَّع ويُبنى منه رابطا الاتصال وواتساب', function (): void {
    $page = publishedContactPage(['heading' => 'تواصل معنا', 'phone' => '+966 51 234 5678', 'show_form' => false]);

    expect($page->blocks[0]['data']['phone'])->toBe('+966512345678');

    $this->get(route('pages.show', $page->slug))
        ->assertOk()
        ->assertSee('tel:+966512345678', false)
        ->assertSee('https://wa.me/966512345678', false)
        ->assertSee(__('contact.buttons.call'))
        ->assertSee(__('contact.buttons.whatsapp'));
});

test('رقم جوال غير سعودي في كتلة اتصل بنا يُرفض برسالة عربية', function (): void {
    expect(fn () => app(CreatePage::class)->handle(contentManager(), pageInput([
        'blocks' => [['type' => PageBlocks::CONTACT, 'data' => ['phone' => '+201001234567']]],
    ])))->toThrow(ValidationException::class, __('pages.validation.phone'));

    expect(Page::query()->exists())->toBeFalse();
});

test('رقم الجهة مطلوب في كتلة اتصل بنا', function (): void {
    expect(fn () => app(CreatePage::class)->handle(contentManager(), pageInput([
        'blocks' => [['type' => PageBlocks::CONTACT, 'data' => []]],
    ])))->toThrow(ValidationException::class);
});

test('النموذج لا يظهر إلا عند تفعيله في الكتلة', function (): void {
    $withoutForm = publishedContactPage(['phone' => '0512345678', 'show_form' => false], 'no-form');

    $this->get(route('pages.show', $withoutForm->slug))
        ->assertOk()
        ->assertDontSee(__('contact.fields.submit'));

    $withForm = publishedContactPage(['phone' => '0512345679', 'show_form' => true], 'with-form');

    $this->get(route('pages.show', $withForm->slug))
        ->assertOk()
        ->assertSee(__('contact.fields.submit'));
});
