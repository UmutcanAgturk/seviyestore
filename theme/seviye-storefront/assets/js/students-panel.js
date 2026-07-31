/**
 * Student management for /admin (every branch) and /sube (own branch only)
 * - the same script and markup serve both, since seviye/v1/students already
 * scopes the data server-side based on the current user's branch
 * membership. This script only additionally shows/hides a branch picker.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canManageAllBranches }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-students-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-students-status]');
    var tableBody = root.querySelector('[data-scp-students-body]');
    var form = root.querySelector('[data-scp-student-form]');
    var branchField = root.querySelector('[data-scp-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var parentsPanel = root.querySelector('[data-scp-parents-panel]');
    var parentsList = root.querySelector('[data-scp-parents-list]');
    var parentUserIdInput = root.querySelector('[data-scp-parent-user-id]');
    var parentRelationshipSelect = root.querySelector('[data-scp-parent-relationship]');
    var parentQuickAdd = root.querySelector('[data-scp-parent-quick-add]');
    var summaryCard = root.querySelector('[data-scp-registration-summary]');
    var summaryList = root.querySelector('[data-scp-registration-summary-list]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function statusBadgeCell(status) {
        var cell = document.createElement('td');
        var badge = document.createElement('span');
        var isActive = status === 'active';
        badge.className = 'scp-badge ' + (isActive ? 'scp-badge--active' : 'scp-badge--inactive');
        badge.textContent = isActive ? scpPanelText.statusActive : scpPanelText.statusInactive;
        cell.appendChild(badge);
        return cell;
    }

    var apiFetch = scpApiFetch;

    function loadBranchesIfNeeded() {
        if (!scpPanel.canManageAllBranches) {
            return;
        }

        branchField.hidden = false;

        apiFetch('branches').then(function (result) {
            if (!result.ok) {
                return;
            }

            branchSelect.innerHTML = '';
            result.data.forEach(function (branch) {
                var option = document.createElement('option');
                option.value = String(branch.id);
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });
        });
    }

    function loadStudents() {
        apiFetch('students').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            renderStudents(result.data);
        });
    }

    function renderStudents(students) {
        tableBody.innerHTML = '';

        students.forEach(function (student) {
            var row = document.createElement('tr');

            [
                student.first_name + ' ' + student.last_name,
                student.branch_name || '',
                student.tc_no || scpPanelText.summaryNotSet,
                student.education_year,
                student.class_name
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            row.appendChild(statusBadgeCell(student.status));

            var actionsCell = document.createElement('td');
            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            editButton.textContent = scpPanelText.edit;
            editButton.addEventListener('click', function () {
                openStudentForm(student);
            });
            actionsCell.appendChild(editButton);
            row.appendChild(actionsCell);

            tableBody.appendChild(row);
        });
    }

    function openStudentForm(student) {
        form.hidden = false;
        setStatus('');
        form.reset();
        form.id.value = student ? student.id : '';
        form.first_name.value = student ? student.first_name : '';
        form.last_name.value = student ? student.last_name : '';
        form.tc_no.value = student && student.tc_no ? student.tc_no : '';
        form.education_year.value = student ? student.education_year : '';
        form.class_name.value = student ? student.class_name : '';

        if (scpPanel.canManageAllBranches && student) {
            branchSelect.value = String(student.branch_id);
        }

        if (student) {
            parentsPanel.hidden = false;
            parentQuickAdd.hidden = true;
            loadParents(student.id);
        } else {
            parentsPanel.hidden = true;
            parentsList.innerHTML = '';
            parentQuickAdd.hidden = false;
        }

        summaryCard.hidden = true;
    }

    function summaryRow(label, value, useCode) {
        var dt = document.createElement('dt');
        dt.textContent = label;

        var dd = document.createElement('dd');
        if (useCode) {
            var code = document.createElement('code');
            code.textContent = value;
            dd.appendChild(code);
        } else {
            dd.textContent = value;
        }

        summaryList.appendChild(dt);
        summaryList.appendChild(dd);
    }

    function showRegistrationSummary(payload, branchLabel, credentials, tcNo, tcNoError) {
        summaryList.innerHTML = '';

        summaryRow(scpPanelText.summaryStudent, payload.first_name + ' ' + payload.last_name, false);

        if (branchLabel) {
            summaryRow(scpPanelText.summaryBranch, branchLabel, false);
        }

        if (payload.tc_no) {
            summaryRow(scpPanelText.summaryStudentTcNo, payload.tc_no, true);
        }

        summaryRow(scpPanelText.summaryClass, payload.class_name, false);
        summaryRow(scpPanelText.summaryEducationYear, payload.education_year, false);
        summaryRow(scpPanelText.summaryParent, credentials.name, false);
        summaryRow(scpPanelText.summaryParentEmail, credentials.email, false);
        summaryRow(scpPanelText.summaryTcNo, tcNo || scpPanelText.summaryNotSet, true);
        summaryRow(scpPanelText.summaryPassword, credentials.password, true);

        if (tcNoError) {
            summaryRow(scpPanelText.summaryTcNoError, tcNoError, false);
        }

        summaryCard.hidden = false;
    }

    function loadParents(studentId) {
        apiFetch('students/' + studentId + '/parents').then(function (result) {
            parentsList.innerHTML = '';

            if (!result.ok) {
                return;
            }

            result.data.forEach(function (parentUserId) {
                var item = document.createElement('li');

                var label = document.createElement('span');
                label.textContent = String(parentUserId);
                item.appendChild(label);

                var removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                removeButton.textContent = scpPanelText.remove;
                removeButton.addEventListener('click', function () {
                    apiFetch('students/' + studentId + '/parents/' + parentUserId, { method: 'DELETE' })
                        .then(function (removeResult) {
                            if (removeResult.ok) {
                                loadParents(studentId);
                            }
                        });
                });
                item.appendChild(removeButton);

                parentsList.appendChild(item);
            });
        });
    }

    root.querySelector('[data-scp-new-student]').addEventListener('click', function () {
        openStudentForm(null);
    });

    root.querySelector('[data-scp-cancel-student]').addEventListener('click', function () {
        form.hidden = true;
    });

    root.querySelector('[data-scp-link-parent]').addEventListener('click', function () {
        var studentId = form.id.value;

        if (!studentId || !parentUserIdInput.value) {
            return;
        }

        apiFetch('students/' + studentId + '/parents', {
            method: 'POST',
            body: JSON.stringify({
                parent_user_id: parseInt(parentUserIdInput.value, 10),
                relationship: parentRelationshipSelect.value
            })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.parentLinked);
            parentUserIdInput.value = '';
            loadParents(studentId);
        });
    });

    root.querySelector('[data-scp-dismiss-summary]').addEventListener('click', function () {
        summaryCard.hidden = true;
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var id = form.id.value;
        var payload = {
            first_name: form.first_name.value,
            last_name: form.last_name.value,
            tc_no: form.tc_no.value.trim(),
            education_year: form.education_year.value,
            class_name: form.class_name.value
        };

        var branchLabel = '';

        if (scpPanel.canManageAllBranches) {
            payload.branch_id = parseInt(branchSelect.value, 10);
            branchLabel = branchSelect.selectedOptions.length ? branchSelect.selectedOptions[0].textContent : '';
        }

        var tcNo = '';

        if (!id) {
            payload.parent_first_name = form.parent_first_name.value;
            payload.parent_last_name = form.parent_last_name.value;
            payload.parent_email = form.parent_email.value;
            payload.parent_relationship = form.parent_relationship.value;
            tcNo = form.parent_tc_no.value.trim();
        }

        var path = id ? 'students/' + id : 'students';
        var method = id ? 'PUT' : 'POST';

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            var credentials = result.data && result.data.parent_credentials;

            function finish(tcNoError) {
                if (result.data && result.data.parent_error) {
                    setStatus(scpPanelText.saved + ' ' + result.data.parent_error, true);
                } else {
                    setStatus(scpPanelText.saved);
                }

                if (credentials) {
                    showRegistrationSummary(payload, branchLabel, credentials, tcNo, tcNoError);
                }

                form.hidden = true;
                loadStudents();
            }

            if (credentials && tcNo) {
                apiFetch('security/users/' + credentials.user_id + '/tc-no', {
                    method: 'POST',
                    body: JSON.stringify({ tc_no: tcNo })
                }).then(function (tcResult) {
                    finish(tcResult.ok ? '' : ((tcResult.data && tcResult.data.message) || scpPanelText.saveError));
                });
            } else {
                finish('');
            }
        });
    });

    loadBranchesIfNeeded();
    loadStudents();
})();
