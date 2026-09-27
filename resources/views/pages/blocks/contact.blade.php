{{-- أزرار اتصال وواتساب من رقم الجهة المطبَّع في الكتلة، ونموذج اختياري يُرسل إلى صندوق الرسائل (docs/SPEC.md FR-55). --}}
<div class="mx-auto max-w-6xl px-4 pt-8">
    <section @if (filled($data['heading'] ?? null)) aria-labelledby="{{ $blockId }}-heading" @endif class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
        @if (filled($data['heading'] ?? null))
            <h2 id="{{ $blockId }}-heading" class="text-xl font-bold text-ink">{{ $data['heading'] }}</h2>
        @endif

        @if ($system['tel_url'] !== null || $system['whatsapp_url'] !== null)
            <div @class(['flex flex-wrap gap-3', 'mt-4' => filled($data['heading'] ?? null)])>
                @if ($system['tel_url'] !== null)
                    <a
                        href="{{ $system['tel_url'] }}"
                        dir="ltr"
                        class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-pri px-5 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
                    >
                        {{ __('contact.buttons.call') }}
                    </a>
                @endif
                @if ($system['whatsapp_url'] !== null)
                    <a
                        href="{{ $system['whatsapp_url'] }}"
                        rel="noopener noreferrer"
                        dir="ltr"
                        class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-pri px-5 text-base font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
                    >
                        {{ __('contact.buttons.whatsapp') }}
                    </a>
                @endif
            </div>
        @endif

        @if ($data['show_form'] ?? false)
            <livewire:contact-form :key="$blockId.'-form'" />
        @endif
    </section>
</div>
