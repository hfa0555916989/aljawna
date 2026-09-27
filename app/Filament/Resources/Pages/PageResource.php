<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Filament\Resources\Pages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * منشئ الصفحات {ADMIN_PATH}/content/pages (docs/SPEC.md FR-50..56). الوصول عبر PagePolicy
 * بصلاحية content.manage، ولا حذف لأي صفحة. كل حفظ ونشر واسترجاع عبر App\Actions\Content.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $slug = 'content/pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('pages.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pages.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    /**
     * أخطاء الإجراءات تأتي بمواضع رقمية (blocks.2.data.title)، وحالة النموذج في
     * Builder وRepeater مفهرسة بمعرّفات عشوائية، فيُترجم كل رقم إلى مفتاحه الفعلي
     * ليظهر الخطأ تحت حقله في النموذج (data.*).
     *
     * @param  array<array-key, mixed>  $state
     */
    public static function formValidationException(ValidationException $exception, array $state): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $field => $fieldMessages) {
            $messages['data.'.self::statePath($field, $state)] = $fieldMessages;
        }

        return ValidationException::withMessages($messages);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $state
     */
    private static function statePath(string $path, array $state): string
    {
        $current = $state;
        $mapped = [];

        foreach (explode('.', $path) as $segment) {
            if (ctype_digit($segment) && is_array($current)) {
                $segment = (string) (array_keys($current)[(int) $segment] ?? $segment);
            }

            $mapped[] = $segment;
            $current = is_array($current) ? ($current[$segment] ?? null) : null;
        }

        return implode('.', $mapped);
    }
}
