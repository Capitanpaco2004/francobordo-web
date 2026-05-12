<?php

global $hooks;

define('DIR_MODULES_HOOKS', strpos(strtolower(dirname($_SERVER['SCRIPT_NAME'])), 'admin') !== false ? '../includes/addons/' : 'includes/addons/');

function hooksAddAction($sAction, $Task, $Priority = 10)
{
    global $hooks;

    return $hooks->action->add($sAction, $Task, $Priority);
}

function hooksRunAction($sAction = false)
{
    global $hooks;

    if (!$sAction) {
        return false;
    }
    return $hooks->action->run($sAction);
}

function hookFilterApply($sName, $sValue)
{
    global $hooks;

    return $hooks->filter->apply($sName, $sValue);
}

function hooksAddFilter($sAction, $Filter)
{
    global $hooks;

    return $hooks->filter->add($sAction, $Filter);
}
