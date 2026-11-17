<?php

namespace Tualo\Office\RunningTasks;

use Tualo\Office\ExtJSCompiler\CompilerHelper;
use Tualo\Office\ExtJSCompiler\ICompiler;

class Compiler implements ICompiler
{
    public static function getFiles()
    {
        return CompilerHelper::getFiles(__DIR__, 'running-tasks', 10003);
    }
}
