<?php
/**
 * CFI.co Awards — transparency export.
 *
 * Run via:  php8.2 /usr/local/bin/wp eval-file scripts/export.php --allow-root \
 *               --path=/var/customers/webs/marten/cfi.co/awards
 *
 * Emits, for every PUBLISHED award announcement (post_type=post, status=publish):
 *   announcements/<year>/<ID>-<slug>.json   exact machine record + content_sha256
 *   announcements/<year>/<ID>-<slug>.md     human-readable view (verbatim HTML body)
 *
 * Design rules that protect the "we never modify announcements" guarantee:
 *  - Body is the RAW stored post_content, byte-for-byte (no the_content filters,
 *    no HTML->MD conversion). $wpdb is used, not get_post(), to avoid filters.
 *  - Volatile internal postmeta (_edit_lock, quadrum_post_views_count, Yoast
 *    caches, ...) is deliberately NOT exported — it changes constantly and would
 *    manufacture fake "modification" commits.
 *  - Curation/display-only categories (FRONT, FEATURED*, approval, x-*, ...)
 *    rotate by design for the homepage and are excluded; only substantive
 *    sector/region/award categories are recorded, so re-exports stay stable.
 *  - The internal WP username is NOT exposed; author is a fixed editorial label.
 */

if (!defined('ABSPATH')) { fwrite(STDERR, "Must run via wp eval-file\n"); exit(1); }

global $wpdb;

$REPO   = dirname(__DIR__);
$OUTDIR = $REPO . '/announcements';
$PLAN   = $REPO . '/scripts/.commit-plan';   // consumed by commit.sh
$US     = "\x1f";                            // field separator (unit separator)

$EDITORIAL_AUTHOR = 'CFI.co Editorial';

// Machine-readable licence identifier stamped into every record (schema v2.2,
// 2026-07-08). Canonical text: LICENCE.md / https://cfi.co/licence/oaal-1.0
$LICENCE_ID = 'CFI-OAAL-1.0';

// Every item here is an award recognition/assessment, not general news.
$DEFAULT_CONTENT_CLASS = 'award_rationale';
$CONTENT_CLASS_BY_SLUG = array();   // no interview/opinion/review on the awards site

// Display/curation categories that rotate by design — excluded for stability.
$EXCLUDE_CAT_SLUGS = array(
    'front', 'approval', 'featured', 'featured_random', 'featured_small',
    'featured-in-app', 'uncategorized',
    'x-africa', 'x-asia-pacific', 'x-europe', 'x-latin-america',
    'x-middle-east', 'x-north-america',
);

@mkdir($OUTDIR, 0755, true);

/* 1. All published announcements, oldest first (chronological history). */
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content, post_excerpt,
            post_date, post_date_gmt, post_modified_gmt
       FROM {$wpdb->posts}
      WHERE post_type='post' AND post_status='publish'
      ORDER BY post_date_gmt ASC, ID ASC"
);

/* 2. Bulk category map (one query) -> [post_id => sorted [slug => name]]. */
$catrows = $wpdb->get_results(
    "SELECT tr.object_id pid, t.name name, t.slug slug
       FROM {$wpdb->term_relationships} tr
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
       JOIN {$wpdb->terms} t          ON t.term_id = tt.term_id
      WHERE tt.taxonomy='category'"
);
$catmap = array();      // filtered -> recorded categories
$catslugs = array();    // ALL slugs per post -> content_class detection
foreach ($catrows as $r) {
    $catslugs[$r->pid][$r->slug] = true;
    if (in_array($r->slug, $EXCLUDE_CAT_SLUGS, true)) continue;
    $catmap[$r->pid][$r->name] = true;
}

/* 2b. Sponsored flag (authoritative paid-content signal, if present). */
$sponmap = array();
foreach ($wpdb->get_results(
    "SELECT post_id pid, meta_key mk, meta_value mv
       FROM {$wpdb->postmeta}
      WHERE meta_key IN ('_cfi_jsonld_sponsored','_cfi_jsonld_sponsor_name')"
) as $m) {
    if ($m->mk === '_cfi_jsonld_sponsored')   $sponmap[$m->pid]['flag'] = $m->mv;
    if ($m->mk === '_cfi_jsonld_sponsor_name') $sponmap[$m->pid]['name'] = $m->mv;
}

/* 2c. Wayback evidence cache (built by scripts/wayback.php; gitignored).
       url => [status, earliest snapshot ts, snapshot url]. */
$waybackmap = array();
if (is_file("$REPO/scripts/.wayback-cache.tsv")) {
    foreach (file("$REPO/scripts/.wayback-cache.tsv",
                  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $p = explode("\t", $l);
        if (count($p) >= 4) {
            $waybackmap[$p[0]] = array('status' => $p[1], 'ts' => $p[2], 'snap' => $p[3]);
        }
    }
}

$plan = fopen($PLAN, 'w');
$n = 0; $bytes = 0;

foreach ($posts as $p) {
    $id    = (int) $p->ID;
    $year  = substr($p->post_date, 0, 4);
    $slug  = $p->post_name !== '' ? $p->post_name : 'post';
    $slug  = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug));
    $slug  = trim(preg_replace('/-+/', '-', $slug), '-');
    if (strlen($slug) > 80) $slug = substr($slug, 0, 80);

    $cats = array();
    if (isset($catmap[$id])) { $cats = array_keys($catmap[$id]); sort($cats); }

    $url     = get_permalink($id);
    $content = (string) $p->post_content;          // RAW, verbatim
    $chash   = hash('sha256', $content);

    // --- Content classification (grounded; rules documented in README). ---
    $sponsored = isset($sponmap[$id]['flag']) && $sponmap[$id]['flag'] === '1';
    $sponsor   = $sponsored ? (string) ($sponmap[$id]['name'] ?? '') : '';
    if ($sponsored) {
        $content_class = 'sponsored_article';
    } else {
        $content_class = $DEFAULT_CONTENT_CLASS;
        foreach ($CONTENT_CLASS_BY_SLUG as $cslug => $cclass) {
            if (isset($catslugs[$id][$cslug])) { $content_class = $cclass; break; }
        }
    }
    $wb = $waybackmap[$url] ?? array('status' => 'pending_check', 'ts' => '', 'snap' => '');
    $classification = array(
        'content_class'          => $content_class,
        'editorial_lens'         => 'constructive_positive_lens', // CFI's stated stance
        'independence_status'    => $sponsored ? 'commercially_supported' : 'independent_editorial',
        'sponsor_disclosure'     => $sponsored ? 'visible_and_machine_readable' : 'none',
        'sponsor_name'           => $sponsor,
        'article_status'         => 'published',
        'historical_status'      => 'current_at_publication',
        'correction_status'      => 'none',          // git history is the live correction record
        'archive_policy'         => 'no_delete',
        'provenance_layer'       => 'github_versioned',
        'wayback_status'         => $wb['status'],   // archived | submitted_pending | not_found | pending_check
        'wayback_first_snapshot' => $wb['ts'],       // earliest Wayback capture (YYYYMMDDhhmmss)
        'wayback_snapshot_url'   => $wb['snap'],
        'license'                => $LICENCE_ID,   // CFI.co Open AI Access Licence (schema v2.2)
    );

    // Exact machine record. Key order is fixed; record_sha256 covers all
    // fields except itself, so the public can independently re-verify.
    $record = array(
        'id'             => $id,
        'title'          => $p->post_title,
        'slug'           => $p->post_name,
        'url'            => $url,
        'author'         => $EDITORIAL_AUTHOR,
        'published'      => $p->post_date,          // site-local
        'published_gmt'  => $p->post_date_gmt,
        'modified_gmt'   => $p->post_modified_gmt,
        'categories'     => $cats,
        'classification' => $classification,
        'excerpt'        => $p->post_excerpt,
        'content_html'   => $content,
        'content_sha256' => $chash,
    );
    $json = json_encode($record,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $record['record_sha256'] = hash('sha256', $json);
    $json = json_encode($record,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    // Human-readable view. Front-matter is YAML; body is the verbatim HTML
    // so nothing is transformed. JSON sidecar is the canonical source.
    $fm  = "---\n";
    $fm .= 'id: ' . $id . "\n";
    $fm .= 'title: ' . yaml_str($p->post_title) . "\n";
    $fm .= 'award_year: ' . (int) $year . "\n";
    $fm .= 'published: ' . $p->post_date . "\n";
    $fm .= 'published_gmt: ' . $p->post_date_gmt . "\n";
    $fm .= 'author: ' . yaml_str($EDITORIAL_AUTHOR) . "\n";
    $fm .= 'url: ' . yaml_str($url) . "\n";
    $fm .= 'categories: [' . implode(', ', array_map('yaml_str', $cats)) . "]\n";
    $fm .= 'content_class: ' . $classification['content_class'] . "\n";
    $fm .= 'independence_status: ' . $classification['independence_status'] . "\n";
    $fm .= 'sponsor_disclosure: ' . $classification['sponsor_disclosure'] . "\n";
    if ($sponsored) $fm .= 'sponsor_name: ' . yaml_str($sponsor) . "\n";
    $fm .= 'editorial_lens: ' . $classification['editorial_lens'] . "\n";
    $fm .= 'historical_status: ' . $classification['historical_status'] . "\n";
    $fm .= 'correction_status: ' . $classification['correction_status'] . "\n";
    $fm .= 'archive_policy: ' . $classification['archive_policy'] . "\n";
    $fm .= 'provenance_layer: ' . $classification['provenance_layer'] . "\n";
    $fm .= 'wayback_status: ' . $classification['wayback_status'] . "\n";
    if ($classification['wayback_first_snapshot'] !== '') {
        $fm .= 'wayback_first_snapshot: ' . $classification['wayback_first_snapshot'] . "\n";
        $fm .= 'wayback_snapshot_url: ' . yaml_str($classification['wayback_snapshot_url']) . "\n";
    }
    $fm .= 'license: ' . $classification['license'] . "\n";
    $fm .= 'content_sha256: ' . $chash . "\n";
    $fm .= 'canonical: ' . $id . '-' . $slug . ".json\n";
    $fm .= "---\n\n";
    $fm .= '# ' . $p->post_title . "\n\n";
    $fm .= "> Verbatim archived copy. Canonical machine record: `" .
           $id . '-' . $slug . ".json`.\n\n";
    $md  = $fm . $content . "\n";

    $dir = $OUTDIR . '/' . $year;
    @mkdir($dir, 0755, true);
    $base   = $id . '-' . $slug;
    $relmd  = "announcements/$year/$base.md";
    $reljs  = "announcements/$year/$base.json";
    file_put_contents("$REPO/$relmd", $md);
    file_put_contents("$REPO/$reljs", $json);

    $msg = sprintf('Add award announcement #%d: %s (%s)',
        $id, sanitize_oneline($p->post_title), $year);
    fwrite($plan, implode($US, array(
        $p->post_date_gmt, $id, $relmd, $reljs, $msg,
    )) . "\n");

    $n++; $bytes += strlen($md) + strlen($json);
}
fclose($plan);

echo "Exported $n announcements (" . round($bytes / 1048576, 1) . " MB)\n";
echo "Commit plan: $PLAN\n";

function yaml_str($s) {
    return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), (string) $s) . '"';
}
function sanitize_oneline($s) {
    $s = preg_replace('/\s+/', ' ', trim((string) $s));
    return strlen($s) > 120 ? substr($s, 0, 117) . '...' : $s;
}
