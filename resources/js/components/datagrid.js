export default function nihamDataGrid(config = {}) {
    return {
        pageRowIds: Array.isArray(config.pageRowIds) ? config.pageRowIds : [],
        bulkUpdateUrl: config.bulkUpdateUrl || null,
        bulkDeleteUrl: config.bulkDeleteUrl || null,
        reloadOnBulkUpdate: config.reloadOnBulkUpdate ?? true,
        reloadOnBulkDelete: config.reloadOnBulkDelete ?? true,
        dialogId: config.dialogId || 'datagrid_confirm_dialog',
        options: config.options || {},
        selectedIds: [],
        isMutating: false,
        menuOpen: false,
        menuX: 0,
        menuY: 0,
        labels: Object.assign({
            rowsSelected: ':count selected',
            deleteSelected: 'Delete Selected (:count)',
            bulkEditHint: 'Edit a field below to apply it to all :count selected rows.',
            deleteRowTitle: 'Delete Row',
            deleteRowsTitle: 'Delete Rows',
            deleteRowConfirm: 'Are you sure you want to delete this row? This action cannot be undone.',
            deleteRowsConfirm: 'Are you sure you want to delete :count rows? This action cannot be undone.',
            bulkUpdateError: 'Failed to update the selected rows: :message',
            deleteRowError: 'Failed to delete row: :message',
            deleteRowsError: 'Failed to delete the selected rows: :message',
        }, config.labels || {}),

        get selectionCount() {
            return this.selectedIds.length;
        },

        get allSelected() {
            return this.pageRowIds.length > 0 && this.selectedIds.length === this.pageRowIds.length;
        },

        get someSelected() {
            return this.selectedIds.length > 0 && !this.allSelected;
        },

        get selectedText() {
            return this.formatCount(this.labels.rowsSelected, this.selectionCount);
        },

        get deleteSelectedText() {
            return this.formatCount(this.labels.deleteSelected, this.selectionCount);
        },

        get bulkHintText() {
            return this.formatCount(this.labels.bulkEditHint, this.selectionCount);
        },

        get deleteTitleText() {
            return this.selectionCount > 1 ? this.labels.deleteRowsTitle : this.labels.deleteRowTitle;
        },

        get deleteConfirmText() {
            if (this.selectionCount <= 1) {
                return this.labels.deleteRowConfirm;
            }
            return this.formatCount(this.labels.deleteRowsConfirm, this.selectionCount);
        },

        formatCount(template, count) {
            const formatted = Number(count).toLocaleString();
            return String(template || '')
                .replace(':count', formatted)
                .replace('__COUNT__', formatted);
        },

        csrfToken() {
            return config.csrfToken
                || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || '';
        },

        isSelectable(rowId) {
            return this.pageRowIds.includes(rowId);
        },

        isSelected(rowId) {
            return this.selectedIds.includes(rowId);
        },

        toggleRow(rowId) {
            if (!this.isSelectable(rowId)) return;
            const i = this.selectedIds.indexOf(rowId);
            if (i === -1) this.selectedIds.push(rowId);
            else this.selectedIds.splice(i, 1);
        },

        toggleAll() {
            this.selectedIds = this.allSelected ? [] : [...this.pageRowIds];
        },

        clearSelection() {
            this.selectedIds = [];
        },

        onEscape() {
            if (this.menuOpen) {
                this.closeRowMenu();
                return;
            }
            this.clearSelection();
        },

        openRowMenu(e, rowId) {
            if (!this.isSelectable(rowId)) return;

            const t = e.target;
            if (t.matches && t.matches('input, select, textarea') && document.activeElement === t) {
                return;
            }

            e.preventDefault();

            if (!this.isSelected(rowId)) {
                this.selectedIds = [rowId];
            }

            this.menuX = Math.min(e.clientX, window.innerWidth - 230);
            this.menuY = Math.min(e.clientY, window.innerHeight - 140);
            this.menuOpen = true;
        },

        closeRowMenu() {
            this.menuOpen = false;
        },

        addOption(listKey, option) {
            if (!listKey || !option) return;
            if (!Array.isArray(this.options[listKey])) {
                this.options[listKey] = [];
            }
            const list = this.options[listKey];
            const at = list.findIndex((item) => String(item.name).localeCompare(String(option.name)) > 0);
            list.splice(at === -1 ? list.length : at, 0, { id: option.id, name: option.name });
        },

        async parseJsonSafe(response) {
            const text = await response.text();
            let data = null;
            try {
                data = text ? JSON.parse(text) : {};
            } catch (_) {
                if (!response.ok) {
                    throw new Error(`Server error (${response.status})`);
                }
                throw new Error('Invalid JSON response from server');
            }

            if (!response.ok) {
                throw new Error(data.message || `Request failed (${response.status})`);
            }

            return data;
        },

        async bulkUpdate(fieldName, newValue) {
            if (this.selectionCount === 0 || !this.bulkUpdateUrl || this.isMutating) return;
            this.isMutating = true;

            try {
                const response = await fetch(this.bulkUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        row_ids: this.selectedIds,
                        field_name: fieldName,
                        new_value: newValue,
                    }),
                });

                const data = await this.parseJsonSafe(response);
                if (!data.success) {
                    throw new Error(data.message || 'Bulk update failed');
                }

                this.$dispatch('datagrid-bulk-updated', {
                    fieldName,
                    newValue,
                    data,
                    selectedIds: [...this.selectedIds],
                });

                if (this.reloadOnBulkUpdate) {
                    window.location.reload();
                    return;
                }

                this.applyRowFlags(data.rowFlags || {});
                this.syncVisibleCells(fieldName, newValue);
            } catch (err) {
                console.error('Bulk update failed:', err);
                const msg = String(this.labels.bulkUpdateError)
                    .replace(':message', err.message)
                    .replace('__MSG__', err.message);
                alert(msg);
            } finally {
                this.isMutating = false;
            }
        },

        applyRowFlags(rowFlags) {
            const root = this.$root || document;
            Object.entries(rowFlags).forEach(([id, isInvalid]) => {
                const tr = root.querySelector(`tr[data-row-id="${id}"]`);
                if (!tr) return;
                const scope = window.Alpine ? window.Alpine.$data(tr) : null;
                if (scope) scope.isInvalid = isInvalid;
            });
        },

        syncVisibleCells(fieldName, newValue) {
            const root = this.$root || document;
            this.selectedIds.forEach((id) => {
                const tr = root.querySelector(`tr[data-row-id="${id}"]`);
                if (!tr) return;
                const field = tr.querySelector(`[name$="[${fieldName}]"]`);
                if (field) field.value = newValue;
            });
        },

        requestDeleteSelected() {
            if (this.selectionCount === 0 || !this.bulkDeleteUrl) return;
            this.closeRowMenu();
            document.getElementById(this.dialogId)?.showModal();
        },

        cancelDeleteRow() {
            document.getElementById(this.dialogId)?.close();
        },

        async confirmDeleteRow() {
            document.getElementById(this.dialogId)?.close();
            const rowIds = [...this.selectedIds];
            if (rowIds.length === 0 || !this.bulkDeleteUrl || this.isMutating) return;
            this.isMutating = true;

            try {
                const response = await fetch(this.bulkDeleteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        row_ids: rowIds,
                    }),
                });

                const data = await this.parseJsonSafe(response);
                if (!data.success) {
                    throw new Error(data.message || 'Delete request failed');
                }

                this.$dispatch('datagrid-bulk-deleted', { rowIds, data });
                this.clearSelection();

                if (this.reloadOnBulkDelete) {
                    window.location.reload();
                }
            } catch (err) {
                console.error('Delete failed:', err);
                const template = rowIds.length > 1 ? this.labels.deleteRowsError : this.labels.deleteRowError;
                const msg = String(template)
                    .replace(':message', err.message)
                    .replace('__MSG__', err.message);
                alert(msg);
            } finally {
                this.isMutating = false;
            }
        },
    };
}
