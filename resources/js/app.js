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
        roleLabels: config?.roleLabels || [],
        roleRates: config?.roleRates || [],
        roleDetails: config?.roleDetails || [],
        academicDeptLabels: config?.academicDeptLabels || [],
        academicDeptNames: config?.academicDeptNames || [],
        academicDeptAverages: config?.academicDeptAverages || [],
        academicDeptCounts: config?.academicDeptCounts || [],
        adminDeptLabels: config?.adminDeptLabels || [],
        adminDeptNames: config?.adminDeptNames || [],
        adminDeptAverages: config?.adminDeptAverages || [],
        adminDeptCounts: config?.adminDeptCounts || [],

        isDarkMode() {
            return document.documentElement.classList.contains('dark');
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
            const axisTitleColor = isDark ? '#a1a1aa' : '#71717a';
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

            const self = this;

            this.roleTurnoutInstance = new Chart(this.$refs.roleTurnoutChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: this.roleLabels,
                    datasets: [
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
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 16,
                            right: 12,
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            filter: function(item) {
                                return item.datasetIndex === 1;
                            },
                            callbacks: {
                                label: function(ctx) {
                                    const index = ctx.dataIndex;
                                    const rate = Number(ctx.raw).toFixed(1);
                                    const detail = self.roleDetails[index];
                                    if (detail && detail.expected > 0) {
                                        return `${rate}% completion (${Number(detail.submitted).toLocaleString()} of ${Number(detail.expected).toLocaleString()} evaluators completed)`;
                                    }
                                    return `${rate}% completion rate`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Completion Rate (%)',
                                color: axisTitleColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                padding: { top: 6, bottom: 0 }
                            },
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                callback: (v) => v + '%'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Evaluator Role',
                                color: axisTitleColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                padding: { bottom: 6 }
                            },
                            grid: { display: false },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: 12, family: 'Lexend, sans-serif' }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'benchmarkAndLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx, chartArea: { top, bottom, left, right }, scales: { x } } = chart;
                        if (!x) return;

                        // 1. Draw 80% Target Benchmark vertical dashed line
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

                            // Benchmark badge pill at top
                            ctx.setLineDash([]);
                            ctx.fillStyle = isDark ? '#fca5a5' : '#b91c1c';
                            ctx.font = 'bold 10px Lexend, sans-serif';
                            ctx.textAlign = 'center';
                            ctx.fillText('80% Benchmark', targetX, top - 6);
                            ctx.restore();
                        }

                        // 2. Draw inline text labels on each bar
                        const meta = chart.getDatasetMeta(1);
                        if (meta && meta.data) {
                            ctx.save();
                            ctx.font = 'bold 11px Lexend, sans-serif';
                            ctx.textBaseline = 'middle';

                            meta.data.forEach((bar, index) => {
                                const rate = self.roleRates[index];
                                const detail = self.roleDetails[index];
                                if (rate !== undefined) {
                                    const pctText = `${Number(rate).toFixed(1)}%`;
                                    const countText = detail && detail.expected > 0 ? ` (${Number(detail.submitted).toLocaleString()}/${Number(detail.expected).toLocaleString()})` : '';
                                    const fullText = pctText + countText;

                                    const barWidth = bar.x - left;
                                    if (barWidth > 180) {
                                        ctx.fillStyle = '#ffffff';
                                        ctx.textAlign = 'right';
                                        ctx.fillText(fullText, bar.x - 10, bar.y);
                                    } else {
                                        ctx.fillStyle = textColor;
                                        ctx.textAlign = 'left';
                                        ctx.fillText(fullText, bar.x + 8, bar.y);
                                    }
                                }
                            });
                            ctx.restore();
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
            const averages = isAcademic ? this.academicDeptAverages : this.adminDeptAverages;
            const counts = isAcademic ? this.academicDeptCounts : this.adminDeptCounts;

            if (!labels || labels.length === 0) return;

            const isDark = this.isDarkMode();
            const textColor = isDark ? '#f4f4f5' : '#18181b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)';

            // Color coding vs. 3.50 Passing Benchmark
            const backgroundColors = averages.map(avg => {
                if (avg >= 3.50) return isDark ? '#f89696' : '#9b0000';
                return isDark ? '#f87171' : '#dc2626';
            });

            const hoverColors = averages.map(avg => {
                if (avg >= 3.50) return isDark ? '#fca5a5' : '#7a0000';
                return isDark ? '#fca5a5' : '#b91c1c';
            });

            this.deptInstance = new Chart(this.$refs.deptChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        data: averages,
                        backgroundColor: backgroundColors,
                        hoverBackgroundColor: hoverColors,
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: isAcademic ? 22 : 14,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function(ctxArr) {
                                    if (!ctxArr.length) return '';
                                    const index = ctxArr[0].dataIndex;
                                    return names[index] || labels[index];
                                },
                                label: function(ctx) {
                                    const index = ctx.dataIndex;
                                    const count = counts[index] || 0;
                                    const avg = Number(ctx.raw).toFixed(2);
                                    const status = avg >= 3.50 ? 'Meets Benchmark (≥ 3.50)' : 'Below Benchmark (< 3.50)';
                                    return `${avg} / 5.00 rating • ${status} (${Number(count).toLocaleString()} evaluations)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 5.0,
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' },
                                callback: (v) => Number(v).toFixed(1)
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' }
                            }
                        }
                    }
                }
            });
        }
    };
};
