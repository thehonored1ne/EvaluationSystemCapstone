<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
}; ?>

<div class="flex flex-col gap-8">
    <flux:heading size="xl" level="1">Program Head Dashboard</flux:heading>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <flux:card class="p-6">
            <flux:heading size="lg">Program Statistics</flux:heading>
            <div class="mt-4 text-sm text-zinc-500">Statistics for your assigned program.</div>
        </flux:card>

        <flux:card class="p-6">
            <flux:heading size="lg">Faculty Monitoring</flux:heading>
            <div class="mt-4 text-sm text-zinc-500">Monitor faculty performance and evaluations.</div>
        </flux:card>
    </div>
</div>
