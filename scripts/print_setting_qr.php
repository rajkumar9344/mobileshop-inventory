<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Modules\Setting\Entities\Setting;
$s = Setting::first();
echo "gpay_qr=" . ($s->gpay_qr ?? 'NULL') . PHP_EOL;
echo "phonepe_qr=" . ($s->phonepe_qr ?? 'NULL') . PHP_EOL;
