<?php
/**
 * Wayback Machine evidence layer (repo-agnostic; run with plain php8.2).
 *
 *   php8.2 scripts/wayback.php check    # query each URL's EARLIEST snapshot -> cache
 *   php8.2 scripts/wayback.php submit   # submit cache 'not_found' URLs to web.archive.org/save
 *   php8.2 scripts/wayback.php          # check then submit
 *
 * Cache: scripts/.wayback-cache.tsv (gitignored, resumable, incremental):
 *   url \t status \t first_snapshot_ts \t snapshot_url \t checked_epoch
 * status: archived | submitted_pending | not_found
 *
 * export.php reads this cache and embeds per-record wayback evidence. We never
 * claim "archived" without a real snapshot returned by archive.org.
 */

$REPO = dirname(__DIR__);
$CACHE = $REPO . '/scripts/.wayback-cache.tsv';
$mode = $argv[1] ?? 'all';

$contentDir = is_dir("$REPO/announcements") ? 'announcements'
            : (is_dir("$REPO/articles") ? 'articles' : null);
if (!$contentDir) { fwrite(STDERR, "no content dir\n"); exit(1); }

/* Collect canonical URLs from JSON records. */
$urls = array();
foreach (glob("$REPO/$contentDir/*/*.json") as $f) {
    $j = json_decode(file_get_contents($f), true);
    if (!empty($j['url'])) $urls[$j['url']] = true;
}
$urls = array_keys($urls);

/* Load cache. */
$cache = array();
if (is_file($CACHE)) {
    foreach (file($CACHE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $p = explode("\t", $l);
        if (count($p) >= 4) $cache[$p[0]] = array(
            'status' => $p[1], 'ts' => $p[2], 'snap' => $p[3],
            'checked' => $p[4] ?? '0',
        );
    }
}
$save = function () use (&$cache, $CACHE) {
    $tmp = $CACHE . '.tmp';
    $fh = fopen($tmp, 'w');
    foreach ($cache as $u => $c) {
        fwrite($fh, implode("\t", array($u, $c['status'], $c['ts'], $c['snap'], $c['checked'])) . "\n");
    }
    fclose($fh);
    rename($tmp, $CACHE);
};

function http_get($url, $timeout = 20) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'cfi-co-transparency-archive (+https://github.com/cfi-co)',
    ));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return array($code, $body);
}

if ($mode === 'check' || $mode === 'all') {
    $i = 0; $arch = 0; $miss = 0; $n = count($urls);
    foreach ($urls as $u) {
        // Skip URLs already confirmed archived — we only need the evidence once.
        if (isset($cache[$u]) && $cache[$u]['status'] === 'archived') continue;
        $i++;
        // timestamp=20000101 biases to the EARLIEST available snapshot.
        $api = 'http://archive.org/wayback/available?timestamp=20000101&url=' . rawurlencode($u);
        list($code, $body) = http_get($api);
        $snap = null;
        if ($code === 200 && $body) {
            $d = json_decode($body, true);
            $snap = $d['archived_snapshots']['closest'] ?? null;
        }
        if ($snap && !empty($snap['timestamp'])) {
            $cache[$u] = array('status' => 'archived', 'ts' => $snap['timestamp'],
                'snap' => $snap['url'], 'checked' => time());
            $arch++;
        } else {
            // keep submitted_pending if we'd already submitted; else not_found
            $prev = $cache[$u]['status'] ?? 'not_found';
            $cache[$u] = array('status' => ($prev === 'submitted_pending' ? 'submitted_pending' : 'not_found'),
                'ts' => '', 'snap' => '', 'checked' => time());
            $miss++;
        }
        if ($i % 50 === 0) { $save(); fwrite(STDERR, "  checked $i (archived+$arch, missing+$miss)\n"); }
        usleep(200000); // 0.2s — polite
    }
    $save();
    fwrite(STDERR, "check done: $i queried this run\n");
}

if ($mode === 'submit' || $mode === 'all') {
    $sub = 0;
    $limit = isset($argv[2]) ? (int) $argv[2] : PHP_INT_MAX;  // cap per run (nightly)
    foreach ($cache as $u => $c) {
        if ($c['status'] !== 'not_found') continue;
        if ($sub >= $limit) break;
        list($code, $body) = http_get('https://web.archive.org/save/' . $u, 60);
        // 200/redirect => capture initiated; 429 => rate-limited, leave for next run.
        if ($code === 200 || ($code >= 300 && $code < 400)) {
            $cache[$u]['status'] = 'submitted_pending';
            $cache[$u]['checked'] = time();
            $sub++;
            $save();
        }
        sleep($code === 429 ? 30 : 6); // back off hard on rate-limit
    }
    fwrite(STDERR, "submit done: $sub submitted this run\n");
}
