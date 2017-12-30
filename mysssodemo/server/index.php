<?php
require __DIR__.'/MySSOServer.php';
require_once __DIR__.'/config.php';

$ssoServer = new MySSOServer($brokers);

$command = isset($_REQUEST['command']) ? $_REQUEST['command'] : null;
if (!$command || !method_exists($ssoServer, $command)) {
    output_error('未知的 command');
}

$result = $ssoServer->$command();
exit;
