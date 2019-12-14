<?php
include('vendor/autoload.php');

function sumAmount($rows)
{
    $sum = 0;
    foreach ($rows as $row) {
        $amount = (float)str_replace(
            ',',
            '.',
            str_replace(
                '.',
                '',
                $row['amount']
            )
        );
        $cent = (int)($amount * 100);
        $sum += $cent;
    }
    return number_format((float)($sum / 100), 2, ',', '.') . ' EUR';
}

$bootstrap = new \StefanFroemken\AccountAnalyzer\Bootstrap();
$bootstrap->run();
