<?php
/**
 * /sitemap.xml —— 只收录公开内容（private / hidden / archive 一律不进）
 * nginx 里把 /sitemap.xml 转到这里，见 sites-enabled/typecho。
 */

$root = dirname(__DIR__, 4);
require $root . '/config.inc.php';

$db = \Typecho\Db::get();
$options = \Widget\Options::alloc();

$rows = $db->fetchAll(
    $db->select('cid', 'title', 'slug', 'type', 'status', 'created', 'modified')
        ->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('type = ? OR type = ?', 'post', 'page')
        ->order('modified', \Typecho\Db::SORT_DESC)
);

$siteUrl = rtrim((string) $options->siteUrl, '/');

function xml_esc($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

echo "  <url>\n";
echo '    <loc>', xml_esc($siteUrl . '/'), "</loc>\n";
echo '    <changefreq>daily</changefreq>', "\n";
echo "  </url>\n";

foreach ($rows as $row) {
    $path = $row['type'] === 'page'
        ? '/' . rawurlencode((string) $row['slug']) . '.html'
        : '/' . (int) $row['cid'];
    $lastmod = date('c', (int) ($row['modified'] ?: $row['created']));

    echo "  <url>\n";
    echo '    <loc>', xml_esc($siteUrl . $path), "</loc>\n";
    echo '    <lastmod>', $lastmod, "</lastmod>\n";
    echo '    <changefreq>', $row['type'] === 'page' ? 'monthly' : 'yearly', "</changefreq>\n";
    echo "  </url>\n";
}

echo '</urlset>', "\n";
