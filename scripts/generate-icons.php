<?php
/**
 * One-time icon generator for SmartRecur PWA.
 * Generates 192x192 and 512x512 PNG icons with a calendar motif.
 *
 * Run once on the dev machine:  php scripts/generate-icons.php
 *
 * NOT needed on the production server. Icons are committed to git.
 */

function drawIcon(int $size, string $outPath): void {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    // Rounded square background with indigo gradient
    $bgTop    = imagecolorallocate($img, 0x63, 0x66, 0xF1); // primary-500
    $bgBottom = imagecolorallocate($img, 0x43, 0x38, 0xCA); // primary-700
    $white    = imagecolorallocate($img, 255, 255, 255);
    $whiteSemi = imagecolorallocatealpha($img, 255, 255, 255, 80);

    // Gradient fill
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (int) (0x63 + ($ratio * (0x43 - 0x63)));
        $g = (int) (0x66 + ($ratio * (0x38 - 0x66)));
        $b = (int) (0xF1 + ($ratio * (0xCA - 0xF1)));
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $size, $y, $lineColor);
    }

    // Calendar icon centered (~60% of canvas)
    $iconSize = (int) ($size * 0.55);
    $x0 = (int) (($size - $iconSize) / 2);
    $y0 = (int) (($size - $iconSize) / 2) + (int) ($size * 0.02);
    $x1 = $x0 + $iconSize;
    $y1 = $y0 + $iconSize;

    // Calendar body (white rounded rect)
    $radius = (int) ($iconSize * 0.12);
    drawRoundedRect($img, $x0, $y0, $x1, $y1, $radius, $white);

    // Calendar header strip (darker indigo)
    $headerHeight = (int) ($iconSize * 0.22);
    $headerColor = imagecolorallocate($img, 0x43, 0x38, 0xCA);
    drawRoundedRect($img, $x0, $y0, $x1, $y0 + $headerHeight, $radius, $headerColor, true);

    // Binding pegs (small rectangles on top)
    $pegWidth = (int) ($iconSize * 0.06);
    $pegHeight = (int) ($iconSize * 0.12);
    $pegColor = imagecolorallocate($img, 0x31, 0x2E, 0x81);
    $pegY = $y0 - (int) ($pegHeight * 0.35);
    $pegY2 = $pegY + $pegHeight;
    $peg1X = $x0 + (int) ($iconSize * 0.22);
    $peg2X = $x0 + (int) ($iconSize * 0.72);
    imagefilledrectangle($img, $peg1X, $pegY, $peg1X + $pegWidth, $pegY2, $pegColor);
    imagefilledrectangle($img, $peg2X, $pegY, $peg2X + $pegWidth, $pegY2, $pegColor);

    // Grid dots (3x3 grid of dots representing days)
    $gridStartX = $x0 + (int) ($iconSize * 0.18);
    $gridStartY = $y0 + $headerHeight + (int) ($iconSize * 0.12);
    $gridSpacing = (int) (($iconSize * 0.64) / 3);
    $dotRadius = max(2, (int) ($iconSize * 0.04));
    $dotColor = imagecolorallocate($img, 0xC7, 0xD2, 0xFE); // primary-200
    $todayColor = imagecolorallocate($img, 0x63, 0x66, 0xF1); // primary-500

    for ($row = 0; $row < 3; $row++) {
        for ($col = 0; $col < 3; $col++) {
            $dx = $gridStartX + ($col * $gridSpacing);
            $dy = $gridStartY + ($row * $gridSpacing);
            // Highlight one "today" dot
            $color = ($row === 1 && $col === 1) ? $todayColor : $dotColor;
            imagefilledellipse($img, $dx, $dy, $dotRadius * 2, $dotRadius * 2, $color);
        }
    }

    imagepng($img, $outPath, 9);
    imagedestroy($img);
    echo "Generated: {$outPath}\n";
}

function drawRoundedRect($img, int $x0, int $y0, int $x1, int $y1, int $r, int $color, bool $topOnly = false): void {
    $w = $x1 - $x0;
    $h = $y1 - $y0;
    if ($topOnly) {
        // Only round top corners, square bottom
        imagefilledrectangle($img, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledrectangle($img, $x0, $y0 + $r, $x1, $y1, $color);
        imagefilledellipse($img, $x0 + $r, $y0 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x1 - $r, $y0 + $r, $r * 2, $r * 2, $color);
        return;
    }
    imagefilledrectangle($img, $x0 + $r, $y0, $x1 - $r, $y1, $color);
    imagefilledrectangle($img, $x0, $y0 + $r, $x1, $y1 - $r, $color);
    imagefilledellipse($img, $x0 + $r, $y0 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x1 - $r, $y0 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x0 + $r, $y1 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x1 - $r, $y1 - $r, $r * 2, $r * 2, $color);
}

$outDir = dirname(__DIR__);

drawIcon(192, $outDir . '/icon-192.png');
drawIcon(512, $outDir . '/icon-512.png');
drawIcon(180, $outDir . '/apple-touch-icon.png');

echo "\nDone. Commit the PNG files to git.\n";
