/**
 * WP Media Cleaner - Admin JavaScript
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // --- Helper: wire up select-all + count for a group ---
        function wireCheckboxGroup(selectAllId, checkboxClass, countClass) {
            var selectAll  = document.getElementById(selectAllId);
            var checkboxes = document.querySelectorAll('.' + checkboxClass);
            var counts     = document.querySelectorAll('.' + countClass);

            if (!selectAll || checkboxes.length === 0) return;

            function updateCount() {
                var checked = document.querySelectorAll('.' + checkboxClass + ':checked').length;
                counts.forEach(function (el) {
                    el.textContent = checked + ' geselecteerd';
                });
            }

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                updateCount();
            });

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', updateCount);
            });
        }

        // Files on disk
        wireCheckboxGroup('wmc-select-all', 'wmc-file-checkbox', 'wmc-selected-count');

        // Unattached Media Library items
        wireCheckboxGroup('wmc-select-all-unattached', 'wmc-unattached-checkbox', 'wmc-selected-count-unattached');

        // --- Bulk action buttons (files on disk) ---
        var reviewForm  = document.getElementById('wmc-review-form');
        var actionInput = document.getElementById('wmc-review-action');
        var actionButtons = document.querySelectorAll('[data-action]');

        actionButtons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var action  = this.getAttribute('data-action');
                var checked = document.querySelectorAll('.wmc-file-checkbox:checked');

                if (checked.length === 0) {
                    e.preventDefault();
                    alert('Selecteer eerst een of meer bestanden.');
                    return;
                }

                if (action === 'delete') {
                    if (!confirm('Weet je zeker dat je de geselecteerde bestanden definitief wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
                        e.preventDefault();
                        return;
                    }
                }

                actionInput.value = action;
            });
        });

        // --- Scan button loading state ---
        var scanBtn = document.getElementById('wmc-scan-btn');
        if (scanBtn) {
            scanBtn.closest('form').addEventListener('submit', function () {
                scanBtn.textContent = 'Bezig met scannen\u2026';
                scanBtn.closest('.wmc-panel').classList.add('wmc-scanning');
            });
        }
    });
})();
