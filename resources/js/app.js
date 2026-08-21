// Global chart helper for Admin Dashboard (dynamically lazy-loads Chart.js on demand)
window.dashboardAnalyticsCharts = function(config) {
    return {
        ratingsInstance: null,
        deptInstance: null,
        ratingsData: config?.ratingsData || [],
        deptLabels: config?.deptLabels || [],
        deptAverages: config?.deptAverages || [],
        isDarkMode() {
            return document.documentElement.classList.contains('dark');
        },
        async init() {
            if (typeof window.Chart === 'undefined') {
                const { default: Chart } = await import('chart.js/auto');
                window.Chart = Chart;
            }

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
        async renderAll() {
            if (typeof window.Chart === 'undefined') {
                const { default: Chart } = await import('chart.js/auto');
                window.Chart = Chart;
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
            const isDark = this.isDarkMode();
            const textColor = isDark ? '#f4f4f5' : '#18181b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)';

            this.ratingsInstance = new Chart(this.$refs.ratingsChart.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Rating 5', 'Rating 4', 'Rating 3', 'Rating 2', 'Rating 1'],
                    datasets: [{
                        data: this.ratingsData,
                        backgroundColor: ['#9b0000', '#b91c1c', '#f59e0b', '#ef4444', '#52525b'],
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
                            ticks: { 
                                precision: 0, 
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { 
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' }
                            }
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
            const isDark = this.isDarkMode();
            const textColor = isDark ? '#f4f4f5' : '#18181b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)';

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
                            ticks: { 
                                color: textColor,
                                font: { weight: '600', size: 11, family: 'Lexend, sans-serif' }
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
