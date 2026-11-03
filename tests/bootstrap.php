<?php

require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

require_once __DIR__ . '/Support/DatabaseSafety.php';

\Tests\Support\DatabaseSafety::verify();
