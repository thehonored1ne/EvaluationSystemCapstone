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
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto bg-black/70 backdrop-blur-xs"
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
        <!-- Modal Document Container -->
        <div 
            class="relative w-full max-w-3xl bg-white dark:bg-zinc-900 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-800 flex flex-col max-h-[90vh] overflow-hidden"
            @click.outside="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <!-- Top Header Accent -->
            <div class="h-1 bg-[#9b0000] dark:bg-[#f89696]"></div>

            <!-- Header & Navigation Bar -->
            <div class="p-4 sm:p-6 border-b border-zinc-200 dark:border-zinc-800 flex flex-col gap-4 bg-zinc-50/50 dark:bg-zinc-900/50 shrink-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#9b0000] dark:text-[#f89696]">Institutional Policy</span>
                            <span class="text-zinc-300 dark:text-zinc-700">•</span>
                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">DOC ID: GRC-POL-EVAL-2026</span>
                        </div>
                        <h2 id="terms-modal-title" class="text-base sm:text-xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-1">
                            Institutional Evaluation Governance & Policies
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Global Reciprocal Colleges • Academic Performance Evaluation System
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

                <!-- Clean Document Tabs -->
                <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 -mb-4 sm:-mb-6 pt-1">
                    <button 
                        type="button" 
                        @click="activeTab = 'terms'" 
                        :class="activeTab === 'terms' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 font-medium'"
                        class="pb-3 text-xs sm:text-sm border-b-2 transition-all cursor-pointer px-2 flex items-center gap-1.5"
                    >
                        <flux:icon icon="document-text" class="size-4" />
                        Terms of Service
                    </button>
                    <button 
                        type="button" 
                        @click="activeTab = 'privacy'" 
                        :class="activeTab === 'privacy' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 font-medium'"
                        class="pb-3 text-xs sm:text-sm border-b-2 transition-all cursor-pointer px-2 flex items-center gap-1.5"
                    >
                        <flux:icon icon="shield-check" class="size-4" />
                        Privacy Policy & AI Disclosure
                    </button>
                </div>
            </div>

            <!-- Document Body Content -->
            <div class="p-5 sm:p-8 overflow-y-auto space-y-6 text-xs sm:text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed font-sans">
                
                <!-- TAB 1: TERMS OF SERVICE -->
                <div x-show="activeTab === 'terms'" class="space-y-6">
                    <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
                        <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            Terms of Service
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                            Last Revised: Academic Year 2025–2026 | Effective Date: August 1, 2025
                        </p>
                    </div>

                    <!-- Section 1.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            1.0 Acceptance of Terms
                        </h4>
                        <p>
                            By authenticating into and utilizing the Global Reciprocal Colleges (GRC) Institutional Evaluation System, you ("User", "Evaluator", or "Student") agree to be legally and administratively bound by these Terms of Service, institutional policies, and all applicable academic regulations. If you do not agree to these terms, you must refrain from accessing the evaluation portal and notify the Office of Academic Affairs.
                        </p>
                    </section>

                    <!-- Section 2.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            2.0 User Authentication & Account Security
                        </h4>
                        <p>
                            Access to the evaluation portal requires official institutional credentials assigned by GRC Administration. Users are strictly responsible for maintaining the confidentiality of their login credentials. Any action performed under an authenticated account shall be deemed authorized by the account owner. Sharing credentials or impersonating other evaluators constitutes a severe academic integrity violation.
                        </p>
                    </section>

                    <!-- Section 3.0 -->
                    <section class="space-y-3">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            3.0 Evaluation Integrity & Acceptable Use Policy
                        </h4>
                        <p>
                            The evaluation system exists to facilitate objective assessment of instructional, administrative, and departmental performance. Users covenant to uphold the following standards:
                        </p>
                        <div class="pl-4 border-l-2 border-zinc-200 dark:border-zinc-800 space-y-2">
                            <p>
                                <strong>3.1 Objective & Constructive Standard:</strong> Ratings and qualitative feedback must reflect genuine academic interactions and truthful assessments of pedagogical or operational effectiveness.
                            </p>
                            <p>
                                <strong>3.2 Prohibited Conduct:</strong> Users shall not submit feedback containing defamatory, libelous, profane, sexually explicit, abusive, or discriminatory remarks. The system incorporates automated linguistic filtering and rate-limiting security mechanisms to detect and log malicious activity.
                            </p>
                            <p>
                                <strong>3.3 Non-Retaliation:</strong> Faculty members, administrators, and supervisors are strictly prohibited from attempting to identify individual student respondents or taking retaliatory academic/administrative action against any evaluator.
                            </p>
                        </div>
                    </section>

                    <!-- Section 4.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            4.0 Submission Finality & Computation Immutability
                        </h4>
                        <p>
                            Upon clicking "Submit Evaluation" and completing the confirmation dialogue, evaluation submissions are cryptographically queued and immutably processed. Submissions cannot be re-opened, altered, or retracted by evaluators. Composite scores, weighted averages, and rating categories are calculated in accordance with the institutional criteria established by Academic Council governance.
                        </p>
                    </section>

                    <!-- Section 5.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            5.0 System Availability & Administrative Rights
                        </h4>
                        <p>
                            Global Reciprocal Colleges reserves the right to modify evaluation schedules, adjust category weighting parameters prior to active windows, and restrict access for scheduled maintenance or administrative review without prior individual notice.
                        </p>
                    </section>
                </div>

                <!-- TAB 2: PRIVACY POLICY & AI DISCLOSURE -->
                <div x-show="activeTab === 'privacy'" class="space-y-6">
                    <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
                        <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            Data Privacy Policy & AI Disclosure
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                            Compliance Framework: Republic Act No. 10173 (Data Privacy Act of 2012)
                        </p>
                    </div>

                    <!-- Section 1.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            1.0 Statutory Compliance & Scope
                        </h4>
                        <p>
                            Global Reciprocal Colleges is committed to safeguarding the fundamental right to privacy of all students, faculty members, and administrative staff. This policy governs the collection, processing, anonymization, and storage of evaluation data pursuant to the <strong>Philippine Data Privacy Act of 2012 (RA 10173)</strong> and its Implementing Rules and Regulations (IRR).
                        </p>
                    </section>

                    <!-- Section 2.0 -->
                    <section class="space-y-3">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            2.0 Evaluator Anonymity & Data Decoupling
                        </h4>
                        <p>
                            To ensure honest and uninhibited evaluation, the system enforces structural anonymity:
                        </p>
                        <div class="pl-4 border-l-2 border-zinc-200 dark:border-zinc-800 space-y-2">
                            <p>
                                <strong>2.1 Decoupling of Identity:</strong> While the system tracks completion records to confirm that a student or employee has fulfilled their evaluation requirement, submitted ratings and textual remarks are decoupled from user identification in all faculty and department reports.
                            </p>
                            <p>
                                <strong>2.2 Aggregated Metric Reporting:</strong> Reports displayed to evaluated faculty, Program Heads, and Deans contain only aggregated mean scores, rating distributions, and anonymized qualitative feedback streams.
                            </p>
                        </div>
                    </section>

                    <!-- Section 3.0 -->
                    <section class="space-y-3">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            3.0 Artificial Intelligence (AI) Sentiment Processing
                        </h4>
                        <p>
                            Pursuant to transparency standards in educational technology:
                        </p>
                        <div class="pl-4 border-l-2 border-zinc-200 dark:border-zinc-800 space-y-2">
                            <p>
                                <strong>3.1 Automated Natural Language Analysis:</strong> Qualitative comments are processed by an on-premise Natural Language Processing (NLP) pipeline (RoBERTa architecture) to classify general tone into Positive, Neutral, or Negative sentiment polarity.
                            </p>
                            <p>
                                <strong>3.2 Advisory Purpose:</strong> AI sentiment classifications serve exclusively as aggregate analytical insights to assist academic leaders in identifying curriculum trends and institutional strengths. AI sentiment output is never utilized as a sole deterministic factor in faculty evaluation rankings.
                            </p>
                            <p>
                                <strong>3.3 Profanity & Harm Mitigation:</strong> Automated filters screen comments to prevent abusive language from propagating into official faculty summaries.
                            </p>
                        </div>
                    </section>

                    <!-- Section 4.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            4.0 Data Retention, Security & Governance
                        </h4>
                        <p>
                            Evaluation records are encrypted in transit (TLS/HTTPS) and stored in secured relational databases with strict role-based access control (RBAC). Data is retained for the duration of the institutional accreditation cycle and archived according to Commission on Higher Education (CHED) record-keeping guidelines.
                        </p>
                    </section>

                    <!-- Section 5.0 -->
                    <section class="space-y-2">
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm uppercase tracking-wide">
                            5.0 Data Subject Rights & Inquiries
                        </h4>
                        <p>
                            Under RA 10173, data subjects retain the right to be informed, access, and object to unlawful data processing. Inquiries, concerns, or requests regarding evaluation data handling may be formally directed to the <strong>GRC Data Protection Office</strong> and the <strong>Office of Academic Affairs</strong>.
                        </p>
                    </section>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 sm:p-5 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/80 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium text-center sm:text-left">
                    By using this service, you acknowledge compliance with GRC Academic Governance.
                </div>
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
