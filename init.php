<?php

require_once __DIR__ . '/vendor/autoload.php';
use josegonzalez\Dotenv\Loader;

set_time_limit(0);
date_default_timezone_set('Asia/Chongqing');

# 加载配置
$Loader = new Loader(dirname(__FILE__) . '/.env');
$Loader->parse();
$Loader->toEnv();


# 调试
if ($_ENV["DEBUG"]) {
    #error_reporting(E_ALL);
} else {
    #error_reporting(0);
}

require_once 'BaseSpider.php';