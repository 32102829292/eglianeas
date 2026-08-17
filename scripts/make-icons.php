<?php

/**
 * Generates the Egliane "E" mark PNG icons (navy block + sky-blue diagonal
 * ribbon + white blocky E) without GD, using a pure-PHP PNG encoder.
 */

declare(strict_types=1);

const NAVY = [27, 27, 58];
const SKY = [90, 179, 240];
const WHITE = [255, 255, 255];

function pngFromPixels(array $pixels, int $w, int $h): string
{
    $raw = '';
    foreach ($pixels as $row) {
        $raw .= "\x00";
        foreach ($row as $px) {
            $raw .= pack('CCCC', $px[0], $px[1], $px[2], $px[3]);
        }
    }

    $ihdr = pack('NNNC', $w, $h, 8, 6); // 8-bit RGBA
    $chunks = '';
    $chunks .= chunk('IHDR', $ihdr);
    $chunks .= chunk('IDAT', gzcompress($raw, 9));
    $chunks .= chunk('IEND', '');

    return "\x89PNG\r\n\x1a\n".$chunks;
}

function chunk(string $type, string $data): string
{
    $length = pack('N', strlen($data));
    $crc = crc32($type.$data);

    return $length.$type.$data.pack('N', $crc);
}

function savePng(array $pixels, int $size, string $path): void
{
    file_put_contents($path, pngFromPixels($pixels, $size, $size));
}

function inRoundedRect(float $x, float $y, float $cx, float $cy, float $half, float $r): bool
{
    $dx = abs($x - $cx);
    $dy = abs($y - $cy);
    if ($dx > $half || $dy > $half) {
        return false;
    }
    if ($dx <= $half - $r || $dy <= $half - $r) {
        return true;
    }
    $rx = $dx - ($half - $r);
    $ry = $dy - ($half - $r);

    return $rx * $rx + $ry * $ry <= $r * $r;
}

function inRibbon(float $x, float $y): bool
{
    // Distance to the line y = 1 - x (the "/" diagonal), in rotated frame.
    $cx = 0.5;
    $cy = 0.5;
    $s = sin(deg2rad(45));
    $c = cos(deg2rad(45));
    $yr = ($x - $cx) * $s + ($y - $cy) * $c;
    $xr = ($x - $cx) * $c - ($y - $cy) * $s;

    $half = 0.085;
    $len = 0.44;

    return abs($yr) <= $half && abs($xr) <= $len;
}

function inE(float $x, float $y): bool
{
    $spineX0 = 0.29;
    $spineX1 = 0.43;
    $topY0 = 0.235;
    $topY1 = 0.355;
    $midY0 = 0.47;
    $midY1 = 0.53;
    $botY0 = 0.645;
    $botY1 = 0.765;
    $endX = 0.735;

    if ($x >= $spineX0 && $x <= $spineX1 && $y >= $topY0 && $y <= $botY1) {
        return true;
    }
    if ($x >= $spineX1 && $x <= $endX && (($y >= $topY0 && $y <= $topY1) || ($y >= $midY0 && $y <= $midY1) || ($y >= $botY0 && $y <= $botY1))) {
        return true;
    }

    return false;
}

function renderIcon(int $size, bool $maskable): array
{
    $scale = $maskable ? 0.8 : 1.0;
    $offset = (1 - $scale) / 2;

    $grid = array_fill(0, $size, []);
    $samples = 4;

    for ($py = 0; $py < $size; $py++) {
        $row = [];
        for ($px = 0; $px < $size; $px++) {
            $r = 0;
            $g = 0;
            $b = 0;
            $a = 0;
            for ($sy = 0; $sy < $samples; $sy++) {
                for ($sx = 0; $sx < $samples; $sx++) {
                    $fx = ($px + ($sx + 0.5) / $samples) / $size;
                    $fy = ($py + ($sy + 0.5) / $samples) / $size;

                    // Map into design space with scale/offset (centered).
                    $dx = ($fx - 0.5) / $scale + 0.5;
                    $dy = ($fy - 0.5) / $scale + 0.5;

                    if ($maskable) {
                        // Full-bleed navy background always.
                        $col = NAVY;
                    } elseif (! inRoundedRect($dx, $dy, 0.5, 0.5, 0.42, 0.17)) {
                        continue;
                    } else {
                        $col = NAVY;
                    }

                    if (inE($dx, $dy)) {
                        $col = WHITE;
                    } elseif (inRibbon($dx, $dy)) {
                        $col = SKY;
                    }

                    $r += $col[0];
                    $g += $col[1];
                    $b += $col[2];
                    $a += 255;
                }
            }
            $n = $samples * $samples;
            $row[] = [round($r / $n), round($g / $n), round($b / $n), round($a / $n)];
        }
        $grid[$py] = $row;
    }

    return $grid;
}

function icoFromPng(string $pngData, int $size): string
{
    // Pure-PHP ICO writer: ICO can embed a PNG directly (Vista+).
    $header = "\x00\x00\x01\x00\x01\x00";
    $header .= pack('CC', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size);
    $header .= "\x00\x00";
    $header .= pack('v', 1);
    $header .= pack('v', 32);
    $header .= pack('V', strlen($pngData));
    $header .= pack('V', 6 + 16);

    return $header.$pngData;
}

$dir = __DIR__.'/../public/icons';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach (['icon-512' => false, 'icon-192' => false, 'icon-180' => false, 'maskable-512' => true, 'maskable-192' => true, 'icon-32' => false] as $name => $maskable) {
    $size = (int) explode('-', $name)[1];
    $pixels = renderIcon($size, $maskable);
    savePng($pixels, $size, $dir.'/'.$name.'.png');
    echo "wrote {$name}.png ({$size}px)\n";
}

// Favicon.ico wraps the 32px PNG.
$png32 = file_get_contents($dir.'/icon-32.png');
file_put_contents(__DIR__.'/../public/favicon.ico', icoFromPng($png32, 32));
echo "wrote favicon.ico\n";

// Apple touch icon (any-purpose 180).
copy($dir.'/icon-180.png', __DIR__.'/../public/icons/apple-touch-icon.png');
echo "wrote apple-touch-icon.png\n";
