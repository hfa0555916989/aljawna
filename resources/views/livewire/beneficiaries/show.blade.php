<div class="mx-auto max-w-3xl px-4 py-8">
    <a href="{{ route('beneficiaries.index', $beneficiary->status === \App\BeneficiaryStatus::Closed ? ['tab' => 'closed'] : []) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-pri underline-offset-4 hover:underline">
        <span aria-hidden="true" class="me-1">→</span>{{ __('site.show.back') }}
    </a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <h1 class="min-w-0 break-words text-2xl font-bold leading-10 text-ink">{{ $beneficiary->display_name }}</h1>
        <x-beneficiary.status :beneficiary="$beneficiary" class="mt-2" />
    </div>

    <section class="mt-6 rounded-[14px] border border-line bg-surface p-5">
        <x-beneficiary.progress :beneficiary="$beneficiary" />
        <p class="mt-2 text-sm text-muted">{{ __('site.beneficiaries.target', ['amount' => \App\Support\Money::format($beneficiary->target_amount)]) }}</p>
    </section>

    @if ($account)
        <section aria-labelledby="account-heading" class="mt-6 rounded-[14px] border-2 border-brass bg-surface p-5">
            <h2 id="account-heading" class="text-lg font-bold text-ink">{{ __('site.show.account_heading') }}</h2>
            <p class="mt-1 text-sm leading-6 text-muted">{{ __('site.show.account_note') }}</p>

            <dl class="mt-5 grid gap-5">
                <div>
                    <dt class="text-sm text-muted">{{ __('beneficiaries.fields.bank_name') }}</dt>
                    <dd class="mt-1 break-words text-base font-semibold text-ink">{{ $account['bank_name'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">{{ __('beneficiaries.fields.account_holder') }}</dt>
                    <dd class="mt-1 break-words text-base font-semibold text-ink">{{ $account['account_holder'] }}</dd>
                </div>
                <x-copy-field :label="__('beneficiaries.fields.account_number')" :value="$account['account_number']" />
                <x-copy-field :label="__('beneficiaries.fields.iban')" :value="$account['iban']" :display="$account['iban_grouped']" />
            </dl>

            <p class="mt-5 border-t border-line pt-4 text-sm text-ink">
                {{ __('site.show.upload_hint') }}
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center font-semibold text-pri underline-offset-4 hover:underline">{{ __('site.show.dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-semibold text-pri underline-offset-4 hover:underline">{{ __('site.show.login') }}</a>
                @endauth
            </p>
        </section>
    @else
        <p role="note" class="mt-6 rounded-[14px] border border-line bg-surface p-5 text-ink">
            {{ $beneficiary->status === \App\BeneficiaryStatus::Closed ? __('site.show.closed_notice') : __('site.show.account_unavailable') }}
        </p>
    @endif

    <section aria-labelledby="dates-heading" class="mt-6 rounded-[14px] border border-line bg-surface p-5">
        <h2 id="dates-heading" class="text-lg font-bold text-ink">{{ __('site.show.dates_heading') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <x-dual-date :label="__('beneficiaries.fields.target_deadline')" :date="$beneficiary->target_deadline" :countdown="$beneficiary->acceptsTransfers()" />
            <x-dual-date :label="__('beneficiaries.fields.recommended_deadline')" :date="$beneficiary->recommended_deadline" :countdown="$beneficiary->acceptsTransfers()" />
            <x-dual-date :label="__('beneficiaries.fields.wedding_date')" :date="$beneficiary->wedding_date" />
        </dl>
    </section>
</div>
