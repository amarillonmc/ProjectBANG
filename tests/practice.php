<?php
/** Regression coverage for configurable bot teams and the defense property of Evade. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Engine;

set_error_handler(function ($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
$checks=0;
function checkPractice(bool $ok,string $message): void {
    global $checks; $checks++; if(!$ok) throw new RuntimeException($message);
}
function practiceGame(array $colors=['cool','cool','warm'],string $mode='color'): array {
    $players=[]; $presets=Catalog::presets();
    foreach($colors as $i=>$color) {
        $build=$presets[$i%count($presets)]; $build['character']['color']=$color;
        $build['character']['flipColor']=null; $build['character']['skills']=[];
        $players[]=['id'=>'p'.$i,'name'=>'Seat '.$i,'bot'=>$i===0,'build'=>$build];
    }
    $g=Engine::create($players,$mode);
    foreach($g['players'] as &$player) {
        $g['deck']=array_merge($g['deck'],$player['hand']); $player['hand']=[];
    }
    unset($player);
    $g['phase']=$g['resumePhase']='play';
    return $g;
}
function practiceCard(array &$g,string $id,string $type): string {
    foreach($g['deck'] as $i=>$card) if($card['type']===$type) {
        array_splice($g['deck'],$i,1); $g['players'][$id]['hand'][]=$card; return $card['uid'];
    }
    // Energy is a mind-only card; take it from its actual owner's mind zone.
    foreach($g['players'] as &$player) foreach($player['mind'] as $i=>$card) if($card['type']===$type) {
        array_splice($player['mind'],$i,1); unset($player); $g['players'][$id]['hand'][]=$card; return $card['uid'];
    }
    throw new RuntimeException('Missing fixture card '.$type);
}
function rejectPractice(array &$g,string $id,array $action): void {
    $before=$g;
    try { Engine::act($g,$id,$action); } catch(InvalidArgumentException $e) {
        checkPractice($before===$g,'Invalid response must roll back'); return;
    }
    throw new RuntimeException('Expected invalid response');
}
function practiceSkill(array $effects,string $trigger='active'): array {
    return ['name'=>'Test skill','trigger'=>$trigger,'condition'=>'always','cost'=>['hand'=>0,'mind'=>0],'effects'=>$effects,'limit'=>1];
}

// Warm, cool and colorless ordinary attacks all accept Evade directly from the hand.
foreach(['attack_neutral','attack_cool','attack_warm'] as $attack) {
    $g=practiceGame(); $g['players']['p0']['bot']=false;
    $g['players']['p1']['color']=$attack==='attack_cool'?'warm':'cool';
    $evade=practiceCard($g,'p1','evade'); $hit=practiceCard($g,'p0',$attack);
    Engine::act($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p1']);
    $actions=array_column(Engine::view($g,'p1')['legalActions'],'action');
    checkPractice(in_array(['type'=>'respond','choice'=>'defend','card'=>$evade],$actions,true),'UI offers hand Evade for '.$attack);
    $before=$g['players']['p1'];
    Engine::act($g,'p1',['type'=>'respond','choice'=>'defend','card'=>$evade]);
    checkPractice($g['pending']===null&&$g['players']['p1']['marks']===$before['marks']&&$g['players']['p1']['maxHp']===$before['maxHp'],'Hand Evade cancels '.$attack);
    checkPractice(!$g['players']['p1']['hand']&&end($g['discard'])['uid']===$evade,'Hand Evade is spent without a bonus draw');
}

// Real mind ownership and custom fallback survive this response.
$g=practiceGame(); $g['players']['p0']['bot']=false;
$mind=array_splice($g['players']['p2']['mind'],4,1)[0];
$mind['type']='custom'; $mind['custom']=['name'=>'Fallback Evade','series'=>'Another series','fallback'=>'evade','effects'=>[Catalog::effect('draw')]];
$g['players']['p1']['hand'][]=$mind;
$hit=practiceCard($g,'p0','attack_neutral'); Engine::act($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p1']);
Engine::act($g,'p1',['type'=>'respond','choice'=>'defend','card'=>$mind['uid']]);
checkPractice(end($g['players']['p2']['spent'])['uid']===$mind['uid']&&end($g['players']['p2']['spent'])['type']==='custom','Fallback Evade returns to its original mind owner');

$g=practiceGame(); $g['players']['p0']['bot']=false; $g['players']['p0']['doubleDefense']=true;
$g['players']['p1']['character']['skills']=[practiceSkill([Catalog::effect('draw')],'on_defend')];
$first=practiceCard($g,'p1','evade'); $second=practiceCard($g,'p1','evade'); $hit=practiceCard($g,'p0','attack_neutral');
Engine::act($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p1']);
Engine::act($g,'p1',['type'=>'respond','choice'=>'defend','card'=>$first]);
checkPractice($g['pending']['defenses']===1&&count($g['players']['p1']['hand'])===1&&!$g['players']['p1']['usedSkills'],'First Evade only pays one defense requirement');
Engine::act($g,'p1',['type'=>'respond','choice'=>'defend','card'=>$second]);
checkPractice($g['pending']===null&&count($g['players']['p1']['hand'])===1,'Second Evade completes defense and triggers on_defend once');

$g=practiceGame(); $g['players']['p0']['bot']=false;
$evade=practiceCard($g,'p1','evade'); $hit=practiceCard($g,'p0','attack_neutral');
$g['players']['p1']['handLockedUntil']=$g['turnNumber'];
Engine::act($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p1']);
checkPractice(count(Engine::legalActions($g,'p1'))===1,'Hand lock hides hand Evade');
rejectPractice($g,'p1',['type'=>'respond','choice'=>'defend','card'=>$evade]);

$g=practiceGame(); $g['players']['p0']['bot']=false;
$punch=practiceCard($g,'p0','punch'); $equipment=array_pop($g['players']['p0']['hand']); $equipment['readyAt']=0; $g['players']['p0']['equipment'][]=$equipment;
$evade=practiceCard($g,'p1','evade');
Engine::act($g,'p0',['type'=>'equip_use','card'=>$punch,'target'=>'p1']);
rejectPractice($g,'p1',['type'=>'respond','choice'=>'defend','card'=>$evade]);
$equipment=array_pop($g['players']['p1']['hand']); $equipment['readyAt']=1; $g['players']['p1']['equipment'][]=$equipment;
rejectPractice($g,'p1',['type'=>'respond','choice'=>'evade','card'=>$evade]);
$g['players']['p1']['equipment'][0]['readyAt']=0;
Engine::act($g,'p1',['type'=>'respond','choice'=>'evade','card'=>$evade]);
checkPractice($g['pending']===null&&count($g['players']['p1']['hand'])===1&&!$g['players']['p1']['equipment'],'Mature equipped Evade still cancels Punch and draws');

// Select an enemy rather than the earlier seat, including colorless and flipped characters.
foreach([['cool','cool','warm'],['neutral','neutral','warm'],['warm','warm','cool']] as $colors) {
    $g=practiceGame($colors); $hit=practiceCard($g,'p0','attack_neutral');
    checkPractice(Engine::botStep($g)&&$g['pending']['player']===($colors[0]==='neutral'?'p1':'p2'),'Bot respects color teams; colorless characters are independent');
}
$g=practiceGame(); $g['players']['p1']['color']='warm'; $g['players']['p1']['flipped']=true; $g['players']['p2']['color']='cool';
practiceCard($g,'p0','attack_neutral'); Engine::botStep($g);
checkPractice($g['pending']['player']==='p1','Bot uses current flipped colors');

$g=practiceGame(['cool','cool','warm','cool']); $hit=practiceCard($g,'p0','attack_neutral'); Engine::botStep($g);
checkPractice($g['turn']==='p1'&&$g['players']['p0']['hand'][0]['uid']===$hit,'Bot ends when only same-color targets are in range');

$g=practiceGame(['cool','warm','cool'],'series'); $g['players']['p1']['series']=$g['players']['p0']['series'];
$hit=practiceCard($g,'p0','attack_neutral'); Engine::botStep($g);
checkPractice($g['pending']['player']==='p2','Series bots attack same-color rivals while avoiding their own series');

$g=practiceGame(['cool','cool'],'series'); $mana=practiceCard($g,'p0','mana'); Engine::botStep($g);
checkPractice($g['turn']==='p0'&&count($g['players']['p0']['hand'])===2,'Series bot uses resources against same-color rival series');

$g=practiceGame(); $g['players']['p0']['character']['skills']=[['name'=>'Convert','trigger'=>'convert','condition'=>'always','cost'=>['hand'=>0,'mind'=>0],'limit'=>1,'effects'=>[],'conversion'=>['from'=>'defense','to'=>'attack_neutral']]];
practiceCard($g,'p0','defense'); Engine::botStep($g);
checkPractice($g['pending']['player']==='p2','Converted attacks avoid same-color targets');

$g=practiceGame(); $punch=practiceCard($g,'p0','punch'); $equipment=array_pop($g['players']['p0']['hand']); $equipment['readyAt']=0; $g['players']['p0']['equipment'][]=$equipment;
Engine::botStep($g); checkPractice($g['pending']['player']==='p2','Punch avoids same-color targets');

foreach(['energy','potential','automaton'] as $type) {
    $g=practiceGame(); $uid=practiceCard($g,'p0',$type);
    if($type==='automaton') { $equipment=array_pop($g['players']['p0']['hand']); $equipment['readyAt']=0; $g['players']['p0']['equipment'][]=$equipment; }
    Engine::botStep($g);
    checkPractice($g['turn']==='p1'&&$g['pending']===null,'Bot avoids friendly fire from '.$type);
    $g=practiceGame(['cool','warm','neutral']); $uid=practiceCard($g,'p0',$type);
    if($type==='automaton') { $equipment=array_pop($g['players']['p0']['hand']); $equipment['readyAt']=0; $g['players']['p0']['equipment'][]=$equipment; }
    Engine::botStep($g);
    checkPractice($g['pending']['player']==='p1','Bot still uses '.$type.' when all others are opponents');
}

foreach(['damage','attack','lose_health','steal_hand','discard_hand','sequester_hand','discard_equipment','hand_lock'] as $op) {
    $g=practiceGame(); practiceCard($g,'p1','defense'); practiceCard($g,'p2','defense');
    $g['players']['p0']['character']['skills']=[practiceSkill([Catalog::effect($op,1,'target')])];
    $friend=$g['players']['p1']; Engine::botStep($g);
    checkPractice($g['players']['p1']===$friend,'Bot skill '.$op.' preserves same-color partner');
    checkPractice(isset($g['players']['p0']['usedSkills'][0]),'Bot can still use offensive skill '.$op.' against an opponent');
}

$g=practiceGame(); $uid=practiceCard($g,'p0','mana');
$g['players']['p0']['hand'][0]['type']='custom';
$g['players']['p0']['hand'][0]['custom']=['name'=>'Mixed custom attack','series'=>$g['players']['p0']['series'],'fallback'=>'mana','effects'=>[Catalog::effect('draw',1,'target'),Catalog::effect('attack',1,'target'),Catalog::effect('damage',1,'target')]];
$friend=$g['players']['p1']; Engine::botStep($g);
checkPractice($g['pending']['player']==='p2'&&$g['players']['p1']===$friend,'Mixed custom effects target a differently colored opponent');
// A flip during the attack changes the relation before the remaining direct damage resolves.
$g['players']['p2']['character']['flipColor']='cool'; $g['players']['p2']['marks']['cool']=$g['players']['p2']['maxHp']-1;
$g['pending']['color']='cool'; $body=$g['players']['p2']['maxHp'];
Engine::act($g,'p2',['type'=>'respond','choice'=>'damage']);
checkPractice($g['players']['p2']['color']==='cool'&&$g['players']['p2']['maxHp']===$body,'Deferred bot effects recheck color after a flip');

$g=practiceGame(); $g['players']['p0']['bot']=false; $g['players']['p1']['bot']=true;
$g['players']['p1']['character']['skills']=[practiceSkill([Catalog::effect('damage',1,'target')],'on_targeted')];
$hit=practiceCard($g,'p0','attack_neutral'); $before=$g['players']['p0']['maxHp'];
Engine::act($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p1']);
checkPractice($g['players']['p0']['maxHp']===$before-1&&!empty($g['players']['p1']['usedSkills']),'Locked retaliation resolves for bots even against same-color attackers');

echo 'PASS '.$checks.' practice/defense assertions'.PHP_EOL;
