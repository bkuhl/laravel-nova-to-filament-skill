<?php

namespace Bkuhl\LaravelNovaToFilamentSkill;

use Illuminate\Support\ServiceProvider;

class LaravelNovaToFilamentSkillServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../skill.md' => base_path('skill.md'),
        ], 'nova-to-filament-skill');
    }
}
