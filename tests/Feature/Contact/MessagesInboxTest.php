<?php

declare(strict_types=1);

use App\Filament\Pages\Messages;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| صندوق رسائل "اتصل بنا" (T18 — docs/SPEC.md §9 contact_messages، FR-55)
|--------------------------------------------------------------------------
| القراءة والوسم "مقروءة" بصلاحية messages.view فقط.
*/

const MESSAGES_PAGE = 'messages';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
});

function messagesOfficer(): User
{
    return User::factory()->supervisor()->withPermissions(['messages.view'])->create();
}

test('من لا يملك messages.view يُرفض بـ 403', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();

    $this->actingAs($supervisor)->get(adminPath(MESSAGES_PAGE))->assertForbidden();

    expect(Messages::canAccess())->toBeFalse();
});

test('المشرف المعطَّل يحصل على 404 ولو ملك messages.view', function (): void {
    $supervisor = User::factory()->supervisor()->inactive()->withPermissions(['messages.view'])->create();

    $this->actingAs($supervisor)->get(adminPath(MESSAGES_PAGE))->assertNotFound();
});

test('المشرف الممنوح messages.view يفتح الصندوق', function (): void {
    $this->actingAs(messagesOfficer())->get(adminPath(MESSAGES_PAGE))->assertOk()->assertSee(__('contact.inbox.navigation'));
});

test('المدير يفتح الصندوق ضمنيًا', function (): void {
    $this->actingAs(User::factory()->admin()->create())->get(adminPath(MESSAGES_PAGE))->assertOk();
});

test('المبادر لا يصل إلى الصندوق (404)', function (): void {
    $this->actingAs(User::factory()->create())->get(adminPath(MESSAGES_PAGE))->assertNotFound();
});

test('زائر غير مسجّل يُحوَّل إلى الدخول', function (): void {
    $this->get(adminPath(MESSAGES_PAGE))->assertRedirect(route('login'));
});

test('من يملك messages.view يرى الرسائل بترتيب الأحدث أولًا', function (): void {
    ContactMessage::factory()->create(['name' => 'أحمد', 'phone' => '+966512345678', 'body' => 'رسالة أولى']);
    ContactMessage::factory()->read()->create(['name' => 'سالم', 'phone' => '+966512345679', 'body' => 'رسالة ثانية']);

    $this->actingAs(messagesOfficer())
        ->get(adminPath(MESSAGES_PAGE))
        ->assertOk()
        ->assertSeeText('أحمد')
        ->assertSeeText('سالم')
        ->assertSee('tel:+966512345678', false)
        ->assertSee('https://wa.me/966512345678', false)
        ->assertSeeInOrder(['سالم', 'أحمد']);
});

test('لا رسائل بعد يظهر نص فارغ', function (): void {
    $this->actingAs(messagesOfficer())
        ->get(adminPath(MESSAGES_PAGE))
        ->assertOk()
        ->assertSeeText(__('contact.inbox.empty'));
});

test('وسم رسالة كمقروءة يخفي زر الوسم ويسجّل وقت القراءة', function (): void {
    $message = ContactMessage::factory()->create();

    Livewire::actingAs(messagesOfficer())
        ->test(Messages::class)
        ->assertSee(__('contact.inbox.mark_read'))
        ->call('markRead', $message->id)
        ->assertHasNoErrors()
        ->assertNotified(__('contact.inbox.marked_read'));

    expect($message->refresh()->isRead())->toBeTrue();
});
