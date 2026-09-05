<?php
declare(strict_types=1);
/** Independent adversarial regressions; run php tests/audit-api-engine.php. */
require_once __DIR__ . '/../src/Catalog.php';
require_once __DIR__ . '/../src/Rules.php';
require_once __DIR__ . '/../src/Engine.php';

use Imaginary\Catalog;
use Imaginary\Engine;

$auditChecks = 0;
function audit_check(bool $condition, string $message): void
{
    global $auditChecks;
    $auditChecks++;
    if (!$condition) {
        throw new RuntimeException('AUDIT FAIL: ' . $message);
    }
}
function audit_fixture(): array
{
    $presets = Catalog::presets();
    $presets[0]['deck'][0] = ['type' => 'custom', 'rank' => 1, 'custom' => ['name' => '跨系列物性测试', 'series' => $presets[0]['character']['series'], 'fallback' => 'treasure', 'effects' => [Catalog::effect('draw')]]];
    $players = [];
    foreach (['a', 'b', 'c'] as $i => $id) {
        $presets[$i]['character']['skills'] = [];
        $presets[$i]['character']['flipColor'] = null;
        $players[] = ['id' => $id, 'name' => $id, 'bot' => false, 'build' => $presets[$i]];
    }
    $game = Engine::create($players, 'color');
    foreach ($game['players'] as &$player) {
        foreach ($player['hand'] as $card) {
            $game['discard'][] = $card;
        }
        $player['hand'] = [];
    }
    unset($player);
    $game['phase'] = $game['resumePhase'] = 'play';
    return $game;
}
function audit_normal(array &$game, string $type, string $target): string
{
    foreach (['deck', 'discard'] as $zone) {
        foreach ($game[$zone] as $i => $card) {
            if ($card['type'] === $type) {
                array_splice($game[$zone], $i, 1);
                $game['players'][$target]['hand'][] = $card;
                return $card['uid'];
            }
        }
    }
    throw new RuntimeException('Fixture cannot find ordinary ' . $type);
}
function audit_cards(array $game): array
{
    $uids = [];
    foreach (['deck', 'discard', 'draft'] as $zone) {
        foreach ($game[$zone] ?? [] as $card) {
            $uids[] = $card['uid'];
        }
    }
    foreach ($game['players'] as $player) {
        foreach (['hand', 'mind', 'spent', 'equipment', 'delayed'] as $zone) {
            foreach ($player[$zone] as $card) {
                $uids[] = $card['uid'];
            }
        }
    }
    foreach ($game['queue'] as $event) {
        if (($event['effect'] ?? '') === 'delayed') {
            $uids[] = $event['card']['uid'];
        }
    }
    if (($game['pending']['event']['effect'] ?? '') === 'delayed') {
        $uids[] = $game['pending']['event']['card']['uid'];
    }
    sort($uids);
    return $uids;
}
function audit_respond(array &$game, string $player, string $choice, array $extra = []): void
{
    Engine::act($game, $player, array_merge(['type' => 'respond', 'choice' => $choice], $extra));
}
function audit_reject(array &$game, string $player, array $action, string $label): void
{
    $before = $game;
    try {
        Engine::act($game, $player, $action);
    } catch (InvalidArgumentException $error) {
        audit_check($game === $before, $label . ' preserves state');
        return;
    }
    throw new RuntimeException('AUDIT FAIL: expected InvalidArgumentException: ' . $label);
}

// Countering a public draft leaves a physical card; it must be settled before another draft.
$g = audit_fixture();
$baseline = audit_cards($g);
$mana = audit_normal($g, 'mana', 'a');
$counter = audit_normal($g, 'alliance', 'b');
Engine::act($g, 'a', ['type' => 'play', 'card' => $mana, 'mode' => 'draft']);
audit_respond($g, 'a', 'choose', ['card' => $g['draft'][0]['uid']]);
audit_check($g['pending']['kind'] === 'event' && $g['pending']['player'] === 'b', 'draft gives counter window');
audit_respond($g, 'b', 'alliance', ['card' => $counter]);
audit_respond($g, 'c', 'choose', ['card' => $g['draft'][0]['uid']]);
audit_check($g['pending'] === null && !$g['queue'], 'draft queue fully settled');
audit_check(empty($g['draft']), 'countered draft leftovers move to discard');
audit_check(audit_cards($g) === $baseline, 'countered draft conserves all physical cards');
$mana = audit_normal($g, 'mana', 'a');
Engine::act($g, 'a', ['type' => 'play', 'card' => $mana, 'mode' => 'draft']);
audit_check(audit_cards($g) === $baseline, 'second draft cannot erase previous leftovers');

// A custom card borrowed by another series becomes a persistent fallback then recovers its print.
$g = audit_fixture();
$baseline = audit_cards($g);
$custom = array_shift($g['players']['a']['mind']);
$g['players']['b']['hand'][] = $custom;
$g['turn'] = 'b';
Engine::act($g, 'b', ['type' => 'play', 'card' => $custom['uid']]);
audit_check($g['players']['b']['equipment'][0]['type'] === 'treasure', 'foreign-series card equips fallback');
audit_check($g['players']['b']['equipment'][0]['printedType'] === 'custom', 'persistent fallback remembers printed type');
$g['turn'] = 'a';
$surprise = audit_normal($g, 'surprise', 'a');
Engine::act($g, 'a', ['type' => 'play', 'card' => $surprise, 'target' => 'b', 'mode' => 'outside', 'selection' => [$custom['uid']]]);
audit_check($g['players']['b']['maxHp'] === 4, 'foreign treasure removal still causes backlash');
$spent = end($g['players']['a']['spent']);
audit_check($spent['uid'] === $custom['uid'] && $spent['type'] === 'custom' && !isset($spent['printedType']), 'discarded fallback restores original custom card and owner');
audit_check(audit_cards($g) === $baseline, 'persistent transformation and removal conserve cards');

// Remove treasure via steal: property resets in hand and the original owner keeps return rights.
$g = audit_fixture();
$baseline = audit_cards($g);
$custom = array_shift($g['players']['a']['mind']);
$g['players']['b']['hand'][] = $custom;
$g['turn'] = 'b';
Engine::act($g, 'b', ['type' => 'play', 'card' => $custom['uid']]);
$g['turn'] = 'c';
$exchange = audit_normal($g, 'exchange', 'c');
$cost = audit_normal($g, 'defense', 'c');
Engine::act($g, 'c', ['type' => 'play', 'card' => $exchange, 'target' => 'b', 'mode' => 'steal', 'costCard' => $cost]);
$stolen = end($g['players']['c']['hand']);
audit_check($stolen['uid'] === $custom['uid'] && $stolen['type'] === 'custom' && $stolen['owner'] === 'a', 'stolen persistent reverts print and keeps original ownership');
audit_check(!isset($stolen['readyAt']) && !isset($stolen['printedType']), 'stolen persistent drops zone-only metadata');
audit_check(audit_cards($g) === $baseline, 'steal and backlash conserve cards');

// Already-started event queues continue after their source dies; gifts must not enter a dead hand.
$g = audit_fixture();
$baseline = audit_cards($g);
$g['players']['a']['maxHp'] = 1;
$g['players']['b']['character']['skills'] = [['name' => '反震', 'trigger' => 'after_damage', 'condition' => 'always', 'cost' => ['hand' => 0, 'mind' => 0], 'limit' => 1, 'effects' => [Catalog::effect('damage', 1, 'target', 'neutral')]]];
$potential = audit_normal($g, 'potential', 'a');
$gift = audit_normal($g, 'defense', 'c');
Engine::act($g, 'a', ['type' => 'play', 'card' => $potential]);
audit_respond($g, 'b', 'damage');
audit_check(!$g['players']['a']['alive'] && $g['status'] === 'playing', 'reaction can eliminate the source while two opponents remain');
audit_check($g['pending']['player'] === 'c' && $g['pending']['kind'] === 'potential', 'remaining event continues after source death');
audit_respond($g, 'c', 'give', ['card' => $gift]);
audit_check(empty($g['players']['a']['hand']), 'giving to eliminated source uses discard destination');
audit_check(in_array($gift, array_column($g['discard'], 'uid'), true), 'ordinary gift to eliminated source is discarded');
audit_check(audit_cards($g) === $baseline, 'source death and continued queue conserve all cards');
audit_check($g['turn'] === 'b' && $g['pending'] === null, 'dead source turn advances after queue settlement');

// Untrusted JSON may not become a TypeError/500 or leave partially paid costs.
$g = audit_fixture();
$g['players']['a']['character']['skills'] = [['name' => '结构检查', 'trigger' => 'active', 'condition' => 'always', 'cost' => ['hand' => 0, 'mind' => 0], 'limit' => 1, 'effects' => [Catalog::effect('draw')]]];
audit_reject($g, 'a', ['type' => 'skill', 'index' => 0, 'costCards' => 'not-an-array'], 'malformed skill cost');
$uid = audit_normal($g, 'mana', 'a');
audit_reject($g, 'a', ['type' => 'play', 'card' => [$uid]], 'array card identifier');
$g['phase'] = $g['resumePhase'] = 'discard';
while (count($g['players']['a']['hand']) < $g['players']['a']['maxHp'] + 1) {
    $g['players']['a']['hand'][] = array_shift($g['deck']);
}
audit_reject($g, 'a', ['type' => 'discard', 'cards' => [[]]], 'nested discard identifier');

echo 'PASS: ' . $auditChecks . " independent engine audit assertions.\n";
