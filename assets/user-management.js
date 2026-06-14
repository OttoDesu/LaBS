(function () {
    var modal = document.getElementById('user-modal');
    var addModal = document.getElementById('add-user-modal');
    var importModal = document.getElementById('import-user-modal');
    var form = document.getElementById('user-form');
    var addForm = document.getElementById('add-user-form');
    var buttons = document.querySelectorAll('.edit-user');
    var addButtons = document.querySelectorAll('[data-modal="add-user-modal"]');
    var addRole = document.getElementById('add-role');
    var addLabs = document.getElementById('add-labs');
    var addLabsGroup = document.getElementById('add-labs-group');
    var addClusterGroup = document.getElementById('add-cluster-group');
    var editLabs = document.getElementById('form-labs');
    var editLabsGroup = document.getElementById('form-labs-group');
    var editClusterGroup = document.getElementById('form-cluster-group');

    function openModal() {
        if (modal) {
            modal.classList.add('active');
        }
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('active');
        }
    }

    function openAddModal() {
        if (addModal) {
            addModal.classList.add('active');
        }
    }

    function closeAddModal() {
        if (addModal) {
            addModal.classList.remove('active');
        }
    }

    function closeImportModal() {
        if (importModal) {
            importModal.classList.remove('active');
        }
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-id') || '';
            var name = button.getAttribute('data-name') || '';
            var email = button.getAttribute('data-email') || '';
            var phone = button.getAttribute('data-phone') || '';
            var ic = button.getAttribute('data-ic') || '';
            var role = button.getAttribute('data-role') || '';
            var clusterId = button.getAttribute('data-cluster-id') || '0';
            var labs = (button.getAttribute('data-labs') || '').split(',').filter(Boolean);

            document.getElementById('form-user-id').value = id;
            document.getElementById('form-name').value = name;
            document.getElementById('form-email').value = email;
            document.getElementById('form-phone').value = phone;
            document.getElementById('form-ic').value = ic;
            document.getElementById('form-role').value = role;
            var clusterSelect = document.getElementById('form-cluster');
            if (clusterSelect) {
                clusterSelect.value = clusterId;
            }
            if (editLabs) {
                Array.prototype.forEach.call(editLabs.options, function (option) {
                    option.selected = labs.indexOf(option.value) !== -1;
                });
                toggleLabScope(editLabsGroup, role === 'Lab Supervisor');
            }
            toggleClusterScope(editClusterGroup, role !== 'Lab Supervisor');

            openModal();
        });
    });

    document.querySelectorAll('[data-close="user-modal"]').forEach(function (closeButton) {
        closeButton.addEventListener('click', function () {
            closeModal();
        });
    });

    document.querySelectorAll('[data-close="add-user-modal"]').forEach(function (closeButton) {
        closeButton.addEventListener('click', function () {
            closeAddModal();
        });
    });

    document.querySelectorAll('[data-close="import-user-modal"]').forEach(function (closeButton) {
        closeButton.addEventListener('click', function () {
            closeImportModal();
        });
    });

    addButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openAddModal();
        });
    });

    function toggleLabScope(groupEl, show) {
        if (!groupEl) {
            return;
        }
        groupEl.style.display = show ? '' : 'none';
    }

    function toggleClusterScope(groupEl, show) {
        if (!groupEl) {
            return;
        }
        groupEl.style.display = show ? '' : 'none';
    }

    if (addRole) {
        addRole.addEventListener('change', function () {
            toggleLabScope(addLabsGroup, addRole.value === 'lab_supervisor');
            toggleClusterScope(addClusterGroup, addRole.value !== 'lab_supervisor');
        });
        toggleLabScope(addLabsGroup, addRole.value === 'lab_supervisor');
        toggleClusterScope(addClusterGroup, addRole.value !== 'lab_supervisor');
    }

    if (form) {
        form.addEventListener('submit', function () {
            closeModal();
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', function () {
            closeAddModal();
        });
    }

    if (window.LABS_SHOW_ADD_USER) {
        openAddModal();
    }
})();
