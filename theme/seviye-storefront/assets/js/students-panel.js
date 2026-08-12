/**
 * Student management for /admin (every branch) and /sube (own branch only)
 * - the same script and markup serve both, since seviye/v1/students already
 * scopes the data server-side based on the current user's branch
 * membership. This script only additionally shows/hides a branch picker.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canManageStudents, canManageAllBranches }
 *   scpPanelText { ...translated UI strings }
 *
 * canManageStudents is false only for Rehberlik (VIEW_STUDENTS, read-only -
 * see StudentCapability's own docblock): the list still loads and a row
 * still opens the same detail form, but every write control (create, edit,
 * delete, CSV import, class promotion, parent link/edit/remove, spending
 * limit) is hidden or disabled.
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-students-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    // Captured once, synchronously, at script load - NOT re-read later
    // via the bare `scpPanel`/`scpPanelText` globals, which several
    // OTHER scripts on this same page (account-security.js,
    // privacy-requests-panel.js, support-tickets-panel.js - all
    // unconditionally enqueued on every admin/sube page) also localize
    // under the SAME global variable names. Whichever of those loads
    // LAST overwrites `window.scpPanel`/`window.scpPanelText` for the
    // whole page; code that reads the bare global later (inside an
    // apiFetch().then() callback, a click handler, ...) would silently
    // see THAT other script's narrower data instead of this page's own
    // - capturing a local reference immediately, before any later
    // script's localize tag runs, avoids that.
    var scpPanelData = scpPanel;
    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    // "Rehberlik" holds VIEW_STUDENTS, not MANAGE_STUDENTS (read-only branch
    // access to students - see StudentCapability's own docblock). This
    // page/script still loads for them (inc/assets.php enqueues it on
    // MANAGE_STUDENTS *or* VIEW_STUDENTS), but every write control below is
    // gated on this flag so a view-only visitor never sees a button that
    // would just 403 on click.
    var canManageStudents = Boolean(scpPanelData.canManageStudents);

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
    var spendingLimitPanel = root.querySelector('[data-scp-spending-limit-panel]');
    var spendingLimitStatus = root.querySelector('[data-scp-spending-limit-status]');
    var spendingLimitPeriodSelect = root.querySelector('[data-scp-spending-limit-period]');
    var spendingLimitAmountInput = root.querySelector('[data-scp-spending-limit-amount]');
    var importForm = root.querySelector('[data-scp-import-form]');
    var importBranchField = root.querySelector('[data-scp-import-branch-field]');
    var importBranchSelect = importBranchField.querySelector('select');
    var importFileInput = importForm.querySelector('[name="csv_file"]');
    var importResult = root.querySelector('[data-scp-import-result]');
    var importSummary = root.querySelector('[data-scp-import-summary]');
    var importErrorsList = root.querySelector('[data-scp-import-errors]');
    var importTemplateLink = root.querySelector('[data-scp-download-import-template]');
    var promoteForm = root.querySelector('[data-scp-promote-form]');
    var promoteBranchField = root.querySelector('[data-scp-promote-branch-field]');
    var promoteBranchSelect = promoteBranchField.querySelector('select');
    var promoteClassMapField = root.querySelector('[data-scp-promote-class-map]');
    var promoteResult = root.querySelector('[data-scp-promote-result]');
    var promoteSummary = root.querySelector('[data-scp-promote-summary]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function statusBadgeCell(status) {
        var cell = document.createElement('td');
        var badge = document.createElement('span');
        var isActive = status === 'active';
        badge.className = 'scp-badge ' + (isActive ? 'scp-badge--active' : 'scp-badge--inactive');
        badge.textContent = isActive ? scpPanelTextData.statusActive : scpPanelTextData.statusInactive;
        cell.appendChild(badge);
        return cell;
    }

    var apiFetch = scpApiFetch;

    function loadBranchesIfNeeded() {
        if (!scpPanelData.canManageAllBranches) {
            return;
        }

        branchField.hidden = false;
        importBranchField.hidden = false;
        promoteBranchField.hidden = false;

        var allBranchesOption = document.createElement('option');
        allBranchesOption.value = '';
        allBranchesOption.textContent = scpPanelTextData.allBranches;
        promoteBranchSelect.appendChild(allBranchesOption);

        apiFetch('branches').then(function (result) {
            if (!result.ok) {
                return;
            }

            [branchSelect, importBranchSelect, promoteBranchSelect].forEach(function (select) {
                var isPromoteSelect = select === promoteBranchSelect;

                if (!isPromoteSelect) {
                    select.innerHTML = '';
                }

                result.data.forEach(function (branch) {
                    var option = document.createElement('option');
                    option.value = String(branch.id);
                    option.textContent = branch.name;
                    select.appendChild(option);
                });
            });
        });
    }

    function parseClassNameMap(raw) {
        var map = {};

        raw.split('\n').forEach(function (line) {
            var parts = line.split('=');

            if (parts.length !== 2) {
                return;
            }

            var fromClassName = parts[0].trim();
            var toClassName = parts[1].trim();

            if (fromClassName !== '' && toClassName !== '') {
                map[fromClassName] = toClassName;
            }
        });

        return map;
    }

    function loadStudents() {
        apiFetch('students').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderStudents(result.data);
        });
    }

    function renderStudents(students) {
        tableBody.innerHTML = '';

        students.forEach(function (student) {
            var row = document.createElement('tr');
            var fullName = student.first_name + ' ' + student.last_name;

            var nameCell = document.createElement('td');
            var nameGroup = document.createElement('span');
            nameGroup.className = 'scp-table__name-group';

            if (typeof window.scpAvatar === 'function') {
                nameGroup.appendChild(window.scpAvatar(fullName, student.photo_url));
            }

            var nameLabel = document.createElement('span');
            nameLabel.textContent = fullName;
            nameGroup.appendChild(nameLabel);
            nameCell.appendChild(nameGroup);
            row.appendChild(nameCell);

            [
                student.branch_name || '',
                student.tc_no || scpPanelTextData.summaryNotSet,
                student.education_year,
                student.class_name
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            row.appendChild(statusBadgeCell(student.status));

            var actionsCell = document.createElement('td');

            if (canManageStudents) {
                var editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                editButton.textContent = scpPanelTextData.edit;
                editButton.addEventListener('click', function () {
                    openStudentForm(student);
                });
                actionsCell.appendChild(editButton);

                var deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                deleteButton.textContent = scpPanelTextData.remove;
                deleteButton.addEventListener('click', function () {
                    if (!window.confirm(scpPanelTextData.confirmDeleteStudent)) {
                        return;
                    }

                    apiFetch('students/' + student.id, { method: 'DELETE' }).then(function (result) {
                        if (!result.ok) {
                            setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                            return;
                        }

                        setStatus(scpPanelTextData.studentDeleted);
                        loadStudents();
                    });
                });
                actionsCell.appendChild(deleteButton);
            } else {
                // Read-only (Rehberlik): no edit/delete controls, but the
                // whole row still opens the same detail form - readonly
                // there too - so a counselor can look up a student's class/
                // parent contact without a dead-end list.
                row.classList.add('scp-row--clickable');
                row.addEventListener('click', function () {
                    openStudentForm(student);
                });
            }

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

        // Sınıf artık bir <select> (sabit 14 seçenek) - ama bu değişiklikten
        // ÖNCE serbest metin olarak girilmiş eski kayıtlar (ör. "5-A") bu
        // listede yok. .value ataması eşleşmezse tarayıcı sessizce hiçbir
        // seçeneği seçili göstermez, bu da öğrencinin gerçek sınıfını
        // görünmez şekilde kaybettirir - bunun yerine listeye geçici bir
        // seçenek ekleyip seçili yapıyoruz, formu kaydetmeden değiştirmezse
        // eski değer korunur.
        if (student && student.class_name && form.class_name.selectedIndex === -1) {
            var scpLegacyClassOption = document.createElement('option');
            scpLegacyClassOption.value = student.class_name;
            scpLegacyClassOption.textContent = student.class_name;
            scpLegacyClassOption.selected = true;
            form.class_name.appendChild(scpLegacyClassOption);
        }

        if (scpPanelData.canManageAllBranches && student) {
            branchSelect.value = String(student.branch_id);
        }

        if (student) {
            parentsPanel.hidden = false;
            parentQuickAdd.hidden = true;
            loadParents(student.id);

            // The spending-limit endpoint is MANAGE_STUDENTS-only server
            // side (see SpendingLimitRestController::canAccessStudent()) -
            // a read-only (Rehberlik) visitor has no business seeing or
            // editing it, and calling it here would just 403.
            spendingLimitPanel.hidden = !canManageStudents;

            if (canManageStudents) {
                loadSpendingLimit(student.id);
            }
        } else {
            parentsPanel.hidden = true;
            parentsList.innerHTML = '';
            parentQuickAdd.hidden = false;
            spendingLimitPanel.hidden = true;
        }

        summaryCard.hidden = true;
    }

    function periodLabel(period) {
        return period === 'term' ? scpPanelTextData.spendingLimitPeriodTerm : scpPanelTextData.spendingLimitPeriodMonthly;
    }

    function formatCurrency(amount) {
        return Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TRY';
    }

    function loadSpendingLimit(studentId) {
        spendingLimitStatus.textContent = '';
        spendingLimitPeriodSelect.value = 'monthly';
        spendingLimitAmountInput.value = '';

        apiFetch('commerce/students/' + studentId + '/spending-limit').then(function (result) {
            if (!result.ok) {
                spendingLimitStatus.textContent = scpPanelTextData.spendingLimitLoadError;
                return;
            }

            var data = result.data;

            if (!data.period) {
                spendingLimitStatus.textContent = scpPanelTextData.spendingLimitNone;
                return;
            }

            spendingLimitPeriodSelect.value = data.period;
            spendingLimitAmountInput.value = data.limit_amount;
            spendingLimitStatus.textContent = periodLabel(data.period) + ' limit: ' + formatCurrency(data.limit_amount)
                + ' - ' + scpPanelTextData.spendingLimitSpent + ': ' + formatCurrency(data.spent_amount)
                + ' - ' + scpPanelTextData.spendingLimitRemaining + ': ' + formatCurrency(data.remaining_amount);
        });
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

    function showRegistrationSummary(payload, branchLabel, parent, tcNo, tcNoError, isExisting) {
        summaryList.innerHTML = '';

        summaryRow(scpPanelTextData.summaryStudent, payload.first_name + ' ' + payload.last_name, false);

        if (branchLabel) {
            summaryRow(scpPanelTextData.summaryBranch, branchLabel, false);
        }

        if (payload.tc_no) {
            summaryRow(scpPanelTextData.summaryStudentTcNo, payload.tc_no, true);
        }

        summaryRow(scpPanelTextData.summaryClass, payload.class_name, false);
        summaryRow(scpPanelTextData.summaryEducationYear, payload.education_year, false);
        summaryRow(scpPanelTextData.summaryParent, parent.name, false);
        summaryRow(scpPanelTextData.summaryParentEmail, parent.email, false);

        if (isExisting) {
            summaryRow('', scpPanelTextData.summaryLinkedExistingNote, false);
        } else {
            summaryRow(scpPanelTextData.summaryTcNo, tcNo || scpPanelTextData.summaryNotSet, true);
            summaryRow(scpPanelTextData.summaryPassword, parent.password, true);
        }

        if (tcNoError) {
            summaryRow(scpPanelTextData.summaryTcNoError, tcNoError, false);
        }

        summaryCard.hidden = false;
    }

    function loadParents(studentId) {
        apiFetch('students/' + studentId + '/parents').then(function (result) {
            parentsList.innerHTML = '';

            if (!result.ok) {
                return;
            }

            result.data.forEach(function (parent) {
                parentsList.appendChild(renderParentItem(studentId, parent));
            });
        });
    }

    function renderParentItem(studentId, parent) {
        var item = document.createElement('li');

        var label = document.createElement('span');
        label.textContent = parent.name + ' (' + parent.email + ')';
        item.appendChild(label);

        if (canManageStudents) {
            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            editButton.textContent = scpPanelTextData.edit;
            editButton.addEventListener('click', function () {
                item.replaceWith(renderParentEditForm(studentId, parent));
            });
            item.appendChild(editButton);

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            removeButton.textContent = scpPanelTextData.remove;
            removeButton.addEventListener('click', function () {
                apiFetch('students/' + studentId + '/parents/' + parent.id, { method: 'DELETE' })
                    .then(function (removeResult) {
                        if (removeResult.ok) {
                            loadParents(studentId);
                        }
                    });
            });
            item.appendChild(removeButton);
        }

        return item;
    }

    function renderParentEditForm(studentId, parent) {
        var item = document.createElement('li');
        item.className = 'scp-form scp-form--inline';

        var nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.value = parent.name;
        item.appendChild(nameInput);

        var emailInput = document.createElement('input');
        emailInput.type = 'email';
        emailInput.value = parent.email;
        item.appendChild(emailInput);

        var saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'scp-btn scp-btn--small';
        saveButton.textContent = scpPanelTextData.save;
        saveButton.addEventListener('click', function () {
            apiFetch('students/' + studentId + '/parents/' + parent.id, {
                method: 'PUT',
                body: JSON.stringify({ display_name: nameInput.value, email: emailInput.value })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.parentUpdated);
                loadParents(studentId);
            });
        });
        item.appendChild(saveButton);

        var cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
        cancelButton.textContent = scpPanelTextData.cancel;
        cancelButton.addEventListener('click', function () {
            loadParents(studentId);
        });
        item.appendChild(cancelButton);

        return item;
    }

    if (!canManageStudents) {
        // Read-only (Rehberlik): hide every write-only entry point up
        // front - create, CSV import, class promotion - rather than
        // leaving buttons whose click handler would just 403. The detail
        // form itself stays reachable (row click, see renderStudents())
        // but every field/button inside it - including link-parent and
        // the spending-limit save/remove buttons, all in the same
        // <form data-scp-student-form> - is disabled too.
        root.querySelector('[data-scp-new-student]').hidden = true;

        // The import/promote forms' own heading + hint text live in the
        // SAME wrapping .scp-card--nested as the form, not inside it -
        // hiding just the <form> would leave an explanatory card with no
        // control to explain. Hide the whole card.
        var importCard = importForm.closest('.scp-card--nested');
        if (importCard) {
            importCard.hidden = true;
        }

        var promoteCard = promoteForm.closest('.scp-card--nested');
        if (promoteCard) {
            promoteCard.hidden = true;
        }

        form.querySelectorAll('input, select, button, textarea').forEach(function (el) {
            el.disabled = true;
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
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.parentLinked);
            parentUserIdInput.value = '';
            loadParents(studentId);
        });
    });

    root.querySelector('[data-scp-dismiss-summary]').addEventListener('click', function () {
        summaryCard.hidden = true;
    });

    root.querySelector('[data-scp-save-spending-limit]').addEventListener('click', function () {
        var studentId = form.id.value;

        if (!studentId || !spendingLimitAmountInput.value) {
            return;
        }

        apiFetch('commerce/students/' + studentId + '/spending-limit', {
            method: 'PUT',
            body: JSON.stringify({
                period: spendingLimitPeriodSelect.value,
                limit_amount: parseFloat(spendingLimitAmountInput.value)
            })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.spendingLimitSaved);
            loadSpendingLimit(studentId);
        });
    });

    root.querySelector('[data-scp-remove-spending-limit]').addEventListener('click', function () {
        var studentId = form.id.value;

        if (!studentId) {
            return;
        }

        apiFetch('commerce/students/' + studentId + '/spending-limit', { method: 'DELETE' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.spendingLimitRemoved);
            loadSpendingLimit(studentId);
        });
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

        if (scpPanelData.canManageAllBranches) {
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
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            var credentials = result.data && result.data.parent_credentials;
            var linkedExisting = result.data && result.data.parent_linked_existing;

            function finish(tcNoError) {
                if (result.data && result.data.parent_error) {
                    setStatus(scpPanelTextData.saved + ' ' + result.data.parent_error, true);
                } else {
                    setStatus(scpPanelTextData.saved);
                }

                if (credentials) {
                    showRegistrationSummary(payload, branchLabel, credentials, tcNo, tcNoError, false);
                } else if (linkedExisting) {
                    showRegistrationSummary(payload, branchLabel, linkedExisting, tcNo, tcNoError, true);
                }

                form.hidden = true;
                loadStudents();
            }

            if (credentials && tcNo) {
                apiFetch('security/users/' + credentials.user_id + '/tc-no', {
                    method: 'POST',
                    body: JSON.stringify({ tc_no: tcNo })
                }).then(function (tcResult) {
                    finish(tcResult.ok ? '' : ((tcResult.data && tcResult.data.message) || scpPanelTextData.saveError));
                });
            } else {
                finish('');
            }
        });
    });

    // ---- Toplu İçe Aktarma (CSV) ----

    importTemplateLink.addEventListener('click', function (event) {
        event.preventDefault();

        var csv = '﻿first_name,last_name,education_year,class_name,tc_no\n'
            + 'Ayşe,Yılmaz,2025-2026,5-A,\n';
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'ogrenci-ice-aktarma-sablonu.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });

    function renderImportResult(data) {
        importResult.hidden = false;
        importSummary.textContent = scpPanelTextData.importSummary
            .replace('%1$d', String(data.imported_count))
            .replace('%2$d', String(data.error_count));

        importErrorsList.innerHTML = '';
        (data.errors || []).forEach(function (error) {
            var item = document.createElement('li');
            item.textContent = scpPanelTextData.importErrorLine
                .replace('%1$d', String(error.line))
                .replace('%2$s', error.message);
            importErrorsList.appendChild(item);
        });
    }

    importForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var file = importFileInput.files[0];

        if (!file) {
            return;
        }

        importResult.hidden = true;
        setStatus(scpPanelTextData.importing);

        var reader = new FileReader();
        reader.onload = function () {
            var payload = { csv: String(reader.result) };

            if (scpPanelData.canManageAllBranches) {
                payload.branch_id = parseInt(importBranchSelect.value, 10);
            }

            apiFetch('students/import', { method: 'POST', body: JSON.stringify(payload) }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus('');
                renderImportResult(result.data);
                importForm.reset();
                loadStudents();
            });
        };
        reader.readAsText(file, 'UTF-8');
    });

    promoteForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var fromEducationYear = new FormData(promoteForm).get('from_education_year');

        // phpcs is not relevant to JS, but the confirm() copy mirrors this
        // codebase's other irreversible-bulk-action confirmations (e.g.
        // confirmDeleteStudent) - see inc/assets.php's scpPanelTextData.
        if (!window.confirm(scpPanelTextData.confirmPromoteStudents.replace('%s', fromEducationYear))) {
            return;
        }

        var payload = { from_education_year: fromEducationYear };
        var classNameMap = parseClassNameMap(promoteClassMapField.value);

        if (Object.keys(classNameMap).length > 0) {
            payload.class_name_map = classNameMap;
        }

        if (scpPanelData.canManageAllBranches && promoteBranchSelect.value !== '') {
            payload.branch_id = parseInt(promoteBranchSelect.value, 10);
        }

        promoteResult.hidden = true;
        setStatus(scpPanelTextData.promoting);

        apiFetch('students/promote', { method: 'POST', body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus('');
            promoteSummary.textContent = scpPanelTextData.promoteSummary
                .replace('%1$d', String(result.data.promoted_count))
                .replace('%2$s', result.data.to_education_year);
            promoteResult.hidden = false;
            promoteForm.reset();
            promoteClassMapField.value = '';
            loadStudents();
        });
    });

    loadBranchesIfNeeded();
    loadStudents();
})();
