<?php
$tempFiles = [
    __DIR__ . '/add_column.php',
    __DIR__ . '/makeReservation_temp.php',
    __DIR__ . '/cleanup_temp.php',
];
foreach ($tempFiles as $f) {
    if (file_exists($f)) unlink($f);
}
echo "Cleanup done";