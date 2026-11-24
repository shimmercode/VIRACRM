<?php

namespace App\Support;

/**
 * Where a screen's data comes from — and whether it is real.
 *
 * THE PROBLEM THIS SOLVES
 * -----------------------
 * The review environment shows screens that read the database and screens that
 * are design only. Both look alike in a browser. The directive is unambiguous:
 * a preview must never be mistakable for working software, and a demo must never
 * be mistakable for production data.
 *
 * So every data-backed review screen reports its provenance through this class,
 * the page prints it in a banner, and `tools/checks/review-integrity.mjs`
 * asserts that no page claims `live` without querying the database.
 */
final class ReviewProvenance
{
    /**
     * Describe what a screen is showing right now.
     *
     * @param  int|null  $rows  number of rows actually rendered, or null if the
     *                          screen is design-only
     * @return array{kind:string,label:string,detail:string,rows:int|null}
     */
    public static function of(?int $rows, string $source = 'review database'): array
    {
        if ($rows === null) {
            return [
                'kind' => 'design',
                'label' => 'طراحی — بدون داده',
                'detail' => 'این صفحه نمایش طراحی است؛ هیچ کوئری‌ای اجرا نشده و هیچ داده‌ای خوانده نشده است.',
                'rows' => null,
            ];
        }

        if ($rows === 0) {
            return [
                'kind' => 'empty',
                'label' => 'داده نمایشی — جدول خالی',
                'detail' => 'صفحه به پایگاه داده وصل است، اما در این نسخه رکوردی وجود ندارد. '
                    . 'خالی بودن جدول اثبات کارکرد قابلیت نیست.',
                'rows' => 0,
            ];
        }

        return [
            'kind' => 'demo',
            'label' => 'داده نمایشی (Demo)',
            'detail' => 'این اعداد از ' . $source . ' خوانده شده‌اند: کوئری واقعی روی داده‌های ساختگی. '
                . 'هیچ دادهٔ واقعی مشتری در این محیط نیست.',
            'rows' => $rows,
        ];
    }
}
