<?php

$testdata = (strtr($_GET['state'], '-_~', '+/='));

if (base64_encode(base64_decode($testdata)) === $testdata) {
    $redirectto = base64_decode($testdata);
} else {
    $redirectto = urldecode($_GET['state']);
}

$params = http_build_query($_GET);
$url = $redirectto.'&'.$params;

header('location: '.$url);
die();
