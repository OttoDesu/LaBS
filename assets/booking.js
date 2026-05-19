(function () {
    function toggleModal(id, open) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }
        modal.classList.toggle('active', open);
    }

    function wireClusterModal() {
        var button = document.getElementById('add-cluster-btn');
        var form = document.getElementById('cluster-form');
        if (!button || !form) {
            return;
        }
        button.addEventListener('click', function () {
            form.reset();
            toggleModal('cluster-modal', true);
        });

        document.querySelectorAll('[data-close="cluster-modal"]').forEach(function (close) {
            close.addEventListener('click', function () {
                toggleModal('cluster-modal', false);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var formData = new FormData(form);
            fetch('add-cluster.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        alert((data.errors || ['Unable to save cluster.']).join('\n'));
                        return;
                    }
                    if (window.LABS_DATA && window.LABS_DATA.clusters) {
                        window.LABS_DATA.clusters.push({
                            cluster_id: data.cluster_id,
                            cluster_name: data.cluster_name,
                            cluster_description: data.cluster_description
                        });
                    }
                    if (window.LABS_DATA && window.LABS_DATA.clusterMeta) {
                        window.LABS_DATA.clusterMeta[String(data.cluster_id)] = { labs: [], assets: [] };
                    }
                    renderClusters();
                    toggleModal('cluster-modal', false);
                })
                .catch(function () {
                    alert('Unable to save cluster.');
                });
        });
    }
    function getClusterFilters() {
        var industrySelect = document.getElementById('filter-industry');
        var labInput = document.getElementById('filter-lab');
        var assetInput = document.getElementById('filter-asset');
        return {
            industry: industrySelect ? industrySelect.value : 'all',
            lab: labInput ? labInput.value.trim().toLowerCase() : '',
            asset: assetInput ? assetInput.value.trim().toLowerCase() : ''
        };
    }

    function clusterMatchesFilters(cluster, filters, clusterMeta) {
        if (filters.industry !== 'all' && String(cluster.cluster_id) !== String(filters.industry)) {
            return false;
        }
        if (!filters.lab && !filters.asset) {
            return true;
        }
        var meta = clusterMeta[String(cluster.cluster_id)] || clusterMeta[cluster.cluster_id] || { labs: [], assets: [] };
        if (filters.lab) {
            var hasLab = meta.labs.some(function (labName) {
                return String(labName || '').toLowerCase().indexOf(filters.lab) !== -1;
            });
            if (!hasLab) {
                return false;
            }
        }
        if (filters.asset) {
            var hasAsset = meta.assets.some(function (assetName) {
                return String(assetName || '').toLowerCase().indexOf(filters.asset) !== -1;
            });
            if (!hasAsset) {
                return false;
            }
        }
        return true;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function highlightText(text, term) {
        var safeText = escapeHtml(text);
        if (!term) {
            return safeText;
        }
        var lowerText = String(text || '').toLowerCase();
        var lowerTerm = term.toLowerCase();
        var result = '';
        var startIndex = 0;
        var matchIndex = lowerText.indexOf(lowerTerm);
        while (matchIndex !== -1) {
            result += escapeHtml(text.slice(startIndex, matchIndex));
            result += '<span class="match-highlight">' + escapeHtml(text.slice(matchIndex, matchIndex + term.length)) + '</span>';
            startIndex = matchIndex + term.length;
            matchIndex = lowerText.indexOf(lowerTerm, startIndex);
        }
        result += escapeHtml(text.slice(startIndex));
        return result;
    }
    function renderClusters() {
        var container = document.getElementById('cluster-list');
        if (!container || !window.LABS_DATA || !window.LABS_DATA.clusters) {
            return;
        }
        var filters = getClusterFilters();
        var clusterMeta = window.LABS_DATA.clusterMeta || {};
        container.innerHTML = '';
        var matches = 0;
        window.LABS_DATA.clusters.forEach(function (cluster) {
            if (!clusterMatchesFilters(cluster, filters, clusterMeta)) {
                return;
            }
            var matchLines = [];
            if (filters.lab) {
                var matchingLabs = (clusterMeta[String(cluster.cluster_id)] || clusterMeta[cluster.cluster_id] || { labs: [] }).labs
                    .filter(function (labName) {
                        return String(labName || '').toLowerCase().indexOf(filters.lab) !== -1;
                    })
                    .map(function (labName) {
                        return highlightText(labName, filters.lab);
                    });
                if (matchingLabs.length) {
                    matchLines.push('<div class="cluster-match">Matching labs: ' + matchingLabs.join(', ') + '</div>');
                }
            }
            if (filters.asset) {
                var matchingAssets = (clusterMeta[String(cluster.cluster_id)] || clusterMeta[cluster.cluster_id] || { assets: [] }).assets
                    .filter(function (assetName) {
                        return String(assetName || '').toLowerCase().indexOf(filters.asset) !== -1;
                    })
                    .map(function (assetName) {
                        return highlightText(assetName, filters.asset);
                    });
                if (matchingAssets.length) {
                    matchLines.push('<div class="cluster-match">Matching assets: ' + matchingAssets.join(', ') + '</div>');
                }
            }
            var card = document.createElement('div');
            card.className = 'cluster-card';
            card.innerHTML = [
                '<span class="badge">Cluster</span>',
                '<h3>' + escapeHtml(cluster.cluster_name) + '</h3>',
                '<p>' + escapeHtml(cluster.cluster_description || '') + '</p>',
                matchLines.join(''),
                '<a class="btn primary small" href="labs.php?cluster_id=' + cluster.cluster_id + '">View laboratories</a>'
            ].join('');
            container.appendChild(card);
            matches += 1;
        });
        if (matches === 0) {
            container.innerHTML = '<div class="empty-state">No clusters match your filters.</div>';
        }
    }

    var currentLabPage = 1;
    var labsPerPage = 6;

    function renderLabs(page) {
        var container = document.getElementById('lab-list');
        var summary = document.getElementById('lab-search-summary');
        var pagination = document.getElementById('lab-pagination');
        if (!container || !window.LABS_DATA || !window.LABS_DATA.labs) {
            return;
        }
        if (typeof page === 'number' && page > 0) {
            currentLabPage = page;
        }
        var searchInput = document.getElementById('lab-search');
        var searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
        var totalLabs = window.LABS_DATA.labs.length;
        var matchedLabs = [];
        container.innerHTML = '';
        window.LABS_DATA.labs.forEach(function (lab) {
            var labName = (lab.lab_name || '').toLowerCase();
            var supervisorName = (lab.supervisor_name || '').toLowerCase();
            var assets = lab.assets || [];
            var assetsText = assets.map(function (asset) {
                return String(asset || '').toLowerCase();
            }).join(' ');
            if (searchTerm) {
                var inName = labName.indexOf(searchTerm) !== -1;
                var inSupervisor = supervisorName.indexOf(searchTerm) !== -1;
                var inAssets = assetsText.indexOf(searchTerm) !== -1;
                if (!inName && !inSupervisor && !inAssets) {
                    return;
                }
            }
            matchedLabs.push(lab);
        });

        var matched = matchedLabs.length;
        var totalPages = Math.max(1, Math.ceil(matched / labsPerPage));
        if (currentLabPage > totalPages) {
            currentLabPage = totalPages;
        }
        var startIndex = (currentLabPage - 1) * labsPerPage;
        var visibleLabs = matchedLabs.slice(startIndex, startIndex + labsPerPage);

        visibleLabs.forEach(function (lab) {
            var assets = lab.assets || [];
            var matchedAssets = [];
            if (searchTerm) {
                matchedAssets = assets.filter(function (asset) {
                    return String(asset || '').toLowerCase().indexOf(searchTerm) !== -1;
                });
            }
            var card = document.createElement('div');
            card.className = 'lab-card';
            var matchLine = '';
            if (searchTerm && matchedAssets.length) {
                matchLine = '<div class="lab-match">Matching asset: ' + matchedAssets.join(', ') + '</div>';
            }
            var maintenanceLine = '';
            if (lab.maintenance_status === 'maintenance') {
                var maintenanceLabel = '';
                if (lab.maintenance_start_date && lab.maintenance_end_date) {
                    maintenanceLabel = lab.maintenance_start_date + ' to ' + lab.maintenance_end_date;
                } else {
                    maintenanceLabel = 'Schedule pending';
                }
                maintenanceLine = '<div class="lab-match">Maintenance: ' + escapeHtml(maintenanceLabel) + '</div>';
            }
            card.innerHTML = [
                '<h3>' + escapeHtml(lab.lab_name || '') + '</h3>',
                '<div class="lab-meta">',
                (lab.cluster_name ? '<div><span>Cluster:</span> ' + escapeHtml(lab.cluster_name) + '</div>' : ''),
                '<div><span>Capacity:</span> ' + (lab.lab_capacity || '-') + '</div>',
                '<div><span>Supervisor:</span> ' + escapeHtml(lab.supervisor_name || '-') + '</div>',
                '</div>',
                (lab.maintenance_status === 'maintenance' ? '<div class="cluster-match">Under maintenance</div>' : ''),
                maintenanceLine,
                matchLine,
                '<a class="btn primary" href="availability.php?lab_id=' + encodeURIComponent(lab.lab_id) + '">Select Lab</a>'
            ].join('');
            container.appendChild(card);
        });
        if (matched === 0) {
            container.innerHTML = '<div class="empty-state">No labs match your search.</div>';
        }
        if (pagination) {
            if (matched > 0 && totalPages > 1) {
                pagination.hidden = false;
                var prevDisabled = currentLabPage <= 1 ? ' is-disabled' : '';
                var nextDisabled = currentLabPage >= totalPages ? ' is-disabled' : '';
                pagination.innerHTML = [
                    '<button class="btn ghost small' + prevDisabled + '" type="button" data-page-action="prev">Previous</button>',
                    '<div class="pagination-status">Page ' + currentLabPage + ' of ' + totalPages + '</div>',
                    '<button class="btn ghost small' + nextDisabled + '" type="button" data-page-action="next">Next</button>'
                ].join('');
                var prevButton = pagination.querySelector('[data-page-action="prev"]');
                var nextButton = pagination.querySelector('[data-page-action="next"]');
                if (prevButton) {
                    prevButton.addEventListener('click', function () {
                        if (currentLabPage > 1) {
                            renderLabs(currentLabPage - 1);
                        }
                    });
                }
                if (nextButton) {
                    nextButton.addEventListener('click', function () {
                        if (currentLabPage < totalPages) {
                            renderLabs(currentLabPage + 1);
                        }
                    });
                }
            } else {
                pagination.hidden = true;
                pagination.innerHTML = '';
            }
        }
        if (summary) {
            if (searchTerm) {
                summary.textContent = 'Jumlah makmal dipaparkan: ' + matched + ' / ' + totalLabs;
            } else {
                summary.textContent = 'Total makmal: ' + totalLabs;
            }
        }
    }

    function renderCalendar() {
        var availability = window.LABS_AVAILABILITY;
        if (!availability) {
            return;
        }

        var month = availability.month;
        var year = availability.year;
        var bookedByDate = availability.bookedByDate || {};
        var timeSlots = availability.timeSlots || [];
        var maintenanceStatus = availability.maintenanceStatus || 'available';
        var maintenanceStartDate = availability.maintenanceStartDate || null;
        var maintenanceEndDate = availability.maintenanceEndDate || null;
        var maintenanceLabel = availability.maintenanceLabel || 'Maintenance schedule not set.';

        var calendarGrid = document.getElementById('calendar-grid');
        var calendarTitle = document.getElementById('calendar-title');
        var slotGrid = document.getElementById('slot-grid');
        var slotTitle = document.getElementById('slot-title');
        var bookedList = document.getElementById('booked-slots');
        var bookingDateInput = document.getElementById('booking-date');
        var bookingSlotsInput = document.getElementById('booking-slots') || document.getElementById('booking-slot');
        var bookingSubmit = document.getElementById('booking-submit');
        var bookingHint = document.getElementById('booking-hint');
        var selectedDisplay = document.getElementById('selected-display');

        if (!calendarGrid || !slotGrid || !bookingDateInput || !bookingSlotsInput || !bookingSubmit) {
            return;
        }

        var selectedSlots = [];

        var monthIndex = month - 1;
        var firstDay = new Date(year, monthIndex, 1);
        var startDay = firstDay.getDay();
        var totalDays = new Date(year, monthIndex + 1, 0).getDate();
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var minDate = new Date(today);
        minDate.setDate(minDate.getDate() + 3);

        calendarGrid.innerHTML = '';
        var labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        labels.forEach(function (label) {
            var labelCell = document.createElement('div');
            labelCell.className = 'calendar-label';
            labelCell.textContent = label;
            calendarGrid.appendChild(labelCell);
        });

        for (var i = 0; i < startDay; i++) {
            var emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-cell disabled';
            calendarGrid.appendChild(emptyCell);
        }

        var selectedDate = null;

        function formatKey(date) {
            var monthValue = String(date.getMonth() + 1).padStart(2, '0');
            var dayValue = String(date.getDate()).padStart(2, '0');
            return date.getFullYear() + '-' + monthValue + '-' + dayValue;
        }

        function formatDate(date) {
            return date.toLocaleDateString('en-GB', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        }

        function updateSelectedDisplay() {
            if (selectedDisplay) {
                if (selectedSlots.length === 0) {
                    selectedDisplay.style.display = 'none';
                    selectedDisplay.textContent = '';
                } else {
                    selectedDisplay.style.display = 'block';
                    selectedDisplay.textContent = 'Selected: ' + selectedSlots.join(', ');
                }
            }
            bookingSubmit.textContent = selectedSlots.length === 0
                ? 'Make Reservation'
                : 'Make Reservation (' + selectedSlots.length + ' slot' + (selectedSlots.length !== 1 ? 's' : '') + ')';
            bookingSlotsInput.value = bookingSlotsInput.id === 'booking-slots'
                ? JSON.stringify(selectedSlots)
                : (selectedSlots[0] || '');
            bookingSubmit.disabled = !selectedSlots.length || !bookingDateInput.value;
        }

        function isMaintenanceDate(dateKey) {
            if (maintenanceStatus !== 'maintenance') {
                return false;
            }
            if (maintenanceStartDate && dateKey < maintenanceStartDate) {
                return false;
            }
            if (maintenanceEndDate && dateKey > maintenanceEndDate) {
                return false;
            }
            return true;
        }

        function renderSlots(dateKey, isRestricted, isMaintenance) {
            var bookedSlots = bookedByDate[dateKey] || [];
            slotGrid.innerHTML = '';
            selectedSlots = [];
            updateSelectedDisplay();
            
            timeSlots.forEach(function (slot) {
                var isBooked = bookedSlots.indexOf(slot) !== -1;
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot-button';
                button.textContent = slot;
                if (isBooked || isRestricted || isMaintenance) {
                    button.classList.add('booked');
                    button.disabled = true;
                }
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (button.classList.contains('booked')) {
                        return;
                    }
                    button.classList.toggle('selected');
                    var index = selectedSlots.indexOf(slot);
                    if (index !== -1) {
                        selectedSlots.splice(index, 1);
                    } else {
                        selectedSlots.push(slot);
                    }
                    selectedSlots.sort();
                    updateSelectedDisplay();
                    if (bookingHint) {
                        bookingHint.textContent = '';
                    }
                });
                slotGrid.appendChild(button);
            });

            if (bookedList) {
                if (isMaintenance) {
                    bookedList.textContent = 'Lab is under maintenance on this date.';
                } else if (bookedSlots.length === 0) {
                    bookedList.textContent = 'No bookings for this date.';
                } else {
                    bookedList.innerHTML = bookedSlots.map(function (slot) {
                        return '<span class="badge">' + slot + '</span>';
                    }).join(' ');
                }
            }
        }

        function selectDate(dateKey) {
            selectedDate = dateKey;
            bookingDateInput.value = dateKey;
            selectedSlots = [];
            updateSelectedDisplay();
            if (slotTitle) {
                slotTitle.textContent = 'Slots on ' + formatDate(new Date(dateKey + 'T00:00:00'));
            }
            var minDateKey = formatKey(minDate);
            var isRestricted = dateKey < minDateKey;
            var isMaintenance = isMaintenanceDate(dateKey);
            renderSlots(dateKey, isRestricted, isMaintenance);
            bookingSubmit.disabled = true;
            if (bookingHint) {
                if (isRestricted) {
                    bookingHint.textContent = 'Bookings must be made at least 3 days in advance.';
                } else if (isMaintenance) {
                    bookingHint.textContent = 'This lab is under maintenance on ' + dateKey + ' (' + maintenanceLabel + ').';
                } else {
                    bookingHint.textContent = 'Please pick one or more time slots to continue.';
                }
            }
        }

        for (var day = 1; day <= totalDays; day++) {
            var cellDate = new Date(year, monthIndex, day);
            var dateKey = formatKey(cellDate);
            var cell = document.createElement('div');
            cell.className = 'calendar-cell';
            var bookingCount = (bookedByDate[dateKey] || []).length;
            var cellIsMaintenance = isMaintenanceDate(dateKey);

            cell.innerHTML = [
                '<div class="calendar-day">' + day + '</div>',
                '<div class="calendar-meta">' + (cellIsMaintenance ? 'Maintenance' : (bookingCount + ' bookings')) + '</div>'
            ].join('');

            if (cellDate < minDate || cellIsMaintenance) {
                cell.classList.add('disabled');
            }
            cell.addEventListener('click', function () {
                if (this.classList.contains('disabled')) {
                    return;
                }
                var selectedKey = this.getAttribute('data-date');
                var cells = calendarGrid.querySelectorAll('.calendar-cell');
                cells.forEach(function (item) { item.classList.remove('selected'); });
                this.classList.add('selected');
                selectDate(selectedKey);
            });

            cell.setAttribute('data-date', dateKey);
            calendarGrid.appendChild(cell);

            if (!selectedDate && cellDate >= minDate && !cellIsMaintenance) {
                selectedDate = dateKey;
            }
        }

        if (calendarTitle) {
            calendarTitle.textContent = new Date(year, monthIndex, 1).toLocaleDateString('en-GB', {
                month: 'long',
                year: 'numeric'
            });
        }

        if (selectedDate) {
            var defaultCell = calendarGrid.querySelector('[data-date="' + selectedDate + '"]');
            if (defaultCell && !defaultCell.classList.contains('disabled')) {
                defaultCell.classList.add('selected');
                selectDate(selectedDate);
            }
        }
    }

    renderClusters();
    renderLabs();
    renderCalendar();
    wireClusterModal();

    var filterApply = document.getElementById('filter-apply');
    var filterReset = document.getElementById('filter-reset');
    var filterLab = document.getElementById('filter-lab');
    var filterAsset = document.getElementById('filter-asset');
    var filterIndustry = document.getElementById('filter-industry');
    if (filterApply) {
        filterApply.addEventListener('click', renderClusters);
    }
    if (filterReset) {
        filterReset.addEventListener('click', function () {
            if (filterIndustry) {
                filterIndustry.value = 'all';
            }
            if (filterLab) {
                filterLab.value = '';
            }
            if (filterAsset) {
                filterAsset.value = '';
            }
            renderClusters();
        });
    }
    [filterLab, filterAsset].forEach(function (input) {
        if (!input) {
            return;
        }
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                renderClusters();
            }
        });
    });

    var labSearch = document.getElementById('lab-search');
    var labSearchBtn = document.getElementById('lab-search-btn');
    if (labSearch) {
        labSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                currentLabPage = 1;
                renderLabs();
            }
        });
    }
    if (labSearchBtn) {
        labSearchBtn.addEventListener('click', function () {
            currentLabPage = 1;
            renderLabs();
        });
    }
})();
