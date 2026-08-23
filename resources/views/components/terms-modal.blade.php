<div 
    x-data="{ 
        open: false, 
        activeTab: 'terms' 
    }" 
    @open-terms-modal.window="open = true; if ($event.detail?.tab) activeTab = $event.detail.tab" 
    @keydown.escape.window="open = false" 
    x-cloak
>
    <div 
        x-show="open" 
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto bg-black/60 backdrop-blur-xs"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        role="dialog"
        aria-modal="true"
        aria-labelledby="terms-modal-title"
    >
        <!-- Modal Container -->
        <div 
            class="relative w-full max-w-2xl bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 flex flex-col max-h-[85vh] overflow-hidden"
            @click.outside="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <!-- Header Bar -->
            <div class="px-6 pt-6 pb-4 border-b border-zinc-100 dark:border-zinc-800 flex flex-col gap-4 bg-white dark:bg-zinc-900 shrink-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="terms-modal-title" class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            Terms & Privacy Policy
                        </h2>
                        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Guidelines, acceptable use, and data protection policies for the evaluation system.
                        </p>
                    </div>

                    <button 
                        type="button" 
                        @click="open = false" 
                        class="size-8 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer shrink-0"
                        aria-label="Close modal"
                    >
                        <flux:icon icon="x-mark" class="size-5" />
                    </button>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center gap-1 border-b border-zinc-200 dark:border-zinc-800 -mb-4 pt-1">
                    <button 
                        type="button" 
                        @click="activeTab = 'terms'" 
                        :class="activeTab === 'terms' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 font-medium'"
                        class="pb-3 text-xs sm:text-sm border-b-2 transition-all cursor-pointer px-3 flex items-center gap-2"
                    >
                        <flux:icon icon="document-text" class="size-4" />
                        Terms of Service
                    </button>
                    <button 
                        type="button" 
                        @click="activeTab = 'privacy'" 
                        :class="activeTab === 'privacy' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 font-medium'"
                        class="pb-3 text-xs sm:text-sm border-b-2 transition-all cursor-pointer px-3 flex items-center gap-2"
                    >
                        <flux:icon icon="shield-check" class="size-4" />
                        Privacy & AI Disclosure
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-6 overflow-y-auto space-y-6 text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed font-sans">
                
                <!-- TAB 1: TERMS OF SERVICE -->
                <div x-show="activeTab === 'terms'" class="space-y-5">
                    <!-- 1. Acceptable Use -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="chat-bubble-bottom-center-text" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>Constructive & Honest Feedback</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            Evaluations should reflect your genuine academic and professional experience. Feedback must remain objective, constructive, and professional. Profane, abusive, or discriminatory remarks are strictly prohibited and automatically filtered.
                        </p>
                    </div>

                    <!-- 2. Account Responsibility -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="key" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>Account Security</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            You are responsible for safeguarding your login credentials. Sharing accounts or evaluating on behalf of another student or employee is a violation of academic integrity.
                        </p>
                    </div>

                    <!-- 3. Submission Finality -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="check-badge" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>Submission Finality</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            Once an evaluation is submitted, it is final and cannot be modified, re-opened, or retracted. Please review your ratings and comments carefully before submitting.
                        </p>
                    </div>

                    <!-- 4. Non-Retaliation Policy -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="shield-exclamation" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>Strict Non-Retaliation</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            Evaluators are protected by institutional policy. Faculty and administrators are strictly prohibited from attempting to identify respondents or retaliating against any evaluator.
                        </p>
                    </div>
                </div>

                <!-- TAB 2: PRIVACY & AI DISCLOSURE -->
                <div x-show="activeTab === 'privacy'" class="space-y-5">
                    <!-- 1. Evaluator Anonymity -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="eye-slash" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>Guaranteed Anonymity</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            Your personal identity is strictly decoupled from your submitted ratings and comments. Faculty and department heads only receive aggregated averages and anonymized feedback.
                        </p>
                    </div>

                    <!-- 2. Data Privacy Compliance -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="lock-closed" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>Data Protection & Privacy</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            All evaluation data is encrypted and securely stored in compliance with Republic Act No. 10173 (Data Privacy Act of 2012). Access is restricted strictly to authorized academic officials.
                        </p>
                    </div>

                    <!-- 3. AI Sentiment Analysis -->
                    <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/50 space-y-1.5">
                        <div class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100 font-semibold text-sm">
                            <flux:icon icon="cpu-chip" class="size-4 text-[#9b0000] dark:text-[#f89696]" />
                            <span>AI Sentiment Analysis Disclosure</span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-normal">
                            Qualitative feedback is analyzed by an automated Natural Language Processing model to summarize overall sentiment trends (Positive, Neutral, Negative). This output serves purely as an advisory tool for academic improvement and is never used as the sole basis for faculty evaluation.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 sm:px-6 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                <span class="text-xs text-zinc-400 text-center sm:text-left">
                    Global Reciprocal Colleges • Academic Evaluation Governance
                </span>
                <flux:button 
                    type="button" 
                    @click="open = false" 
                    variant="primary" 
                    size="sm"
                    class="w-full sm:w-auto font-bold cursor-pointer justify-center"
                >
                    Acknowledge & Close
                </flux:button>
            </div>
        </div>
    </div>
</div>
