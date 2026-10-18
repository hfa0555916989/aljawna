<div class="flex flex-col gap-6">
    <form class="grid gap-4 sm:grid-cols-2" wire:submit.prevent="save">
        <label class="block text-sm sm:col-span-2">
            {{ __('branding.name') }}
            <input type="text" wire:model="initiativeName" class="mt-1 min-h-11 w-full rounded-lg border p-2" data-branding-input="name">
            @error('initiativeName') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            {{ __('branding.primary_color') }}
            <select wire:model="primaryColorKey" class="mt-1 min-h-11 w-full rounded-lg border p-2" data-branding-input="primary-color">
                @foreach ($this->primaryOptions() as $key => $option)
                    <option value="{{ $key }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            @error('primaryColorKey') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            {{ __('branding.secondary_color') }}
            <select wire:model="secondaryColorKey" class="mt-1 min-h-11 w-full rounded-lg border p-2" data-branding-input="secondary-color">
                @foreach ($this->secondaryOptions() as $key => $option)
                    <option value="{{ $key }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            @error('secondaryColorKey') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </label>

        @php($branding = \App\Models\SiteBranding::current())
        @php($previewHeight = max(\App\Models\SiteBranding::MIN_LOGO_HEIGHT, min(\App\Models\SiteBranding::MAX_LOGO_HEIGHT, (int) $logoHeight)))

        <div class="block text-sm sm:col-span-2">
            <label for="branding-logo-height">{{ __('branding.logo_height') }}</label>
            <div class="mt-1 flex items-center gap-3">
                <input
                    id="branding-logo-height"
                    type="range"
                    min="{{ \App\Models\SiteBranding::MIN_LOGO_HEIGHT }}"
                    max="{{ \App\Models\SiteBranding::MAX_LOGO_HEIGHT }}"
                    step="2"
                    wire:model.live.debounce.250ms="logoHeight"
                    class="min-h-11 w-full"
                    data-branding-input="logo-height"
                >
                <span class="w-16 shrink-0 text-center tabular-nums" dir="ltr">{{ $previewHeight }}px</span>
            </div>
            <p class="mt-1 text-gray-500">{{ __('branding.logo_height_hint') }}</p>
            <p class="mt-2 overflow-hidden rounded bg-white p-2">
                <img src="{{ $branding->lightLogoUrl() }}" alt="" class="w-auto max-w-full object-contain" style="height: {{ $previewHeight }}px" data-branding-preview="logo-height">
            </p>
            @error('logoHeight') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </div>

        <div class="block text-sm">
            {{ __('branding.logo_light') }}
            <p class="mt-1"><img src="{{ $branding->lightLogoUrl() }}" alt="" class="h-12 rounded bg-white p-1" data-branding-preview="logo-light"></p>
            <input type="file" wire:model="logoLight" accept="image/png,image/jpeg" class="mt-2 block w-full text-sm" data-branding-input="logo-light">
            @error('logoLight') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </div>

        <div class="block text-sm">
            {{ __('branding.logo_dark') }}
            <p class="mt-1"><img src="{{ $branding->darkLogoUrl() }}" alt="" class="h-12 rounded bg-gray-900 p-1" data-branding-preview="logo-dark"></p>
            <input type="file" wire:model="logoDark" accept="image/png,image/jpeg" class="mt-2 block w-full text-sm" data-branding-input="logo-dark">
            @error('logoDark') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </div>

        <div class="block text-sm">
            {{ __('branding.icon') }}
            <p class="mt-1"><img src="{{ $branding->iconUrl() }}" alt="" class="h-12 w-12 rounded" data-branding-preview="icon"></p>
            <input type="file" wire:model="icon" accept="image/png,image/jpeg" class="mt-2 block w-full text-sm" data-branding-input="icon">
            @error('icon') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
        </div>

        <div class="sm:col-span-2">
            <button type="submit" class="min-h-11 rounded-lg bg-primary-600 px-5 text-white" data-branding-action="save">
                {{ __('branding.save') }}
            </button>
        </div>
    </form>
</div>
