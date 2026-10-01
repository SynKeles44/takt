<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What the sidebar shows, and what it may be asked to show.
 *
 * Seven sections are fixed. They are the shape of the app — every page lives under exactly one of
 * them, and a sidebar whose top level can be emptied is a sidebar you can lock yourself out of.
 *
 * Under them sit the sub-areas: the pages reached today through a tab row inside a section. Those
 * are opinions, not structure. Somebody who lives in Docker should not pass through Development
 * every morning, and somebody who never opens it should not carry the row. So each one can be
 * lifted out and pinned next to its parent, and nothing breaks if none of them are.
 */
final class Sidebar
{
    /**
     * The fixed seven. `match` is what keeps a section lit on its own sub-pages — anything
     * reachable under a section belongs in its list, and `SidebarSectionTest` asks every page
     * whether exactly one section claims it.
     *
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return [
            ['route' => 'dashboard', 'label' => __('app.nav.dashboard'), 'icon' => 'clock'],
            ['route' => 'history', 'label' => __('app.nav.history'), 'icon' => 'calendar', 'match' => ['history', 'entries.*']],
            ['route' => 'calendar', 'label' => __('app.nav.calendar'), 'icon' => 'calendar-days', 'match' => ['calendar', 'absences', 'absences.*']],
            ['route' => 'insights', 'label' => __('app.nav.insights'), 'icon' => 'chart'],
            ['route' => 'todos.index', 'label' => __('app.nav.todos'), 'icon' => 'list-check', 'match' => ['todos.*', 'tags.*', 'steps.*', 'attachments.*', 'templates', 'templates.*']],
            ['route' => 'tickets', 'label' => __('app.nav.tickets'), 'icon' => 'tag', 'match' => ['tickets', 'tickets.*']],
            ['route' => 'dev', 'label' => __('app.nav.dev'), 'icon' => 'terminal', 'match' => ['dev', 'dev.*', 'projects', 'projects.*', 'snippets', 'snippets.*', 'releases', 'docker', 'docker.*', 'commands', 'commands.*', 'packages', 'packages.*']],
        ];
    }

    /**
     * Every sub-area that may be pinned, in the order its section has them.
     *
     * `query` is what lets a ticket view be pinned at all: the board, the list and the sprints are
     * one route with a parameter, and a pin that cannot carry a parameter could offer only one of
     * the three.
     *
     * @return list<array<string, mixed>>
     */
    public static function extras(): array
    {
        return [
            ['key' => 'absences', 'parent' => 'calendar', 'route' => 'absences', 'label' => __('app.absence.title'), 'icon' => 'sun'],

            ['key' => 'tags', 'parent' => 'todos.index', 'route' => 'tags.index', 'label' => __('app.tags.manage'), 'icon' => 'tags'],
            ['key' => 'templates', 'parent' => 'todos.index', 'route' => 'templates', 'label' => __('app.templates.title'), 'icon' => 'clipboard'],

            ['key' => 'tickets-board', 'parent' => 'tickets', 'route' => 'tickets', 'query' => ['ansicht' => 'board'], 'label' => __('app.ticket.board'), 'icon' => 'columns'],
            ['key' => 'tickets-list', 'parent' => 'tickets', 'route' => 'tickets', 'query' => ['ansicht' => 'liste'], 'label' => __('app.ticket.list'), 'icon' => 'list'],
            ['key' => 'tickets-sprints', 'parent' => 'tickets', 'route' => 'tickets', 'query' => ['ansicht' => 'sprints'], 'label' => __('app.sprint.tab'), 'icon' => 'repeat'],

            ['key' => 'projects', 'parent' => 'dev', 'route' => 'projects', 'label' => __('app.dev.projects'), 'icon' => 'folder'],
            ['key' => 'docker', 'parent' => 'dev', 'route' => 'docker', 'label' => __('app.docker.title'), 'icon' => 'whale'],
            ['key' => 'snippets', 'parent' => 'dev', 'route' => 'snippets', 'label' => __('app.dev.snippets'), 'icon' => 'squares'],
            ['key' => 'testpost', 'parent' => 'dev', 'route' => 'dev.testpost', 'label' => __('app.dev.testpost'), 'icon' => 'send'],
            ['key' => 'commands', 'parent' => 'dev', 'route' => 'commands', 'label' => __('app.dev.commands'), 'icon' => 'terminal'],
            ['key' => 'packages', 'parent' => 'dev', 'route' => 'packages', 'label' => __('app.packages.title'), 'icon' => 'package'],
            ['key' => 'releases', 'parent' => 'dev', 'route' => 'releases', 'label' => __('app.dev.releases'), 'icon' => 'rocket'],

            ['key' => 'settings', 'parent' => null, 'route' => 'settings', 'label' => __('app.nav.settings'), 'icon' => 'gear'],
            ['key' => 'trash', 'parent' => null, 'route' => 'trash', 'label' => __('app.trash.title'), 'icon' => 'trash'],
        ];
    }

    /** @return Collection<string, list<array<string, mixed>>> the extras, grouped under their section's label */
    public static function offered(): Collection
    {
        $names = collect(self::sections())->pluck('label', 'route');

        return collect(self::extras())->groupBy(
            fn (array $extra): string => $names[$extra['parent']] ?? __('app.nav.account'),
        );
    }

    /**
     * The list the sidebar renders: the fixed seven, each followed by whichever of its sub-areas
     * this user pinned.
     *
     * Exactly one entry comes back marked `current`. A pinned sub-area that matches the page wins
     * over its parent — two lit rows would be two markers, and the marker travels between rows on
     * the assumption that there is one.
     *
     * @return list<array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        $picked = collect(self::extras())
            ->filter(fn (array $extra): bool => in_array($extra['key'], $user?->sidebar_extras ?? [], true));

        $here = $picked->first(static fn (array $extra): bool => self::isHere($extra));
        $rows = [];

        foreach (self::sections() as $section) {
            $rows[] = $section + [
                'current' => $here === null && request()->routeIs($section['match'] ?? $section['route']),
                'url' => route($section['route']),
                'depth' => 0,
            ];

            foreach ($picked->where('parent', $section['route']) as $extra) {
                $rows[] = $extra + [
                    'current' => $here !== null && $here['key'] === $extra['key'],
                    'url' => route($extra['route'], $extra['query'] ?? []),
                    'depth' => 1,
                ];
            }
        }

        // the account pages have no section above them, so a pin puts them at the end on their own
        foreach ($picked->whereNull('parent') as $extra) {
            $rows[] = $extra + [
                'current' => $here !== null && $here['key'] === $extra['key'],
                'url' => route($extra['route']),
                'depth' => 0,
            ];
        }

        return $rows;
    }

    /**
     * Whether this sub-area is the page being looked at.
     *
     * The query matters: three ticket views share one route, so a board pin must not light up
     * while the sprints are on screen. An absent parameter counts as the route's own default,
     * which is why the board is the one that matches a bare `/tickets`.
     */
    private static function isHere(array $extra): bool
    {
        if (! request()->routeIs($extra['route'])) {
            return false;
        }

        foreach ($extra['query'] ?? [] as $name => $value) {
            if ((string) request()->query($name, self::fallback($extra['route'], $name)) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private static function fallback(string $route, string $name): string
    {
        return $route === 'tickets' && $name === 'ansicht' ? 'board' : '';
    }
}
