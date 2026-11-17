Ext.define('Tualo.RunningTasks.Loader', {
    singleton: true,

    constructor: function () {
        Ext.Loader.setPath('Tualo.RunningTasks.lazy', './js/running-tasks/lazy');
    }
});
