<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Snippet;
use App\Services\Commits;
use App\Services\Linear;
use App\Services\ProjectRunner;
use App\Services\Reviews;
use App\Services\Slack;
use App\Services\TestPost;
use App\Services\Tickets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    /** Enough to find the one you are posting about, few enough that the list stays scannable. */
    private const int TICKET_CHOICES = 40;

    public function index(Request $request, Commits $commits, Reviews $reviews, ProjectRunner $runner): View
    {
        $request->validate(['tag' => ['nullable', 'date_format:Y-m-d']]);

        $day = $request->filled('tag')
            ? Carbon::createFromFormat('Y-m-d', $request->string('tag')->toString())
            : Carbon::today();

        $projects = Project::query()->inOrder()->get();
        $groups = $commits->forDay($day, $projects);

        // the reviews cost over a second when they are not cached, so the page does not wait
        $cachedReviews = $reviews->cached($request->user());

        return view('developer', [
            'day' => $day,
            'previousDay' => $day->copy()->subDay()->toDateString(),
            'nextDay' => $day->copy()->addDay()->toDateString(),
            'isToday' => $day->isToday(),
            'groups' => $groups,
            'commitCount' => $commits->count($groups),
            'projects' => $projects,
            'states' => $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $runner->state($project),
            ]),
            'reviews' => $cachedReviews,
            'reviewsConfigured' => $reviews->configured($request->user()),
            'byProject' => $cachedReviews === null ? collect() : $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $reviews->mineFor($cachedReviews, $project->slug()),
            ]),
            'unassigned' => $unassigned = ($cachedReviews === null ? [] : $this->unassigned($cachedReviews, $projects)),
            'clipboard' => $this->clipboard($reviews, $cachedReviews, $projects, $unassigned),
            'waits' => $cachedReviews === null ? null : $reviews->waitStats($cachedReviews),
            'snippets' => Snippet::query()->inOrder()->limit(8)->get(),
        ]);
    }

    public function refreshReviews(Request $request, Reviews $reviews): RedirectResponse
    {
        $reviews->forget($request->user());

        return back()->with('status', __('app.dev.reviews_refreshed'));
    }

    /** The review sections on their own, fetched by the page once it stands. */
    public function reviewSections(Request $request, Reviews $reviews): View
    {
        $projects = Project::query()->inOrder()->get();
        $data = $reviews->forUser($request->user());

        $unassigned = $this->unassigned($data, $projects);

        return view('partials.reviews', [
            'reviews' => $data,
            'reviewsConfigured' => $reviews->configured($request->user()),
            'projects' => $projects,
            'byProject' => $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $reviews->mineFor($data, $project->slug()),
            ]),
            'unassigned' => $unassigned,
            'clipboard' => $this->clipboard($reviews, $data, $projects, $unassigned),
            'waits' => $reviews->waitStats($data),
        ]);
    }

    /**
     * The ready-made clipboard texts the lists offer: one per project, one for the
     * unregistered repositories, and one across everything.
     *
     * @param  array{mine: list<array>}|null  $data
     * @param  Collection<int, Project>  $projects
     * @param  list<array>  $unassigned
     * @return array{all: string, projects: array<int, string>, unassigned: string}
     */
    private function clipboard(Reviews $reviews, ?array $data, $projects, array $unassigned): array
    {
        if ($data === null) {
            return ['all' => '', 'projects' => [], 'unassigned' => ''];
        }

        $groups = [];
        $perProject = [];

        foreach ($projects as $project) {
            $pulls = $reviews->mineFor($data, $project->slug());

            $groups[$project->name] = $pulls;
            $perProject[$project->getKey()] = $reviews->clipboardText([$project->name => $pulls]);
        }

        $others = $reviews->byRepository($unassigned);

        return [
            'all' => $reviews->clipboardText([...$groups, ...$others]),
            'projects' => $perProject,
            'unassigned' => $reviews->clipboardText($others),
        ];
    }

    /**
     * Pull requests that belong to no registered project — otherwise they would vanish.
     *
     * @param  array{mine: list<array>}  $reviews
     * @param  Collection<int, Project>  $projects
     * @return list<array>
     */
    private function unassigned(array $reviews, $projects): array
    {
        $known = $projects->map(fn (Project $project): ?string => $project->slug())->filter()->map('strtolower')->all();

        return array_values(array_filter(
            $reviews['mine'],
            fn (array $pull): bool => ! in_array(strtolower($pull['repository']), $known, true),
        ));
    }

    public function post(Request $request, TestPost $builder, Linear $linear, Reviews $reviews, Tickets $tickets): View
    {
        $input = $request->validate([
            'ticket' => ['nullable', 'string', 'max:400'],
            'pr' => ['nullable', 'string', 'max:400'],
            'instance' => ['nullable', 'string', 'max:400'],
            'fuellen' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $key = mb_strtoupper(trim((string) ($input['ticket'] ?? '')));
        $filled = null;

        /*
         * Filling is its own button rather than something the Build button does on the side: an
         * automatic fill that runs on every submit cannot be undone — clear a field it guessed
         * wrong and the next click puts it back.
         */
        if (($input['fuellen'] ?? null) !== null && $key !== '') {
            // fetched rather than read from cache: this runs on a click that asked for exactly
            // this, and a cold cache answering "nothing found" would be a lie about the ticket
            $all = $reviews->forUser($user);

            $filled = $builder->suggest(
                $user,
                $key,
                [...$all['mine'], ...$all['incoming']],
                $linear->forIds($user, [$key])['issues'][$key] ?? null,
                fn (string $repository, int $number): string => $reviews->conversation($user, $repository, $number),
            );

            // what was found wins over what was in the field; what was not found leaves it alone
            foreach (['pr', 'instance'] as $field) {
                if ($filled[$field] !== '') {
                    $input[$field] = $filled[$field];
                }
            }
        }

        return view('testpost', [
            'input' => $input,
            'result' => $builder->build($user, $input),
            'slackReady' => app(Slack::class)->configured($user),
            'filled' => $filled,
            // the keys to choose from, so the field is a picker as well as a text box
            'choices' => $tickets->collect($user)['tickets']
                ->map(fn (array $row): array => ['key' => $row['id'], 'title' => $row['title']])
                ->take(self::TICKET_CHOICES)
                ->values(),
            'defaults' => [
                'ticket' => $user->ticket_url_template ?: TestPost::TICKET_DEFAULT,
                'pr' => $user->pr_url_template ?: TestPost::PR_DEFAULT,
                'instance' => $user->instance_url_template ?: TestPost::INSTANCE_DEFAULT,
            ],
        ]);
    }

    /** Sends the built block to Slack, under the user's own name. */
    public function send(Request $request, TestPost $builder, Slack $slack): RedirectResponse
    {
        $input = $request->validate([
            'ticket' => ['nullable', 'string', 'max:400'],
            'pr' => ['nullable', 'string', 'max:400'],
            'instance' => ['nullable', 'string', 'max:400'],
        ]);

        $user = $request->user();
        $result = $builder->build($user, $input);

        if ($result['missing'] !== []) {
            return back()->withErrors([
                'slack' => __('app.dev.missing_fields', [
                    'fields' => collect($result['missing'])->map(fn (string $key): string => __('app.dev.'.$key))->implode(', '),
                ]),
            ]);
        }

        $sent = $slack->post($user, $result['text']);

        if (! $sent['ok']) {
            return back()->withErrors(['slack' => $sent['error']]);
        }

        return back()->with('status', __('app.slack.sent'))->with('slack_permalink', $sent['permalink']);
    }
}
