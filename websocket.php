<?php
ob_implicit_flush();
require_once  'webchat.php';
$socket=new socket('127.0.0.1',8080);
$socket->run();