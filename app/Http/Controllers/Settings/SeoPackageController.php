<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SeoPackageRequest;
use App\Models\SeoPackage;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §15 / REQ-15.01 — SEO service packages.
 *
 * The table was read by the project form's package dropdown and by nothing
 * else, so the keyword ceiling a package imposes could never be changed by
 * anyone. This is the screen that makes it configurable, which is also what
 * lets the ceiling be *tested* rather than assumed.
 *
 * Gated by `settings.manage` for writes and `settings.view` for reading: a
 * package is system configuration, not project data.
 */
final class SeoPackageController extends Controller
{
    public function index(): View
    {
        return view('settings.seo-packages.index', [
            'packages' => SeoPackage::query()
                // seo_project_details is hasOne from Project, so one detail row
                // is one project on this package. SeoPackage has no projects()
                // relation and withCount('projects') would fatal.
                ->withCount('details')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.seo-packages.create', ['package' => new SeoPackage()]);
    }

    public function store(SeoPackageRequest $request): RedirectResponse
    {
        $package = SeoPackage::query()->create($request->validatedForModel());

        AuditRecorder::created($package, 'ثبت پکیج سئو: ' . $package->title);

        return redirect()
            ->route('settings.seo-packages.index')
            ->with('success', 'پکیج ثبت شد.');
    }

    public function edit(SeoPackage $seoPackage): View
    {
        return view('settings.seo-packages.edit', ['package' => $seoPackage]);
    }

    public function update(SeoPackageRequest $request, SeoPackage $seoPackage): RedirectResponse
    {
        $before = $seoPackage->only(['title', 'code', 'keyword_limit', 'is_active']);
        $seoPackage->fill($request->validatedForModel())->save();

        AuditRecorder::updated(
            $seoPackage,
            $seoPackage->getChanges(),
            'ویرایش پکیج سئو: ' . ($before['title'] ?? ''),
        );

        return redirect()
            ->route('settings.seo-packages.index')
            ->with('success', 'پکیج به‌روزرسانی شد.');
    }

    /**
     * Retire a package.
     *
     * Never a delete. Projects already sold on a package must keep showing
     * which one, and the keyword ceiling those projects were sold under is
     * part of their record. Deactivating removes it from the project form —
     * `packageOptions()` filters on `is_active` — without touching history.
     */
    public function destroy(SeoPackage $seoPackage): RedirectResponse
    {
        $inUse = $seoPackage->details()->count();

        if ($inUse > 0) {
            // denied(string $permission, ?Model $subject) takes two arguments
            // and means a permission refusal; a business refusal is a plain
            // updated-style event with a description.
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $seoPackage,
                sprintf('حذف پکیج سئو «%s» رد شد: در %d پروژه استفاده شده است', $seoPackage->title, $inUse),
                ['refused' => 'in_use', 'projects' => $inUse],
            );

            return redirect()
                ->route('settings.seo-packages.index')
                ->with('error', sprintf(
                    'این پکیج در %d پروژه استفاده شده است؛ به‌جای حذف، آن را غیرفعال کنید.',
                    $inUse,
                ));
        }

        $title = $seoPackage->title;
        $seoPackage->delete();

        AuditRecorder::deleted($seoPackage, 'حذف پکیج سئو: ' . $title);

        return redirect()
            ->route('settings.seo-packages.index')
            ->with('success', 'پکیج حذف شد.');
    }
}
