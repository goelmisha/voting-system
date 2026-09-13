<?php
/**
 * includes/party_symbols.php
 * ---------------------------------------------------------------
 * Maps a candidate's party name to a real party symbol image stored
 * in images/parties/ (downloaded from Wikimedia Commons — see
 * scripts/fetch_party_logos.php). Parties without a known logo fall
 * back to a neutral generic badge so nothing is ever a broken image.
 *
 * Usage:
 *   require_once __DIR__ . '/party_symbols.php';
 *   $src = party_symbol($row['party']);              // web path
 *   $ok = party_symbol_exists($row['party']);        // bool
 * ---------------------------------------------------------------
 */

/**
 * Static alias table: party name (as stored in the candidates table)
 * => logo slug in images/parties/. Matching is additionally done on
 * the abbreviated form in parentheses, e.g. "(BJP)", so minor naming
 * differences still resolve.
 */
$PARTY_SYMBOL_MAP = [
    // National parties
    'bharatiya janata party'             => 'bjp',
    'indian national congress'           => 'inc',
    'bahujan samaj party'                => 'bsp',
    'communist party of india (marxist)' => 'cpim',
    'communist party of india'           => 'cpi',
    'aam aadmi party'                    => 'aap',
    // Regional & others
    'all india trinamool congress'       => 'aitc',
    'trinamool congress'                 => 'aitc',
    'samajwadi party'                    => 'sp',
    'telugu desam party'                 => 'tdp',
    'ysr congress party'                 => 'ysrcp',
    'dravida munnetra kazhagam'          => 'dmk',
    'all india anna dravida munnetra kazhagam' => 'aiadmk',
    'janata dal (united)'                => 'jdu',
    'janata dal (secular)'               => 'jds',
    'rashtriya janata dal'               => 'rjd',
    'shiv sena'                          => 'shivsena',
    'all india majlis-e-ittehadul muslimeen' => 'aimim',
    "national people's party"            => 'npp',
];

/**
 * Normalize a party name for fuzzy comparison: uppercase, strip every
 * non-alphanumeric char (spaces, punctuation, parentheses). E.g.
 * "Communist Party of India (Marxist) (CPI(M))" => "COMMUNISTPARTYOFINDIAMARXISTCPIM".
 */
function party_normalize(string $s): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($s));
}

/**
 * Resolve a party name (any spelling/abbreviation variant) to a logo
 * slug present in images/parties/, or '' when unknown.
 *
 * Strategy, most-specific first:
 *   1. Exact (lowercased) map lookup.
 *   2. Normalized substring match: known party names/abbreviations
 *      searched inside the normalized party string, LONGEST first so
 *      "CPIM" wins over "CPI" and "AIADMK" over "DMK".
 *   3. Any "(ABBR)" token whose abbreviation matches a logo file.
 */
function party_resolve_slug(string $party): string
{
    global $PARTY_SYMBOL_MAP;
    $base = realpath(__DIR__ . '/../images/parties');
    if ($base === false) {
        return '';
    }

    $key = strtolower(trim($party));
    if (isset($PARTY_SYMBOL_MAP[$key])) {
        return $PARTY_SYMBOL_MAP[$key];
    }

    $norm = party_normalize($party);
    if ($norm === '') {
        return '';
    }

    // Candidate tokens: known party names + their slugs, longest first.
    $candidates = [];
    foreach ($PARTY_SYMBOL_MAP as $name => $slug) {
        $candidates[] = party_normalize($name);
        $candidates[] = party_normalize($slug);
    }
    $candidates = array_values(array_unique(array_filter($candidates, fn($c) => strlen($c) >= 2)));
    usort($candidates, fn($a, $b) => strlen($b) - strlen($a));

    foreach ($candidates as $cand) {
        if (str_contains($norm, $cand)) {
            // Which slug does this candidate belong to?
            foreach ($PARTY_SYMBOL_MAP as $name => $slug) {
                if (party_normalize($name) === $cand || party_normalize($slug) === $cand) {
                    return $slug;
                }
            }
        }
    }

    // Last resort: "(ABBR)" token that directly matches a logo file.
    if (preg_match_all('/\(([A-Za-z0-9]{2,10})\)/', $party, $ms)) {
        foreach ($ms[1] as $tok) {
            $abbr = strtolower(preg_replace('/[^a-z0-9_]/', '', strtolower($tok)));
            if ($abbr !== '' && is_file($base . '/' . $abbr . '.png')) {
                return $abbr;
            }
        }
    }

    return '';
}

/**
 * True if the given party name resolves to a real logo file on disk.
 */
function party_symbol_exists(string $party): bool
{
    $base = realpath(__DIR__ . '/../images/parties');
    if ($base === false) {
        return false;
    }
    $slug = party_resolve_slug($party);
    if ($slug === '') {
        return false;
    }
    $file = $base . '/' . preg_replace('/[^a-z0-9_]/', '', $slug) . '.png';
    return is_file($file) && filesize($file) > 200;
}

/**
 * Web-relative path (from the project root) to the symbol image for a
 * party name. Always returns an existing file: real logo when known,
 * generic badge otherwise.
 */
function party_symbol(string $party): string
{
    $base = realpath(__DIR__ . '/../images/parties');
    if ($base === false) {
        return 'images/parties/_generic.png';
    }

    $slug = party_resolve_slug($party);
    if ($slug !== '') {
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);
        $file = $base . '/' . $slug . '.png';
        if (is_file($file) && filesize($file) > 200) {
            return 'images/parties/' . $slug . '.png';
        }
    }

    return 'images/parties/_generic.png';
}
