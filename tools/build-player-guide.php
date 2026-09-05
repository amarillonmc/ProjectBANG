<?php
/** Compile our trusted Markdown rules to a static, offline player guide. CLI only. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$lines = preg_split('/\R/', file_get_contents($root . '/docs/RULES.md'));
function guideInline(string $value): string {
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/`([^`]+)`/', '<code>$1</code>', $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value);
    return preg_replace('/\[([^\]]+)\]\((https:\/\/[^ )]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $value);
}
$body = ''; $list = null; $table = false; $tableHead = false;
foreach ($lines as $line) {
    $line = trim($line);
    $listKind = preg_match('/^- (.*)$/', $line, $item) ? 'ul' : (preg_match('/^\d+\. (.*)$/', $line, $item) ? 'ol' : null);
    if ($list !== null && $listKind !== $list) { $body .= '</' . $list . '>'; $list = null; }
    if ($table && substr($line, 0, 1) !== '|') { $body .= '</tbody></table></div>'; $table = false; }
    if ($line === '') { continue; }
    if (substr($line, 0, 1) === '|') {
        if (preg_match('/^\|[\s:|\-]+\|$/', $line)) { continue; }
        if (!$table) { $body .= '<div class="table-wrap"><table>'; $table = true; $tableHead = true; }
        $cells = explode('|', trim($line, '|'));
        if ($tableHead) { $body .= '<thead>'; }
        $tag = $tableHead ? 'th' : 'td'; $body .= '<tr>';
        foreach ($cells as $cell) { $body .= '<' . $tag . '>' . guideInline(trim($cell)) . '</' . $tag . '>'; }
        $body .= '</tr>';
        if ($tableHead) { $body .= '</thead><tbody>'; $tableHead = false; }
    } elseif (preg_match('/^(#{1,4}) (.*)$/', $line, $match)) {
        $n = strlen($match[1]); $body .= '<h' . $n . '>' . guideInline($match[2]) . '</h' . $n . '>';
    } elseif ($listKind !== null) {
        if ($list === null) { $body .= '<' . $listKind . '>'; $list = $listKind; }
        $body .= '<li>' . guideInline($item[1]) . '</li>';
    } else { $body .= '<p>' . guideInline($line) . '</p>'; }
}
if ($list !== null) { $body .= '</' . $list . '>'; }
if ($table) { $body .= '</tbody></table></div>'; }
$html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>完整内测规则 · 时空并错</title><style>body{margin:0;background:#f4f2ed;color:#293f49;font:16px/1.85 system-ui,"Microsoft YaHei",sans-serif}main{max-width:920px;margin:auto;padding:32px 22px 80px}a{color:#446778}h1,h2,h3{font-family:Georgia,"Songti SC",serif;line-height:1.45}h1{font-size:32px;margin-top:30px}h2{font-size:23px;margin-top:42px;border-top:1px solid #d7dbd5;padding-top:25px}p,li{overflow-wrap:anywhere}li{margin:8px 0}.table-wrap{overflow:auto;border:1px solid #d7dbd5;border-radius:8px}table{border-collapse:collapse;width:100%;background:#faf9f6}th,td{padding:12px 15px;border-bottom:1px solid #d7dbd5;vertical-align:top;text-align:left}th{background:#e7ece7}td:first-child{min-width:90px}code{font-size:13px;background:#e7ece7;padding:2px 4px}nav{font-size:14px;display:flex;justify-content:space-between}.stamp{color:#8c6054}@media(max-width:600px){h1{font-size:26px}main{padding:24px 18px 60px}table{font-size:14px}}@media print{nav{display:none}body{background:#fff}main{padding:0}h2{break-after:avoid}tr{break-inside:avoid}}</style></head><body><main><nav><a href="./#reference">← 返回游戏</a><span class="stamp">PLAYTEST / 0.1.0-alpha</span></nav>' . $body . '</main></body></html>';
file_put_contents($root . '/public/rules.html', $html);
echo "Built public/rules.html from docs/RULES.md\n";
