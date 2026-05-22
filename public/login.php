<?php

$scriptDirectory = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = $scriptDirectory !== '' ? '/'.$scriptDirectory : '';

header('Location: '.$basePath.'/login', true, 302);
exit;
