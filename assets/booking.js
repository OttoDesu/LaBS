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

    function formatDateKey(dateKey) {
        var value = String(dateKey || '');
        var match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) {
            return value;
        }
        return match[3] + '/' + match[2] + '/' + match[1];
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
                    maintenanceLabel = formatDateKey(lab.maintenance_start_date) + ' to ' + formatDateKey(lab.maintenance_end_date);
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
        var bookedDetailsByDate = availability.bookedDetailsByDate || {};
        var timeSlots = availability.timeSlots || [];
        var bookingMode = availability.bookingMode === 'group' ? 'group' : 'slot';
        var maintenanceStatus = availability.maintenanceStatus || 'available';
        var maintenanceStartDate = availability.maintenanceStartDate || null;
        var maintenanceEndDate = availability.maintenanceEndDate || null;
        var maintenanceLabel = availability.maintenanceLabel || 'Maintenance schedule not set.';
        var labName = availability.labName || 'this lab';
        var labId = Number(availability.labId || 0);
        var canViewCalendarHistory = availability.canViewCalendarHistory === true;

        var calendarGrid = document.getElementById('calendar-grid');
        var calendarTitle = document.getElementById('calendar-title');
        var slotGrid = document.getElementById('slot-grid');
        var slotTitle = document.getElementById('slot-title');
        var bookingDateInput = document.getElementById('booking-date');
        var bookingSlotsInput = document.getElementById('booking-slots') || document.getElementById('booking-slot');
        var bookingSubmit = document.getElementById('booking-submit');
        var bookingHint = document.getElementById('booking-hint');
        var selectedDisplay = document.getElementById('selected-display');
        var bookedSlotsModal = document.getElementById('booked-slots-modal');
        var bookedSlotsModalTitle = document.getElementById('booked-slots-modal-title');
        var bookedSlotsModalSubtitle = document.getElementById('booked-slots-modal-subtitle');
        var bookedSlotsModalList = document.getElementById('booked-slots-modal-list');
        var bookedSlotsModalClose = document.getElementById('booked-slots-modal-close');
        var bookedSlotsModalBookNow = document.getElementById('booked-slots-modal-book-now');

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
            return formatDateKey(formatKey(date));
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
            if (bookingMode === 'group') {
                bookingSubmit.textContent = bookingDateInput.value ? 'Continue Group Booking' : 'Select Reference Date';
                bookingSlotsInput.value = bookingSlotsInput.id === 'booking-slots' ? '[]' : '';
                bookingSubmit.disabled = !bookingDateInput.value;
                return;
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

        function closeBookedSlotsModal() {
            if (!bookedSlotsModal) {
                return;
            }
            bookedSlotsModal.classList.remove('active');
            bookedSlotsModal.setAttribute('aria-hidden', 'true');
        }

        function slotBounds(slot) {
            var match = String(slot || '').match(/^(\d{2}:\d{2})-(\d{2}:\d{2})$/);
            if (!match) {
                return null;
            }
            return {
                start: match[1],
                end: match[2]
            };
        }

        function groupBookedSlotDetails(bookedSlotDetails) {
            var groups = [];
            bookedSlotDetails.forEach(function (item) {
                var label = item && item.label ? item.label : 'Booked';
                var bookingType = item && item.booking_type ? String(item.booking_type) : 'booked';
                var bookingMode = item && item.booking_mode ? String(item.booking_mode) : 'slot';
                var modalGroupKey = item && item.modal_group_key ? String(item.modal_group_key) : '';
                var groupKeySource = item && item.group_booking_key ? String(item.group_booking_key) : '';
                var identity = bookingMode === 'group'
                    ? ['group', groupKeySource || modalGroupKey || label, bookingType].join('|')
                    : ['slot', modalGroupKey || label, bookingType].join('|');
                var bounds = slotBounds(item && item.time_slot ? item.time_slot : '');
                var previous = groups.length ? groups[groups.length - 1] : null;

                if (
                    previous
                    && previous.identity === identity
                    && previous.lastEnd
                    && bounds
                    && previous.lastEnd === bounds.start
                ) {
                    previous.time_slot = previous.startTime + '-' + bounds.end;
                    previous.lastEnd = bounds.end;
                    previous.items.push(item);
                    previous.can_edit_group = previous.can_edit_group || !!(item && item.can_edit_group);
                    if (!previous.reservation_id && item && item.reservation_id) {
                        previous.reservation_id = item.reservation_id;
                    }
                    return;
                }

                groups.push({
                    identity: identity,
                    label: label,
                    booking_type: bookingType,
                    booking_mode: bookingMode,
                    reservation_id: item && item.reservation_id ? item.reservation_id : 0,
                    can_edit_group: !!(item && item.can_edit_group),
                    time_slot: item && item.time_slot ? item.time_slot : 'Booked',
                    startTime: bounds ? bounds.start : null,
                    lastEnd: bounds ? bounds.end : null,
                    items: [item]
                });
            });
            return groups;
        }

        function openBookedSlotsModal(dateKey, bookedSlotDetails, canBookNow) {
            if (!bookedSlotsModal || !bookedSlotsModalList || !bookedSlotDetails.length) {
                return;
            }
            var allowBooking = canBookNow !== false;
            var groupedSlotDetails = groupBookedSlotDetails(bookedSlotDetails);
            if (bookedSlotsModalTitle) {
                bookedSlotsModalTitle.textContent = bookedSlotDetails.length + ' slot' + (bookedSlotDetails.length === 1 ? '' : 's') + ' booked for ' + labName;
            }
            if (bookedSlotsModalSubtitle) {
                bookedSlotsModalSubtitle.textContent = 'Booking details on ' + dateKey;
            }
            if (bookedSlotsModalBookNow) {
                bookedSlotsModalBookNow.disabled = !allowBooking;
                bookedSlotsModalBookNow.textContent = allowBooking ? 'Book Now' : 'View Only';
            }
            bookedSlotsModalList.innerHTML = groupedSlotDetails.map(function (item) {
                var label = item && item.label ? item.label : 'Booked';
                var bookingType = 'booked';
                if (item && item.booking_type === 'lecture') {
                    bookingType = 'lecture';
                } else if (item && item.booking_type === 'lab' && item && item.booking_mode === 'group') {
                    bookingType = 'lab';
                } else if (item && item.booking_type === 'hold') {
                    bookingType = 'hold';
                }
                var bookingTypeLabel = bookingType === 'lecture'
                    ? 'Lecture'
                    : (bookingType === 'lab'
                        ? 'Lab'
                        : (bookingType === 'hold' ? 'On Hold' : 'Booked'));
                var editButton = '';
                if (item && item.can_edit_group && Number(item.reservation_id || 0) > 0 && labId > 0) {
                    var editUrl = 'reservation-form.php?lab_id=' + encodeURIComponent(String(labId)) +
                        '&booking_mode=group&edit_group_reservation_id=' + encodeURIComponent(String(item.reservation_id));
                    editButton = '<div class="booked-slot-modal-actions"><button class="btn ghost small booked-slot-edit-button" type="button" data-edit-url="' + escapeHtml(editUrl) + '">Edit Group Booking</button></div>';
                }
                return [
                    '<div class="booked-slot-modal-item booking-type-' + bookingType + '">',
                    '<div class="booked-slot-modal-time">' + escapeHtml(item.time_slot) + '</div>',
                    '<div class="booked-slot-modal-content-block">',
                    '<div class="booked-slot-modal-top">',
                    '<span class="booked-slot-type-badge type-' + bookingType + '">' + bookingTypeLabel + '</span>',
                    '</div>',
                    '<div class="booked-slot-modal-booking">' + escapeHtml(label) + '</div>',
                    editButton,
                    '</div>',
                    '</div>'
                ].join('');
            }).join('');
            bookedSlotsModal.classList.add('active');
            bookedSlotsModal.setAttribute('aria-hidden', 'false');
        }

        function renderSlots(dateKey, isRestricted, isMaintenance) {
            var bookedSlots = bookedByDate[dateKey] || [];
            var bookedSlotDetails = bookedDetailsByDate[dateKey] || [];
            slotGrid.innerHTML = '';
            selectedSlots = [];
            updateSelectedDisplay();

            if (bookingMode === 'group') {
                slotGrid.innerHTML = '<div class="empty-state">Group booking uses weekly session setup in the next form. Select a reference date, then continue.</div>';
                return;
            }
            
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
        }

        function selectDate(dateKey) {
            selectedDate = dateKey;
            bookingDateInput.value = dateKey;
            selectedSlots = [];
            updateSelectedDisplay();
            if (slotTitle) {
                slotTitle.textContent = bookingMode === 'group'
                    ? 'Reference date: ' + formatDate(new Date(dateKey + 'T00:00:00'))
                    : 'Slots on ' + formatDate(new Date(dateKey + 'T00:00:00'));
            }
            var minDateKey = formatKey(minDate);
            var isRestricted = dateKey < minDateKey;
            var isMaintenance = isMaintenanceDate(dateKey);
            renderSlots(dateKey, isRestricted, isMaintenance);
            if (bookingMode !== 'group') {
                bookingSubmit.disabled = true;
            }
            closeBookedSlotsModal();
            if (canViewCalendarHistory && !isMaintenance && bookedSlotDetailsByDateHasItems(dateKey)) {
                openBookedSlotsModal(dateKey, bookedDetailsByDate[dateKey] || [], true);
            }
            if (bookingHint) {
                if (isRestricted) {
                    bookingHint.textContent = 'Bookings must be made at least 3 days in advance.';
                } else if (isMaintenance) {
                    bookingHint.textContent = 'This lab is under maintenance on ' + dateKey + ' (' + maintenanceLabel + ').';
                } else if (bookingMode === 'group') {
                    bookingHint.textContent = 'Reference date selected. Continue to set number of weeks, days, and times for lecture or lab sessions.';
                } else {
                    bookingHint.textContent = 'Please pick one or more time slots to continue.';
                }
            }
        }

        function bookedSlotDetailsByDateHasItems(dateKey) {
            return Array.isArray(bookedDetailsByDate[dateKey]) && bookedDetailsByDate[dateKey].length > 0;
        }

        for (var day = 1; day <= totalDays; day++) {
            var cellDate = new Date(year, monthIndex, day);
            var dateKey = formatKey(cellDate);
            var cell = document.createElement('div');
            cell.className = 'calendar-cell';
            var bookingCount = (bookedByDate[dateKey] || []).length;
            var cellIsMaintenance = isMaintenanceDate(dateKey);

            if (!cellIsMaintenance) {
                cell.classList.add(bookingCount > 0 ? 'has-booking' : 'is-available');
            }

            cell.innerHTML = [
                '<div class="calendar-day">' + day + '</div>',
                '<div class="calendar-meta">' + (cellIsMaintenance ? 'Maintenance' : (bookingCount + ' bookings')) + '</div>'
            ].join('');

            var cellIsPast = cellDate < today;
            var cellIsLeadRestricted = cellDate >= today && cellDate < minDate;

            if (cellIsPast && canViewCalendarHistory) {
                cell.classList.add('past-viewable');
            }
            if ((cellIsPast && !canViewCalendarHistory) || cellIsLeadRestricted || cellIsMaintenance) {
                cell.classList.add('disabled');
            }
            (function (cellElement, isPast, isLeadRestricted, isMaintenance) {
                cellElement.addEventListener('click', function () {
                    if (isMaintenance || isLeadRestricted || (isPast && !canViewCalendarHistory)) {
                        return;
                    }
                    var selectedKey = this.getAttribute('data-date');
                    if (isPast) {
                        closeBookedSlotsModal();
                        renderSlots(selectedKey, true, false);
                        bookingDateInput.value = '';
                        bookingSlotsInput.value = bookingSlotsInput.id === 'booking-slots' ? '[]' : '';
                        selectedSlots = [];
                        updateSelectedDisplay();
                        if (slotTitle) {
                            slotTitle.textContent = 'Booked slots on ' + formatDateKey(selectedKey);
                        }
                        if (bookingHint) {
                            bookingHint.textContent = 'Past dates are view-only. You can review booked slots but cannot make a reservation.';
                        }
                        if (bookedSlotDetailsByDateHasItems(selectedKey)) {
                            openBookedSlotsModal(selectedKey, bookedDetailsByDate[selectedKey] || [], false);
                        }
                        return;
                    }
                    var cells = calendarGrid.querySelectorAll('.calendar-cell');
                    cells.forEach(function (item) { item.classList.remove('selected'); });
                    this.classList.add('selected');
                    selectDate(selectedKey);
                });
            })(cell, cellIsPast, cellIsLeadRestricted, cellIsMaintenance);

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

        if (bookedSlotsModalClose) {
            bookedSlotsModalClose.addEventListener('click', closeBookedSlotsModal);
        }
        if (bookedSlotsModal) {
            bookedSlotsModal.addEventListener('click', function (event) {
                if (event.target === bookedSlotsModal) {
                    closeBookedSlotsModal();
                }
            });
        }
        if (bookedSlotsModalBookNow) {
            bookedSlotsModalBookNow.addEventListener('click', function () {
                closeBookedSlotsModal();
                if (slotGrid) {
                    slotGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
        if (bookedSlotsModalList) {
            bookedSlotsModalList.addEventListener('click', function (event) {
                var editButton = event.target.closest('.booked-slot-edit-button');
                if (!editButton) {
                    return;
                }
                var editUrl = editButton.getAttribute('data-edit-url');
                if (!editUrl) {
                    return;
                }
                window.location.href = editUrl;
            });
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
