<?php
// Display errors.
ini_set('display_errors', 'on');
// Reporting all.
error_reporting(E_ALL);

// 定义根目录
define('ROOT_PATH', str_replace(array('/'.basename(__FILE__), '\\'.basename(__FILE__)), '', __FILE__));

// 自动加载
require_once ROOT_PATH . '/vendor/autoload.php';



