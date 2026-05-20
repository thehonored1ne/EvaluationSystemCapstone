<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
}; ?>

<div class="flex flex-col gap-8">
    <flux:heading size="xl" level="1">Staff Dashboard</flux:heading>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <flux:card class="p-6">
            <flux:heading size="lg">Tasks</flux:heading>
            <div class="mt-4 text-sm text-zinc-500">Your assigned administrative tasks.</div>
        </flux:card>

        <flux:card class="p-6">
            <flux:heading size="lg">Support Tickets</flux:heading>
            <div class="mt-4 text-sm text-zinc-500">Manage student and faculty support requests.</div>
        </flux:card>
    </div>
</div>
