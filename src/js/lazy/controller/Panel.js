Ext.define('Tualo.RunningTasks.lazy.controller.Panel', {
    extend: 'Ext.app.ViewController',
    alias: 'controller.running_tasks_panel',

    onBoxReady: function () {
        this.messageRequest = 0;
        this.onRefresh();
    },

    onRefresh: function () {
        this.startPolling();
        this.loadEntries();
    },

    startPolling: function () {
        if (!this.pollTask) {
            this.pollTask = Ext.TaskManager.start({
                run: function () {
                    this.loadEntries();
                },
                scope: this,
                interval: 5000,
                fireOnStart: false
            });
        }
    },

    stopPolling: function () {
        if (this.pollTask) {
            Ext.TaskManager.stop(this.pollTask);
            this.pollTask = null;
        }
    },

    loadEntries: function (page) {
        let me = this,
            store = me.getViewModel().getStore('tasks');

        if (store.isLoading()) {
            return;
        }
        let selected = me.lookupReference('taskGrid').getSelectionModel().getSelection(),
            selectedId = selected.length ? selected[0].getId() : null;

        store.loadPage(typeof page === 'number' ? page : store.currentPage, {
            callback: function (records, operation, success) {
                if (me.destroyed || me.destroying) {
                    return;
                }
                if (!success) {
                    me.showError(operation.getError());
                    return;
                }
                if (store.currentPage > 1 && !records.length) {
                    me.loadEntries(1);
                    return;
                }
                let record = selectedId === null ? null : store.getById(selectedId);
                if (record) {
                    me.lookupReference('taskGrid').getSelectionModel().select(record, false, true);
                    me.updateSelection(record);
                } else {
                    me.updateSelection(null);
                }
            }
        });
    },

    onPageChange: function (toolbar, page) {
        this.loadEntries(page);
        return false;
    },

    onIncludeFinishedChange: function (field, value) {
        let store = this.getViewModel().getStore('tasks');
        store.getProxy().setExtraParam('include_finished', value ? '1' : '0');
        // A pending response must not overwrite the newly selected filter.
        if (store.isLoading()) {
            store.on('load', function () {
                if (!this.destroyed && !this.destroying) {
                    this.loadEntries(1);
                }
            }, this, { single: true });
        } else {
            this.loadEntries(1);
        }
    },

    onSelectionChange: function (selectionModel, selected) {
        this.updateSelection(selected.length ? selected[0] : null);
    },

    updateSelection: function (record) {
        this.selectedId = record ? record.getId() : null;
        this.lookupReference('stopButton').setDisabled(!record || record.get('finished') || record.get('stoped') !== null);
        if (record) {
            this.loadMessages(this.selectedId);
        } else {
            this.messageRequest++;
            this.getViewModel().getStore('messages').removeAll();
        }
    },

    loadMessages: async function (id) {
        let request = ++this.messageRequest;
        try {
            let result = await this.request('./running-tasks/messages?id=' + encodeURIComponent(id));
            if (!this.destroyed && !this.destroying && request === this.messageRequest) {
                this.getViewModel().getStore('messages').loadData(result.data);
            }
        } catch (error) {
            if (!this.destroyed && !this.destroying && request === this.messageRequest) {
                this.getViewModel().getStore('messages').removeAll();
                this.showError(error);
            }
        }
    },

    onStop: function () {
        let me = this,
            id = me.selectedId;
        if (id === null || id === undefined) {
            return;
        }
        Ext.Msg.confirm('Laufende Prozesse', 'Stopp fuer diesen Prozess anfordern?', async function (answer) {
            if (answer !== 'yes' || me.destroyed || me.destroying) {
                return;
            }
            try {
                await me.request('./running-tasks/stop', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: 'id=' + encodeURIComponent(id)
                });
                if (!me.destroyed && !me.destroying) {
                    me.loadEntries();
                }
            } catch (error) {
                if (!me.destroyed && !me.destroying) {
                    me.showError(error);
                }
            }
        });
    },

    request: async function (url, options) {
        let response = await fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store' }, options)),
            result = await response.json();
        if (!response.ok || result.success !== true) {
            throw new Error(result.msg || 'Die Anfrage ist fehlgeschlagen.');
        }
        return result;
    },

    showError: function (error) {
        this.stopPolling();
        Ext.Msg.alert('Laufende Prozesse', Ext.String.htmlEncode((error && (error.message || error.statusText)) ||
            'Die Prozesse konnten nicht geladen werden. Mit Aktualisieren erneut versuchen.'));
    },

    destroy: function () {
        this.stopPolling();
        this.messageRequest++;
        this.callParent(arguments);
    }
});
