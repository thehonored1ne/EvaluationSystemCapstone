<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        return [
            'stats' => [
                'faculty' => 0,
                'students' => 0,
                'evaluations' => 0,
            ]
        ];
    }
}; ?>

<div class="flex flex-col gap-8">
    <flux:heading size="xl" level="1">Dean Dashboard</flux:heading>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Managed Faculty</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['faculty'] }}</span>
                <flux:icon name="user-group" class="size-6 text-zinc-400" />
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Department Students</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['students'] }}</span>
                <flux:icon name="academic-cap" class="size-6 text-zinc-400" />
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Ongoing Evaluations</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['evaluations'] }}</span>
                <flux:icon name="clipboard-document-check" class="size-6 text-zinc-400" />
            </div>
        </flux:card>
    </div>

    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-4">Department Overview</flux:heading>
        <div class="text-sm text-zinc-500">Detailed department analytics will appear here.</div>
    </flux:card>
</div>
