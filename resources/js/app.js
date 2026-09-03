async function loadChartJs() {
    if (typeof window.Chart === 'undefined') {
        const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } = await import('chart.js');
        Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);
        window.Chart = Chart;
    }
    return window.Chart;
}

// Global chart helper for Admin Dashboard (dynamically lazy-loads Chart.js on demand)
window.dashboardAnalyticsCharts = function(config) {
    return {
        roleTurnoutInstance: null,
        deptInstance: null,
        activeDeptType: 'academic', // 'academic' | 'administrative'
        currentSemName: config?.currentSemName || 'Current Term',
        prevSemName: config?.prevSemName || 'Prior Term',
        hasPrevComparison: config?.hasPrevComparison || false,
        compareRole: false,
        compareDept: false,
        roleLabels: config?.roleLabels || [],
        roleRates: config?.roleRates || [],
        prevRoleRates: config?.prevRoleRates || [],
        roleDetails: config?.roleDetails || [],
        academicDeptLabels: config?.academicDeptLabels || [],
        academicDeptNames: config?.academicDeptNames || [],
        academicDeptRates: config?.academicDeptRates || config?.academicDeptAverages || [],
        prevAcademicDeptRates: config?.prevAcademicDeptRates || [],
        academicDeptSubmitted: config?.academicDeptSubmitted || config?.academicDeptCounts || [],
        academicDeptExpected: config?.academicDeptExpected || [],
        adminDeptLabels: config?.adminDeptLabels || [],
        adminDeptNames: config?.adminDeptNames || [],
        adminDeptRates: config?.adminDeptRates || config?.adminDeptAverages || [],
        prevAdminDeptRates: config?.prevAdminDeptRates || [],
        adminDeptSubmitted: config?.adminDeptSubmitted || config?.adminDeptCounts || [],
        adminDeptExpected: config?.adminDeptExpected || [],

        isDarkMode() {
            return document.documentElement.classList.contains('dark');
        },

        toggleComparison(type) {
            if (type === 'role') {
                this.compareRole = !this.compareRole;
                this.$nextTick(() => this.renderRoleTurnout());
            } else if (type === 'dept') {
                this.compareDept = !this.compareDept;
                this.$nextTick(() => this.renderDept());
            }
        },

        async init() {
            await loadChartJs();

            this.$nextTick(() => {
                this.renderAll();
            });

            // Re-render instantly whenever dark/light class is toggled on <html>
            if (window.MutationObserver) {
                const observer = new MutationObserver(() => {
                    this.renderAll();
                });
                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            // Also listen to Flux appearance change events
            window.addEventListener('flux:appearance:changed', () => {
                setTimeout(() => this.renderAll(), 50);
            });
        },

        switchDeptType(type) {
            this.activeDeptType = type;
            this.$nextTick(() => {
                this.renderDept();
            });
        },

        async renderAll() {
            await loadChartJs();
            this.renderRoleTurnout();
            this.renderDept();
        },

        renderRoleTurnout() {
            if (!this.$refs.roleTurnoutChart || typeof Chart === 'undefined' || !this.roleLabels || this.roleLabels.length === 0) return;
            if (this.roleTurnoutInstance) {
                this.roleTurnoutInstance.destroy();
                this.roleTurnoutInstance = null;
            }

            const isDark = this.isDarkMode();
            const textColor = isDark ? '#f4f4f5' : '#18181b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.10)' : 'rgba(0, 0, 0, 0.06)';
            const trackColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.04)';

            // Dynamic color coding: Green >= 80%, Amber 50-79%, Red < 50%
            const backgroundColors = this.roleRates.map(rate => {
                if (rate >= 80) return isDark ? '#22c55e' : '#16a34a';
                if (rate >= 50) return isDark ? '#f59e0b' : '#d97706';
                return isDark ? '#ef4444' : '#dc2626';
            });

            const hoverColors = this.roleRates.map(rate => {
                if (rate >= 80) return isDark ? '#4ade80' : '#15803d';
                if (rate >= 50) return isDark ? '#fbbf24' : '#b45309';
                return isDark ? '#f87171' : '#b91c1c';
            });

            const chartLabels = this.roleLabels.map(label => {
                if (label === 'Department Heads') return 'Dept. Heads';
                if (label === 'Program Heads') return 'Prog. Heads';
                return label;
            });

            const isComparing = this.compareRole && this.prevRoleRates && this.prevRoleRates.length > 0;
            let datasets;

            if (isComparing) {
                datasets = [
                    {
                        label: this.currentSemName,
                        data: this.roleRates,
                        backgroundColor: backgroundColors,
                        hoverBackgroundColor: hoverColors,
                        borderRadius: 4,
                        borderSkipped: false,
                        barThickness: 11,
                        grouped: true,
                        order: 1,
                    },
                    {
                        label: this.prevSemName,
                        data: this.prevRoleRates,
                        backgroundColor: isDark ? 'rgba(161, 161, 170, 0.45)' : 'rgba(113, 113, 122, 0.45)',
                        hoverBackgroundColor: isDark ? 'rgba(161, 161, 170, 0.75)' : 'rgba(113, 113, 122, 0.75)',
                        borderRadius: 4,
                        borderSkipped: false,
                        barThickness: 11,
                        grouped: true,
                        order: 2,
                    }
                ];
            } else {
                datasets = [
                    {
                        label: 'Track',
                        data: this.roleRates.map(() => 100),
                        backgroundColor: trackColor,
                        hoverBackgroundColor: trackColor,
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: 20,
                        grouped: false,
                        order: 2,
                    },
                    {
                        label: 'Completion Rate',
                        data: this.roleRates,
                        backgroundColor: backgroundColors,
                        hoverBackgroundColor: hoverColors,
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: 20,
                        grouped: false,
                        order: 1,
                    }
                ];
            }

            this.roleTurnoutInstance = new Chart(this.$refs.roleTurnoutChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: datasets
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 16,
                            right: 28,
                        }
                    },
                    plugins: {
                        legend: {
                            display: isComparing,
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                usePointStyle: true,
                                pointStyle: 'rectRounded',
                                color: textColor,
                                font: { weight: '600', size: 10, family: 'Lexend, sans-serif' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    if (!isComparing && ctx.datasetIndex === 0) return null;
                                    const index = ctx.dataIndex;
                                    const rate = Number(ctx.raw).toFixed(1);
                                    if (isComparing) {
                                        const curRate = this.roleRates[index];
                                        const pRate = this.prevRoleRates[index];
                                        const delta = (curRate !== undefined && pRate !== undefined) ? Number(curRate - pRate).toFixed(1) : null;
                                        const deltaStr = delta !== null ? ` (Delta: ${delta >= 0 ? '+' : ''}${delta}%)` : '';
                                        return `${ctx.dataset.label}: ${rate}%${ctx.datasetIndex === 0 ? deltaStr : ''}`;
                                    }
                                    const detail = this.roleDetails && this.roleDetails[index];
                                    if (detail) {
                                        const sub = Number(detail.submitted).toLocaleString();
                                        const exp = Number(detail.expected).toLocaleString();
                                        const pend = Math.max(0, Number(detail.expected) - Number(detail.submitted)).toLocaleString();
                                        return `${rate}% Finished (${sub}/${exp}) • ${pend} pending`;
                                    }
                                    return `${rate}% Finished`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 100,
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                callback: (v) => v + '%'
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                callback: function(value) {
                                    const raw = this.getLabelForValue(value);
                                    if (raw === 'Department Heads') return 'Dept. Heads';
                                    if (raw === 'Program Heads') return 'Prog. Heads';
                                    return raw;
                                }
                            },
                            title: {
                                display: false
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'roleTurnoutLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx, chartArea: { top, bottom, left, right }, scales: { x } } = chart;

                        if (x) {
                            const targetX = x.getPixelForValue(80);
                            if (targetX >= left && targetX <= right) {
                                ctx.save();
                                ctx.strokeStyle = isDark ? '#f87171' : '#dc2626';
                                ctx.lineWidth = 1.5;
                                ctx.setLineDash([5, 4]);
                                ctx.beginPath();
                                ctx.moveTo(targetX, top);
                                ctx.lineTo(targetX, bottom);
                                ctx.stroke();

                                ctx.setLineDash([]);
                                ctx.fillStyle = isDark ? '#f87171' : '#dc2626';
                                ctx.font = 'bold 10px Lexend, sans-serif';
                                ctx.textAlign = 'center';
                                ctx.fillText('80% Target', targetX, top - 6);
                                ctx.restore();
                            }
                        }

                        // 2. Draw percentage text on bars (only when not in comparison mode)
                        if (!isComparing) {
                            const meta = chart.getDatasetMeta(1);
                            if (meta && meta.data) {
                                ctx.save();
                                ctx.font = 'bold 11px Lexend, sans-serif';
                                ctx.textBaseline = 'middle';

                                meta.data.forEach((bar, index) => {
                                    const val = chart.data.datasets[1]?.data[index];
                                    if (val !== undefined && val !== null) {
                                        const pctText = `${Number(val).toFixed(1)}%`;
                                        const barWidth = bar.width;

                                        if (val >= 50 || barWidth > 55) {
                                            ctx.fillStyle = '#ffffff';
                                            ctx.textAlign = 'right';
                                            ctx.fillText(pctText, bar.x - 8, bar.y);
                                        } else {
                                            ctx.fillStyle = textColor;
                                            ctx.textAlign = 'left';
                                            ctx.fillText(pctText, bar.x + 6, bar.y);
                                        }
                                    }
                                });
                                ctx.restore();
                            }
                        }
                    }
                }]
            });
        },

        renderDept() {
            if (!this.$refs.deptChart || typeof Chart === 'undefined') return;
            if (this.deptInstance) {
                this.deptInstance.destroy();
                this.deptInstance = null;
            }

            const isAcademic = this.activeDeptType === 'academic';
            const labels = isAcademic ? this.academicDeptLabels : this.adminDeptLabels;
            const names = isAcademic ? this.academicDeptNames : this.adminDeptNames;
            const rates = isAcademic ? this.academicDeptRates : this.adminDeptRates;
            const prevRates = isAcademic ? this.prevAcademicDeptRates : this.prevAdminDeptRates;
            const submitted = isAcademic ? this.academicDeptSubmitted : this.adminDeptSubmitted;
            const expected = isAcademic ? this.academicDeptExpected : this.adminDeptExpected;

            if (!labels || labels.length === 0) return;

            const isDark = this.isDarkMode();
            const textColor = isDark ? '#f4f4f5' : '#18181b';
            const mutedColor = isDark ? '#a1a1aa' : '#71717a';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
            const isComparing = this.compareDept && prevRates && prevRates.length > 0;

            // Threshold color coding:
            // >= 80%: Emerald green (High Turnout)
            // 50% - 79.9%: Amber (Moderate)
            // < 50%: Crimson/Rose (Needs Follow-up)
            const backgroundColors = rates.map(rate => {
                if (rate >= 80) return isDark ? '#22c55e' : '#16a34a';
                if (rate >= 50) return isDark ? '#f59e0b' : '#d97706';
                return isDark ? '#ef4444' : '#dc2626';
            });

            const hoverColors = rates.map(rate => {
                if (rate >= 80) return isDark ? '#4ade80' : '#15803d';
                if (rate >= 50) return isDark ? '#fbbf24' : '#b45309';
                return isDark ? '#f87171' : '#b91c1c';
            });

            let datasets;
            if (isComparing) {
                datasets = [
                    {
                        label: this.currentSemName,
                        data: rates,
                        backgroundColor: backgroundColors,
                        hoverBackgroundColor: hoverColors,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: isAcademic ? 32 : 18,
                        categoryPercentage: isAcademic ? 0.75 : 0.88,
                        barPercentage: 0.9,
                    },
                    {
                        label: this.prevSemName,
                        data: prevRates,
                        backgroundColor: isDark ? 'rgba(161, 161, 170, 0.45)' : 'rgba(113, 113, 122, 0.45)',
                        hoverBackgroundColor: isDark ? 'rgba(161, 161, 170, 0.75)' : 'rgba(113, 113, 122, 0.75)',
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: isAcademic ? 32 : 18,
                        categoryPercentage: isAcademic ? 0.75 : 0.88,
                        barPercentage: 0.9,
                    }
                ];
            } else {
                datasets = [{
                    label: 'Turnout',
                    data: rates,
                    backgroundColor: backgroundColors,
                    hoverBackgroundColor: hoverColors,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: isAcademic ? 56 : 38,
                    categoryPercentage: isAcademic ? 0.75 : 0.88,
                    barPercentage: isAcademic ? 0.85 : 0.92,
                }];
            }

            this.deptInstance = new Chart(this.$refs.deptChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 16,
                            bottom: 4,
                        }
                    },
                    plugins: {
                        legend: {
                            display: isComparing,
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                usePointStyle: true,
                                pointStyle: 'rectRounded',
                                color: textColor,
                                font: { weight: '600', size: 10, family: 'Lexend, sans-serif' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(ctxArr) {
                                    if (!ctxArr.length) return '';
                                    const index = ctxArr[0].dataIndex;
                                    return names[index] || labels[index];
                                },
                                label: (ctx) => {
                                    const index = ctx.dataIndex;
                                    const rate = Number(ctx.raw).toFixed(1);
                                    if (isComparing) {
                                        const curRate = rates[index];
                                        const pRate = prevRates[index];
                                        const delta = (curRate !== undefined && pRate !== undefined) ? Number(curRate - pRate).toFixed(1) : null;
                                        const deltaStr = delta !== null ? ` (Delta: ${delta >= 0 ? '+' : ''}${delta}%)` : '';
                                        return `${ctx.dataset.label}: ${rate}%${ctx.datasetIndex === 0 ? deltaStr : ''}`;
                                    }
                                    const sub = submitted[index] || 0;
                                    const exp = expected[index] || 0;
                                    const pending = Math.max(0, exp - sub);
                                    const unit = isAcademic ? 'students' : 'employees';
                                    return `${rate}% Completed (${Number(sub).toLocaleString()} of ${Number(exp).toLocaleString()} ${unit}) • ${Number(pending).toLocaleString()} pending`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: isAcademic ? 12 : 10, family: 'Lexend, sans-serif' },
                                maxRotation: isAcademic ? 0 : 45,
                                minRotation: 0,
                            }
                        },
                        y: {
                            min: 0,
                            max: 100,
                            grid: { color: gridColor },
                            ticks: {
                                stepSize: 25,
                                color: mutedColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                callback: (v) => v + '%'
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'deptBenchmarkAndLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx, chartArea: { top, bottom, left, right }, scales: { y } } = chart;
                        if (!y) return;

                        // 1. Draw 80% Target Benchmark horizontal dashed line
                        const targetY = y.getPixelForValue(80);
                        if (targetY >= top && targetY <= bottom) {
                            ctx.save();
                            ctx.strokeStyle = isDark ? '#f87171' : '#dc2626';
                            ctx.lineWidth = 1.5;
                            ctx.setLineDash([5, 4]);
                            ctx.beginPath();
                            ctx.moveTo(left, targetY);
                            ctx.lineTo(right, targetY);
                            ctx.stroke();

                            // Benchmark badge text above line
                            ctx.setLineDash([]);
                            ctx.fillStyle = isDark ? '#f87171' : '#dc2626';
                            ctx.font = 'bold 10px Lexend, sans-serif';
                            ctx.textAlign = 'right';
                            ctx.fillText('80% Target', right, targetY - 6);
                            ctx.restore();
                        }

                        // 2. Draw percentage text inside bar near top (only when not in comparison mode)
                        if (!isComparing) {
                            ctx.save();
                            ctx.textAlign = 'center';

                            chart.getDatasetMeta(0).data.forEach((bar, index) => {
                                const rate = rates[index];
                                if (rate !== undefined && rate !== null) {
                                    const barW = bar.width || 30;
                                    const fontSize = isAcademic ? 11.5 : (barW < 32 ? 9.5 : 10.5);
                                    ctx.font = `bold ${fontSize}px Lexend, sans-serif`;

                                    if (rate >= 25) {
                                        ctx.fillStyle = '#ffffff';
                                        ctx.textBaseline = 'top';
                                        ctx.fillText(`${Number(rate).toFixed(1)}%`, bar.x, bar.y + 7);
                                    } else {
                                        ctx.fillStyle = textColor;
                                        ctx.textBaseline = 'bottom';
                                        ctx.fillText(`${Number(rate).toFixed(1)}%`, bar.x, bar.y - 4);
                                    }
                                }
                            });
                            ctx.restore();
                        }
                    }
                }]
            });
        }
    };
};
