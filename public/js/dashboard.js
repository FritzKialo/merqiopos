document.addEventListener('DOMContentLoaded', function () {

    var canvas = document.getElementById('revenueChart');
    if (!canvas || typeof Chart === 'undefined') return;

    var ctx = canvas.getContext('2d');

    // ── State ────────────────────────────────────────────────────────────
    var state = {
        labels:       typeof CHART_LABELS      !== 'undefined' ? CHART_LABELS      : [],
        revenue:      typeof CHART_REVENUE     !== 'undefined' ? CHART_REVENUE     : [],
        expenses:     typeof CHART_EXPENSES    !== 'undefined' ? CHART_EXPENSES    : [],
        profit:       typeof CHART_PROFIT      !== 'undefined' ? CHART_PROFIT      : [],
        granularity:  typeof CHART_GRANULARITY !== 'undefined' ? CHART_GRANULARITY : 'month',
        rangeStart:   typeof CHART_RANGE_START !== 'undefined' ? CHART_RANGE_START : null,
        rangeEnd:     typeof CHART_RANGE_END   !== 'undefined' ? CHART_RANGE_END   : null,
        days:         180,
        prev:         null,   // { labels, revenue, expenses, profit } when compare is on
        chartType:    'line', // line | bar | area
        activeDataset:'all',  // all | revenue | expenses | profit
        compare:      false,
    };

    var chart = null;

    // ── Palette ──────────────────────────────────────────────────────────
    // Brand-aligned modern palette (teal/amber/slate) replacing the old
    // Excel accent1/2/3 blue/orange/gray theme — ties the chart back to
    // the app's own primary color instead of reading as a spreadsheet.
    var COLORS = {
        revenue:  { line: '#0f766e', rgb: '15,118,110'  },
        expenses: { line: '#d97706', rgb: '217,119,6'   },
        profit:   { line: '#64748b', rgb: '100,116,139' },
    };
    var CHART_FONT = "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

    function makeGradient(rgb) {
        var grad = ctx.createLinearGradient(0, 0, 0, 340);
        grad.addColorStop(0,   'rgba(' + rgb + ',0.20)');
        grad.addColorStop(0.6, 'rgba(' + rgb + ',0.06)');
        grad.addColorStop(1,   'rgba(' + rgb + ',0)');
        return grad;
    }

    function fmtKes(n) {
        n = Number(n) || 0;
        return 'KES ' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function buildDataset(key, label, dashed) {
        var color = COLORS[key];
        var isBar = state.chartType === 'bar';
        return {
            label: label,
            data: state[key],
            borderColor: color.line,
            backgroundColor: isBar ? 'rgba(' + color.rgb + ',0.65)' : (state.chartType === 'area' ? makeGradient(color.rgb) : 'transparent'),
            borderWidth: dashed ? 1.5 : 2,
            borderDash: dashed ? [6, 4] : [],
            pointRadius: isBar ? 0 : (dashed ? 0 : 2.5),
            pointHoverRadius: isBar ? 0 : 5,
            pointStyle: 'circle',
            pointBackgroundColor: '#fff',
            pointBorderColor: color.line,
            pointBorderWidth: 2,
            fill: state.chartType === 'area',
            tension: isBar ? 0 : 0.35,
            borderRadius: isBar ? 4 : 0,
            barPercentage: 0.55,
            categoryPercentage: 0.7,
            order: dashed ? 1 : 0,
        };
    }

    function visibleFor(dsKey) {
        return state.activeDataset === 'all' || state.activeDataset === dsKey;
    }

    function buildDatasets() {
        var datasets = [
            buildDataset('revenue',  'Revenue',  false),
            buildDataset('expenses', 'Expenses', false),
            buildDataset('profit',   'Profit',   false),
        ];
        datasets[0].hidden = !visibleFor('revenue');
        datasets[1].hidden = !visibleFor('expenses');
        datasets[2].hidden = !visibleFor('profit');

        if (state.compare && state.prev) {
            var prevDatasets = [
                buildDataset2('revenue',  'Revenue (previous)'),
                buildDataset2('expenses', 'Expenses (previous)'),
                buildDataset2('profit',   'Profit (previous)'),
            ];
            prevDatasets[0].hidden = !visibleFor('revenue');
            prevDatasets[1].hidden = !visibleFor('expenses');
            prevDatasets[2].hidden = !visibleFor('profit');
            datasets = datasets.concat(prevDatasets);
        }

        return datasets;
    }

    // Previous-period overlay: same color, dashed, no fill — reads as "the
    // ghost of last time" rather than a competing series.
    function buildDataset2(key, label) {
        var color = COLORS[key];
        return {
            label: label,
            data: state.prev[key],
            borderColor: color.line,
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [5, 4],
            pointRadius: 0,
            pointHoverRadius: 5,
            pointBackgroundColor: color.line,
            fill: false,
            tension: 0.45,
            order: 2,
        };
    }

    function chartJsType() {
        return state.chartType === 'bar' ? 'bar' : 'line';
    }

    function renderChart() {
        var cfg = {
            type: chartJsType(),
            data: {
                labels: state.labels,
                datasets: buildDatasets(),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                onClick: handleChartClick,
                onHover: function (evt, elements) {
                    evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    // Modern legend: app font, muted neutral text, round
                    // swatches — replacing the old Excel-style flat-square/
                    // Arial treatment.
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: {
                            boxWidth: 8, boxHeight: 8, padding: 18,
                            color: '#5b6675',
                            font: { size: 12, family: CHART_FONT, weight: '500' },
                            usePointStyle: true,
                            pointStyle: 'circle',
                        }
                    },
                    tooltip: {
                        backgroundColor: '#fff',
                        titleColor: '#11181f',
                        bodyColor: '#11181f',
                        borderColor: '#e3e7ec',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 10,
                        boxPadding: 4,
                        titleFont: { size: 12, weight: '600', family: CHART_FONT },
                        bodyFont:  { size: 12, family: CHART_FONT },
                        callbacks: {
                            label: function (item) {
                                return '  ' + item.dataset.label + ':  ' + fmtKes(item.raw);
                            }
                        }
                    }
                },
                // Soft, low-contrast horizontal gridlines only (no vertical
                // lines, no visible axis border) — a quieter, more modern
                // read than the previous visible-on-both-axes spreadsheet grid.
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: '#8a93a0', font: { size: 11, family: CHART_FONT }, padding: 8 }
                    },
                    y: {
                        grid: { color: '#eef1f4' },
                        border: { display: false },
                        ticks: {
                            color: '#8a93a0',
                            font: { size: 11, family: CHART_FONT },
                            padding: 8,
                            callback: function (val) {
                                if (Math.abs(val) >= 1000000) return 'KES ' + (val / 1000000).toFixed(1) + 'm';
                                if (Math.abs(val) >= 1000)    return 'KES ' + (val / 1000).toFixed(0) + 'k';
                                return 'KES ' + val;
                            }
                        },
                        beginAtZero: true,
                    }
                }
            }
        };

        if (chart) { chart.destroy(); }
        chart = new Chart(ctx, cfg);
        updateLegendTotals();
    }

    function updateLegendTotals() {
        var sum = function (arr) { return (arr || []).reduce(function (a, b) { return a + Number(b); }, 0); };
        var revEl = document.getElementById('chartLegendRevenue');
        var expEl = document.getElementById('chartLegendExpenses');
        var proEl = document.getElementById('chartLegendProfit');
        if (revEl) revEl.textContent = fmtKes(sum(state.revenue)) + ' Revenue';
        if (expEl) expEl.textContent = fmtKes(sum(state.expenses)) + ' Expenses';
        if (proEl) proEl.textContent = fmtKes(sum(state.profit)) + ' Net profit';
    }

    // ── Drill-down: click a point/bar → that period's sales list ──────────
    function handleChartClick(evt) {
        var points = chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, false);
        if (!points.length) return;
        var index = points[0].index;
        var label = state.labels[index];
        if (!label || typeof SALES_INDEX_URL === 'undefined') return;

        var dateFrom, dateTo;
        if (state.granularity === 'day') {
            // label is "j M" (e.g. "3 Aug") for the CURRENT range end year —
            // rebuild the actual date from rangeEnd's year, correcting across
            // a year boundary if the range spans one.
            var end = state.rangeEnd ? new Date(state.rangeEnd) : new Date();
            var guess = new Date(label + ' ' + end.getFullYear());
            if (guess > end) guess.setFullYear(guess.getFullYear() - 1);
            var iso = guess.toISOString().slice(0, 10);
            dateFrom = dateTo = iso;
        } else {
            // label is "Mon yy" (e.g. "Aug 25")
            var parsed = new Date('1 ' + label);
            if (isNaN(parsed.getTime())) return;
            var y = parsed.getFullYear(), m = parsed.getMonth();
            dateFrom = new Date(y, m, 1).toISOString().slice(0, 10);
            dateTo   = new Date(y, m + 1, 0).toISOString().slice(0, 10);
        }

        window.location.href = SALES_INDEX_URL + '?date_from=' + dateFrom + '&date_to=' + dateTo;
    }

    // ── Fetching a new range from the server ───────────────────────────────
    var loadingEl = document.getElementById('chartLoading');

    function setLoading(on) {
        if (loadingEl) loadingEl.style.display = on ? 'flex' : 'none';
    }

    function fetchRange(params) {
        if (typeof DASHBOARD_CHART_DATA_URL === 'undefined') return;
        setLoading(true);

        var qs = new URLSearchParams(params).toString();
        fetch(DASHBOARD_CHART_DATA_URL + '?' + qs, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error('Request failed'); return r.json(); })
            .then(function (json) {
                state.labels      = json.current.labels;
                state.revenue     = json.current.revenue;
                state.expenses    = json.current.expenses;
                state.profit      = json.current.profit;
                state.granularity = json.current.granularity;
                state.prev        = json.previous || null;
                renderChart();
            })
            .catch(function () {
                // Silent — leave the previously rendered chart in place
                // rather than blank the panel over a transient network blip.
            })
            .finally(function () { setLoading(false); });
    }

    // ── Range preset buttons ────────────────────────────────────────────────
    var rangeGroup = document.getElementById('chartRangeGroup');
    var customRange = document.getElementById('chartCustomRange');
    var chartTitle = document.getElementById('chartTitle');
    var RANGE_TITLES = { 7: 'Last 7 Days', 30: 'Last 30 Days', 90: 'Last 3 Months', 180: 'Last 6 Months', 365: 'Last 12 Months' };

    if (rangeGroup) {
        rangeGroup.addEventListener('click', function (e) {
            var btn = e.target.closest('.chart-toggle-btn');
            if (!btn) return;

            if (btn.id === 'chartCustomBtn') {
                customRange.style.display = customRange.style.display === 'none' ? 'flex' : 'none';
                return;
            }

            rangeGroup.querySelectorAll('.chart-toggle-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            customRange.style.display = 'none';

            var days = parseInt(btn.dataset.days, 10);
            state.days = days;
            if (chartTitle) chartTitle.textContent = 'Revenue vs Expenses — ' + (RANGE_TITLES[days] || 'Custom Range');

            fetchRange({ days: days, compare: state.compare ? 1 : 0 });
        });
    }

    var customApply = document.getElementById('chartCustomApply');
    if (customApply) {
        customApply.addEventListener('click', function () {
            var start = document.getElementById('chartStartDate').value;
            var end   = document.getElementById('chartEndDate').value;
            if (!start || !end) return;

            if (rangeGroup) rangeGroup.querySelectorAll('.chart-toggle-btn').forEach(function (b) { b.classList.remove('active'); });
            if (chartTitle) chartTitle.textContent = 'Revenue vs Expenses — ' + start + ' to ' + end;

            fetchRange({ start: start, end: end, compare: state.compare ? 1 : 0 });
        });
    }

    // ── Compare-to-previous-period toggle ──────────────────────────────────
    var compareToggle = document.getElementById('chartCompareToggle');
    if (compareToggle) {
        compareToggle.addEventListener('change', function () {
            state.compare = compareToggle.checked;
            var start = document.getElementById('chartStartDate').value;
            var end   = document.getElementById('chartEndDate').value;
            if (customRange.style.display !== 'none' && start && end) {
                fetchRange({ start: start, end: end, compare: state.compare ? 1 : 0 });
            } else {
                fetchRange({ days: state.days, compare: state.compare ? 1 : 0 });
            }
        });
    }

    // ── Chart type toggle (Line / Bar / Area) ──────────────────────────────
    var typeGroup = document.getElementById('chartTypeGroup');
    if (typeGroup) {
        typeGroup.addEventListener('click', function (e) {
            var btn = e.target.closest('.chart-toggle-btn');
            if (!btn) return;
            typeGroup.querySelectorAll('.chart-toggle-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            state.chartType = btn.dataset.type;
            renderChart();
        });
    }

    // ── Dataset visibility toggle (All / Revenue / Expenses / Profit) ──────
    var toggleGroup = document.getElementById('chartToggle');
    if (toggleGroup) {
        toggleGroup.addEventListener('click', function (e) {
            var btn = e.target.closest('.chart-toggle-btn');
            if (!btn) return;
            toggleGroup.querySelectorAll('.chart-toggle-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            state.activeDataset = btn.dataset.dataset;
            renderChart();
        });
    }

    // ── Export: PNG ─────────────────────────────────────────────────────────
    var exportPng = document.getElementById('chartExportPng');
    if (exportPng) {
        exportPng.addEventListener('click', function () {
            if (!chart) return;
            // Flatten onto white first — a transparent canvas export looks
            // broken when opened outside the app's own dark/light theming.
            var tmp = document.createElement('canvas');
            tmp.width = chart.canvas.width;
            tmp.height = chart.canvas.height;
            var tctx = tmp.getContext('2d');
            tctx.fillStyle = '#ffffff';
            tctx.fillRect(0, 0, tmp.width, tmp.height);
            tctx.drawImage(chart.canvas, 0, 0);

            var link = document.createElement('a');
            link.download = 'revenue-vs-expenses.png';
            link.href = tmp.toDataURL('image/png');
            link.click();
        });
    }

    // ── Export: CSV ─────────────────────────────────────────────────────────
    var exportCsv = document.getElementById('chartExportCsv');
    if (exportCsv) {
        exportCsv.addEventListener('click', function () {
            var rows = [['Period', 'Revenue', 'Expenses', 'Profit']];
            state.labels.forEach(function (label, i) {
                rows.push([label, state.revenue[i] ?? 0, state.expenses[i] ?? 0, state.profit[i] ?? 0]);
            });
            var csv = rows.map(function (r) {
                return r.map(function (cell) { return '"' + String(cell).replace(/"/g, '""') + '"'; }).join(',');
            }).join('\n');

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'revenue-vs-expenses.csv';
            link.click();
            URL.revokeObjectURL(url);
        });
    }

    // ── Live KPI polling (every 60s, no page reload) ───────────────────────
    function pulseUpdate(el, newText) {
        if (!el || el.textContent === newText) return;
        el.textContent = newText;
        el.classList.remove('kpi-pulse');
        // Force reflow so the animation restarts if it fires twice quickly.
        void el.offsetWidth;
        el.classList.add('kpi-pulse');
    }

    function pollKpis() {
        if (typeof DASHBOARD_KPI_URL === 'undefined' || document.hidden) return;

        fetch(DASHBOARD_KPI_URL, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error('failed'); return r.json(); })
            .then(function (k) {
                pulseUpdate(document.getElementById('kpiTodaySales'),    fmtKes(k.todaySales));
                pulseUpdate(document.getElementById('kpiTodayExpenses'), fmtKes(k.todayExpenses));
                pulseUpdate(document.getElementById('kpiTodayProfit'),   fmtKes(k.todayProfit));
                pulseUpdate(document.getElementById('kpiMonthSales'),    fmtKes(k.monthSales));
                pulseUpdate(document.getElementById('kpiMonthProfit'),   fmtKes(k.monthProfit));

                var profitEl = document.getElementById('kpiTodayProfit');
                if (profitEl) profitEl.classList.toggle('is-negative', k.todayProfit < 0);
                var monthProfitEl = document.getElementById('kpiMonthProfit');
                if (monthProfitEl) monthProfitEl.classList.toggle('is-negative', k.monthProfit < 0);
            })
            .catch(function () { /* silent — try again next interval */ });
    }

    if (typeof DASHBOARD_KPI_URL !== 'undefined') {
        setInterval(pollKpis, 60000);
    }

    // ── Sparkline ────────────────────────────────────────────────────────────
    var sparkCanvas = document.getElementById('sparklineChart');
    if (sparkCanvas && typeof SPARKLINE_DATA !== 'undefined') {
        var sctx = sparkCanvas.getContext('2d');
        new Chart(sctx, {
            type: 'bar',
            data: {
                labels: Array.from({ length: 14 }, function (_, i) { return i === 13 ? 'Today' : (13 - i) + 'd'; }).reverse(),
                datasets: [{
                    data: SPARKLINE_DATA,
                    backgroundColor: function (c) { return c.dataIndex === 13 ? '#0f766e' : '#e3e7ec'; },
                    borderRadius: 3,
                    borderWidth: 0,
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return 'KES ' + Number(c.raw).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } } }
                },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
            }
        });
    }

    // ── Sales by category (donut) ───────────────────────────────────────────
    // Softer modern qualitative palette (teal/amber/slate first, matching
    // the main chart) instead of the old saturated Office theme colors.
    var DONUT_COLORS = ['#0f766e', '#d97706', '#64748b', '#be185d', '#4f46e5', '#059669', '#0284c7', '#9333ea'];

    var categoryCanvas = document.getElementById('categoryChart');
    if (categoryCanvas && typeof CATEGORY_LABELS !== 'undefined' && CATEGORY_LABELS.length) {
        new Chart(categoryCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: CATEGORY_LABELS,
                datasets: [{
                    data: CATEGORY_REVENUE,
                    backgroundColor: DONUT_COLORS,
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11.5, family: CHART_FONT }, color: '#5b6675' } },
                    tooltip: { callbacks: { label: function (c) { return c.label + ': ' + fmtKes(c.raw); } } }
                }
            }
        });
    }

    // ── Payment method mix (donut) ──────────────────────────────────────────
    var paymentCanvas = document.getElementById('paymentChart');
    if (paymentCanvas && typeof PAYMENT_LABELS !== 'undefined' && PAYMENT_LABELS.length) {
        new Chart(paymentCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: PAYMENT_LABELS,
                datasets: [{
                    data: PAYMENT_REVENUE,
                    backgroundColor: DONUT_COLORS,
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11.5, family: CHART_FONT }, color: '#5b6675' } },
                    tooltip: { callbacks: { label: function (c) { return c.label + ': ' + fmtKes(c.raw); } } }
                }
            }
        });
    }

    // ── Peak hours (bar) ─────────────────────────────────────────────────────
    var peakCanvas = document.getElementById('peakHoursChart');
    if (peakCanvas && typeof PEAK_HOUR_LABELS !== 'undefined') {
        new Chart(peakCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: PEAK_HOUR_LABELS,
                datasets: [{
                    label: 'Transactions',
                    data: PEAK_HOUR_COUNTS,
                    backgroundColor: '#0f766e',
                    borderRadius: 4,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return c.raw + ' transaction' + (c.raw === 1 ? '' : 's'); } } }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a93a0', maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { size: 10, family: CHART_FONT } } },
                    y: { grid: { color: '#eef1f4' }, border: { display: false }, beginAtZero: true, ticks: { color: '#8a93a0', precision: 0, font: { family: CHART_FONT } } }
                }
            }
        });
    }

    // ── Initial render ──────────────────────────────────────────────────────
    renderChart();
});
