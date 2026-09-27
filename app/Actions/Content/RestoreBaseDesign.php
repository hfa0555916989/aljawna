<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\MenuRevision;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Services\Audit;
use App\Services\BaseDesign;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * "استعادة التصميم الأساسي" (قرار المالك في docs/DECISIONS.md): تنسخ نسخة الأساس
 * المحمية للرئيسية وللقائمتين إلى نسخ جديدة في سجليهما، فتظهر فورًا ولا يُحذف
 * شيء مما قبلها، ويمكن التراجع باسترجاع النسخة السابقة لكلٍّ منهما. تُسجَّل في audit_logs.
 */
class RestoreBaseDesign
{
    public const AUDIT_ACTION = 'content.base_design_restored';

    public function __construct(private readonly BaseDesign $baseDesign) {}

    /**
     * @return array{page_revision: PageRevision, menu_revision: MenuRevision}
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor): array
    {
        Gate::forUser($actor)->authorize('restoreBaseDesign', Page::class);

        $home = $this->baseDesign->home();
        $pageBaseline = $home->baselineRevision()->first() ?? throw new RuntimeException('Home baseline revision is missing.');
        $menuBaseline = $this->baseDesign->menuBaseline();

        return DB::transaction(function () use ($actor, $home, $pageBaseline, $menuBaseline): array {
            $previousPageRevisionId = $home->revisions()->max('id');
            $previousMenuRevisionId = MenuRevision::query()->max('id');

            $pageRevision = $home->revisions()->create(['blocks' => $pageBaseline->blocks, 'author_id' => $actor->getKey()]);
            $home->forceFill(['blocks' => $pageBaseline->blocks])->save();
            $home->unsetRelation('latestRevision');

            $menuRevision = SaveMenus::apply($menuBaseline->items, $actor);

            Audit::record(self::AUDIT_ACTION, $home, [
                'page_revision_id' => $pageRevision->getKey(),
                'menu_revision_id' => $menuRevision->getKey(),
                'previous_page_revision_id' => $previousPageRevisionId,
                'previous_menu_revision_id' => $previousMenuRevisionId,
            ], $actor);

            return ['page_revision' => $pageRevision, 'menu_revision' => $menuRevision];
        });
    }
}
