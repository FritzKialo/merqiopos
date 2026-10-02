document.addEventListener('DOMContentLoaded',
    function () {

    // ── Daily Revenue Bar Chart ────────────────
    var canvas = document.getElementById(
        'dailyChart'
    );
    if (!canvas) return;

    var ctx    = canvas.getContext('2d');
    var W      = canvas.offsetWidth || 500;
    var H      = 200;
    canvas.width  = W;
    canvas.height = H;

    var labels  = (typeof DAILY_LABELS  !== 'undefined')
        ? DAILY_LABELS  : [];
    var revenue = (typeof DAILY_REVENUE !== 'undefined')
        ? DAILY_REVENUE : [];

    if (labels.length === 0) return;

    var maxVal  = Math.max(...revenue, 1);
    var padding = { top: 20, right: 10,
                    bottom: 36, left: 10 };
    var chartW  = W - padding.left - padding.right;
    var chartH  = H - padding.top  - padding.bottom;

    // Excel-style horizontal gridlines (4 bands) drawn behind the bars,
    // plus a solid axis line at the base — a plain spreadsheet-chart
    // look, replacing the previous gridline-free canvas.
    var gridLines = 4;
    ctx.strokeStyle = '#e0e0e0';
    ctx.lineWidth = 1;
    for (var g = 0; g <= gridLines; g++) {
        var gy = padding.top + (chartH / gridLines) * g;
        ctx.beginPath();
        ctx.moveTo(padding.left, gy);
        ctx.lineTo(padding.left + chartW, gy);
        ctx.stroke();
    }
    ctx.strokeStyle = '#b0b0b0';
    ctx.beginPath();
    ctx.moveTo(padding.left, padding.top + chartH);
    ctx.lineTo(padding.left + chartW, padding.top + chartH);
    ctx.stroke();

    var barW    = Math.max(
        4, (chartW / labels.length) - 4
    );
    var gap     = (chartW - barW * labels.length)
        / (labels.length + 1);

    revenue.forEach(function (val, i) {
        var barH = (val / maxVal) * chartH;
        var x    = padding.left + gap
            + i * (barW + gap);
        var y    = padding.top + chartH - barH;

        // Bar — square corners, classic Excel accent-1 blue.
        ctx.fillStyle = '#4472C4';
        ctx.fillRect(x, y, barW, barH);

        // X label
        if (labels.length <= 15 || i % 3 === 0) {
            ctx.fillStyle = '#404040';
            ctx.font      = '10px Arial, Calibri, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(
                labels[i],
                x + barW / 2,
                H - padding.bottom + 14
            );
        }
    });

    // ── Auto-hide flash alerts ─────────────────
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.opacity    = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function () {
                alert.remove();
            }, 500);
        }, 4000);
    });
});