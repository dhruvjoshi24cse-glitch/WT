<?php // local dev: php -S localhost:8000 router.php
if(is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))&&!preg_match('/\.php$/',$_SERVER['REQUEST_URI']))return false;
require __DIR__.'/api/index.php';
