<div class="flex flex-col gap-4">
        @forelse ($this->messages as $message)
            <article
                wire:key="contact-message-{{ $message->id }}"
                data-contact-message
                @class([
                    'rounded-[14px] border p-4 sm:p-5',
                    'border-line bg-surface' => $message->isRead(),
                    'border-pri bg-brass-soft' => ! $message->isRead(),
                ])
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-ink">{{ $message->name }}</p>
                        <p dir="ltr" class="text-start font-mono text-sm text-muted">{{ $message->phone }}</p>
                    </div>
                    <x-filament::badge :color="$message->isRead() ? 'gray' : 'warning'">
                        {{ $message->isRead() ? __('contact.inbox.read') : __('contact.inbox.unread') }}
                    </x-filament::badge>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-ink">{{ $message->body }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a
                        href="tel:{{ $message->phone }}"
                        dir="ltr"
                        class="inline-flex min-h-11 items-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft"
                    >
                        {{ __('contact.buttons.call') }}
                    </a>
                    <a
                        href="https://wa.me/{{ ltrim($message->phone, '+') }}"
                        rel="noopener noreferrer"
                        dir="ltr"
                        class="inline-flex min-h-11 items-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft"
                    >
                        {{ __('contact.buttons.whatsapp') }}
                    </a>

                    @unless ($message->isRead())
                        <x-filament::button size="sm" color="gray" wire:click="markRead({{ $message->id }})">
                            {{ __('contact.inbox.mark_read') }}
                        </x-filament::button>
                    @endunless

                    <span class="ms-auto text-xs text-muted">{{ $message->created_at->timezone('Asia/Riyadh')->format('Y-m-d H:i') }}</span>
                </div>
            </article>
        @empty
            <p class="text-muted">{{ __('contact.inbox.empty') }}</p>
        @endforelse

        <x-filament::pagination :paginator="$this->messages" />
    </div>
