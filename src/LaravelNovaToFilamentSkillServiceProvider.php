<?php

namespace Bkuhl\LaravelNovaToFilamentSkill;

use Illuminate\Support\ServiceProvider;

class LaravelNovaToFilamentSkillServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../SKILL.md' => base_path('SKILL.md'),
        ], 'nova-to-filament-skill');
    }
}
