<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public bool $showAiPipeline = true;
    public bool $isTraining = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->showAiPipeline = (bool) ($user->show_ai_pipeline ?? true);
    }

    public function toggleAiPipeline(): void
    {
        $user = Auth::user();
        $this->showAiPipeline = ! $this->showAiPipeline;
        $user->update(['show_ai_pipeline' => $this->showAiPipeline]);

        $status = $this->showAiPipeline ? 'visible' : 'hidden';
        \Flux::toast(
            heading: 'Sidebar Updated',
            text: "AI Pipeline is now {$status} in the sidebar navigation.",
            variant: 'success'
        );
    }

    public function runTraining(): void
    {
        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('ai:train');
            $output = \Illuminate\Support\Facades\Artisan::output();

            \Flux::toast(
                heading: 'AI Training Complete',
                text: 'The Decision Tree model and Tagalog/Taglish lexicon have been retrained successfully.',
                variant: 'success'
            );
        } catch (\Exception $e) {
            \Flux::toast(
                heading: 'Training Error',
                text: 'Could not connect to Flask AI Service. Ensure the service is running on port 5001.',
                variant: 'danger'
            );
        }
    }
}; ?>

<div class="flex flex-col items-start w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Training & AI Controls" subheading="Manage AI pipeline training operations and sidebar navigation visibility">
        <div class="my-6 w-full space-y-6">
            
            <!-- Sidebar Visibility Toggle Card -->
            <div class="p-4 sm:p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="beaker" class="size-5 text-[#9b0000] dark:text-[#f89696] shrink-0" />
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Sidebar AI Pipeline Navigation</h3>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Controls whether the AI Pipeline management portal appears under the sidebar navigation for your account.
                        </p>
                    </div>

                    <flux:badge variant="{{ $showAiPipeline ? 'success' : 'neutral' }}" size="sm" class="font-bold shrink-0">
                        {{ $showAiPipeline ? 'Visible' : 'Hidden' }}
                    </flux:badge>
                </div>

                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ $showAiPipeline ? 'AI Pipeline link is currently shown in the sidebar' : 'AI Pipeline link is currently hidden from the sidebar' }}
                    </span>
                    
                    <flux:button 
                        wire:click="toggleAiPipeline" 
                        size="sm" 
                        variant="{{ $showAiPipeline ? 'outline' : 'primary' }}"
                        icon="{{ $showAiPipeline ? 'eye-slash' : 'eye' }}"
                        class="w-full sm:w-auto shrink-0 justify-center"
                    >
                        {{ $showAiPipeline ? 'Hide from Sidebar' : 'Show in Sidebar' }}
                    </flux:button>
                </div>
            </div>

            @if(auth()->user()->hasRole('admin'))
                <!-- Model Retraining Card -->
                <div class="p-4 sm:p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="cpu-chip" class="size-5 text-[#9b0000] dark:text-[#f89696] shrink-0" />
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">AI Sentiment Model Training</h3>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Retrains the Decision Tree NLP classifier with historical evaluation comments and custom Tagalog/Taglish seed lexicons.
                        </p>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700 text-xs space-y-2">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-zinc-500 dark:text-zinc-400">Service Endpoint:</span>
                            <span class="font-mono font-bold text-zinc-800 dark:text-zinc-200 break-all">{{ config('services.ai.url', 'http://127.0.0.1:5001') }}</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-zinc-500 dark:text-zinc-400">Tagalog Lexicon Dictionary:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">424 Words Active</span>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-2.5 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button href="/admin/ai" variant="ghost" size="sm" icon="arrow-top-right-on-square" class="w-full sm:w-auto justify-center">
                            Open AI Dashboard
                        </flux:button>

                        <flux:button 
                            wire:click="runTraining" 
                            variant="primary" 
                            size="sm" 
                            icon="arrow-path"
                            class="w-full sm:w-auto justify-center shrink-0"
                        >
                            Retrain Model Now
                        </flux:button>
                    </div>
                </div>
            @endif

        </div>
    </x-settings.layout>
</div>
