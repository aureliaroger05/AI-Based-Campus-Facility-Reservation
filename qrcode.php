<?php

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QRCodeException;
use chillerlan\QRCode\QROptions;

function generateQRImage(string $payload, string $outputPath, int $size = 300): bool
{
    require_once __DIR__ . '/vendor/autoload.php';

    $dir = dirname($outputPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('QR generation failed: unable to create the output directory.');
        return false;
    }

    try {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'imageBase64' => false,
            'scale' => max(1, (int)ceil($size / 60)),
        ]);
        $svg = (new QRCode($options))->render($payload);
    } catch (QRCodeException $exception) {
        error_log('QR generation failed: ' . $exception->getMessage());
        return false;
    }

    if (!is_string($svg) || !preg_match('/<svg\b/i', $svg)) {
        error_log('QR generation failed: the QR library returned invalid SVG output.');
        return false;
    }

    $svg = str_replace(
        'style="width: 100%; height: auto;"',
        'width="' . $size . '" height="' . $size . '"',
        $svg,
        $replacements
    );
    if ($replacements !== 1) {
        error_log('QR generation failed: unable to set the SVG display size.');
        return false;
    }

    $temporaryPath = tempnam($dir, basename($outputPath) . '.tmp-');
    if ($temporaryPath === false) {
        error_log('QR generation failed: unable to create a temporary output file.');
        return false;
    }

    $bytesWritten = file_put_contents($temporaryPath, $svg, LOCK_EX);
    if ($bytesWritten !== strlen($svg)) {
        @unlink($temporaryPath);
        error_log('QR generation failed: unable to write the complete SVG file.');
        return false;
    }

    if (!rename($temporaryPath, $outputPath)) {
        @unlink($temporaryPath);
        error_log('QR generation failed: unable to move the SVG file into place.');
        return false;
    }

    return true;
}

function generateReservationQR(int $reservationId, string $outputPath, string $checkinToken): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $checkinToken)) {
        error_log('QR generation failed: the reservation check-in token is invalid.');
        return false;
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (
        strlen($host) > 255 ||
        !preg_match('/^(?<hostname>\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+)(?::(?<port>[0-9]{1,5}))?$/D', $host, $matches) ||
        (isset($matches['port']) && $matches['port'] !== '' && ((int)$matches['port'] < 1 || (int)$matches['port'] > 65535))
    ) {
        error_log('QR generation failed: the request host is missing or invalid.');
        return false;
    }

    $hostname = trim($matches['hostname'], '[]');
    $port = $matches['port'] ?? '';
    $isLoopback = strtolower($hostname) === 'localhost'
        || $hostname === '::1'
        || filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && strpos($hostname, '127.') === 0;
    if ($isLoopback) {
        $lanAddress = gethostbyname(gethostname());
        if (filter_var($lanAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && strpos($lanAddress, '127.') !== 0) {
            $hostname = $lanAddress;
        }
    }

    $host = (strpos($hostname, ':') !== false ? '[' . $hostname . ']' : $hostname)
        . ($port !== '' ? ':' . $port : '');
    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $directorySegments = array_filter(explode('/', trim(dirname($scriptPath), '/')), 'strlen');
    $basePath = $directorySegments
        ? '/' . implode('/', array_map(
            static function (string $segment): string {
                return rawurlencode(rawurldecode($segment));
            },
            $directorySegments
        ))
        : '';

    $scheme = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'
        ? 'https'
        : 'http';
    $url = $scheme . '://' . $host . $basePath . '/view_res.php?id='
        . rawurlencode((string)$reservationId) . '&scan=1&token=' . rawurlencode($checkinToken);

    return generateQRImage($url, $outputPath, 220);
}
