Ext.define('Tualo.routes.RunningTasks', {
    statics: {
        load: async function () {
            return [{
                name: 'Laufende Prozesse',
                path: '#running-tasks/panel'
            }];
        }
    },
    url: 'running-tasks/panel',
    handler: {
        action: function () {
            Ext.getApplication().addView('Tualo.RunningTasks.lazy.Panel');
        },
        before: function (action) {
            Ext.require('Tualo.RunningTasks.lazy.Panel', function () {
                action.resume();
            });
        }
    }
});
