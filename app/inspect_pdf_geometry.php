<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dompdf = new Dompdf\Dompdf;
$dompdf->loadHtml(file_get_contents(__DIR__.'/storage/app/private/report-audit/before.html'));
$dompdf->setPaper('a4');
$dompdf->setCallbacks([['event'=>'begin_page_reflow', 'f'=>static function ($frame) { static $n=0; if (++$n<4) { $s=$frame->get_style(); echo json_encode(['page'=>$n,'margin'=>[$s->margin_top,$s->margin_bottom],'box'=>$frame->get_containing_block()]).PHP_EOL; }}]]);
$dompdf->render();
