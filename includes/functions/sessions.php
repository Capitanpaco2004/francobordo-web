<?php

function tep_session_register($variable): bool
{
	global $sessionCore;

	return $sessionCore->register($variable);
}

function tep_session_is_registered($variable): bool
{
	global $sessionCore;

	return $sessionCore->isRegistered($variable);
}

function tep_session_unregister($variable): void
{
	global $sessionCore;

	$sessionCore->unregister($variable);
}

function tep_session_id($sessid = ''): string
{
	global $sessionCore;

	return $sessionCore->id($sessid);
}

function tep_session_name($name = ''): string
{
	global $sessionCore;

	return $sessionCore->name($name);
}

function tep_session_destroy(): bool
{
	global $sessionCore;

	return $sessionCore->destroy();
}

function tep_session_save_path($path = ''): string
{
	global $sessionCore;

	return $sessionCore->savePath($path);
}
