<?php

namespace App\Services;

use App\Models\SubtitleTemplate;
use Illuminate\Support\Facades\DB;

class SubtitleTemplateService
{
    public function active(): SubtitleTemplate
    {
        $template = SubtitleTemplate::query()->where('is_default', true)->first()
            ?? SubtitleTemplate::query()->orderBy('id')->first();

        if ($template !== null) {
            return $template;
        }

        return SubtitleTemplate::create(SubtitleTemplate::defaults());
    }

    public function makeDefault(SubtitleTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            SubtitleTemplate::query()->whereKeyNot($template->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });
    }

    public function resetToDefault(): SubtitleTemplate
    {
        $template = SubtitleTemplate::create(SubtitleTemplate::defaults());
        $this->makeDefault($template);

        return $template;
    }
}
