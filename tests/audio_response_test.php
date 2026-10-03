<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/audio_response.php';
$cases = [
    ['', 100, null], ['bytes=0-9', 100, [0, 9]],
    ['bytes=50-', 100, [50, 99]], ['bytes=-10', 100, [90, 99]],
    ['bytes=-200', 100, [0, 99]], ['bytes=90-200', 100, [90, 99]],
    ['bytes=100-', 100, false], ['bytes=-0', 100, false],
    ['bytes=0-0', 0, false], ['bytes=0-0', 1, [0, 0]],
    ['bytes=20-10', 100, null], ['bytes=-', 100, null],
    ['bytes=0-1,3-4', 100, null], ['items=0-1', 100, null],
    ['bytes=999999999999999999999999-', 100, false],
    ['bytes=-999999999999999999999999', 100, [0, 99]],
];
foreach ($cases as [$header, $size, $expected]) {
    $actual = audio_byte_range($header, $size);
    if ($actual !== $expected) throw new RuntimeException('Unexpected range result: ' . $header);
}
echo "Audio range tests passed.\n";
