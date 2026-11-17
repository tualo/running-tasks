Ext.define('Tualo.RunningTasks.lazy.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.running_tasks_panel',
    requires: [
        'Tualo.RunningTasks.lazy.controller.Panel',
        'Tualo.RunningTasks.lazy.models.Panel'
    ],
    controller: 'running_tasks_panel',
    viewModel: { type: 'running_tasks_panel' },
    title: 'Laufende Prozesse',
    closable: true,
    layout: 'border',
    listeners: { boxready: 'onBoxReady' },

    getWindowTitle: function () {
        return 'Laufende Prozesse';
    },

    getWindowIcon: function () {
        return 'fa-solid fa-gears';
    },

    items: [{
        xtype: 'grid',
        region: 'center',
        reference: 'taskGrid',
        bind: { store: '{tasks}' },
        tbar: [{
            text: 'Aktualisieren',
            handler: 'onRefresh'
        }, {
            xtype: 'checkbox',
            boxLabel: 'Abgeschlossene anzeigen',
            listeners: { change: 'onIncludeFinishedChange' }
        }, '->', {
            text: 'Stopp anfordern',
            reference: 'stopButton',
            disabled: true,
            handler: 'onStop'
        }],
        columns: [
            { text: 'Prozess-ID', dataIndex: 'id', flex: 1, minWidth: 250, renderer: Ext.util.Format.htmlEncode },
            { text: 'Gestartet', dataIndex: 'started', width: 165, renderer: Ext.util.Format.htmlEncode },
            { text: 'Stopp angefordert', dataIndex: 'stoped', width: 165, renderer: Ext.util.Format.htmlEncode },
            {
                text: 'Status',
                dataIndex: 'status',
                width: 150,
                renderer: function (value) {
                    return {
                        running: 'Laeuft',
                        stopping: 'Stopp angefordert',
                        finished: 'Abgeschlossen',
                        stopped: 'Gestoppt'
                    }[value] || Ext.String.htmlEncode(value);
                }
            }
        ],
        bbar: {
            xtype: 'pagingtoolbar',
            bind: { store: '{tasks}' },
            displayInfo: true,
            listeners: { beforechange: 'onPageChange' }
        },
        listeners: { selectionchange: 'onSelectionChange' }
    }, {
        xtype: 'grid',
        region: 'south',
        reference: 'messageGrid',
        title: 'Statusmeldungen (letzte 200)',
        height: 240,
        split: true,
        collapsible: true,
        bind: { store: '{messages}' },
        columns: [
            { text: 'Zeit', dataIndex: 'created', width: 165, renderer: Ext.util.Format.htmlEncode },
            {
                text: 'Fortschritt',
                dataIndex: 'progress',
                width: 110,
                renderer: function (value) {
                    return value === null || value === undefined ? '' : Ext.util.Format.number(value, '0.00') + ' %';
                }
            },
            { text: 'Meldung', dataIndex: 'message', flex: 1, cellWrap: true, renderer: Ext.util.Format.htmlEncode }
        ]
    }]
});
