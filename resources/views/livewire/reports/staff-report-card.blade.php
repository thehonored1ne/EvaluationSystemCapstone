<div class="staff-report-wrapper flex flex-col gap-10 w-full max-w-5xl mx-auto print:gap-0 print:max-w-none print:w-full print:m-0">
    <!-- ================= PAGE 1: SUMMARY SCORECARD (GRC ADMINISTRATIVE INSTRUMENT) ================= -->
    <div class="bg-white text-black border border-zinc-400 p-3.5 sm:p-6 md:p-10 rounded-2xl shadow-xl flex flex-col gap-3.5 print:border-none print:shadow-none print:p-0 print:m-0 print:gap-2.5 print:rounded-none" style="page-break-after: always; break-after: page;">
        
        <!-- Top Header: Logo + Institutional Header + Boxed Title -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-black pb-2 print:pb-1.5">
            <div class="flex items-center gap-3">
                <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Global Reciprocal Colleges Logo" class="h-12 sm:h-14 md:h-16 w-auto object-contain" />
                <div class="flex flex-col">
                    <p class="text-[10.5px] text-zinc-700 leading-tight">454 GRC Bldg. Rizal Ave. Ext. 9th Avenue</p>
                    <p class="text-[10.5px] text-zinc-700 leading-tight">Grace Park, Caloocan City</p>
                </div>
            </div>

            <div class="border-2 border-black px-3 py-1 text-center max-w-md">
                <h2 class="text-xs md:text-[13px] font-black uppercase tracking-wider leading-snug">
                    Summary of Staff Performance Appraisal
                </h2>
                <p class="text-[9.5px] font-bold text-zinc-700 uppercase tracking-widest">Non-Teaching & Administrative Personnel</p>
            </div>
        </div>

        <!-- Meta Info Grid (School Year, Semester, Staff Name, Department, Position) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-[10px] sm:text-[11px] font-semibold border-b border-black pb-2 print:pb-1">
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">School Year:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->academicYear->name }}</span>
            </div>
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">Semester / Period:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->name }}</span>
            </div>
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2 col-span-1 md:col-span-2 mt-0.5">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">Name of Staff Member:</span>
                <span class="font-black text-[11px] sm:text-xs md:text-[13px] uppercase underline break-words">{{ $report->staff->full_name }}</span>
                @if($report->staff->employee_number)
                    <span class="text-zinc-600 font-mono text-[10px]">({{ $report->staff->employee_number }})</span>
                @endif
            </div>
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2 col-span-1 md:col-span-2">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">Administrative Department / Unit:</span>
                <span class="font-bold uppercase underline break-words">{{ $report->staff->department->name ?? 'Administrative Staff' }} ({{ $report->staff->department->code ?? 'N/A' }})</span>
            </div>
        </div>

        <!-- Intro Notice -->
        <div class="text-[11px] italic font-bold text-zinc-800 -my-0.5 flex items-center justify-between">
            <span>The following are the summary of your performance appraisal ratings:</span>
            <span class="text-[10px] not-italic font-mono text-zinc-600">Total Evaluations: {{ $report->total_submissions }}</span>
        </div>

        <!-- Evaluation Ratings Section -->
        <div class="flex flex-col gap-2 print:gap-1 text-[11px]">
            
            <!-- 1. DEPARTMENT HEAD EVALUATION (50%) -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <div class="flex items-center gap-1.5">
                        <span>1. Department Head's Evaluation ({{ $report->dept_head_section->pct }}%):</span>
                        <span class="text-[9px] font-normal text-zinc-500 normal-case">({{ $report->dept_head_section->count }} evaluation{{ $report->dept_head_section->count === 1 ? '' : 's' }})</span>
                    </div>
                    <span class="font-mono text-xs underline">{{ number_format($report->dept_head_section->subtotal, 2) }}</span>
                </div>
                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                    @foreach($report->dept_head_section->parts as $part)
                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 2. PEER STAFF EVALUATION (30%) -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <div class="flex items-center gap-1.5">
                        <span>2. Peer Staff Evaluation ({{ $report->peer_section->pct }}%):</span>
                        @if($report->is_peer_exempted)
                            <span class="text-[9px] font-bold text-amber-700 dark:text-amber-800 normal-case bg-amber-100 px-1 rounded border border-amber-300 print:border-black print:bg-zinc-100 print:text-black">
                                [Exempted / Reallocated to Head & Self]
                            </span>
                        @else
                            <span class="text-[9px] font-normal text-zinc-500 normal-case">({{ $report->peer_section->count }} peer{{ $report->peer_section->count === 1 ? '' : 's' }})</span>
                        @endif
                    </div>
                    <span class="font-mono text-xs underline">
                        @if($report->is_peer_exempted)
                            N/A
                        @else
                            {{ number_format($report->peer_section->subtotal, 2) }}
                        @endif
                    </span>
                </div>
                @if($report->is_peer_exempted)
                    <div class="pl-3 py-1 text-[10px] text-zinc-600 italic border-l-2 border-amber-400 pl-2">
                        Peer appraisal exempted due to solitary office staffing or unable to observe status. The 30% weight was dynamically redistributed proportionally across Department Head and Self-Appraisal.
                    </div>
                @else
                    <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                        @foreach($report->peer_section->parts as $part)
                            <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                                <span>{{ $part->roman }}. {{ $part->name }}</span>
                                <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- 3. SELF-APPRAISAL (20%) -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <div class="flex items-center gap-1.5">
                        <span>3. Self-Appraisal ({{ $report->self_section->pct }}%):</span>
                        <span class="text-[9px] font-normal text-zinc-500 normal-case">({{ $report->self_section->count > 0 ? 'Completed' : 'Pending' }})</span>
                    </div>
                    <span class="font-mono text-xs underline">{{ number_format($report->self_section->subtotal, 2) }}</span>
                </div>
                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                    @foreach($report->self_section->parts as $part)
                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Performance Legend Table & Overall Rating Box -->
        <div class="grid grid-cols-3 gap-3 border-t border-b border-black py-2 my-0 text-[11px]">
            <!-- Legend Table (Col span 2) -->
            <div class="col-span-2">
                <span class="text-[10px] font-black uppercase tracking-wider mb-0.5 block">Performance Rating Scale:</span>
                <table class="w-full border-collapse border border-black text-[9.5px]">
                    <thead>
                        <tr class="bg-zinc-100 border-b border-black font-bold">
                            <th class="p-0.5 text-center border-r border-black w-8">Code</th>
                            <th class="p-0.5 border-r border-black px-1.5">Descriptive Rating</th>
                            <th class="p-0.5 text-center" colspan="2">Score Range (100% Base)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-black font-medium">
                        <tr class="{{ $report->rating_code === 'E' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">E</td>
                            <td class="p-0.5 border-r border-black px-1.5">Excellent</td>
                            <td class="p-0.5 text-center border-r border-black w-16">95.00</td>
                            <td class="p-0.5 text-center w-16">100.00</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'VS' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">VS</td>
                            <td class="p-0.5 border-r border-black px-1.5">Very Satisfactory</td>
                            <td class="p-0.5 text-center border-r border-black">85.00</td>
                            <td class="p-0.5 text-center">94.99</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'S' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">S</td>
                            <td class="p-0.5 border-r border-black px-1.5">Satisfactory</td>
                            <td class="p-0.5 text-center border-r border-black">75.00</td>
                            <td class="p-0.5 text-center">84.99</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'NI' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">NI</td>
                            <td class="p-0.5 border-r border-black px-1.5">Need Improvement</td>
                            <td class="p-0.5 text-center border-r border-black">65.00</td>
                            <td class="p-0.5 text-center">74.99</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'P' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">P</td>
                            <td class="p-0.5 border-r border-black px-1.5">Poor</td>
                            <td class="p-0.5 text-center border-r border-black">0.00</td>
                            <td class="p-0.5 text-center">64.99</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Overall Rating Box -->
            <div class="col-span-1 border-2 border-black p-2 flex flex-col justify-center items-center text-center bg-zinc-50">
                <span class="text-[10px] font-black uppercase tracking-wider mb-0.5">Overall Rating</span>
                <div class="text-2xl font-black font-mono tracking-tight underline">{{ number_format($report->total_achieved_points, 2) }}</div>
                <div class="text-[10px] font-black uppercase mt-1 px-1.5 py-0 border border-black bg-white">
                    {{ $report->descriptive_rating }}
                </div>
            </div>
        </div>

        <!-- Signatories Section -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-3 print:grid-cols-3 print:pt-3 text-[11px]">
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Conforme / Acknowledged by:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->staff->full_name }}</span>
                <span class="text-[9px] text-zinc-600">Employee Signature over Printed Name</span>
            </div>
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Evaluated / Noted by:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->department_head_name }}</span>
                <span class="text-[9px] text-zinc-600">Administrative Department Head</span>
            </div>
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Approved by:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->hr_director_name }}</span>
                <span class="text-[9px] text-zinc-600">HR Director / VP for Administration</span>
            </div>
        </div>
    </div>

    <!-- ================= PAGE 2: QUALITATIVE FEEDBACK & ADMINISTRATIVE ACTIONS ================= -->
    <div class="bg-white text-black border border-zinc-400 p-3.5 sm:p-8 md:p-12 rounded-2xl shadow-xl flex flex-col gap-6 print:border-none print:shadow-none print:p-0 print:m-0 print:rounded-none" style="page-break-after: always; break-after: page;">
        
        <!-- Header: Logo + Institutional Header + Boxed Title -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-black pb-4">
            <div class="flex items-center gap-3.5">
                <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Global Reciprocal Colleges Logo" class="h-14 md:h-18 w-auto object-contain" />
                <div class="flex flex-col">
                    <h1 class="text-sm font-black tracking-tight uppercase leading-tight">Global Reciprocal Colleges</h1>
                    <p class="text-[10px] text-zinc-700 leading-tight">454 GRC Bldg. Rizal Ave. Ext. 9th Avenue, Grace Park, Caloocan City</p>
                </div>
            </div>

            <div class="border-2 border-black px-4 py-2 text-center max-w-md">
                <h2 class="text-xs md:text-sm font-black uppercase tracking-wider leading-snug">
                    Qualitative Feedback & Administrative Actions
                </h2>
                <p class="text-[9.5px] font-bold text-zinc-700 uppercase tracking-widest">HR Committee Record Sheet</p>
            </div>
        </div>

        <!-- Meta Info Line -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-[10px] sm:text-xs font-semibold border-b border-black pb-2.5 print:pb-2">
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">School Year:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->academicYear->name }}</span>
            </div>
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">Semester / Period:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->name }}</span>
            </div>
            <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2 col-span-1 md:col-span-2 mt-0.5">
                <span class="uppercase tracking-normal sm:tracking-wider shrink-0 text-zinc-700">Staff Member:</span>
                <span class="font-black text-xs sm:text-sm uppercase underline break-words">{{ $report->staff->full_name }}</span>
                <span class="text-zinc-600 font-normal">({{ $report->staff->department->name ?? 'Administrative Unit' }})</span>
            </div>
        </div>

        <!-- Section A: Qualitative Synthesis & Feedback Breakdown -->
        <div class="flex flex-col gap-4">
            <div class="flex justify-between items-baseline border-b border-black pb-1">
                <span class="font-black text-xs uppercase tracking-wider">I. Qualitative Synthesis & Feedback Analysis</span>
                <span class="text-[11px] font-bold">Dominant Tone: <span class="underline">{{ $report->ai_sentiment->dominant_label }}</span></span>
            </div>

            <!-- Sentiment Distribution Metric -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 text-center">
                <div class="border border-black p-2 rounded">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase text-zinc-600 block">Favorable / Commendations</span>
                    <span class="font-mono text-sm sm:text-base font-black">{{ $report->ai_sentiment->pos_count }} comments</span>
                </div>
                <div class="border border-black p-2 rounded">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase text-zinc-600 block">Neutral / General Notes</span>
                    <span class="font-mono text-sm sm:text-base font-black">{{ $report->ai_sentiment->neu_count }} comments</span>
                </div>
                <div class="border border-black p-2 rounded">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase text-zinc-600 block">Developmental Opportunities</span>
                    <span class="font-mono text-sm sm:text-base font-black">{{ $report->ai_sentiment->neg_count }} comments</span>
                </div>
            </div>

            <!-- Strengths / Positive Drivers -->
            <div class="flex flex-col gap-1.5 mt-2">
                <div class="font-bold text-[11.5px] uppercase tracking-wide flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block print:border print:border-black"></span>
                    <span>Demonstrated Strengths & Commendable Workplace Behaviors:</span>
                </div>
                <ul class="text-xs flex flex-col gap-1.5 mt-1 list-disc pl-4">
                    @foreach($report->ai_sentiment->positive_drivers as $driver)
                        <li class="font-medium text-zinc-800">{{ $driver }}</li>
                    @endforeach
                </ul>
            </div>

            <!-- Constructive Themes -->
            <div class="flex flex-col gap-1.5 mt-2">
                <div class="font-bold text-[11.5px] uppercase tracking-wide flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block print:border print:border-black"></span>
                    <span>Recommended Areas for Training & Capability Enhancement:</span>
                </div>
                <ul class="text-xs flex flex-col gap-1.5 mt-1 list-disc pl-4">
                    @foreach($report->ai_sentiment->constructive_themes as $theme)
                        <li class="font-medium text-zinc-800">{{ $theme }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Section B: Performance Trend & HR Recommendations -->
        <div class="border-t border-black pt-4 flex flex-col gap-4 text-xs text-black">
            <!-- Performance of employee -->
            <div class="flex flex-col gap-1.5">
                <span class="font-bold text-[11.5px] uppercase tracking-wider">II. Performance Trend:</span>
                <div class="flex flex-col gap-1.5 pl-6 sm:pl-10">
                    <div class="flex items-center gap-3">
                        <span class="w-14 sm:w-16 border-b border-black inline-block text-center font-black font-mono text-xs leading-none pb-0.5">
                            {{ $report->performance_trend === 'improving' ? '✓' : '' }}
                        </span>
                        <span class="font-semibold {{ $report->performance_trend === 'improving' ? 'font-black' : '' }}">Improving</span>
                        @if($report->performance_trend === 'improving' && $report->score_growth !== null)
                            <span class="text-[10.5px] font-mono text-zinc-600 print:text-zinc-600">(+{{ number_format($report->score_growth, 2) }} pts vs. prior term)</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-14 sm:w-16 border-b border-black inline-block text-center font-black font-mono text-xs leading-none pb-0.5">
                            {{ $report->performance_trend === 'stationary' ? '✓' : '' }}
                        </span>
                        <span class="font-semibold {{ $report->performance_trend === 'stationary' ? 'font-black' : '' }}">Stationary</span>
                        @if($report->performance_trend === 'stationary' && $report->score_growth !== null)
                            <span class="text-[10.5px] font-mono text-zinc-600 print:text-zinc-600">({{ $report->score_growth >= 0 ? '+' : '' }}{{ number_format($report->score_growth, 2) }} pts vs. prior term)</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-14 sm:w-16 border-b border-black inline-block text-center font-black font-mono text-xs leading-none pb-0.5">
                            {{ $report->performance_trend === 'deteriorating' ? '✓' : '' }}
                        </span>
                        <span class="font-semibold {{ $report->performance_trend === 'deteriorating' ? 'font-black' : '' }}">Deteriorating</span>
                        @if($report->performance_trend === 'deteriorating' && $report->score_growth !== null)
                            <span class="text-[10.5px] font-mono text-zinc-600 print:text-zinc-600">({{ number_format($report->score_growth, 2) }} pts vs. prior term)</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Recommendations for employee -->
            <div class="flex flex-col gap-2 pt-1">
                <span class="font-bold text-[11.5px] uppercase tracking-wider">III. HR Administrative Recommendations for Employee:</span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2.5 pl-6 sm:pl-10 text-[11.5px] font-medium">
                    <!-- Left Column -->
                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>Extension of Probationary period</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>For Regularization (Permanent Status)</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>Retention in present position</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>Transfer / Reassignment to another department</span>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>Salary Step Increment / Merit (%)</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>Promotion to position</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-14 sm:w-16 border-b border-black inline-block shrink-0"></span>
                            <span>Separation from service</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HR Sign-off -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 pt-6 print:grid-cols-2 print:pt-4 text-[11px] border-t border-black">
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Evaluated by HR Officer:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">Evaluation & Appraisal Officer</span>
                <span class="text-[9px] text-zinc-600">Human Resources Development Office</span>
            </div>
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Approved by Administration:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->hr_director_name }}</span>
                <span class="text-[9px] text-zinc-600">Vice President for Administration / HR Director</span>
            </div>
        </div>

    </div>
</div>
