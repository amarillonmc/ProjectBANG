<?php
/** Rule 0.3 regression: temporary zones, response timing and reusable expansion conditions. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Rules;
use Imaginary\Engine;
use Imaginary\SkillBlocks;
set_error_handler(function($severity,$message,$file,$line) { if(error_reporting()&$severity) throw new ErrorException($message,0,$severity,$file,$line); return false; });
$checks=0;
function check($ok,string $why): void { global $checks; $checks++; if(!$ok) throw new RuntimeException($why); }
function effect(string $op,int $n=1,string $to='self',string $color='neutral'): array { return ['op'=>$op,'amount'=>$n,'target'=>$to,'color'=>$color]; }
function skill(string $when,array $effects,string $condition='always',int $hand=0,int $mind=0,int $limit=1): array { return ['name'=>'扩展测试','trigger'=>$when,'condition'=>$condition,'cost'=>['hand'=>$hand,'mind'=>$mind],'effects'=>$effects,'limit'=>$limit]; }
function game(array $as=[],array $bs=[],array $cs=[]): array {
    $builds=Catalog::presets(); $players=[];
    foreach(['a','b','c'] as $i=>$id) {
        $b=$builds[$i]; $b['character']['skills']=[$as,$bs,$cs][$i]; $b['character']['hp']=4; $b['character']['flipColor']=null;
        $players[]=['id'=>$id,'name'=>$id,'build'=>$b];
    }
    $g=Engine::create($players,'series');
    foreach($g['players'] as &$p) { $g['deck']=array_merge($g['deck'],$p['hand']); $p['hand']=[]; $p['usedSkills']=[]; } unset($p);
    $g['phase']='play'; $g['resumePhase']='play'; $g['pending']=null; $g['queue']=[];
    return $g;
}
function physical(array $g): array {
    $cards=array_merge($g['deck'],$g['discard'],$g['draft']??[]);
    foreach($g['players'] as $p) { foreach(['hand','mind','spent','equipment','delayed'] as $zone) $cards=array_merge($cards,$p[$zone]); foreach($p['sequestered']??[] as $held) $cards[]=$held['card']; }
    foreach($g['queue'] as $e) if(($e['effect']??'')==='delayed') $cards[]=$e['card'];
    if(($g['pending']['event']['effect']??'')==='delayed') $cards[]=$g['pending']['event']['card'];
    if(($g['pending']['kind']??'')==='scry') $cards=array_merge($cards,$g['pending']['cards']);
    $uids=array_column($cards,'uid'); sort($uids); return $uids;
}
function act(array &$g,string $id,array $action): void { $before=physical($g); Engine::act($g,$id,$action); check(physical($g)===$before,'physical identities conserved'); }
function reject(array &$g,string $id,array $action,string $why): void {
    $before=$g; try { Engine::act($g,$id,$action); } catch(InvalidArgumentException $e) { check($g===$before,$why.' rolls back'); return; } throw new RuntimeException('not rejected: '.$why);
}
function card(array &$g,string $id,string $type,string $zone='hand'): string {
    foreach($g['deck'] as $i=>$c) if($c['type']===$type) { array_splice($g['deck'],$i,1); if($zone==='equipment') $c['readyAt']=0; $g['players'][$id][$zone][]=$c; return $c['uid']; }
    throw new RuntimeException('fixture missing '.$type);
}
function endTurn(array &$g): void {
    $id=$g['turn']; act($g,$id,['type'=>'end']);
    if($g['phase']==='discard') act($g,$id,['type'=>'discard','cards'=>array_column(array_slice($g['players'][$id]['hand'],0,Engine::view($g,$id)['discardRequired']),'uid')]);
}
function choices(array $g,string $id): array { return array_map(function($a){return $a['action']['choice']??$a['action']['type'];},Engine::legalActions($g,$id)); }

$data=json_decode(file_get_contents(__DIR__.'/../docs/content/sgs-expansion-templates.json'),true);
check(count($data['skillTemplates'])===30,'thirty distinct source templates'); $ids=[]; $packages=[];
foreach($data['skillTemplates'] as $t) {
    $b=Catalog::presets()[0]; $b['character']['hp']=4; $b['character']['flipColor']=null; $b['character']['skills']=[$t['skill']];
    $valid=Rules::validateBuild($b); check(Rules::budget($valid)['character']<=8,'composable budget '.$t['id']);
    check(strpos($t['sourceUrl'],'sanguosha.com/')!==false&&$t['adaptation']!==''&&$t['sourceVersion']!=='','official source and bounded adaptation '.$t['id']);
    $ids[]=$t['id']; $packages[$t['expansion']]=true;
}
check(count(array_unique($ids))===30&&count($packages)===4,'ids distinct and four required packages');

// 0.2 snapshots gain additive defaults without losing cards or used-skill counters.
$g=game([skill('active',[effect('shield')])]); $g['rulesVersion']='0.2.0-alpha';
unset($g['players']['a']['sequestered'],$g['players']['a']['handLockedUntil'],$g['players']['a']['damageGuard'],$g['players']['a']['playedThisTurn']);
$before=physical($g); check(Engine::tick($g)&&$g['rulesVersion']===SkillBlocks::VERSION,'0.2 state explicitly upgrades');
check($g['players']['a']['sequestered']===[]&&physical($g)===$before,'migration preserves all cards');

// Temporary cards are private to their holder, keep mind ownership, and return only at global turn end.
$g=game([skill('active',[effect('sequester_hand',2,'target')])]);
$mind=array_shift($g['players']['c']['mind']); $g['players']['b']['hand'][]=$mind; $normal=card($g,'b','defense');
reject($g,'a',['type'=>'skill','index'=>0,'target'=>'a'],'cannot sequester self');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
check(count($g['players']['b']['hand'])===0&&count($g['players']['b']['sequestered'])===2,'cards removed into temporary zone');
$other=json_encode(Engine::view($g,'a')); $own=Engine::view($g,'b');
check(strpos($other,$normal)===false&&strpos($other,$mind['uid'])===false,'hidden identities never exposed to attacker');
check(count($own['me']['sequestered'])===2,'holder sees own temporary cards');
endTurn($g); check(count($g['players']['b']['hand'])===2&&$g['players']['b']['sequestered']===[],'turn end returns temporary cards');
$found=array_values(array_filter($g['players']['b']['hand'],function($c)use($mind){return $c['uid']===$mind['uid'];}));
check($found[0]['owner']==='c','return preserves original third-party mind owner');

// A killed holder spends its temporary cards; source death and early victory cannot strand any.
$g=game([skill('active',[effect('sequester_hand',1,'target')]),skill('active',[effect('damage',3,'target')])]);
$mind=array_shift($g['players']['c']['mind']); $g['players']['b']['hand'][]=$mind; $g['players']['b']['marks']['neutral']=1;
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); act($g,'a',['type'=>'skill','index'=>1,'target'=>'b']);
check(!$g['players']['b']['alive']&&$g['players']['b']['sequestered']===[]&&in_array($mind['uid'],array_column($g['players']['c']['spent'],'uid'),true),'holder death spends mind to original owner');
$g=game([skill('active',[effect('sequester_hand',1,'target')]),skill('active',[effect('lose_health',3)])]); $g['players']['a']['marks']['neutral']=1;
$held=card($g,'b','defense'); act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); act($g,'a',['type'=>'skill','index'=>1]);
check(!$g['players']['a']['alive']&&$g['turn']==='b'&&in_array($held,array_column($g['players']['b']['hand'],'uid'),true),'source dies and next turn restores held cards');
$g=game([skill('active',[effect('sequester_hand',1,'target')]),skill('active',[effect('damage',3,'target')])]);
$g['players']['b']['series']=$g['players']['a']['series']; $g['players']['c']['marks']['neutral']=1; $held=card($g,'b','defense');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); act($g,'a',['type'=>'skill','index'=>1,'target'=>'c']);
check($g['status']==='finished'&&$g['players']['b']['sequestered']===[]&&in_array($held,array_column($g['players']['b']['hand'],'uid'),true),'early victory returns living holder cards');

// An attack-target reaction may open private scry, then resume exactly one attack.
$g=game([], [skill('on_targeted',[effect('scry'),effect('draw')])]); $attack=card($g,'a','attack_neutral');
act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);
check($g['pending']['kind']==='scry'&&$g['pending']['player']==='b','target reaction suspends attack for private choice');
check(!isset(Engine::view($g,'a')['pending']['cards']),'reaction scry stays private');
act($g,'b',['type'=>'respond','choice'=>'confirm']); check($g['pending']['kind']==='attack'&&count($g['players']['b']['hand'])===1,'attack resumes after scry and draw');
act($g,'b',['type'=>'respond','choice'=>'damage']); check($g['players']['b']['marks']['neutral']===1&&$g['pending']===null,'target announcement triggers exactly once');

// Nearby ally receives the aid; the attacker is not mistaken for the linked target.
$g=game([],[],[skill('ally_targeted',[effect('draw'),effect('give_hand',1,'target')])]); $g['players']['c']['series']=$g['players']['b']['series'];
$attack=card($g,'a','attack_neutral'); act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);
check(count($g['players']['b']['hand'])===1&&count($g['players']['c']['hand'])===0,'ally aid gives to attacked teammate');
check(($g['players']['c']['usedSkills'][0]['count']??0)===1,'ally aid bounded once');

// Lock applies equally to API, candidate actions, conversions, and automatic choices.
$convert=skill('convert',[]); $convert['conversion']=['from'=>'hand','to'=>'defense'];
$g=game([skill('active',[effect('hand_lock',1,'target')])],[$convert]); $defense=card($g,'b','defense'); $evade=card($g,'b','evade','equipment'); $attack=card($g,'a','attack_neutral');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);
check(!in_array('defend',choices($g,'b'),true)&&in_array('evade',choices($g,'b'),true),'hand lock excludes defense and conversion but preserves equipment');
reject($g,'b',['type'=>'respond','choice'=>'defend','card'=>$defense],'locked physical defense');
reject($g,'b',['type'=>'respond','choice'=>'defend','card'=>$defense,'conversion'=>0],'locked conversion defense');
act($g,'b',['type'=>'respond','choice'=>'evade','card'=>$evade]); endTurn($g);
check(!$g['players']['b']['handLockedUntil']&&!Engine::view($g,'b')['players'][1]['handLocked'],'turn ends clears lock');
act($g,'b',['type'=>'draw','mind'=>0]); $play=card($g,'b','mana'); act($g,'b',['type'=>'play','card'=>$play,'mode'=>'draw']);
$g=game([skill('active',[effect('hand_lock',1,'target')])]); card($g,'b','defense'); $attack=card($g,'a','attack_neutral');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']); $g['players']['b']['bot']=true;
check(Engine::botStep($g)&&$g['players']['b']['marks']['neutral']===1,'bot observes hand lock');

// Recurring guard is applied before shield, positive damage counts only, and health loss bypasses it.
$g=game([skill('active',[effect('damage',1,'target')],'always',0,1),skill('active',[effect('damage',3,'target')],'always',0,1)]); $g['players']['b']['damageGuard']=1; $g['players']['b']['shield']=1;
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); check($g['players']['b']['marks']['neutral']===0&&$g['players']['b']['shield']===1&&$g['players']['b']['damageThisTurn']===0,'guard precedes shield and prevents damage trigger');
act($g,'a',['type'=>'skill','index'=>1,'target'=>'b']); check($g['players']['b']['marks']['neutral']===1&&$g['players']['b']['shield']===0&&$g['players']['b']['damageThisTurn']===1,'larger damage penetrates guard and shield');
endTurn($g); check($g['players']['b']['damageGuard']===0,'guard expires at own next turn');
$g=game([skill('active',[effect('lose_health')])]); $g['players']['a']['damageGuard']=2; $g['players']['a']['shield']=3;
act($g,'a',['type'=>'skill','index'=>0]); check($g['players']['a']['marks']['neutral']===1&&$g['players']['a']['damageThisTurn']===0,'health loss bypasses reductions and damage counter');

// Discard equipment preserves treasure backlash and announces the actual remover after the chain.
$g=game([skill('active',[effect('discard_equipment',1,'target')])],[skill('after_lose_equipment',[effect('discard_hand',1,'target')])]);
$treasure=card($g,'b','treasure','equipment'); $retaliation=card($g,'a','defense');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
check($g['players']['b']['marks']['neutral']===2&&count($g['players']['b']['equipment'])===0,'treasure removal retains backlash');
check(in_array($retaliation,array_column($g['discard'],'uid'),true),'equipment loss linked to actual remover');
$g=game([skill('active',[effect('discard_equipment'),effect('draw',2)])]); reject($g,'a',['type'=>'skill','index'=>0],'self discard cannot grant free follow-up without equipment');
$g['players']['a']['marks']['neutral']=3; card($g,'a','treasure','equipment'); act($g,'a',['type'=>'skill','index'=>0]);
check(!$g['players']['a']['alive']&&count($g['players']['a']['hand'])===0,'self treasure death stops follow-up rewards');

// Same rank/suit follows physical cards and active plays; responses never increment this count.
$g=game([skill('after_play_card',[effect('draw')],'same_rank_or_suit',0,0,2)]); $first=card($g,'a','mana'); $second=card($g,'a','mana');
$g['players']['a']['hand'][1]['rank']=$g['players']['a']['hand'][0]['rank'];
act($g,'a',['type'=>'play','card'=>$first,'mode'=>'draw']); $count=count($g['players']['a']['hand']); act($g,'a',['type'=>'play','card'=>$second,'mode'=>'draw']);
check($g['players']['a']['playedThisTurn']===2&&count($g['players']['a']['hand'])===$count+2,'same rank yields one extra draw after second physical play');
check(($g['players']['a']['usedSkills'][0]['count']??0)===1,'first unmatched play does not consume trigger');
$g=game([], [skill('after_damage',[effect('heal')],'first_damage'),skill('after_damage',[effect('lose_health')],'repeat_damage')]);
$g['players']['a']['character']['skills']=[skill('active',[effect('damage',1,'target')],'always',0,0,2)];
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); check($g['players']['b']['marks']['neutral']===0,'first actual damage heals');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); check($g['players']['b']['marks']['neutral']===2&&$g['players']['b']['damageThisTurn']===2,'repeat damage condition adds health loss without a third damage event');

// Count thresholds reflect cards rather than effects, and reset at every global turn.
foreach(['played_two'=>2,'played_three'=>3,'played_hp'=>4] as $condition=>$threshold) {
    $g=game([skill('active',[effect('shield')],$condition)]);
    $g['players']['a']['playedThisTurn']=$threshold-1; reject($g,'a',['type'=>'skill','index'=>0],$condition.' below threshold');
    $g['players']['a']['playedThisTurn']=$threshold; act($g,'a',['type'=>'skill','index'=>0]); check($g['players']['a']['shield']===1,$condition.' meets threshold');
    endTurn($g); check($g['players']['a']['playedThisTurn']===0,$condition.' resets globally');
}
$g=game([skill('after_play_card',[effect('shield')],'first_card')]); $one=card($g,'a','mana'); $two=card($g,'a','mana');
act($g,'a',['type'=>'play','card'=>$one,'mode'=>'draw']); act($g,'a',['type'=>'play','card'=>$two,'mode'=>'draw']);
check($g['players']['a']['shield']===1&&$g['players']['a']['playedThisTurn']===2,'first card triggers once for active physical play');
$g=game([skill('active',[effect('shield')],'has_equipment')]); reject($g,'a',['type'=>'skill','index'=>0],'has equipment rejects empty slot'); card($g,'a','evade','equipment'); act($g,'a',['type'=>'skill','index'=>0]); check($g['players']['a']['shield']===1,'has equipment allows occupied slot');
$g=game([skill('active',[effect('shield')],'hand_same_color')]); card($g,'a','defense'); reject($g,'a',['type'=>'skill','index'=>0],'single card not same-color hand'); card($g,'a','defense');
$g['players']['a']['hand'][0]['suit']='♥'; $g['players']['a']['hand'][1]['suit']='♠'; reject($g,'a',['type'=>'skill','index'=>0],'mixed suits not same-color');
unset($g['players']['a']['hand'][1]['suit']); reject($g,'a',['type'=>'skill','index'=>0],'suitless card not red by character color');
$g['players']['a']['hand'][1]['suit']='♦'; act($g,'a',['type'=>'skill','index'=>0]); check($g['players']['a']['shield']===1,'two different red suits qualify');

// Lock on an attacker prevents its next play but not active skill payment or gifting.
$g=game([skill('active',[effect('give_hand',1,'target')])],[skill('on_targeted',[effect('hand_lock',1,'target')])]);
$attack=card($g,'a','attack_neutral'); $gift=card($g,'a','mana'); act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']); act($g,'b',['type'=>'respond','choice'=>'damage']);
reject($g,'a',['type'=>'play','card'=>$gift,'mode'=>'draw'],'locked attacker next play');
check(!in_array('play',choices($g,'a'),true),'active legal actions omit locked hand plays');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b','selection'=>[$gift]]); check(in_array($gift,array_column($g['players']['b']['hand'],'uid'),true),'locked hand may still be gifted');
endTurn($g); check(!Engine::view($g,'a')['players'][0]['handLocked'],'lock expires without requiring hand use');

// Sequestered cards survive an asynchronous turn-end reaction and bounded-game termination.
$g=game([skill('active',[effect('sequester_hand',1,'target')]),skill('turn_end',[effect('scry')])]); $held=card($g,'b','defense');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); endTurn($g);
check($g['pending']['kind']==='scry'&&count($g['players']['b']['sequestered'])===1,'turn-end response retains temporary zone until resolution');
act($g,'a',['type'=>'respond','choice'=>'confirm']); check($g['turn']==='b'&&in_array($held,array_column($g['players']['b']['hand'],'uid'),true),'response completion returns cards before next turn');
$g=game([skill('active',[effect('sequester_hand',1,'target'),effect('hand_lock',1,'target')])]); $g['turnNumber']=300; $held=card($g,'b','defense');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']); endTurn($g);
check($g['status']==='finished'&&$g['winner']==='平局'&&$g['players']['b']['sequestered']===[]&&$g['players']['b']['handLockedUntil']===0,'300-turn limit releases all temporary effects');

// The finite-resource guard template cannot refresh after its last mind card is consumed.
$guard=array_values(array_filter($data['skillTemplates'],function($t){return $t['id']==='sgsx_huaiju';}))[0]['skill'];
check($guard['cost']['mind']===1,'guard template requires finite mind resource');
$g=game([],[$guard]); $g['players']['b']['spent']=array_slice($g['players']['b']['mind'],1); $g['players']['b']['mind']=array_slice($g['players']['b']['mind'],0,1);
endTurn($g); check($g['players']['b']['mind']===[]&&$g['players']['b']['damageGuard']===1,'last mind pays for one final guard');
act($g,'b',['type'=>'draw','mind'=>0]); endTurn($g); act($g,'c',['type'=>'draw','mind'=>0]); endTurn($g); act($g,'a',['type'=>'draw','mind'=>0]); endTurn($g);
check($g['players']['b']['damageGuard']===0,'broken character cannot renew guard');

echo "PASS $checks expansion assertions\n";
