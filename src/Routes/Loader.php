<?php

namespace Tualo\Office\RunningTasks\Routes;

use Tualo\Office\Basic\Route;
use Tualo\Office\Basic\RouteSecurityHelper;
use Tualo\Office\Basic\RouteWrapper;

class Loader extends RouteWrapper
{
    public static function register()
    {
        Route::add('/js/running-tasks/lazy/(?P<file>[\w.\/\-]+)', function ($matches) {
            RouteSecurityHelper::serveSecureStaticFile(
                $matches['file'],
                dirname(__DIR__) . '/js/lazy/',
                ['js'],
                ['js' => 'application/javascript']
            );
        }, ['get'], true);
    }
}
