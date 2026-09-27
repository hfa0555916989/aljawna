<?php

declare(strict_types=1);

use App\Livewire\ContactForm;
use App\Models\ContactMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| نموذج "اتصل بنا" (T18 — docs/SPEC.md FR-55، §9 contact_messages)
|--------------------------------------------------------------------------
| محمي بحقل فخ وTurnstile وحدّ معدّل لكل IP، مطابقةً لتسجيل المبادر (T02).
*/

/**
 * @param  array<string, string>  $overrides
 */
function contactForm(array $overrides = []): Testable
{
    $values = array_merge([
        'name' => 'زائر الموقع',
        'phone' => '0512345678',
        'body' => 'أريد الاستفسار عن كيفية المساهمة.',
    ], $overrides);

    $component = Livewire::test(ContactForm::class);

    foreach ($values as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

test('إرسال بيانات صالحة ينشئ رسالة ويعرض رسالة النجاح', function (): void {
    contactForm()
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee(__('contact.sent'));

    $message = ContactMessage::query()->sole();

    expect($message->name)->toBe('زائر الموقع')
        ->and($message->phone)->toBe('+966512345678')
        ->and($message->body)->toBe('أريد الاستفسار عن كيفية المساهمة.')
        ->and($message->isRead())->toBeFalse();
});

test('حقل الفخ المعبّأ يمنع الإرسال', function (): void {
    contactForm(['website' => 'https://spam.example'])
        ->call('send')
        ->assertHasErrors(['form']);

    expect(ContactMessage::query()->count())->toBe(0);
});

test('رقم جوال غير سعودي يُرفض', function (string $phone): void {
    contactForm(['phone' => $phone])
        ->call('send')
        ->assertHasErrors(['phone']);

    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'مصري' => ['+201001234567'],
    'أمريكي' => ['+12025551234'],
    'صيغة غير صحيحة' => ['0412345678'],
]);

test('حدّ الإرسال لكل IP في الساعة', function (): void {
    config(['security.contact.max_per_ip_per_hour' => 2]);

    foreach (range(1, 2) as $i) {
        contactForm(['body' => "رسالة رقم {$i}."])
            ->call('send')
            ->assertHasNoErrors();
    }

    contactForm(['body' => 'رسالة ثالثة.'])
        ->call('send')
        ->assertHasErrors(['form']);

    expect(ContactMessage::query()->count())->toBe(2);

    $this->travel(61)->minutes();

    contactForm(['body' => 'رسالة بعد انتهاء الحدّ.'])
        ->call('send')
        ->assertHasNoErrors();

    expect(ContactMessage::query()->count())->toBe(3);
});

test('المحاولات الفاشلة تُحتسب ضمن حدّ الإرسال', function (): void {
    config(['security.contact.max_per_ip_per_hour' => 2]);

    contactForm(['phone' => 'invalid'])->call('send')->assertHasErrors(['phone']);
    contactForm(['website' => 'https://spam.example'])->call('send')->assertHasErrors(['form']);

    contactForm()
        ->call('send')
        ->assertHasErrors(['form'])
        ->assertSee('تجاوزت عدد محاولات الإرسال');

    expect(ContactMessage::query()->count())->toBe(0);
});

describe('Turnstile مفعَّل', function (): void {
    beforeEach(function (): void {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);
    });

    test('رمز مرفوض من Cloudflare يمنع الإرسال ويعيد ضبط العنصر', function (): void {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        contactForm(['turnstileToken' => 'invalid-token'])
            ->call('send')
            ->assertHasErrors(['turnstile'])
            ->assertDispatched('turnstile-reset');

        expect(ContactMessage::query()->count())->toBe(0);
    });

    test('غياب الرمز يمنع الإرسال دون اتصال بـ Cloudflare', function (): void {
        Http::fake();

        contactForm()
            ->call('send')
            ->assertHasErrors(['turnstile']);

        Http::assertNothingSent();
        expect(ContactMessage::query()->count())->toBe(0);
    });

    test('رمز صالح يسمح بالإرسال ويُرسل السر والرمز إلى Cloudflare', function (): void {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        contactForm(['turnstileToken' => 'valid-token'])
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        Http::assertSent(fn (Request $request): bool => $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token');

        expect(ContactMessage::query()->count())->toBe(1);
    });
});
