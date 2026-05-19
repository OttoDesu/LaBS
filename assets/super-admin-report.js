(function () {
    var config = window.SUPER_ADMIN_REPORT;
    if (!config) {
        return;
    }

    var form = document.getElementById('super-admin-report-form');
    var filterType = document.getElementById('report-filter-type');
    var yearSelect = document.getElementById('report-year');
    var monthSelect = document.getElementById('report-month');
    var weekSelect = document.getElementById('report-week');
    var dateRangeFields = document.getElementById('report-date-range-fields');
    var startDateInput = document.getElementById('report-start-date');
    var endDateInput = document.getElementById('report-end-date');
    var clusterSelect = document.getElementById('report-cluster');
    var labSelect = document.getElementById('report-lab');
    var submitButton = document.getElementById('report-submit');
    var loadingState = document.getElementById('report-loading-state');
    var description = document.getElementById('report-filter-description');
    var selectedDaysState = document.getElementById('report-selected-days');
    var errorBox = document.getElementById('report-error');
    var summaryCards = document.getElementById('report-summary-cards');
    var barChart = document.getElementById('report-bar-chart');
    var pieChart = document.getElementById('report-pie-chart');
    var barMeta = document.getElementById('report-bar-meta');
    var tableMeta = document.getElementById('report-table-meta');
    var tableWrapper = document.getElementById('report-table-wrapper');
    var tooltip = document.getElementById('report-tooltip');

    if (!form || !filterType || !yearSelect || !monthSelect || !weekSelect || !dateRangeFields || !startDateInput || !endDateInput || !clusterSelect || !labSelect || !tooltip || !selectedDaysState) {
        return;
    }

    var STATUS_KEYS = ['Approved', 'Cancelled', 'Rejected'];

    function getThemeColors() {
        var styles = getComputedStyle(document.documentElement);
        return {
            accent: styles.getPropertyValue('--accent').trim() || '#2f6bff',
            success: styles.getPropertyValue('--success').trim() || '#2f9e44',
            danger: styles.getPropertyValue('--danger').trim() || '#c0392b',
            muted: '#64748b',
            grid: '#dbe7ff',
            axis: '#7b8794'
        };
    }

    function statusColor(status) {
        var colors = getThemeColors();
        if (status === 'Approved' || status === 'Booked') {
            return colors.success;
        }
        if (status === 'Rejected') {
            return colors.danger;
        }
        return colors.muted;
    }

    function getStatusDisplayLabel(status) {
        return status === 'Approved' ? 'Booked' : status;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('en-US').format(Number(value) || 0);
    }

    function formatHours(value) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(Number(value) || 0);
    }

    function getNiceScaleMax(value) {
        var number = Number(value) || 0;
        if (number <= 5) {
            return 5;
        }

        var exponent = Math.pow(10, Math.floor(Math.log10(number)));
        var fraction = number / exponent;
        var niceFraction = 1;
        if (fraction <= 1) {
            niceFraction = 1;
        } else if (fraction <= 2) {
            niceFraction = 2;
        } else if (fraction <= 5) {
            niceFraction = 5;
        } else {
            niceFraction = 10;
        }
        return niceFraction * exponent;
    }

    function showTooltip(content, event) {
        tooltip.innerHTML = content;
        tooltip.hidden = false;
        tooltip.style.left = (event.pageX + 14) + 'px';
        tooltip.style.top = (event.pageY - 10) + 'px';
    }

    function hideTooltip() {
        tooltip.hidden = true;
    }

    function attachCanvasTooltip(canvas, regions) {
        canvas.addEventListener('mousemove', function (event) {
            var rect = canvas.getBoundingClientRect();
            var x = ((event.clientX - rect.left) * canvas.width) / rect.width;
            var y = ((event.clientY - rect.top) * canvas.height) / rect.height;
            var match = null;

            regions.forEach(function (region) {
                if (region.type === 'rect') {
                    if (x >= region.x && x <= region.x + region.width && y >= region.y && y <= region.y + region.height) {
                        match = region;
                    }
                } else if (region.type === 'arc') {
                    var dx = x - region.cx;
                    var dy = y - region.cy;
                    var distance = Math.sqrt((dx * dx) + (dy * dy));
                    var angle = (Math.atan2(dy, dx) * 180 / Math.PI + 450) % 360;
                    if (distance >= region.innerRadius && distance <= region.outerRadius && angle >= region.startAngle && angle <= region.endAngle) {
                        match = region;
                    }
                }
            });

            if (match) {
                showTooltip(match.tooltip, event);
            } else {
                hideTooltip();
            }
        });

        canvas.addEventListener('mouseleave', hideTooltip);
    }

    function createHiDPICanvas(width, height) {
        var ratio = window.devicePixelRatio || 1;
        var canvas = document.createElement('canvas');
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        var context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        return {
            canvas: canvas,
            context: context
        };
    }

    function drawText(context, text, x, y, options) {
        context.save();
        context.fillStyle = options.color || '#1f2a37';
        context.font = options.font || '12px Plus Jakarta Sans';
        context.textAlign = options.align || 'left';
        context.textBaseline = options.baseline || 'alphabetic';
        context.fillText(String(text), x, y);
        context.restore();
    }

    function getIsoWeekRange(year, week) {
        var simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
        var day = simple.getUTCDay();
        var isoWeekStart = new Date(simple);
        if (day <= 4) {
            isoWeekStart.setUTCDate(simple.getUTCDate() - simple.getUTCDay() + 1);
        } else {
            isoWeekStart.setUTCDate(simple.getUTCDate() + 8 - simple.getUTCDay());
        }
        var isoWeekEnd = new Date(isoWeekStart);
        isoWeekEnd.setUTCDate(isoWeekStart.getUTCDate() + 6);
        return {
            start: isoWeekStart,
            end: isoWeekEnd
        };
    }

    function formatShortDate(date) {
        var day = String(date.getUTCDate()).padStart(2, '0');
        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return day + ' ' + monthNames[date.getUTCMonth()];
    }

    function getIsoWeeksInYear(year) {
        var date = new Date(Date.UTC(year, 11, 28));
        var dayNumber = date.getUTCDay() || 7;
        date.setUTCDate(date.getUTCDate() + 4 - dayNumber);
        var yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
        return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
    }

    function buildWeekOptions(year) {
        var selectedWeek = weekSelect.value;
        var totalWeeks = getIsoWeeksInYear(year);
        weekSelect.innerHTML = '';
        for (var i = 1; i <= totalWeeks; i += 1) {
            var range = getIsoWeekRange(year, i);
            var option = document.createElement('option');
            option.value = String(i);
            option.textContent = 'Week ' + String(i).padStart(2, '0') + ' (' + formatShortDate(range.start) + ' - ' + formatShortDate(range.end) + ')';
            weekSelect.appendChild(option);
        }
        if (selectedWeek) {
            weekSelect.value = selectedWeek;
        }
        if (!weekSelect.value && weekSelect.options.length) {
            weekSelect.selectedIndex = 0;
        }
    }

    function updateFilterVisibility() {
        var type = filterType.value;
        var showYear = type === 'year' || type === 'month' || type === 'week';
        var showMonth = type === 'month';
        var showWeek = type === 'week';
        var showDate = type === 'date';

        yearSelect.disabled = !showYear;
        yearSelect.hidden = !showYear;
        monthSelect.disabled = !showMonth;
        monthSelect.hidden = !showMonth;
        weekSelect.disabled = !showWeek;
        weekSelect.hidden = !showWeek;
        dateRangeFields.hidden = !showDate;
        startDateInput.disabled = !showDate;
        endDateInput.disabled = !showDate;
        updateSelectedDays();
    }

    function getSelectedDayCount() {
        if (filterType.value !== 'date' || !startDateInput.value || !endDateInput.value) {
            return 0;
        }

        var start = new Date(startDateInput.value + 'T00:00:00');
        var end = new Date(endDateInput.value + 'T00:00:00');
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
            return 0;
        }

        return Math.floor((end - start) / 86400000) + 1;
    }

    function updateSelectedDays() {
        var dayCount = getSelectedDayCount();
        var shouldShow = filterType.value === 'date' && dayCount > 0;
        selectedDaysState.hidden = !shouldShow;
        if (!shouldShow) {
            selectedDaysState.textContent = 'Selected days: 0 day';
            return;
        }
        selectedDaysState.textContent = 'Selected days: ' + formatNumber(dayCount) + ' day' + (dayCount === 1 ? '' : 's');
    }

    function setLoading(isLoading) {
        submitButton.disabled = isLoading;
        loadingState.hidden = !isLoading;
    }

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function hideError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function renderSummary(summary) {
        var cards = [
            { label: 'Total Bookings', value: formatNumber(summary.total_bookings), meta: 'Total booking records' },
            { label: 'Unique Users', value: formatNumber(summary.unique_users), meta: 'Distinct users in result' },
            { label: 'Total Hours', value: formatHours(summary.total_hours), meta: 'Combined reservation hours' },
            { label: 'Average Hours', value: formatHours(summary.average_hours), meta: 'Average per booking' }
        ];

        summaryCards.innerHTML = cards.map(function (card) {
            return '<div class="card stat-card"><p class="stat-label">' + escapeHtml(card.label) + '</p><h2 class="stat-value">' + escapeHtml(card.value) + '</h2><span class="stat-meta">' + escapeHtml(card.meta) + '</span></div>';
        }).join('');
    }

    function renderSingleBarChart(rows) {
        var colors = getThemeColors();
        var width = Math.max(680, rows.length * 84);
        var height = 320;
        var margin = { top: 20, right: 20, bottom: 58, left: 52 };
        var chartWidth = width - margin.left - margin.right;
        var chartHeight = height - margin.top - margin.bottom;
        var maxValue = rows.reduce(function (max, row) { return Math.max(max, Number(row.value) || 0); }, 0);
        var scaleMax = getNiceScaleMax(maxValue);
        var step = chartWidth / rows.length;
        var barWidth = Math.max(14, Math.min(44, step * 0.58));
        var ticks = [0, scaleMax * 0.25, scaleMax * 0.5, scaleMax * 0.75, scaleMax];
        var render = createHiDPICanvas(width, height);
        var context = render.context;
        var regions = [];

        context.clearRect(0, 0, width, height);

        ticks.forEach(function (tick) {
            var y = margin.top + chartHeight - ((tick / scaleMax) * chartHeight);
            context.strokeStyle = colors.grid;
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(margin.left, y);
            context.lineTo(width - margin.right, y);
            context.stroke();
            drawText(context, formatNumber(Math.round(tick)), margin.left - 8, y + 4, { color: colors.axis, font: '11px Plus Jakarta Sans', align: 'right' });
        });

        rows.forEach(function (row, index) {
            var value = Number(row.value) || 0;
            var x = margin.left + (index * step) + ((step - barWidth) / 2);
            var barHeight = scaleMax > 0 ? (value / scaleMax) * chartHeight : 0;
            var y = margin.top + chartHeight - barHeight;
            var radius = 10;
            context.fillStyle = colors.accent;
            context.beginPath();
            context.moveTo(x + radius, y);
            context.lineTo(x + barWidth - radius, y);
            context.quadraticCurveTo(x + barWidth, y, x + barWidth, y + radius);
            context.lineTo(x + barWidth, y + Math.max(2, barHeight));
            context.lineTo(x, y + Math.max(2, barHeight));
            context.lineTo(x, y + radius);
            context.quadraticCurveTo(x, y, x + radius, y);
            context.fill();

            drawText(context, formatNumber(value), x + (barWidth / 2), y - 8, { color: colors.axis, font: '700 11px Plus Jakarta Sans', align: 'center' });
            drawText(context, row.label, x + (barWidth / 2), height - 18, { color: colors.axis, font: '11px Plus Jakarta Sans', align: 'center' });

            regions.push({
                type: 'rect',
                x: x,
                y: y,
                width: barWidth,
                height: Math.max(2, barHeight),
                tooltip: row.label + ': ' + formatNumber(value) + ' booking(s)'
            });
        });

        barChart.innerHTML = '<div class="report-canvas-wrap"></div>';
        barChart.querySelector('.report-canvas-wrap').appendChild(render.canvas);
        attachCanvasTooltip(render.canvas, regions);
        barMeta.textContent = rows.length + ' bucket(s) | Peak: ' + formatNumber(maxValue);
    }

    function renderStackedBarChart(rows) {
        var colors = getThemeColors();
        var width = 860;
        var height = 320;
        var margin = { top: 20, right: 20, bottom: 58, left: 52 };
        var chartWidth = width - margin.left - margin.right;
        var chartHeight = height - margin.top - margin.bottom;
        var maxValue = rows.reduce(function (max, row) { return Math.max(max, Number(row.total) || 0); }, 0);
        var scaleMax = getNiceScaleMax(maxValue);
        var step = chartWidth / rows.length;
        var barWidth = Math.max(14, Math.min(44, step * 0.58));
        var ticks = [0, scaleMax * 0.25, scaleMax * 0.5, scaleMax * 0.75, scaleMax];

        var render = createHiDPICanvas(width, height);
        var context = render.context;
        var regions = [];
        ticks.forEach(function (tick) {
            var y = margin.top + chartHeight - ((tick / scaleMax) * chartHeight);
            context.strokeStyle = colors.grid;
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(margin.left, y);
            context.lineTo(width - margin.right, y);
            context.stroke();
            drawText(context, formatNumber(Math.round(tick)), margin.left - 8, y + 4, { color: colors.axis, font: '11px Plus Jakarta Sans', align: 'right' });
        });

        rows.forEach(function (row, index) {
            var x = margin.left + (index * step) + ((step - barWidth) / 2);
            var currentHeight = 0;
            STATUS_KEYS.forEach(function (statusKey) {
                var key = statusKey.toLowerCase();
                var value = Number(row[key]) || 0;
                if (!value) {
                    return;
                }
                var segmentHeight = scaleMax > 0 ? (value / scaleMax) * chartHeight : 0;
                var y = margin.top + chartHeight - currentHeight - segmentHeight;
                context.fillStyle = statusColor(statusKey);
                context.fillRect(x, y, barWidth, Math.max(2, segmentHeight));
                regions.push({
                    type: 'rect',
                    x: x,
                    y: y,
                    width: barWidth,
                    height: Math.max(2, segmentHeight),
                    tooltip: row.label + ' | ' + getStatusDisplayLabel(statusKey) + ': ' + formatNumber(value) + ' booking(s) | Total: ' + formatNumber(row.total)
                });
                currentHeight += segmentHeight;
            });
            drawText(context, formatNumber(row.total), x + (barWidth / 2), margin.top + chartHeight - currentHeight - 8, { color: colors.axis, font: '700 11px Plus Jakarta Sans', align: 'center' });
            drawText(context, row.label, x + (barWidth / 2), height - 18, { color: colors.axis, font: '11px Plus Jakarta Sans', align: 'center' });
        });

        var legend = STATUS_KEYS.map(function (statusKey) {
            return '<span class="report-inline-legend-item"><i style="background:' + statusColor(statusKey) + '"></i>' + escapeHtml(getStatusDisplayLabel(statusKey)) + '</span>';
        }).join('');

        barChart.innerHTML = '<div class="report-inline-legend">' + legend + '</div><div class="report-canvas-wrap"></div>';
        barChart.querySelector('.report-canvas-wrap').appendChild(render.canvas);
        attachCanvasTooltip(render.canvas, regions);
        barMeta.textContent = rows.length + ' bucket(s) | Stacked by status';
    }

    function renderPieChart(rows) {
        var total = rows.reduce(function (sum, row) { return sum + (Number(row.value) || 0); }, 0);
        if (!total) {
            pieChart.innerHTML = '<div class="empty-state">No status distribution available for the selected filter.</div>';
            return;
        }

        var width = 320;
        var height = 320;
        var centerX = 160;
        var centerY = 160;
        var radius = 120;
        var innerRadius = 74;
        var startAngle = 0;
        var render = createHiDPICanvas(width, height);
        var context = render.context;
        var regions = [];

        rows.forEach(function (row) {
            var value = Number(row.value) || 0;
            if (!value) {
                return;
            }
            var sliceAngle = (value / total) * Math.PI * 2;
            var endAngle = startAngle + sliceAngle;
            context.beginPath();
            context.moveTo(centerX, centerY);
            context.arc(centerX, centerY, radius, startAngle, endAngle);
            context.closePath();
            context.fillStyle = statusColor(row.label);
            context.fill();
            context.strokeStyle = '#ffffff';
            context.lineWidth = 3;
            context.stroke();

            regions.push({
                type: 'arc',
                cx: centerX,
                cy: centerY,
                innerRadius: innerRadius,
                outerRadius: radius,
                startAngle: (startAngle * 180 / Math.PI),
                endAngle: (endAngle * 180 / Math.PI),
                tooltip: row.label + ': ' + formatNumber(value) + ' booking(s) | ' + ((value / total) * 100).toFixed(1) + '%'
            });

            startAngle = endAngle;
        });

        context.beginPath();
        context.arc(centerX, centerY, innerRadius, 0, Math.PI * 2);
        context.fillStyle = '#ffffff';
        context.fill();
        drawText(context, formatNumber(total), centerX, centerY - 6, { color: '#1f2a37', font: '700 32px Plus Jakarta Sans', align: 'center', baseline: 'middle' });
        drawText(context, 'Total bookings', centerX, centerY + 20, { color: '#6b7280', font: '600 12px Plus Jakarta Sans', align: 'center', baseline: 'middle' });

        var legend = rows.map(function (row) {
            var value = Number(row.value) || 0;
            var percent = total ? ((value / total) * 100).toFixed(1) : '0.0';
            return '<div class="report-legend-item"><span class="report-legend-dot" style="background:' + statusColor(row.label) + '"></span><div><div class="report-legend-top"><strong>' + escapeHtml(row.label) + '</strong><b>' + escapeHtml(percent) + '%</b></div><span>' + escapeHtml(formatNumber(value)) + ' booking(s)</span></div></div>';
        }).join('');

        pieChart.innerHTML = '<div class="report-pie-layout"><div class="report-canvas-wrap report-donut-wrap"></div><div class="report-legend">' + legend + '</div></div>';
        pieChart.querySelector('.report-donut-wrap').appendChild(render.canvas);
        attachCanvasTooltip(render.canvas, regions);
    }

    function renderBarChart(payload) {
        var rows = payload.bar_mode === 'stacked' ? (payload.stacked_bar_chart || []) : (payload.bar_chart || []);
        if (!rows.length) {
            barChart.innerHTML = '<div class="empty-state">No bookings found for the selected filter.</div>';
            barMeta.textContent = '0 records';
            return;
        }

        if (payload.bar_mode === 'stacked') {
            renderStackedBarChart(rows);
        } else {
            renderSingleBarChart(rows);
        }
    }

    function renderTable(rows) {
        tableMeta.textContent = rows.length + ' record(s)';
        if (!rows.length) {
            tableWrapper.innerHTML = '<div class="empty-state">No bookings found for the selected filter.</div>';
            return;
        }

        tableWrapper.innerHTML = '<table><thead><tr><th>#</th><th>Booking ID</th><th>Title</th><th>User</th><th>Cluster</th><th>Lab</th><th>Date</th><th>Time</th><th>Status</th><th>Hours</th></tr></thead><tbody>' +
            rows.map(function (row, index) {
                var timeLabel = row.start_time && row.end_time ? (row.start_time + ' - ' + row.end_time) : (row.time_slot || '-');
                var statusLabel = getStatusDisplayLabel(row.status);
                return '<tr><td>' + (index + 1) + '</td><td>' + escapeHtml(row.booking_id) + '</td><td>' + escapeHtml(row.title) + '</td><td>' + escapeHtml(row.full_name) + '<div class="muted-text">User ID: ' + escapeHtml(row.user_id) + '</div></td><td>' + escapeHtml(row.cluster_name) + '</td><td>' + escapeHtml(row.lab_name) + '</td><td>' + escapeHtml(row.booking_date) + '</td><td>' + escapeHtml(timeLabel) + '</td><td><span class="status ' + escapeHtml(row.status) + '">' + escapeHtml(statusLabel) + '</span></td><td>' + escapeHtml(formatHours(row.total_hours)) + '</td></tr>';
            }).join('') +
            '</tbody></table>';
    }

    function loadLabs(clusterId) {
        if (!clusterId) {
            labSelect.innerHTML = '<option value="">All labs</option>';
            labSelect.disabled = true;
            return Promise.resolve();
        }
        labSelect.disabled = true;
        labSelect.innerHTML = '<option value="">Loading labs...</option>';
        return fetch(config.endpoint + '?action=labs&cluster_id=' + encodeURIComponent(clusterId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                var labs = Array.isArray(payload.labs) ? payload.labs : [];
                labSelect.innerHTML = '<option value="">All labs</option>' + labs.map(function (lab) {
                    return '<option value="' + escapeHtml(lab.lab_id) + '">' + escapeHtml(lab.lab_name) + '</option>';
                }).join('');
                labSelect.disabled = false;
            })
            .catch(function () {
                labSelect.innerHTML = '<option value="">All labs</option>';
                labSelect.disabled = false;
            });
    }

    function buildQueryString() {
        var params = new URLSearchParams();
        params.set('action', 'report');
        params.set('filter_type', filterType.value);
        if (filterType.value !== 'date') {
            params.set('year', yearSelect.value);
        }
        if (filterType.value === 'month') {
            params.set('month', monthSelect.value);
        }
        if (filterType.value === 'week') {
            params.set('week', weekSelect.value);
        }
        if (filterType.value === 'date') {
            params.set('start_date', startDateInput.value);
            params.set('end_date', endDateInput.value);
        }
        if (clusterSelect.value) {
            params.set('cluster_id', clusterSelect.value);
        }
        if (labSelect.value) {
            params.set('lab_id', labSelect.value);
        }
        return params.toString();
    }

    function fetchReport() {
        hideError();
        setLoading(true);
        fetch(config.endpoint + '?' + buildQueryString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.payload.success) {
                    throw new Error(result.payload && result.payload.message ? result.payload.message : 'Unable to load report.');
                }
                description.textContent = result.payload.filter_label || 'Report loaded.';
                if (filterType.value === 'date' && Number(result.payload.selected_days) > 0) {
                    selectedDaysState.hidden = false;
                    selectedDaysState.textContent = 'Selected days: ' + formatNumber(result.payload.selected_days) + ' day' + (Number(result.payload.selected_days) === 1 ? '' : 's');
                }
                renderSummary(result.payload.summary || {});
                renderBarChart(result.payload);
                renderPieChart(result.payload.status_chart || []);
                renderTable(result.payload.table || []);
            })
            .catch(function (error) {
                showError(error.message || 'Unable to load report.');
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function exportChart(targetId, format) {
        var container = document.getElementById(targetId);
        if (!container) {
            return;
        }
        var canvas = container.querySelector('canvas');
        if (!canvas) {
            showError('No chart available to export yet.');
            return;
        }
        var dataUrl = canvas.toDataURL('image/png');
        if (format === 'png') {
            var link = document.createElement('a');
            link.href = dataUrl;
            link.download = targetId + '.png';
            link.click();
            return;
        }
        var pdfWindow = window.open('', '_blank');
        if (!pdfWindow) {
            showError('Popup blocked. Allow popups to export PDF.');
            return;
        }
        pdfWindow.document.write('<html><head><title>Export PDF</title><style>body{margin:0;padding:24px;font-family:Arial,sans-serif;background:#fff}img{max-width:100%;height:auto;display:block;margin:0 auto}</style></head><body><img src="' + dataUrl + '" alt="Chart export"></body></html>');
        pdfWindow.document.close();
        pdfWindow.focus();
        pdfWindow.print();
    }

    filterType.addEventListener('change', updateFilterVisibility);
    yearSelect.addEventListener('change', function () {
        buildWeekOptions(Number(yearSelect.value));
    });
    startDateInput.addEventListener('change', function () {
        if (endDateInput.value && startDateInput.value && endDateInput.value < startDateInput.value) {
            endDateInput.value = startDateInput.value;
        }
        updateSelectedDays();
    });
    endDateInput.addEventListener('change', updateSelectedDays);
    clusterSelect.addEventListener('change', function () {
        loadLabs(clusterSelect.value);
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        fetchReport();
    });

    document.querySelectorAll('.report-export').forEach(function (button) {
        button.addEventListener('click', function () {
            exportChart(button.getAttribute('data-chart-target') || '', button.getAttribute('data-export-format') || 'png');
        });
    });

    yearSelect.value = String(config.defaultYear || yearSelect.value);
    monthSelect.value = String(config.defaultMonth || monthSelect.value);
    endDateInput.value = endDateInput.value || startDateInput.value;
    buildWeekOptions(Number(yearSelect.value));
    updateFilterVisibility();
    fetchReport();
})();
