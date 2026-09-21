<?php
/** Focused audit of creator-composed, asynchronous effect groups. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Engine;
use Imaginary\Rules;

$auditFailures=[]; $auditChecks=0;
function auditCheck(bool $ok,string $message): void {
    global $auditFailures,$auditChecks; $auditChecks++;
    if(!$ok) {$auditFailures[]=$message; echo 'FAIL '.$message."\n";}
}
function auditEffect(string $op,int $amount=1,string $target='self'): array {
    return ['op'=>$op,'amount'=>$amount,'target'=>$target,'color'=>'neutral'];
}
function auditSkill(array $effects): array {
    return ['name'=>'顺序审计','trigger'=>'active','condition'=>'always','cost'=>['hand'=>0,'mind'=>0],'effects'=>$effects,'limit'=>1];
}
function auditGame(array $effects): array {
    $players=[];
    foreach(array_slice(Catalog::presets(),0,3) as $i=>$build) {
        $build['character']['hp']=4; $build['character']['flipColor']=null;
        $build['character']['skills']=$i===0?[auditSkill($effects)]:[];
        $players[]=['id'=>['a','b','c'][$i],'name'=>['甲','乙','丙'][$i],'build'=>$build];
    }
    $g=Engine::create($players,'series');
    foreach($g['players'] as &$p) {$g['deck']=array_merge($g['deck'],$p['hand']);$p['hand']=[];}
    unset($p);$g['phase']='play';$g['resumePhase']='play';return $g;
}
function auditGive(array &$g,string $id,string $type): string {
    foreach($g['deck'] as $i=>$c) if($c['type']===$type) {array_splice($g['deck'],$i,1);$g['players'][$id]['hand'][]=$c;return $c['uid'];}
    throw new RuntimeException('Fixture missing '.$type);
}

// A later discard may not remove the card needed to respond to an earlier attack.
$g=auditGame([auditEffect('attack',1,'target'),auditEffect('discard_hand',1,'target')]);
$defense=auditGive($g,'b','defense');Engine::act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
auditCheck(($g['pending']['kind']??'')==='attack','virtual attack opens response');
auditCheck(in_array($defense,array_column($g['players']['b']['hand'],'uid'),true),'later discard waits until earlier virtual attack resolves');

// Repeating resource acquisition must not count the same target hand twice.
$g=auditGame([auditEffect('steal_hand',1,'target'),auditEffect('steal_hand',1,'target'),auditEffect('give_hand',2,'target')]);
$stolen=auditGive($g,'b','defense');$before=$g;$rejected=false;
try {Engine::act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);} catch(InvalidArgumentException $e) {$rejected=true;}
auditCheck($rejected,'impossible gift after repeated steal is rejected rather than silently skipped');
if($rejected) auditCheck($g===$before,'impossible gift rejection rolls back all prior effects');

// A single explicit selection covers all give blocks in their declared order.
$g=auditGame([auditEffect('give_hand',1,'target'),auditEffect('give_hand',1,'target')]);
$first=auditGive($g,'a','defense');$second=auditGive($g,'a','attack_neutral');$accepted=true;
try {Engine::act($g,'a',['type'=>'skill','index'=>0,'target'=>'b','selection'=>[$second,$first]]);} catch(InvalidArgumentException $e) {$accepted=false;}
auditCheck($accepted,'explicit gift selection spans all give blocks');
if($accepted) auditCheck(array_column($g['players']['b']['hand'],'uid')===[$second,$first],'multiple gifts preserve explicit selection order');

// Scry ordering cannot leak to another player's serialized view, and malformed orders are atomic.
$g=auditGame([auditEffect('scry',3),auditEffect('draw')]);
Engine::act($g,'a',['type'=>'skill','index'=>0]);$before=$g;$order=array_column($g['pending']['cards'],'uid');
$outside=Engine::view($g,'b');auditCheck(!isset($outside['pending']['cards']),'scry hidden cards stay private');
$rejected=false;try {Engine::act($g,'a',['type'=>'respond','choice'=>'confirm','cards'=>[$order[0],$order[0],$order[2]]]);}catch(InvalidArgumentException $e){$rejected=true;}
auditCheck($rejected&&$g===$before,'malformed scry selection rolls back all state');

// Losing the last hand while opening a private choice can eliminate its chooser.
// An eliminated player cannot submit respond, so their pending choice must be retired.
$g=auditGame([auditEffect('scry',2)]);
$g['players']['a']['character']['skills'][0]['cost']['hand']=1;
$empty=auditSkill([auditEffect('lose_health')]);$empty['trigger']='hand_empty';
$g['players']['a']['character']['skills'][]=$empty;$g['players']['a']['marks']['neutral']=3;
$cost=auditGive($g,'a','defense');$deckBefore=count($g['deck']);
Engine::act($g,'a',['type'=>'skill','index'=>0,'costCards'=>[$cost]]);
auditCheck(!$g['players']['a']['alive'],'empty-hand health loss eliminates exhausted chooser');
auditCheck($g['pending']===null||$g['players'][$g['pending']['player']]['alive'],'an eliminated scry chooser cannot leave an unanswerable pending choice');
auditCheck(count($g['deck'])===$deckBefore,'abandoned scry restores its held physical cards');

// The same cleanup applies between the two defenses of an empowered attack.
$g=auditGame([auditEffect('double_defense')]);
$g['players']['b']['character']['skills']=[$empty];$g['players']['b']['marks']['neutral']=3;
$attack=auditGive($g,'a','attack_neutral');$defense=auditGive($g,'b','defense');
Engine::act($g,'a',['type'=>'skill','index'=>0]);
Engine::act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);
Engine::act($g,'b',['type'=>'respond','choice'=>'defend','card'=>$defense]);
auditCheck(!$g['players']['b']['alive'],'first defense can trigger empty-hand elimination');
auditCheck($g['pending']===null||$g['players'][$g['pending']['player']]['alive'],'eliminated defender cannot retain second-defense pending');

echo ($auditFailures?'FAILED ':'PASS ').$auditChecks.' creator composition audit checks'."\n";
exit($auditFailures?1:0);
