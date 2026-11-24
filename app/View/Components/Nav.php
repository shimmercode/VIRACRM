<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * REQ-11.01 — the sidebar menu, derived from config/navigation.php and the
 * signed-in user's permissions.
 *
 * This is the single place that decides what a user can *see*. It is not the
 * place that decides what they can *do*: every route re-checks the permission
 * in middleware. If this component were wrong, the worst outcome is a visible
 * link that produces a clean 403 — never unauthorised data.
 */
class Nav extends Component
{
    /** @var list<array<string, mixed>> */
    public array $primary = [];

    /** @var list<array<string, mixed>> */
    public array $secondary = [];

    public function __construct()
    {
        $user = auth()->user();
        $config = config('navigation', []);

        foreach (['primary', 'secondary'] as $group) {
            $this->{$group} = $this->filter($config[$group] ?? [], $user);
        }
    }

    private function filter(array $items, $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            // A review-only row must not appear when /review does not exist.
            if (! empty($item['review_only']) && ! config('review.enabled')) {
                continue;
            }

            $permission = $item['permission'] ?? null;

            // A null permission means "any signed-in user".
            if ($permission !== null && ($user === null || ! $user->hasPermission($permission))) {
                continue;
            }

            $visible[] = $item;
        }

        return $visible;
    }

    /**
     * Is this item the current page?
     * Matched on route name prefix so `customers.show` still highlights
     * «مشتریان» in the sidebar.
     */
    public function isActive(array $item): bool
    {
        $route = $item['route'] ?? null;

        if ($route === null) {
            return false;
        }

        if (request()->routeIs($route)) {
            return true;
        }

        $prefix = str_contains($route, '.')
            ? substr($route, 0, strpos($route, '.')) . '.*'
            : null;

        return $prefix !== null && request()->routeIs($prefix);
    }

    public function render(): View
    {
        return view('components.nav');
    }
}
