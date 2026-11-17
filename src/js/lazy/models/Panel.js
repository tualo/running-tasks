Ext.define('Tualo.RunningTasks.lazy.models.Panel', {
    extend: 'Ext.app.ViewModel',
    alias: 'viewmodel.running_tasks_panel',

    stores: {
        tasks: {
            fields: ['id', 'started', 'stoped', { name: 'finished', type: 'boolean' }, 'status'],
            pageSize: 100,
            proxy: {
                type: 'ajax',
                url: './running-tasks/read',
                extraParams: { include_finished: '0' },
                reader: {
                    type: 'json',
                    rootProperty: 'data',
                    totalProperty: 'total',
                    successProperty: 'success',
                    messageProperty: 'msg'
                }
            }
        },
        messages: {
            fields: ['id', 'task_id', 'created', 'message', 'progress']
        }
    }
});
