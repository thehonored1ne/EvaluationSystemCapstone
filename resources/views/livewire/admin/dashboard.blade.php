<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        return [
            'stats' => [
                'users' => User::count(),
                'admins' => User::role('admin')->count(),
                'students' => User::role('student')->count(),
                'active_now' => User::where('is_active', true)->count(),
            ]
        ];
    }
}; ?>

<div class="flex flex-col gap-8">
    <flux:heading size="xl" level="1">Admin Dashboard</flux:heading>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Total Users</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['users'] }}</span>
                <flux:icon name="users" class="size-6 text-zinc-400" />
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Administrators</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['admins'] }}</span>
                <flux:icon name="shield-check" class="size-6 text-zinc-400" />
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Total Students</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['students'] }}</span>
                <flux:icon name="academic-cap" class="size-6 text-zinc-400" />
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-2 p-6">
            <flux:heading size="sm" class="text-zinc-500">Active Accounts</flux:heading>
            <div class="flex items-end justify-between">
                <span class="text-3xl font-bold">{{ $stats['active_now'] }}</span>
                <flux:icon name="check-circle" class="size-6 text-zinc-400" />
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <flux:card class="p-6 flex flex-col gap-4">
            <flux:heading size="lg">Recent Activities</flux:heading>
            <div class="text-sm text-zinc-500">No recent activities to show.</div>
        </flux:card>

        <flux:card class="p-6 flex flex-col gap-4">
            <flux:heading size="lg">System Status</flux:heading>
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between text-sm">
                    <span>Database Connection</span>
                    <flux:badge variant="success" size="sm">Healthy</flux:badge>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span>Cache Storage</span>
                    <flux:badge variant="success" size="sm">Healthy</flux:badge>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span>Queue Worker</span>
                    <flux:badge variant="warning" size="sm">Idle</flux:badge>
                </div>
            </div>
        </flux:card>
    </div>
</div>
