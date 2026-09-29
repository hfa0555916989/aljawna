<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Exceptions\StorageOperationFailed;
use App\Models\Page;
use App\Rules\BrandingImageFile;
use App\Rules\NoBankDetailsInContent;
use App\Rules\SafeContentLink;
use App\Rules\SaudiPhoneNumber;
use App\Services\BrandingImageStorage;
use App\Support\PageBlocks;
use App\Support\ReservedSlugs;
use App\Support\SafeLink;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * نموذج الصفحة: بياناتها وكتلها المسموحة فقط (docs/SPEC.md FR-50, FR-53).
 *
 * الحقول تطابق App\Support\PageBlocks، وهو المرجع الذي تتحقق به الإجراءات على
 * الخادم؛ القيود هنا لإظهار الخطأ مبكرًا فقط. لا HTML حر ولا CSS ولا iframe،
 * و"المبادرات" كتلة نظامية تُختار طريقتها وعددها فقط.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('pages.sections.page'))
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('title')
                            ->label(__('pages.fields.title'))
                            ->required()
                            ->maxLength(150)
                            ->rule(new NoBankDetailsInContent),
                        TextInput::make('slug')
                            ->label(__('pages.fields.slug'))
                            ->helperText(fn (?Page $record): string => $record?->is_system ? __('pages.fields.slug_system_help') : __('pages.fields.slug_help'))
                            ->prefix('/')
                            ->required(fn (?Page $record): bool => ! $record?->is_system)
                            ->disabled(fn (?Page $record): bool => (bool) $record?->is_system)
                            ->dehydrated(fn (?Page $record): bool => ! $record?->is_system)
                            ->maxLength(ReservedSlugs::MAX_LENGTH)
                            ->regex('/^'.ReservedSlugs::PATTERN.'$/')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('seo_description')
                            ->label(__('pages.fields.seo_description'))
                            ->helperText(__('pages.fields.seo_description_help'))
                            ->rows(2)
                            ->maxLength(300)
                            ->rule(new NoBankDetailsInContent)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('pages.sections.blocks'))
                    ->description(__('pages.sections.blocks_description'))
                    ->schema([
                        Builder::make('blocks')
                            ->hiddenLabel()
                            ->blocks(self::blocks())
                            ->maxItems(fn (): int => (int) config('security.pages.max_blocks'))
                            ->addActionLabel(__('pages.blocks.add'))
                            ->blockNumbers(false)
                            ->blockPickerColumns(['default' => 1, 'sm' => 2])
                            ->collapsible()
                            ->cloneable(),
                    ]),
            ]);
    }

    /**
     * @return list<Block>
     */
    private static function blocks(): array
    {
        return [
            Block::make(PageBlocks::HERO)
                ->label(__('pages.blocks.types.hero'))
                ->icon(Heroicon::OutlinedSparkles)
                ->schema([
                    self::text('title', 150)->required(),
                    self::textarea('lead', 500),
                    Toggle::make('show_counters')->label(__('pages.blocks.fields.show_counters')),
                    Repeater::make('buttons')
                        ->label(__('pages.blocks.fields.buttons'))
                        ->maxItems(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('pages.blocks.add_button'))
                        ->columns(['default' => 1, 'sm' => 2])
                        ->schema([
                            self::text('label', 40)->required(),
                            self::link('url')->required(),
                            Select::make('style')
                                ->label(__('pages.blocks.fields.style'))
                                ->options(self::options(PageBlocks::BUTTON_STYLES, 'pages.blocks.styles'))
                                ->default('primary')
                                ->selectablePlaceholder(false),
                            Toggle::make('guests_only')->label(__('pages.blocks.fields.guests_only'))->inline(false),
                        ]),
                ]),
            Block::make(PageBlocks::RICH_TEXT)
                ->label(__('pages.blocks.types.rich_text'))
                ->icon(Heroicon::OutlinedBars3BottomRight)
                ->schema([
                    self::text('heading', 150),
                    RichEditor::make('body')
                        ->label(__('pages.blocks.fields.body'))
                        ->helperText(__('pages.blocks.fields.body_help'))
                        ->required()
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'strike', 'link'],
                            ['h2', 'h3'],
                            ['bulletList', 'orderedList', 'blockquote'],
                            ['undo', 'redo'],
                        ]),
                ]),
            Block::make(PageBlocks::IMAGE)
                ->label(__('pages.blocks.types.image'))
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    FileUpload::make('path')
                        ->label(__('pages.blocks.fields.image'))
                        ->helperText(__('pages.blocks.fields.image_help'))
                        ->required()
                        ->disk(fn (): string => (string) config('security.branding.disk'))
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->maxSize(fn (): int => (int) config('security.branding.max_kilobytes'))
                        ->rule(new BrandingImageFile)
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => self::storeImage($file)),
                    self::text('alt', 150)->required(),
                    self::text('caption', 200),
                ]),
            Block::make(PageBlocks::CARDS)
                ->label(__('pages.blocks.types.cards'))
                ->icon(Heroicon::OutlinedSquares2x2)
                ->schema([
                    self::text('heading', 150),
                    Repeater::make('items')
                        ->label(__('pages.blocks.fields.items'))
                        ->minItems(1)
                        ->maxItems(12)
                        ->addActionLabel(__('pages.blocks.add_item'))
                        ->columns(['default' => 1, 'sm' => 2])
                        ->schema([
                            self::text('title', 100)->required(),
                            self::textarea('body', 500),
                            self::text('link_label', 40),
                            self::link('link_url'),
                        ]),
                ]),
            Block::make(PageBlocks::STEPS)
                ->label(__('pages.blocks.types.steps'))
                ->icon(Heroicon::OutlinedListBullet)
                ->schema([
                    self::text('heading', 150),
                    Repeater::make('items')
                        ->label(__('pages.blocks.fields.items'))
                        ->minItems(1)
                        ->maxItems(10)
                        ->addActionLabel(__('pages.blocks.add_item'))
                        ->schema([
                            self::text('title', 100)->required(),
                            self::textarea('body', 500),
                        ]),
                ]),
            Block::make(PageBlocks::FAQ)
                ->label(__('pages.blocks.types.faq'))
                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->schema([
                    self::text('heading', 150),
                    Repeater::make('items')
                        ->label(__('pages.blocks.fields.items'))
                        ->minItems(1)
                        ->maxItems(20)
                        ->addActionLabel(__('pages.blocks.add_item'))
                        ->schema([
                            self::text('question', 200)->required(),
                            self::textarea('answer', 2000)->required(),
                        ]),
                ]),
            Block::make(PageBlocks::COUNTERS)
                ->label(__('pages.blocks.types.counters'))
                ->icon(Heroicon::OutlinedChartBar)
                ->schema([
                    self::text('heading', 150),
                    CheckboxList::make('metrics')
                        ->label(__('pages.blocks.fields.metrics'))
                        ->helperText(__('pages.blocks.fields.metrics_help'))
                        ->required()
                        ->columns(['default' => 1, 'sm' => 2])
                        ->options(self::options(PageBlocks::COUNTER_METRICS, 'pages.metrics')),
                ]),
            Block::make(PageBlocks::DIVIDER)
                ->label(__('pages.blocks.types.divider'))
                ->icon(Heroicon::OutlinedMinus)
                ->schema([]),
            Block::make(PageBlocks::INITIATIVES)
                ->label(__('pages.blocks.types.initiatives'))
                ->icon(Heroicon::OutlinedHeart)
                ->schema([
                    self::text('heading', 150),
                    Select::make('mode')
                        ->label(__('pages.blocks.fields.mode'))
                        ->helperText(__('pages.blocks.fields.mode_help'))
                        ->options(self::options(PageBlocks::INITIATIVE_MODES, 'pages.blocks.modes'))
                        ->default(PageBlocks::INITIATIVES_AVAILABLE)
                        ->selectablePlaceholder(false)
                        ->live(),
                    TextInput::make('limit')
                        ->label(__('pages.blocks.fields.limit'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(PageBlocks::MAX_INITIATIVE_CARDS)
                        ->default(3)
                        ->visible(fn (Get $get): bool => $get('mode') !== PageBlocks::INITIATIVES_LATEST),
                ]),
            Block::make(PageBlocks::CONTACT)
                ->label(__('pages.blocks.types.contact'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([
                    self::text('heading', 150),
                    TextInput::make('phone')
                        ->label(__('pages.blocks.fields.phone'))
                        ->helperText(__('pages.blocks.fields.phone_help'))
                        ->required()
                        ->maxLength(20)
                        ->rule(new SaudiPhoneNumber)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Toggle::make('show_form')->label(__('pages.blocks.fields.show_form')),
                ]),
        ];
    }

    /**
     * يحفظ صورة كتلة الصورة. فشل التخزين يوقف حفظ الصفحة كله (مع التراجع) برسالة واضحة، بدل خطأ خادم.
     *
     * @throws Halt
     */
    private static function storeImage(TemporaryUploadedFile $file): string
    {
        try {
            return app(BrandingImageStorage::class)->store($file);
        } catch (StorageOperationFailed) {
            Notification::make()->title(__('pages.image_store_failed'))->danger()->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    private static function text(string $name, int $maxLength): TextInput
    {
        return TextInput::make($name)
            ->label(__('pages.blocks.fields.'.$name))
            ->maxLength($maxLength)
            ->rule(new NoBankDetailsInContent);
    }

    private static function textarea(string $name, int $maxLength): Textarea
    {
        return Textarea::make($name)
            ->label(__('pages.blocks.fields.'.$name))
            ->rows(3)
            ->maxLength($maxLength)
            ->rule(new NoBankDetailsInContent);
    }

    private static function link(string $name): TextInput
    {
        return TextInput::make($name)
            ->label(__('pages.blocks.fields.'.$name))
            ->helperText(__('pages.blocks.fields.link_help'))
            ->maxLength(SafeLink::MAX_LENGTH)
            ->rules([new SafeContentLink, new NoBankDetailsInContent])
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function options(array $values, string $langPrefix): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = (string) __("{$langPrefix}.{$value}");
        }

        return $options;
    }
}
