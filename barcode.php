<?php
/**
 * Παραγωγή PNG εικόνας barcode (Code 128).
 * Χρησιμοποιεί τη βιβλιοθήκη picqer/php-barcode-generator
 * (εγκατάσταση μέσω composer: composer require picqer/php-barcode-generator)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

$code = $_GET['code'] ?? '';
if ($code === '') { http_response_code(400); exit('Missing code'); }

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$generator = new BarcodeGeneratorPNG();
echo $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 60);
