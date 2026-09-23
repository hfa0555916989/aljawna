<?php

declare(strict_types=1);

use App\Services\LoginThrottle;

/*
|--------------------------------------------------------------------------
| القفل المؤقت لكل جوال ولكل IP مع تزايد المدة (T02 — docs/SPEC.md §12.2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->freezeTime();
    $this->throttle = app(LoginThrottle::class);
});

test('5 محاولات فاشلة لجوال واحد من عناوين مختلفة تقفل الجوال من أي عنوان', function (): void {
    foreach (range(1, 5) as $index) {
        $this->throttle->recordFailure('+966512345678', "10.0.0.{$index}");
    }

    expect($this->throttle->lockedSeconds('+966512345678', '10.9.9.9'))->toBeGreaterThan(0)
        ->and($this->throttle->lockedSeconds('+966598765432', '10.9.9.9'))->toBe(0);
});

test('5 محاولات فاشلة من عنوان واحد لجوالات مختلفة تقفل العنوان لأي جوال', function (): void {
    foreach (range(1, 5) as $index) {
        $this->throttle->recordFailure("+96651234567{$index}", '10.0.0.1');
    }

    expect($this->throttle->lockedSeconds('+966598765432', '10.0.0.1'))->toBeGreaterThan(0)
        ->and($this->throttle->lockedSeconds('+966598765432', '10.0.0.2'))->toBe(0);
});

test('مدة القفل الأولى 15 دقيقة وتتضاعف مع تكرار القفل', function (): void {
    $lockOnce = function (): void {
        foreach (range(1, 5) as $ignored) {
            $this->throttle->recordFailure('+966512345678', '10.0.0.1');
        }
    };

    $lockOnce();
    expect($this->throttle->lockedSeconds('+966512345678', '10.0.0.1'))->toBe(15 * 60);

    $this->travel(16)->minutes();
    expect($this->throttle->lockedSeconds('+966512345678', '10.0.0.1'))->toBe(0);

    $lockOnce();
    expect($this->throttle->lockedSeconds('+966512345678', '10.0.0.1'))->toBe(30 * 60);
});

test('مدة القفل لا تتجاوز الحد الأقصى في الإعدادات', function (): void {
    config(['security.login.lockout_max_minutes' => 20]);

    foreach (range(1, 3) as $ignored) {
        foreach (range(1, 5) as $index) {
            $this->throttle->recordFailure('+966512345678', "10.0.1.{$index}");
        }

        $this->travel(61)->minutes();
    }

    foreach (range(1, 5) as $index) {
        $this->throttle->recordFailure('+966512345678', "10.0.2.{$index}");
    }

    expect($this->throttle->lockedSeconds('+966512345678', '10.9.9.9'))->toBe(20 * 60);
});

test('الدخول الناجح يصفّر عدّاد الجوال', function (): void {
    foreach (range(1, 4) as $index) {
        $this->throttle->recordFailure('+966512345678', "10.0.0.{$index}");
    }

    $this->throttle->clearPhone('+966512345678');
    $this->throttle->recordFailure('+966512345678', '10.0.0.9');

    expect($this->throttle->lockedSeconds('+966512345678', '10.9.9.9'))->toBe(0);
});
