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

            // Re-render smoothly whenever dark/light class is toggled on <html>
            if (window.MutationObserver) {
                let themeDebounce = null;
                const observer = new MutationObserver(() => {
                    clearTimeout(themeDebounce);
                    themeDebounce = setTimeout(() => {
                        this.renderAll();
                    }, 100);
                });
                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            // Also listen to Flux appearance change events
            let fluxDebounce = null;
            window.addEventListener('flux:appearance:changed', () => {
                clearTimeout(fluxDebounce);
                fluxDebounce = setTimeout(() => this.renderAll(), 100);
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
            const canvas = this.$refs.roleTurnoutChart;
            if (!canvas || typeof Chart === 'undefined' || !this.roleLabels || this.roleLabels.length === 0) return;

            const existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.stop();
                existingChart.destroy();
            }
            if (this.roleTurnoutInstance) {
                this.roleTurnoutInstance.stop();
                this.roleTurnoutInstance.destroy();
                this.roleTurnoutInstance = null;
            }

            const isDark = this.isDarkMode();
            const textColor = isDark ? '#f4f4f5' : '#18181b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.10)' : 'rgba(0, 0, 0, 0.06)';
            const trackColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.04)';

            // Uniform primary brand/orange color for current semester, grey for prior semester
            const primaryColor = isDark ? '#f59e0b' : '#d97706';
            const primaryHoverColor = isDark ? '#fbbf24' : '#b45309';
            const prevColor = isDark ? 'rgba(161, 161, 170, 0.45)' : 'rgba(113, 113, 122, 0.45)';
            const prevHoverColor = isDark ? 'rgba(161, 161, 170, 0.75)' : 'rgba(113, 113, 122, 0.75)';

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
                        backgroundColor: primaryColor,
                        hoverBackgroundColor: primaryHoverColor,
                        borderRadius: { topRight: 4, bottomRight: 4 },
                        borderSkipped: false,
                        barThickness: 13,
                        grouped: true,
                        order: 1,
                    },
                    {
                        label: this.prevSemName,
                        data: this.prevRoleRates,
                        backgroundColor: prevColor,
                        hoverBackgroundColor: prevHoverColor,
                        borderRadius: { topRight: 4, bottomRight: 4 },
                        borderSkipped: false,
                        barThickness: 13,
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
                        borderRadius: { topRight: 6, bottomRight: 6 },
                        borderSkipped: false,
                        barThickness: 22,
                        grouped: false,
                        order: 2,
                    },
                    {
                        label: 'Completion Rate',
                        data: this.roleRates,
                        backgroundColor: primaryColor,
                        hoverBackgroundColor: primaryHoverColor,
                        borderRadius: { topRight: 6, bottomRight: 6 },
                        borderSkipped: false,
                        barThickness: 22,
                        grouped: false,
                        order: 1,
                    }
                ];
            }

            this.roleTurnoutInstance = new Chart(canvas, {
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
                            top: 22,
                            right: 36,
                            bottom: 4,
                            left: 4,
                        }
                    },
                    plugins: {
                        legend: {
                            display: false,
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
                        if (!chart || !chart.ctx) return;
                        const { ctx, chartArea, scales: { x } } = chart;
                        if (!ctx || !chartArea || !x) return;
                        const { top, bottom, left, right } = chartArea;

                        if (x) {
                            const targetX = x.getPixelForValue(80);
                            if (targetX >= left && targetX <= right) {
                                ctx.save();
                                ctx.strokeStyle = isDark ? 'rgba(248, 113, 113, 0.75)' : 'rgba(185, 28, 28, 0.65)';
                                ctx.lineWidth = 1.5;
                                ctx.setLineDash([4, 4]);
                                ctx.beginPath();
                                ctx.moveTo(targetX, top);
                                ctx.lineTo(targetX, bottom);
                                ctx.stroke();

                                ctx.setLineDash([]);
                                const badgeText = '80% Target';
                                ctx.font = 'bold 9.5px Lexend, sans-serif';
                                const textWidth = ctx.measureText(badgeText).width;
                                const badgeW = textWidth + 10;
                                const badgeH = 16;
                                const badgeX = targetX - badgeW / 2;
                                const badgeY = top - 18;

                                ctx.fillStyle = isDark ? 'rgba(239, 68, 68, 0.18)' : 'rgba(254, 226, 226, 0.9)';
                                if (ctx.roundRect) {
                                    ctx.beginPath();
                                    ctx.roundRect(badgeX, badgeY, badgeW, badgeH, 4);
                                    ctx.fill();
                                } else {
                                    ctx.fillRect(badgeX, badgeY, badgeW, badgeH);
                                }

                                ctx.fillStyle = isDark ? '#f87171' : '#b91c1c';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(badgeText, targetX, badgeY + badgeH / 2);
                                ctx.restore();
                            }
                        }

                        // 2. Draw percentage text on bars (for both standard and comparison modes)
                        ctx.save();
                        ctx.textBaseline = 'middle';

                        if (!isComparing) {
                            const meta = chart.getDatasetMeta(1);
                            if (meta && meta.data) {
                                ctx.font = 'bold 11px Lexend, sans-serif';
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
                            }
                        } else {
                            const meta0 = chart.getDatasetMeta(0);
                            const meta1 = chart.getDatasetMeta(1);
                            ctx.font = 'bold 9.5px Lexend, sans-serif';

                            if (meta0 && meta0.data) {
                                meta0.data.forEach((bar, index) => {
                                    const val = chart.data.datasets[0]?.data[index];
                                    if (val !== undefined && val !== null) {
                                        const pctText = `${Number(val).toFixed(1)}%`;
                                        if (bar.width > 48) {
                                            ctx.fillStyle = '#ffffff';
                                            ctx.textAlign = 'right';
                                            ctx.fillText(pctText, bar.x - 5, bar.y);
                                        } else {
                                            ctx.fillStyle = textColor;
                                            ctx.textAlign = 'left';
                                            ctx.fillText(pctText, bar.x + 4, bar.y);
                                        }
                                    }
                                });
                            }

                            if (meta1 && meta1.data) {
                                meta1.data.forEach((bar, index) => {
                                    const val = chart.data.datasets[1]?.data[index];
                                    if (val !== undefined && val !== null) {
                                        const pctText = `${Number(val).toFixed(1)}%`;
                                        if (bar.width > 48) {
                                            ctx.fillStyle = '#ffffff';
                                            ctx.textAlign = 'right';
                                            ctx.fillText(pctText, bar.x - 5, bar.y);
                                        } else {
                                            ctx.fillStyle = isDark ? '#a1a1aa' : '#71717a';
                                            ctx.textAlign = 'left';
                                            ctx.fillText(pctText, bar.x + 4, bar.y);
                                        }
                                    }
                                });
                            }
                        }
                        ctx.restore();
                    }
                }]
            });
        },

        renderDept() {
            const canvas = this.$refs.deptChart;
            if (!canvas || typeof Chart === 'undefined') return;

            const isAcademic = this.activeDeptType === 'academic';
            const labels = isAcademic ? this.academicDeptLabels : this.adminDeptLabels;
            const names = isAcademic ? this.academicDeptNames : this.adminDeptNames;
            const rates = isAcademic ? this.academicDeptRates : this.adminDeptRates;
            const prevRates = isAcademic ? this.prevAcademicDeptRates : this.prevAdminDeptRates;
            const submitted = isAcademic ? this.academicDeptSubmitted : this.adminDeptSubmitted;
            const expected = isAcademic ? this.academicDeptExpected : this.adminDeptExpected;

            const isComparing = this.compareDept && prevRates && prevRates.length > 0;

            if (!labels || labels.length === 0) return;

            const existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.stop();
                existingChart.destroy();
            }
            if (this.deptInstance) {
                this.deptInstance.stop();
                this.deptInstance.destroy();
                this.deptInstance = null;
            }

            const isDark = this.isDarkMode();
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
            const textColor = isDark ? '#a1a1aa' : '#71717a';
            const trackColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.04)';

            // Uniform primary brand/orange color for current semester, grey for prior semester
            const primaryColor = isDark ? '#f59e0b' : '#d97706';
            const primaryHoverColor = isDark ? '#fbbf24' : '#b45309';
            const prevColor = isDark ? 'rgba(161, 161, 170, 0.45)' : 'rgba(113, 113, 122, 0.45)';
            const prevHoverColor = isDark ? 'rgba(161, 161, 170, 0.75)' : 'rgba(113, 113, 122, 0.75)';

            let datasets;
            if (isComparing) {
                datasets = [
                    {
                        label: this.currentSemName,
                        data: rates,
                        backgroundColor: primaryColor,
                        hoverBackgroundColor: primaryHoverColor,
                        borderRadius: { topRight: 4, bottomRight: 4 },
                        borderSkipped: false,
                        barThickness: isAcademic ? 13 : 8.5,
                        grouped: true,
                        order: 1,
                    },
                    {
                        label: this.prevSemName,
                        data: prevRates,
                        backgroundColor: prevColor,
                        hoverBackgroundColor: prevHoverColor,
                        borderRadius: { topRight: 4, bottomRight: 4 },
                        borderSkipped: false,
                        barThickness: isAcademic ? 13 : 8.5,
                        grouped: true,
                        order: 2,
                    }
                ];
            } else {
                datasets = [
                    {
                        label: 'Track',
                        data: rates.map(() => 100),
                        backgroundColor: trackColor,
                        hoverBackgroundColor: trackColor,
                        borderRadius: { topRight: 6, bottomRight: 6 },
                        borderSkipped: false,
                        barThickness: isAcademic ? 22 : 13,
                        grouped: false,
                        order: 2,
                    },
                    {
                        label: 'Completion Rate',
                        data: rates,
                        backgroundColor: primaryColor,
                        hoverBackgroundColor: primaryHoverColor,
                        borderRadius: { topRight: 6, bottomRight: 6 },
                        borderSkipped: false,
                        barThickness: isAcademic ? 22 : 13,
                        grouped: false,
                        order: 1,
                    }
                ];
            }

            this.deptInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 22,
                            right: 36,
                            bottom: 4,
                            left: 4,
                        }
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                title: function(ctxArr) {
                                    if (!ctxArr.length) return '';
                                    const index = ctxArr[0].dataIndex;
                                    return names[index] || labels[index];
                                },
                                label: (ctx) => {
                                    if (!isComparing && ctx.datasetIndex === 0) return null;
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
                                font: { weight: '600', size: isAcademic ? 11 : 9.5, family: 'Lexend, sans-serif' }
                            },
                            title: {
                                display: false
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'deptBenchmarkAndLabels',
                    afterDatasetsDraw(chart) {
                        if (!chart || !chart.ctx) return;
                        const { ctx, chartArea, scales: { x } } = chart;
                        if (!ctx || !chartArea || !x) return;
                        const { top, bottom, left, right } = chartArea;

                        // 1. Draw 80% Target Benchmark vertical dashed line
                        const targetX = x.getPixelForValue(80);
                        if (targetX >= left && targetX <= right) {
                            ctx.save();
                            ctx.strokeStyle = isDark ? 'rgba(248, 113, 113, 0.75)' : 'rgba(185, 28, 28, 0.65)';
                            ctx.lineWidth = 1.5;
                            ctx.setLineDash([4, 4]);
                            ctx.beginPath();
                            ctx.moveTo(targetX, top);
                            ctx.lineTo(targetX, bottom);
                            ctx.stroke();

                            // Benchmark badge text above line
                            ctx.setLineDash([]);
                            const badgeText = '80% Target';
                            ctx.font = 'bold 9.5px Lexend, sans-serif';
                            const textWidth = ctx.measureText(badgeText).width;
                            const badgeW = textWidth + 10;
                            const badgeH = 16;
                            const badgeX = targetX - badgeW / 2;
                            const badgeY = top - 18;

                            ctx.fillStyle = isDark ? 'rgba(239, 68, 68, 0.18)' : 'rgba(254, 226, 226, 0.9)';
                            if (ctx.roundRect) {
                                ctx.beginPath();
                                ctx.roundRect(badgeX, badgeY, badgeW, badgeH, 4);
                                ctx.fill();
                            } else {
                                ctx.fillRect(badgeX, badgeY, badgeW, badgeH);
                            }

                            ctx.fillStyle = isDark ? '#f87171' : '#b91c1c';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(badgeText, targetX, badgeY + badgeH / 2);
                            ctx.restore();
                        }

                        // 2. Draw percentage text on bars
                        ctx.save();
                        ctx.textBaseline = 'middle';

                        if (!isComparing) {
                            const meta = chart.getDatasetMeta(1);
                            if (meta && meta.data) {
                                ctx.font = `bold ${isAcademic ? 11 : 9.5}px Lexend, sans-serif`;
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
                            }
                        } else {
                            const meta0 = chart.getDatasetMeta(0);
                            const meta1 = chart.getDatasetMeta(1);
                            const labelFont = isAcademic ? 'bold 9.5px Lexend, sans-serif' : 'bold 8px Lexend, sans-serif';
                            ctx.font = labelFont;

                            if (meta0 && meta0.data) {
                                meta0.data.forEach((bar, index) => {
                                    const val = chart.data.datasets[0]?.data[index];
                                    if (val !== undefined && val !== null) {
                                        const pctText = `${Number(val).toFixed(1)}%`;
                                        if (bar.width > 44) {
                                            ctx.fillStyle = '#ffffff';
                                            ctx.textAlign = 'right';
                                            ctx.fillText(pctText, bar.x - 4, bar.y);
                                        } else {
                                            ctx.fillStyle = textColor;
                                            ctx.textAlign = 'left';
                                            ctx.fillText(pctText, bar.x + 4, bar.y);
                                        }
                                    }
                                });
                            }

                            if (meta1 && meta1.data) {
                                meta1.data.forEach((bar, index) => {
                                    const val = chart.data.datasets[1]?.data[index];
                                    if (val !== undefined && val !== null) {
                                        const pctText = `${Number(val).toFixed(1)}%`;
                                        if (bar.width > 44) {
                                            ctx.fillStyle = '#ffffff';
                                            ctx.textAlign = 'right';
                                            ctx.fillText(pctText, bar.x - 4, bar.y);
                                        } else {
                                            ctx.fillStyle = isDark ? '#a1a1aa' : '#71717a';
                                            ctx.textAlign = 'left';
                                            ctx.fillText(pctText, bar.x + 4, bar.y);
                                        }
                                    }
                                });
                            }
                        }
                        ctx.restore();
                    }
                }]
            });
        }
    };
};
