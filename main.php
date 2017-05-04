#!/usr/bin/env php

<?php
define('ROOT', dirname(__FILE__));
require ROOT . '/scripts/parser.php';

if ($argc != 2) {
  echo $argc;
  exit(1);
}

$rawLog = explode('{{BREAK}}', file_get_contents($argv[1]));
$gameUrl = array_shift($rawLog);
$gameStatus = array_shift($rawLog);
$rawPlayerInfo = array_shift($rawLog);
parseLog($rawLog, $rawPlayerInfo);