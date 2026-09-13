<?php
/**
 * scripts/fetch_party_logos.php
 * ---------------------------------------------------------------
 * One-shot helper (CLI): downloads real party symbol logos for the
 * parties actually present in the candidates table.
 *
 * Strategy: ask the Wikipedia pageimages API for each party's article
 * thumbnail (correct URL every time — no hardcoded hashed paths that
 * can 404), then download the PNG render into images/parties/.
 *
 * Also generates, if missing:
 *   - images/default.png           (voter photo / symbol fallback)
 *   - images/parties/_generic.png  (badge for parties without a logo)
 *
 * Usage:  php scripts/fetch_party_logos.php
 * Safe to re-run: existing valid files are kept, failures can be retried.
 * ---------------------------------------------------------------
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the CLI: php scripts/fetch_party_logos.php\n");
}

$root = dirname(__DIR__);
$dir  = $root . '/images/parties';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/**
 * Party slug => Wikipedia article whose infobox image is the party symbol.
 */
$party_articles = [
    'bjp'      => 'Bharatiya Janata Party',
    'inc'      => 'Indian National Congress',
    'aap'      => 'Aam Aadmi Party',
    'bsp'      => 'Bahujan Samaj Party',
    'cpi'      => 'Communist Party of India',
    'cpim'     => 'Communist Party of India (Marxist)',
    'aitc'     => 'All India Trinamool Congress',
    'sp'       => 'Samajwadi Party',
    'tdp'      => 'Telugu Desam Party',
    'ysrcp'    => 'YSR Congress Party',
    'dmk'      => 'Dravida Munnetra Kazhagam',
    'aiadmk'   => 'All India Anna Dravida Munnetra Kazhagam',
    'jdu'      => 'Janata Dal (United)',
    'rjd'      => 'Rashtriya Janata Dal',
    'shivsena' => 'Shiv Sena',
    'aimim'    => 'All India Majlis-e-Ittehadul Muslimeen',
    'npp'      => "National People's Party (India)",
];

/**
 * Fallback: exact Commons filenames for parties whose Wikipedia
 * infobox image is not their symbol (resolved via Special:FilePath).
 */
$extra_files = [
    'aitc' => 'All India Trinamool Congress flag (2).svg',
    'rjd'  => 'RJD_Flag.svg',
];

const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

/** GET a URL, return body on HTTP 200 or '' on failure. */
function http_get(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_ACCEPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return ($code === 200 && is_string($body) && $body !== '') ? $body : '';
}

/** Is this byte string a real PNG/JPEG image? */
function valid_image(string $bin): bool
{
    if (strlen($bin) < 500) {
        return false;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmp, $bin);
    $info = @getimagesize($tmp);
    unlink($tmp);
    return is_array($info) && in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true);
}

// ---------- 1. Resolve thumbnail URLs via the pageimages API ----------
$api = 'https://en.wikipedia.org/w/api.php?action=query&format=json&prop=pageimages'
     . '&piprop=thumbnail&pithumbsize=320&pilicense=any&redirects=1&titles='
     . rawurlencode(implode('|', $party_articles));

$thumb_by_title = [];
$resp = http_get($api);
if ($resp !== '') {
    $data = json_decode($resp, true);
    foreach ($data['query']['pages'] ?? [] as $page) {
        if (!empty($page['thumbnail']['source'])) {
            $thumb_by_title[$page['title']] = $page['thumbnail']['source'];
        }
    }
}

/** Re-encode any image into a true PNG; returns true on success. */
function normalize_png(string $path): bool
{
    $bin = (string)file_get_contents($path);
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmp, $bin);
    $info = @getimagesize($tmp);
    $src  = null;
    if (is_array($info)) {
        $src = match ($info['mime'] ?? '') {
            'image/png'  => null, // already fine
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            default      => null,
        };
    }
    unlink($tmp);
    if ($src === null || $src === false) {
        return ($info['mime'] ?? '') === 'image/png'; // PNG passes through
    }
    $ok = imagepng($src, $path);
    imagedestroy($src);
    return $ok;
}

$ok = 0;
$fail = [];
foreach ($party_articles as $slug => $article) {
    $dest = $dir . "/{$slug}.png";
    if (is_file($dest) && valid_image((string)file_get_contents($dest))) {
        normalize_png($dest); // fix JPEGs misnamed as .png
        printf("  = %-9s already present (%s)\n", $slug, number_format((int)filesize($dest)) . ' B');
        $ok++;
        continue;
    }
    // Exact Commons filename wins (some infobox images are not the symbol)
    $url = isset($extra_files[$slug])
        ? 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($extra_files[$slug]) . '?width=320'
        : ($thumb_by_title[$article] ?? '');
    $bin = $url !== '' ? http_get($url) : '';
    if ($bin !== '' && valid_image($bin)) {
        file_put_contents($dest, $bin);
        normalize_png($dest);
        printf("  ✓ %-9s downloaded (%5s) — %s\n", $slug, number_format(strlen($bin)) . ' B', $article);
        $ok++;
    } else {
        printf("  ✗ %-9s FAILED — %s\n", $slug, $article);
        $fail[] = $slug;
    }
}
printf("\nLogos OK: %d/%d\n", $ok, count($party_articles));
if ($fail) {
    echo 'Failed: ' . implode(', ', $fail) . " (re-run the script to retry)\n";
}

// ---------- 2. Generated fallback images (GD) ----------

/** Simple 96x96 rounded badge with a letter/glyph. */
function make_badge(string $path, string $bg, string $border, string $letter, string $letterColor): void
{
    if (is_file($path)) {
        printf("  = %s/%s already present\n", basename(dirname($path)), basename($path));
        return;
    }
    $im  = imagecreatetruecolor(96, 96);
    $bgc = imagecolorallocate($im, ...sscanf(ltrim($bg, '#'), '%2x%2x%2x'));
    imagefilledrectangle($im, 0, 0, 96, 96, $bgc);
    $boc = imagecolorallocate($im, ...sscanf(ltrim($border, '#'), '%2x%2x%2x'));
    imagesetthickness($im, 3);
    imagerectangle($im, 1, 1, 94, 94, $boc);
    $lc = imagecolorallocate($im, ...sscanf(ltrim($letterColor, '#'), '%2x%2x%2x'));
    imagestring($im, 5, (int)((96 - imagefontwidth(5) * strlen($letter)) / 2), 38, $letter, $lc);
    imagepng($im, $path);
    printf("  ✓ created %s/%s\n", basename(dirname($path)), basename($path));
}

make_badge($root . '/images/default.png',        '#f3e8fd', '#8a2be2', 'V', '#8a2be2');
make_badge($dir . '/_generic.png',               '#ffffff', '#cccccc', '?', '#888888');

echo "\nDone. images/parties/ + fallbacks are ready.\n";
