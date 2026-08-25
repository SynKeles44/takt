<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Snippet;
use App\Services\Commits;
use App\Services\ProjectRunner;
use App\Services\Reviews;
use App\Services\Slack;
use App\Services\TestPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DeveloperController extends Controller
{
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
            'unassigned' => $cachedReviews === null ? [] : $this->unassigned($cachedReviews, $projects),
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

        return view('partials.reviews', [
            'reviews' => $data,
            'reviewsConfigured' => $reviews->configured($request->user()),
            'projects' => $projects,
            'byProject' => $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $reviews->mineFor($data, $project->slug()),
            ]),
            'unassigned' => $this->unassigned($data, $projects),
        ]);
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

    public function post(Request $request, TestPost $builder): View
    {
        $input = $request->validate([
            'ticket' => ['nullable', 'string', 'max:400'],
            'pr' => ['nullable', 'string', 'max:400'],
            'instance' => ['nullable', 'string', 'max:400'],
        ]);

        return view('testpost', [
            'input' => $input,
            'result' => $builder->build($request->user(), $input),
            'slackReady' => app(Slack::class)->configured($request->user()),
            'defaults' => [
                'ticket' => $request->user()->ticket_url_template ?: TestPost::TICKET_DEFAULT,
                'pr' => $request->user()->pr_url_template ?: TestPost::PR_DEFAULT,
                'instance' => $request->user()->instance_url_template ?: TestPost::INSTANCE_DEFAULT,
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
