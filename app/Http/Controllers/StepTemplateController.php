<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StepTemplate;
use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StepTemplateController extends Controller
{
    public function index(): View
    {
        return view('templates', [
            'templates' => StepTemplate::query()->with('items')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', "unique:step_templates,name,NULL,id,user_id,{$request->user()->getKey()}"],
            'items' => ['required', 'string', 'max:4000'],
        ]);

        $template = StepTemplate::query()->create(['name' => trim($data['name'])]);

        $this->fill($template, $data['items']);

        return back()->with('status', __('app.templates.created'));
    }

    public function fromTodo(Request $request, Todo $todo): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', "unique:step_templates,name,NULL,id,user_id,{$request->user()->getKey()}"],
        ]);

        abort_if($todo->steps()->count() === 0, 422);

        $template = StepTemplate::query()->create(['name' => trim($data['name'])]);

        foreach ($todo->steps as $index => $step) {
            $template->items()->create(['title' => $step->title, 'position' => $index + 1]);
        }

        return back()->with('status', __('app.templates.created'));
    }

    public function apply(Request $request, Todo $todo): RedirectResponse
    {
        $data = $request->validate([
            'step_template_id' => ['required', 'integer', "exists:step_templates,id,user_id,{$request->user()->getKey()}"],
        ]);

        $template = StepTemplate::query()->with('items')->findOrFail($data['step_template_id']);
        $position = (int) $todo->steps()->max('position');

        foreach ($template->items as $item) {
            $todo->steps()->create(['title' => $item->title, 'position' => ++$position]);
        }

        return back()->with('status', __('app.templates.applied', ['name' => $template->name, 'count' => $template->items->count()]));
    }

    public function destroy(StepTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', __('app.templates.deleted'));
    }

    private function fill(StepTemplate $template, string $items): void
    {
        $position = 0;

        foreach (Str::of($items)->explode("\n") as $line) {
            $title = trim($line);

            if ($title === '') {
                continue;
            }

            $template->items()->create(['title' => Str::limit($title, 200, ''), 'position' => ++$position]);
        }
    }
}
