<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Recurrence;
use App\Models\StepTemplate;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class TodoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'due_time' => ['nullable', 'date_format:H:i', 'required_with:due_date_time_only'],
            'recurrence' => ['nullable', Rule::enum(Recurrence::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')->where('user_id', $this->user()?->getKey())],
            'step_template_id' => ['nullable', 'integer', Rule::exists('step_templates', 'id')->where('user_id', $this->user()?->getKey())],
        ];
    }

    public function messages(): array
    {
        return [
            'due_time.required_with' => __('app.validation.time_needs_date'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('due_time') && ! $this->filled('due_date')) {
                $validator->errors()->add('due_date', __('app.validation.time_needs_date'));
            }
        });
    }

    public function stepTemplate(): ?StepTemplate
    {
        if (! $this->filled('step_template_id')) {
            return null;
        }

        return StepTemplate::query()->with('items')->find($this->integer('step_template_id'));
    }

    public function payload(): array
    {
        $date = $this->string('due_date')->trim()->toString();
        $time = $this->string('due_time')->trim()->toString();

        $dueAt = $date === ''
            ? null
            : Carbon::createFromFormat('Y-m-d H:i', $date.' '.($time === '' ? '23:59' : $time))->startOfMinute();

        return [
            'title' => $this->string('title')->trim()->toString(),
            'body' => $this->string('body')->trim()->value() ?: null,
            'due_at' => $dueAt,
            'due_has_time' => $dueAt !== null && $time !== '',
            'recurrence' => Recurrence::tryFrom($this->string('recurrence')->toString()) ?? Recurrence::None,
        ];
    }

    /** @return list<int> */
    public function tagIds(): array
    {
        return array_map('intval', $this->input('tags', []));
    }

    public function tags(): Collection
    {
        return Tag::query()->whereKey($this->tagIds())->get();
    }
}
