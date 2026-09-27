<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Content\RestoreMenuRevision;
use App\Actions\Content\SaveMenus;
use App\Actions\Content\ValidateMenuItems;
use App\Filament\Resources\Pages\PageResource;
use App\MenuLocation;
use App\Models\MenuItem;
use App\Models\MenuRevision;
use App\Models\Page;
use App\Models\User;
use App\PermissionKey;
use App\Rules\NoBankDetailsInContent;
use App\Rules\SafeContentLink;
use App\Services\BaseDesign;
use App\Support\SafeLink;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * قائمتا الرأس والتذييل /admin/content/menus (docs/SPEC.md FR-54) بصلاحية content.manage.
 *
 * كل حفظ يكتب نسخة في menu_revisions، ويمكن استرجاع أي نسخة بتأكيد قبل التنفيذ.
 * الوجهة صفحة من منشئ الصفحات أو رابط http/https/tel/wa.me أو مسار داخلي فقط.
 *
 * @property-read Schema $form
 */
class Menus extends PermissionPage
{
    public const int REVISIONS_SHOWN = 15;

    protected static ?string $slug = 'content/menus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    /** @var view-string */
    protected string $view = 'filament.pages.menus';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::ContentManage;
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.menus.navigation');
    }

    public function getTitle(): string
    {
        return __('pages.menus.navigation');
    }

    public function mount(): void
    {
        app(BaseDesign::class)->menuBaseline();

        $this->fillFromCurrentItems();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(MenuLocation::Header->getLabel())
                    ->description(__('pages.menus.header_description'))
                    ->schema([$this->itemsRepeater(MenuLocation::Header)]),
                Section::make(MenuLocation::Footer->getLabel())
                    ->schema([$this->itemsRepeater(MenuLocation::Footer)]),
            ]);
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        try {
            app(SaveMenus::class)->handle($this->actor(), $state);
        } catch (ValidationException $exception) {
            throw PageResource::formValidationException($exception, $this->data ?? []);
        }

        $this->fillFromCurrentItems();
        unset($this->revisions);

        Notification::make()->success()->title(__('pages.menus.saved'))->send();
    }

    public function restoreRevisionAction(): Action
    {
        return Action::make('restoreRevision')
            ->label(__('pages.revisions.restore.label'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('pages.menus.restore.heading'))
            ->modalDescription(__('pages.menus.restore.description'))
            ->modalSubmitActionLabel(__('pages.revisions.restore.submit'))
            ->action(function (array $arguments): void {
                $revision = MenuRevision::query()->findOrFail($arguments['revision'] ?? null);

                app(RestoreMenuRevision::class)->handle($this->actor(), $revision);

                $this->fillFromCurrentItems();
                unset($this->revisions);

                Notification::make()->success()->title(__('pages.menus.restore.done'))->send();
            });
    }

    /**
     * @return Collection<int, MenuRevision>
     */
    #[Computed]
    public function revisions(): Collection
    {
        return MenuRevision::query()
            ->with('author:id,full_name')
            ->latest('id')
            ->limit(self::REVISIONS_SHOWN)
            ->get();
    }

    private function itemsRepeater(MenuLocation $location): Repeater
    {
        return Repeater::make($location->value)
            ->hiddenLabel()
            ->maxItems(fn (): int => (int) config('security.pages.max_menu_items'))
            ->defaultItems(0)
            ->reorderable()
            ->addActionLabel(__('pages.menus.add_item'))
            ->columns(['default' => 1, 'md' => 3])
            ->schema([
                TextInput::make('label')
                    ->label(__('pages.menus.fields.label'))
                    ->required()
                    ->maxLength(ValidateMenuItems::LABEL_MAX_LENGTH)
                    ->rule(new NoBankDetailsInContent),
                Select::make('target')
                    ->label(__('pages.menus.fields.target'))
                    ->options([
                        'page' => __('pages.menus.targets.page'),
                        'url' => __('pages.menus.targets.url'),
                    ])
                    ->default('page')
                    ->selectablePlaceholder(false)
                    ->live()
                    ->dehydrated(false),
                Select::make('page_id')
                    ->label(__('pages.menus.fields.page'))
                    ->options(fn (): array => Page::query()->orderByDesc('is_system')->orderBy('title')->pluck('title', 'id')->all())
                    ->required()
                    ->visible(fn (Get $get): bool => $get('target') !== 'url'),
                TextInput::make('url')
                    ->label(__('pages.menus.fields.url'))
                    ->helperText(__('pages.blocks.fields.link_help'))
                    ->required()
                    ->maxLength(SafeLink::MAX_LENGTH)
                    ->rules([new SafeContentLink, new NoBankDetailsInContent])
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->visible(fn (Get $get): bool => $get('target') === 'url'),
            ]);
    }

    private function fillFromCurrentItems(): void
    {
        $grouped = array_fill_keys(array_map(fn (MenuLocation $location): string => $location->value, MenuLocation::cases()), []);

        foreach (MenuItem::query()->orderBy('position')->orderBy('id')->get() as $item) {
            $grouped[$item->location->value][] = [
                'label' => $item->label,
                'target' => $item->page_id !== null ? 'page' : 'url',
                'page_id' => $item->page_id,
                'url' => $item->url,
            ];
        }

        $this->form->fill($grouped);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
