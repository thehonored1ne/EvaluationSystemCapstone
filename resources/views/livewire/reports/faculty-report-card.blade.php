<div class="faculty-report-wrapper flex flex-col gap-10 w-full max-w-5xl mx-auto print:gap-0 print:max-w-none print:w-full print:m-0">
    <!-- ================= PAGE 1: SUMMARY SCORECARD (GRC EXACT REPLICA) ================= -->
    <div class="bg-white text-black border border-zinc-400 p-6 sm:p-8 md:p-10 rounded-2xl shadow-xl flex flex-col gap-3.5 print:border-none print:shadow-none print:p-0 print:m-0 print:gap-2.5 print:rounded-none" style="page-break-after: always; break-after: page;">
        
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
                    Summary of Faculty Performance Evaluation on Teaching Effectiveness
                </h2>
            </div>
        </div>

        <!-- Meta Info Grid (School Year, Semester, Faculty Name, Department) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-0.5 text-[11px] font-semibold border-b border-black pb-1.5 print:pb-1">
            <div class="flex items-baseline gap-2">
                <span class="uppercase tracking-wider">School Year:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->academicYear->name }}</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="uppercase tracking-wider">Semester:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->name }}</span>
            </div>
            <div class="flex items-baseline gap-2 col-span-1 md:col-span-2 mt-0.5">
                <span class="uppercase tracking-wider">Name of Faculty Member:</span>
                <span class="font-black text-xs md:text-[13px] uppercase underline">{{ $report->teacher->full_name }}</span>
            </div>
            <div class="flex items-baseline gap-2 col-span-1 md:col-span-2">
                <span class="uppercase tracking-wider">College / Department:</span>
                <span class="font-bold uppercase underline">{{ $report->teacher->department->name ?? 'Academic Faculty' }} ({{ $report->teacher->department->code ?? 'N/A' }})</span>
            </div>
        </div>

        <!-- Intro Notice -->
        <div class="text-[11px] italic font-bold text-zinc-800 -my-0.5">
            The following are the summary of your ratings:
        </div>

        <!-- Evaluation Ratings Section -->
        <div class="flex flex-col gap-2 print:gap-1 text-[11px]">
            
            <!-- 1. STUDENTS EVALUATION -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <span>Students Evaluation ({{ $report->student_pct }}%):</span>
                    <span class="font-mono text-xs underline">{{ number_format($report->student_section->subtotal, 2) }}</span>
                </div>
                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                    @foreach($report->student_section->parts as $part)
                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 2. DEAN'S EVALUATION -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <span>Dean's Evaluation ({{ $report->dean_pct }}%):</span>
                    <span class="font-mono text-xs underline">{{ number_format($report->dean_section->subtotal, 2) }}</span>
                </div>
                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                    @foreach($report->dean_section->parts as $part)
                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 3. PROGRAM HEAD'S EVALUATION -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <span>Program Head's Evaluation ({{ $report->ph_pct }}%):</span>
                    <span class="font-mono text-xs underline">{{ number_format($report->ph_section->subtotal, 2) }}</span>
                </div>
                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                    @foreach($report->ph_section->parts as $part)
                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 4. PEER EVALUATION (360° Inclusion) -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <span>Peer Evaluation ({{ $report->peer_pct }}%):</span>
                    <span class="font-mono text-xs underline">{{ number_format($report->peer_section->subtotal, 2) }}</span>
                </div>
                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                    @foreach($report->peer_section->parts as $part)
                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 5. SELF EVALUATION -->
            <div class="flex flex-col gap-0.5">
                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                    <span>Self Evaluation ({{ $report->self_pct }}%):</span>
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

        <!-- Bottom Section: Legend Table & Overall Rating Box -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1 print:pt-1">
            <!-- Legend Table -->
            <div class="col-span-2 border-2 border-black text-[10px]">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-black bg-zinc-100 font-bold uppercase">
                            <th class="p-0.5 border-r border-black w-6 text-center"></th>
                            <th class="p-0.5 border-r border-black px-1.5">Descriptive Rating</th>
                            <th class="p-0.5 text-center" colspan="2">Weight Equivalent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-black font-medium">
                        <tr class="{{ $report->rating_code === 'E' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">L</td>
                            <td class="p-0.5 border-r border-black px-1.5">Excellent</td>
                            <td class="p-0.5 text-center border-r border-black w-16">194.95</td>
                            <td class="p-0.5 text-center w-16">200.00</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'VS' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">E</td>
                            <td class="p-0.5 border-r border-black px-1.5">Very Satisfactory</td>
                            <td class="p-0.5 text-center border-r border-black">181.05</td>
                            <td class="p-0.5 text-center">194.94</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'S' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">G</td>
                            <td class="p-0.5 border-r border-black px-1.5">Satisfactory</td>
                            <td class="p-0.5 text-center border-r border-black">153.26</td>
                            <td class="p-0.5 text-center">181.04</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'NI' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">E</td>
                            <td class="p-0.5 border-r border-black px-1.5">Need Improvement</td>
                            <td class="p-0.5 text-center border-r border-black">139.35</td>
                            <td class="p-0.5 text-center">153.25</td>
                        </tr>
                        <tr class="{{ $report->rating_code === 'P' ? 'bg-zinc-200 font-bold' : '' }}">
                            <td class="p-0.5 text-center border-r border-black font-bold">N/D</td>
                            <td class="p-0.5 border-r border-black px-1.5">Poor</td>
                            <td class="p-0.5 text-center border-r border-black">1.00</td>
                            <td class="p-0.5 text-center">139.34</td>
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
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Prepared by:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">Evaluation Coordinator</span>
                <span class="text-[9px] text-zinc-600">HR / Academic Affairs</span>
            </div>
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Noted by:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->program_head_name }}</span>
                <span class="text-[9px] text-zinc-600">Program Head</span>
            </div>
            <div class="flex flex-col items-center text-center">
                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Approved by:</span>
                <div class="w-full border-b border-black"></div>
                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->dean_name }}</span>
                <span class="text-[9px] text-zinc-600">College Dean</span>
            </div>
        </div>
    </div>

    <!-- ================= PAGE 2: AI STUDENT COMMENTS ANALYSIS ================= -->
    <div class="bg-white text-black border border-zinc-400 p-8 md:p-12 rounded-2xl shadow-xl flex flex-col gap-6 print:border-none print:shadow-none print:p-0 print:m-0 print:rounded-none" style="page-break-after: always; break-after: page;">
        
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
                    Student's Comments & AI Qualitative Analysis
                </h2>
            </div>
        </div>

        <!-- Meta Info Line -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-xs font-semibold border-b border-black pb-4">
            <div class="flex items-baseline gap-2">
                <span class="uppercase tracking-wider">School Year:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->academicYear->name }}</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="uppercase tracking-wider">Semester:</span>
                <span class="font-bold underline uppercase">{{ $report->semester->name }}</span>
            </div>
            <div class="flex items-baseline gap-2 col-span-1 md:col-span-2 mt-1">
                <span class="uppercase tracking-wider">Name of Faculty Member:</span>
                <span class="font-black text-sm uppercase underline">{{ $report->teacher->full_name }}</span>
            </div>
        </div>

        <!-- 1. AI Sentiment Gauge & Distribution Card -->
        <div class="border-2 border-black p-5 rounded-xl bg-zinc-50 flex flex-col gap-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <span class="text-xs font-black uppercase tracking-wider">AI Evaluator Sentiment Distribution</span>
                <span class="px-2.5 py-0.5 text-xs font-bold border border-black bg-white">
                    {{ $report->ai_sentiment->dominant_label }} ({{ $report->ai_sentiment->total_comments }} Total Comments)
                </span>
            </div>
            
            <div class="flex items-center gap-4 text-xs font-bold font-mono">
                <span>Positive: {{ $report->ai_sentiment->pos_percent }}%</span>
                <span>Neutral: {{ $report->ai_sentiment->neu_percent }}%</span>
                <span>Constructive: {{ $report->ai_sentiment->neg_percent }}%</span>
            </div>

            <div class="w-full h-3 bg-zinc-200 border border-black rounded-full overflow-hidden flex">
                <div class="bg-black h-full" style="width: {{ $report->ai_sentiment->pos_percent }}%" title="Positive: {{ $report->ai_sentiment->pos_percent }}%"></div>
                <div class="bg-zinc-500 h-full" style="width: {{ $report->ai_sentiment->neu_percent }}%" title="Neutral: {{ $report->ai_sentiment->neu_percent }}%"></div>
                <div class="bg-zinc-300 h-full" style="width: {{ $report->ai_sentiment->neg_percent }}%" title="Constructive: {{ $report->ai_sentiment->neg_percent }}%"></div>
            </div>
        </div>

        <!-- 2. Top Commendations & Opportunities (Two-Column Thematic Breakdown) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Positive Themes -->
            <div class="border border-black p-4 rounded-xl flex flex-col gap-2 bg-white">
                <div class="flex items-center gap-1.5 text-xs font-black uppercase text-black border-b border-zinc-200 pb-1.5">
                    <flux:icon icon="hand-thumb-up" class="size-4" />
                    <span>Top Student Commendations</span>
                </div>
                <ul class="text-xs flex flex-col gap-1.5 mt-1 list-disc pl-4">
                    @foreach($report->ai_sentiment->positive_drivers as $theme)
                        <li class="font-medium text-zinc-800">{{ $theme }}</li>
                    @endforeach
                </ul>
            </div>

            <!-- Constructive Themes -->
            <div class="border border-black p-4 rounded-xl flex flex-col gap-2 bg-white">
                <div class="flex items-center gap-1.5 text-xs font-black uppercase text-black border-b border-zinc-200 pb-1.5">
                    <flux:icon icon="light-bulb" class="size-4" />
                    <span>Key Opportunities for Growth</span>
                </div>
                <ul class="text-xs flex flex-col gap-1.5 mt-1 list-disc pl-4">
                    @foreach($report->ai_sentiment->constructive_themes as $theme)
                        <li class="font-medium text-zinc-800">{{ $theme }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

    </div>
</div>
