<?php

function use_your_drive_autoload($className)
{
    $classPath = explode('\\', $className);
    if ('TheLion' != $classPath[0]) {
        return;
    }
    if ('UseyourDrive' != $classPath[1]) {
        return;
    }
    $classPath = array_slice($classPath, 2, 3);

    $filePath = dirname(__FILE__).'/'.implode('/', $classPath).'.php';
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}

spl_autoload_register('use_your_drive_autoload');
