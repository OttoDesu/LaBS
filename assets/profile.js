(function () {
    var modalEl = document.getElementById('profile-modal');
    var editButton = document.getElementById('edit-profile');
    var form = document.getElementById('profile-form');
    var errorEl = document.getElementById('profile-error');
    var ptjOptions = [
        'Pejabat Naib Canselor',
        'Pejabat Timbalan Naib Canselor (Akademik dan Antarabangsa)',
        'Pejabat Timbalan Naib Canselor (Penyelidikan dan Inovasi)',
        'Pejabat Timbalan Naib Canselor (Hal Ehwal Pelajar dan Alumni)',
        'Pejabat Provost UTHM - Kampus Cawangan Pagoh',
        'Pejabat Pendaftar',
        'Pejabat Bendahari',
        'Perpustakaan Tunku Tun Aminah',
        'Pejabat Penasihat Undang-Undang',
        'Pejabat Penolong Naib Canselor (Digital dan Infrastruktur)',
        'Pejabat Penolong Naib Canselor (Strategik dan Kualiti)',
        'Pusat Pengajian Siswazah',
        'Fakulti Kejuruteraan Awam dan Alam Bina',
        'Fakulti Kejuruteraan Elektrik dan Elektronik',
        'Fakulti Kejuruteraan Mekanikal dan Pembuatan',
        'Fakulti Pengurusan Teknologi dan Perniagaan',
        'Fakulti Pendidikan Teknikal Dan Vokasional',
        'Fakulti Sains Komputer dan Teknologi Maklumat',
        'Fakulti Sains Gunaan Dan Teknologi'
    ];

    function updateProfileView(user) {
        var nameEl = document.getElementById('profile-name');
        var emailEl = document.getElementById('profile-email');
        var icEl = document.getElementById('profile-ic');
        var phoneEl = document.getElementById('profile-phone');
        var ptjEl = document.getElementById('profile-ptj');
        var staffOnly = document.querySelectorAll('.staff-only');
        if (nameEl) {
            nameEl.textContent = valueOrNotProvided(user.name);
        }
        if (emailEl) {
            emailEl.textContent = valueOrNotProvided(user.email);
        }
        if (ptjEl) {
            ptjEl.textContent = valueOrNotProvided(user.organization);
        }
        if (icEl) {
            icEl.textContent = valueOrNotProvided(user.ic_no);
        }
        if (phoneEl) {
            phoneEl.textContent = valueOrNotProvided(user.phone);
        }
        if (staffOnly.length) {
            toggleStaffFields(staffOnly, user.user_type === 'uthm_staff');
        }
    }

    function valueOrNotProvided(value) {
        if (value === null || value === undefined || value === '') {
            return 'Not provided';
        }
        return value;
    }

    function toggleStaffFields(elements, isStaff) {
        elements.forEach(function (el) {
            if (isStaff) {
                el.classList.remove('is-hidden');
            } else {
                el.classList.add('is-hidden');
            }
        });
    }

    function populatePusatTanggungJawab(current) {
        var select = document.getElementById('form-ptj');
        if (!select) {
            return;
        }
        select.innerHTML = '<option value="">Select pusat tanggung jawab</option>';
        ptjOptions.forEach(function (ptj) {
            var option = document.createElement('option');
            option.value = ptj;
            option.textContent = ptj;
            if (ptj === current) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    function loadProfile() {
        fetch('profile_api.php', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }
                updateProfileView(data.user);
                if (form) {
                    document.getElementById('form-name').value = data.user.name || '';
                    document.getElementById('form-email').value = data.user.email || '';
                    var icInput = document.getElementById('form-ic');
                    if (icInput) {
                        icInput.value = data.user.ic_no || '';
                        if (data.user.ic_no) {
                            icInput.readOnly = true;
                            icInput.setAttribute('aria-disabled', 'true');
                            icInput.title = 'IC number cannot be changed once saved.';
                            icInput.classList.add('is-readonly');
                        } else {
                            icInput.readOnly = false;
                            icInput.removeAttribute('aria-disabled');
                            icInput.removeAttribute('title');
                            icInput.classList.remove('is-readonly');
                        }
                    }
                    document.getElementById('form-phone').value = data.user.phone || '';
                    populatePusatTanggungJawab(data.user.organization || '');
                }
            });
    }

    if (editButton && modalEl) {
        editButton.addEventListener('click', function () {
            if (errorEl) {
                errorEl.hidden = true;
            }
            openModal(modalEl);
        });
    }

    document.querySelectorAll('[data-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-close');
            closeModal(document.getElementById(target));
        });
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var formData = new FormData(form);
            fetch('profile_api.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        if (errorEl) {
                            errorEl.textContent = (data.errors || ['Unable to update profile.']).join(' ');
                            errorEl.hidden = false;
                        }
                        return;
                    }
                    closeModal(modalEl);
                    loadProfile();
                });
        });
    }

    function openModal(modal) {
        if (modal) {
            modal.classList.add('active');
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.classList.remove('active');
        }
    }

    loadProfile();
})();
