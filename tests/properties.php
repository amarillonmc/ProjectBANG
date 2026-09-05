<?php
/** Independent whole-game invariants, real engine and all built-in builds. */
require_once dirname(__DIR__) . '/src/Catalog.php';
require_once dirname(__DIR__) . '/src/Rules.php';
require_once dirname(__DIR__) . '/src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Rules;
use Imaginary\Engine;
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function expectProperty($condition, $message) {
    global $assertions; $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
}
function physicalCards(array $game): array {
    $cards = array_merge($game['deck'], $game['discard'], $game['draft'] ?? []);
    foreach ($game['players'] as $player) {
        foreach (['hand','mind','spent','equipment','delayed'] as $zone) {
            $cards = array_merge($cards, $player[$zone]);
        }
    }
    // Only placement events hold an in-transit card. Judge events contain references.
    foreach ($game['queue'] as $event) {
        if (($event['effect'] ?? '') === 'delayed') { $cards[] = $event['card']; }
    }
    if (($game['pending']['event']['effect'] ?? '') === 'delayed') {
        $cards[] = $game['pending']['event']['card'];
    }
    return $cards;
}
function checkGame(array $game, int $initialCount, int $run): void {
    $cards = physicalCards($game);
    expectProperty(count($cards) === $initialCount, 'Card conservation run ' . $run . ' turn ' . $game['turnNumber'] . ': ' . count($cards) . ' != ' . $initialCount);
    $uids = array_column($cards, 'uid');
    expectProperty(count($uids) === count(array_unique($uids)), 'Duplicate physical UID');
    foreach ($game['players'] as $p) {
        expectProperty($p['maxHp'] >= 0 && $p['shield'] >= 0 && $p['shield'] <= 6, 'Body/shield bounds');
        foreach ($p['marks'] as $n) { expectProperty(is_int($n) && $n >= 0, 'Negative or noninteger marks'); }
    }
    foreach ($cards as $card) {
        expectProperty($card['rank'] >= 1 && $card['rank'] <= 13, 'Rank bound');
        expectProperty($card['origin'] !== 'mind' || isset($game['players'][$card['owner']]), 'Orphaned mind owner');
    }
    if ($game['status'] === 'playing') {
        $actor = $game['pending']['player'] ?? $game['turn'];
        expectProperty($game['players'][$actor]['alive'], 'Action owned by eliminated player');
    }
}
$presets = Catalog::presets();
foreach ($presets as $preset) { Rules::validateBuild($preset); }
$games = 0; $stepsTotal = 0;
for ($run = 0; $run < 24; $run++) {
    $seats = 2 + ($run % 5); $players = [];
    for ($seat = 0; $seat < $seats; $seat++) {
        $players[] = ['id'=>'p'.$seat,'name'=>'Property '.$seat,'build'=>$presets[$seat % count($presets)],'bot'=>true];
    }
    $game = Engine::create($players, $run % 2 ? 'series' : 'color');
    $expected = 104 + 13 * $seats;
    for ($step = 0; $step < 8000 && $game['status'] === 'playing'; $step++) {
        checkGame($game, $expected, $run);
        if ($step % 11 === 0) {
            $before = json_encode($game);
            $view = Engine::view($game, 'p0');
            expectProperty(json_encode($game) === $before, 'view mutated game');
            expectProperty(!isset($view['deck']) && !isset($view['queue']), 'View leaked internal deck/queue');
            foreach ($view['players'] as $p) {
                expectProperty(!isset($p['hand']) && !isset($p['mind']), 'Opponent private arrays leaked');
            }
        }
        expectProperty(Engine::botStep($game), 'Bot unable to advance game');
        $stepsTotal++;
    }
    checkGame($game, $expected, $run);
    expectProperty($game['status'] === 'finished', 'Game did not terminate');
    expectProperty(is_string($game['winner']) && $game['winner'] !== '', 'Missing winner');
    $games++;
}
echo "PASS: $games complete games, $stepsTotal bot actions, $assertions property assertions.\n";
