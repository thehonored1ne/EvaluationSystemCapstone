// Global chart helper for Admin Dashboard
window.dashboardAnalyticsCharts = function(config) {
    return {
        ratingsInstance: null,
        deptInstance: null,
        ratingsData: config?.ratingsData || [],
        deptLabels: config?.deptLabels || [],
        deptAverages: config?.deptAverages || [],
        init() {
            this.$nextTick(() => {
                this.renderAll();
            });

            // Re-render when appearance changes (light/dark mode toggle)
            window.addEventListener('flux:appearance:changed', () => {
                this.renderAll();
            });
        },
        renderAll() {
            if (typeof Chart === 'undefined') {
                setTimeout(() => this.renderAll(), 100);
                return;
            }
            this.renderRatings();
            this.renderDept();
        },
        renderRatings() {
            if (!this.$refs.ratingsChart || typeof Chart === 'undefined') return;
            if (this.ratingsInstance) {
                this.ratingsInstance.destroy();
                this.ratingsInstance = null;
            }
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#d4d4d8' : '#3f3f46';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

            this.ratingsInstance = new Chart(this.$refs.ratingsChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Rating 5', 'Rating 4', 'Rating 3', 'Rating 2', 'Rating 1'],
                    datasets: [{
                        data: this.ratingsData,
                        backgroundColor: ['#9b0000', '#b91c1c', '#f59e0b', '#ef4444', '#71717a'],
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return Number(ctx.raw).toLocaleString() + ' answers';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: { precision: 0, color: textColor }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: textColor }
                        }
                    }
                }
            });
        },
        renderDept() {
            if (!this.$refs.deptChart || typeof Chart === 'undefined' || !this.deptLabels || this.deptLabels.length === 0) return;
            if (this.deptInstance) {
                this.deptInstance.destroy();
                this.deptInstance = null;
            }
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#d4d4d8' : '#3f3f46';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

            this.deptInstance = new Chart(this.$refs.deptChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: this.deptLabels,
                    datasets: [{
                        data: this.deptAverages,
                        backgroundColor: isDark ? '#f89696' : '#9b0000',
                        hoverBackgroundColor: isDark ? '#fca5a5' : '#7a0000',
                        borderRadius: 6,
                        borderSkipped: false,
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
                                label: function(ctx) {
                                    return Number(ctx.raw).toFixed(2) + ' / 5.00 Rating';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 5.0,
                            grid: { color: gridColor },
                            ticks: { color: textColor }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: textColor }
                        }
                    }
                }
            });
        }
    };
};
