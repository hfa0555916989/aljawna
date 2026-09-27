<div class="flex flex-col gap-6">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('security.navigation') }}">
        @foreach (\App\Filament\Pages\Security::TABS as $tabKey)
            <button
                type="button"
                wire:click="selectTab('{{ $tabKey }}')"
                aria-pressed="{{ $tab === $tabKey ? 'true' : 'false' }}"
                class="min-h-11 rounded-lg px-3 {{ $tab === $tabKey ? 'bg-primary-600 text-white' : 'border' }}"
            >
                {{ __('security.tabs.'.$tabKey) }}
            </button>
        @endforeach
    </nav>

    @if ($tab === 'suspicious')
        <section class="flex flex-col gap-3" data-security-section="suspicious">
            @forelse ($this->suspicious as $row)
                @php($account = $row['user'])
                <article wire:key="suspicious-{{ $account->id }}" data-suspicious-account="{{ $account->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="font-semibold">{{ $account->full_name }}</p>
                    <p dir="ltr" class="text-start font-mono">{{ $account->phone }}</p>
                    <p class="mt-1 text-sm" data-account-status>{{ $account->is_active ? __('security.status.active') : __('security.status.suspended') }}</p>
                    <ul class="mt-2 list-inside list-disc text-sm">
                        @foreach ($row['flags'] as $flag)
                            <li data-suspicious-rule="{{ $flag['rule'] }}">
                                {{ __('security.suspicious.rules.'.$flag['rule'], [
                                    'count' => $flag['count'],
                                    'hours' => (int) config('security.suspicious.'.$flag['rule'].'.hours'),
                                ]) }}
                            </li>
                        @endforeach
                    </ul>
                    @include('filament.pages.security.account-toggle', ['account' => $account])
                </article>
            @empty
                <p>{{ __('security.suspicious.empty') }}</p>
            @endforelse

            <h2 class="mt-4 font-semibold">{{ __('security.suspended.heading') }}</h2>
            @forelse ($this->suspended as $account)
                <article wire:key="suspended-{{ $account->id }}" data-suspended-account="{{ $account->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="font-semibold">{{ $account->full_name }}</p>
                    <p dir="ltr" class="text-start font-mono">{{ $account->phone }}</p>
                    @include('filament.pages.security.account-toggle', ['account' => $account])
                </article>
            @empty
                <p>{{ __('security.suspended.empty') }}</p>
            @endforelse
            <x-filament::pagination :paginator="$this->suspended" />
        </section>
    @elseif ($tab === 'attempts')
        <section class="flex flex-col gap-3" data-security-section="attempts">
            @forelse ($this->attempts as $attempt)
                @php($when = \App\Support\HijriDate::dual($attempt->created_at))
                <article wire:key="attempt-{{ $attempt->id }}" data-login-attempt="{{ $attempt->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="font-semibold">{{ $attempt->succeeded ? __('security.attempts.succeeded') : __('security.attempts.failed') }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('security.attempts.phone') }}</p>
                    <p dir="ltr" class="text-start font-mono">{{ $attempt->phone }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('security.attempts.ip') }}</p>
                    <p dir="ltr" class="text-start font-mono">{{ $attempt->ip }}</p>
                    @if ($when)
                        <p class="mt-2 text-sm">{{ $when['hijri'] }} — {{ $when['gregorian'] }} <span dir="ltr">{{ $attempt->created_at?->format('H:i') }}</span></p>
                    @endif
                </article>
            @empty
                <p>{{ __('security.attempts.empty') }}</p>
            @endforelse
            <x-filament::pagination :paginator="$this->attempts" />
        </section>
    @else
        <section class="flex flex-col gap-3" data-security-section="audit">
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" wire:submit.prevent>
                <label class="block text-sm">
                    {{ __('security.audit.action') }}
                    <select wire:model.live="auditAction" class="mt-1 min-h-11 w-full rounded-lg border p-2">
                        <option value="">{{ __('security.audit.all') }}</option>
                        @foreach ($this->auditActions as $actionKey)
                            <option value="{{ $actionKey }}">{{ \Illuminate\Support\Facades\Lang::has('security.audit_actions.'.$actionKey) ? __('security.audit_actions.'.$actionKey) : $actionKey }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    {{ __('security.audit.actor') }}
                    <select wire:model.live="auditActor" class="mt-1 min-h-11 w-full rounded-lg border p-2">
                        <option value="">{{ __('security.audit.all') }}</option>
                        <option value="{{ \App\Filament\Pages\Security::ACTOR_SYSTEM }}">{{ __('security.audit.system') }}</option>
                        @foreach ($this->auditActors as $actorOption)
                            <option value="{{ $actorOption->id }}">{{ $actorOption->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    {{ __('security.audit.from') }}
                    <input type="date" wire:model.live="auditFrom" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
                </label>
                <label class="block text-sm">
                    {{ __('security.audit.to') }}
                    <input type="date" wire:model.live="auditTo" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
                </label>
            </form>

            @forelse ($this->auditEntries as $entry)
                @php($when = \App\Support\HijriDate::dual($entry->created_at))
                <article wire:key="audit-{{ $entry->id }}" data-audit-entry="{{ $entry->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="font-semibold">{{ \Illuminate\Support\Facades\Lang::has('security.audit_actions.'.$entry->action) ? __('security.audit_actions.'.$entry->action) : $entry->action }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('security.audit.actor') }}</p>
                    <p>{{ $entry->actor?->full_name ?? __('security.audit.system') }}</p>
                    @if ($entry->subject_type)
                        <p class="mt-1 text-sm text-gray-500">{{ __('security.audit.subject') }}</p>
                        <p dir="ltr" class="text-start font-mono">{{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}</p>
                    @endif
                    @if (! empty($entry->meta))
                        <dl class="mt-2 text-sm">
                            @foreach ($entry->meta as $metaKey => $metaValue)
                                <div class="flex flex-wrap gap-1">
                                    <dt dir="ltr" class="text-gray-500">{{ $metaKey }}:</dt>
                                    <dd dir="auto">{{ is_string($metaValue) || is_int($metaValue) || is_float($metaValue) ? $metaValue : json_encode($metaValue, JSON_UNESCAPED_UNICODE) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    @if ($entry->ip)
                        <p dir="ltr" class="mt-2 text-start font-mono text-sm">{{ $entry->ip }}</p>
                    @endif
                    @if ($when)
                        <p class="mt-2 text-sm">{{ $when['hijri'] }} — {{ $when['gregorian'] }} <span dir="ltr">{{ $entry->created_at?->format('H:i') }}</span></p>
                    @endif
                </article>
            @empty
                <p>{{ __('security.audit.empty') }}</p>
            @endforelse
            <x-filament::pagination :paginator="$this->auditEntries" />
        </section>
    @endif
</div>
