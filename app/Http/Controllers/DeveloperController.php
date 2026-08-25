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
            'reviews' => $reviews->forUser($request->user()),
            'reviewsConfigured' => $reviews->configured($request->user()),
            'snippets' => Snippet::query()->inOrder()->limit(8)->get(),
        ]);
    }

    public function refreshReviews(Request $request, Reviews $reviews): RedirectResponse
    {
        $reviews->forget($request->user());

        return back()->with('status', __('app.dev.reviews_refreshed'));
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
