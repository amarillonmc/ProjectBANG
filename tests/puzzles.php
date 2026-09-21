<?php
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Rules;
use Imaginary\Engine;
use Imaginary\SkillBlocks;

$checks=0;
function verify($ok,string $why): void { global $checks; $checks++; if(!$ok) throw new RuntimeException($why); }
function invalid(callable $call,string $why): void { try { $call(); } catch(InvalidArgumentException $e) { verify(true,$why); return; } throw new RuntimeException('Did not reject: '.$why); }
function effect(string $op,int $n=1,string $target='self',string $color='neutral'): array { return ['op'=>$op,'amount'=>$n,'target'=>$target,'color'=>$color]; }
function skill(string $trigger,array $effects,int $hand=0,int $mind=0,int $limit=1,string $condition='always'): array { return ['name'=>'测试拼图','trigger'=>$trigger,'condition'=>$condition,'cost'=>['hand'=>$hand,'mind'=>$mind],'effects'=>$effects,'limit'=>$limit]; }
function conversion(string $from,string $to,int $limit=1,int $hand=0,int $mind=0): array { $s=skill('convert',[],$hand,$mind,$limit); $s['conversion']=['from'=>$from,'to'=>$to]; return $s; }
function setup(array $skills=[],array $other=[]): array {
    $presets=Catalog::presets(); $players=[];
    foreach(['a','b','c'] as $i=>$id) {
        $build=$presets[$i]; $build['character']['skills']=$i===0?$skills:($i===1?$other:[]); $build['character']['hp']=4; $build['character']['flipColor']=null;
        $players[]=['id'=>$id,'name'=>$id,'build'=>$build];
    }
    $g=Engine::create($players,'series');
    foreach($g['players'] as &$p) { foreach($p['hand'] as $card) $g['deck'][]=$card; $p['hand']=[]; } unset($p);
    $g['phase']='play'; $g['resumePhase']='play'; $g['queue']=[]; $g['pending']=null;
    return $g;
}
function takeType(array &$g,string $id,string $type): string {
    foreach($g['deck'] as $i=>$card) if($card['type']===$type) { array_splice($g['deck'],$i,1); $g['players'][$id]['hand'][]=$card; return $card['uid']; }
    throw new RuntimeException('Missing fixture card '.$type);
}
function counts(array $g): array {
    $uids=[];
    foreach(['deck','discard','draft'] as $zone) foreach($g[$zone]??[] as $c) $uids[]=$c['uid'];
    foreach($g['players'] as $p) foreach(['hand','mind','spent','equipment','delayed'] as $zone) foreach($p[$zone] as $c) $uids[]=$c['uid'];
    foreach($g['queue'] as $event) if(($event['effect']??'')==='delayed') $uids[]=$event['card']['uid'];
    if(($g['pending']['event']['effect']??'')==='delayed') $uids[]=$g['pending']['event']['card']['uid'];
    if(($g['pending']['kind']??'')==='scry') foreach($g['pending']['cards'] as $c) $uids[]=$c['uid'];
    return [count($uids),count(array_unique($uids))];
}
function act(array &$g,string $id,array $a): void { Engine::act($g,$id,$a); verify(counts($g)===[143,143],'physical cards conserved'); }
function atomic(array &$g,string $id,array $a,string $why): void { $before=$g; invalid(function()use(&$g,$id,$a){Engine::act($g,$id,$a);},$why); verify($g===$before,'rejection rolled back: '.$why); }

$b=Catalog::presets()[0]; $b['rulesVersion']='0.1.0-alpha'; $b['character']['art']='kf3_0001';
verify(Rules::validateBuild($b)['rulesVersion']===SkillBlocks::VERSION,'legacy builds migrate');
$bad=$b; $bad['rulesVersion']='99.0'; invalid(function()use($bad){Rules::validateBuild($bad);},'unknown rule version');
$bad=$b; $bad['character']['art']='../../secret'; invalid(function()use($bad){Rules::validateBuild($bad);},'art traversal');
$bad=$b; $bad['character']['skills']=[skill('active',[effect('draw',3)],0,0,2),skill('active',[effect('draw',3)],0,0,2)]; invalid(function()use($bad){Rules::validateBuild($bad);},'limit two charged twice');
invalid(function(){Rules::effects([effect('steal_hand')]);},'steal requires another target');
invalid(function(){Rules::effects([effect('double_defense',2)]);},'double defense fixed magnitude');
$bad=$b; $bad['character']['skills']=[conversion('hand','damage')]; invalid(function()use($bad){Rules::validateBuild($bad);},'unknown conversion result');
$bad=$b; $bad['character']['skills']=[conversion('hand','defense')]; $bad['character']['skills'][0]['effects']=[effect('draw')]; invalid(function()use($bad){Rules::validateBuild($bad);},'conversion cannot smuggle effects');

// Stored rooms carry a real version. Migration is explicit, idempotent and transactional with actions.
$g=setup([skill('active',[effect('draw')]),skill('active',[effect('shield')])]);
verify($g['rulesVersion']===SkillBlocks::VERSION,'new games store their rules version');
unset($g['rulesVersion'],$g['handBoundary'],$g['players']['a']['extraAttacks'],$g['players']['a']['doubleDefense']);
$g['players']['a']['usedSkills'][0]=$g['turnNumber']; $legacy=$g;
$legacyView=Engine::view($g,'a');
verify($legacyView['rulesVersion']==='0.1.0-alpha'&&$legacyView['rulesUpgradePending']&&$g===$legacy,'reading a legacy snapshot reports its version without mutation');
atomic($g,'a',['type'=>'skill','index'=>0],'legacy used skill stays used and failed action rolls migration back');
act($g,'a',['type'=>'skill','index'=>1]);
verify($g['rulesVersion']===SkillBlocks::VERSION&&$g['migratedFromRulesVersion']==='0.1.0-alpha','successful action persists explicit upgrade');
verify($g['players']['a']['usedSkills'][0]===['turn'=>$g['turnNumber'],'count'=>1]&&$g['players']['a']['extraAttacks']===0,'migration preserves old use count and adds defaults');
$g=$legacy; $beforeCards=counts($g); $beforeMarks=$g['players']['a']['marks'];
verify(Engine::tick($g)&&$g['rulesVersion']===SkillBlocks::VERSION,'idle legacy tick reports migration for persistence');
verify(counts($g)===$beforeCards&&$g['players']['a']['marks']===$beforeMarks,'migration preserves cards and damage');
$logCount=count($g['log']); verify(!Engine::tick($g)&&count($g['log'])===$logCount,'migration is idempotent');
foreach(['99.0.0-future',null] as $version) {
    $g=setup(); $g['rulesVersion']=$version; $before=$g;
    atomic($g,'a',['type'=>'end'],'unsupported room version blocks act');
    invalid(function()use(&$g){Engine::tick($g);},'unsupported room version blocks tick');
    invalid(function()use(&$g){Engine::botStep($g);},'unsupported room version blocks bot');
    invalid(function()use($g){Engine::view($g,'a');},'unsupported room version blocks view');
    invalid(function()use($g){Engine::legalActions($g,'a');},'unsupported room version blocks candidates');
    verify($g===$before,'unsupported snapshots remain untouched');
}

// Transfers preserve the original mind owner, and never reveal hidden hands in logs or views.
$g=setup([skill('active',[effect('steal_hand',1,'target')]),skill('active',[effect('discard_hand',1,'target')])]);
$stolen=array_shift($g['players']['b']['mind']); $g['players']['b']['hand'][]=$stolen;
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
verify($g['players']['a']['hand'][0]['uid']===$stolen['uid']&&$g['players']['a']['hand'][0]['owner']==='b','stolen mind ownership retained');
atomic($g,'a',['type'=>'skill','index'=>1,'target'=>'a'],'cannot discard own hand using target-only effect');
$gift=takeType($g,'b','defense'); act($g,'a',['type'=>'skill','index'=>1,'target'=>'b']);
verify(in_array($gift,array_column($g['discard'],'uid'),true),'random discard reaches ordinary discard');
$view=Engine::view($g,'c'); foreach($view['players'] as $p) verify(!isset($p['hand'])&&!isset($p['mind']),'private zones absent');
verify(strpos(json_encode($view),$stolen['uid'])===false,'opponent private card UID not exposed');

// Gift selection follows cost payment; insufficient gifts and forged selection roll back.
$g=setup([skill('active',[effect('give_hand',2,'target'),effect('heal')],1)]);
$cost=takeType($g,'a','defense'); $gift1=takeType($g,'a','attack_neutral'); $gift2=takeType($g,'a','attack_warm');
atomic($g,'a',['type'=>'skill','index'=>0,'target'=>'b','costCards'=>[$cost],'selection'=>[$gift1,$gift1]],'duplicate gifts');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b','costCards'=>[$cost],'selection'=>[$gift2,$gift1]]);
verify(array_column($g['players']['b']['hand'],'uid')===[$gift2,$gift1],'gift selected order preserved');
$g=setup([skill('active',[effect('give_hand',2,'target'),effect('heal')])]);
atomic($g,'a',['type'=>'skill','index'=>0,'target'=>'b'],'no free healing from an unpayable gift');
$g=setup([skill('active',[effect('give_hand',1,'target'),effect('give_hand',1,'target')])]);
$gift1=takeType($g,'a','defense'); $gift2=takeType($g,'a','life');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b','selection'=>[$gift2,$gift1]]);
verify(array_column($g['players']['b']['hand'],'uid')===[$gift2,$gift1],'multiple gifts consume one ordered selection across the chain');

// Refill, once/twice limits, and additional attacks are separate from free virtual attacks.
$g=setup([skill('active',[effect('draw_to',3)],0,0,2)]);
act($g,'a',['type'=>'skill','index'=>0]); act($g,'a',['type'=>'skill','index'=>0]);
verify(count($g['players']['a']['hand'])===3,'draw to never overfills');
atomic($g,'a',['type'=>'skill','index'=>0],'third use rejected');
$g=setup([skill('active',[effect('extra_attacks')]),skill('active',[effect('attack',1,'target')])]);
$first=takeType($g,'a','attack_neutral'); $second=takeType($g,'a','attack_neutral'); $third=takeType($g,'a','attack_neutral');
act($g,'a',['type'=>'skill','index'=>0]);
foreach([$first,$second] as $card) { act($g,'a',['type'=>'play','card'=>$card,'target'=>'b']); act($g,'b',['type'=>'respond','choice'=>'damage']); }
atomic($g,'a',['type'=>'play','card'=>$third,'target'=>'b'],'attack quota exhausted');
act($g,'a',['type'=>'skill','index'=>1,'target'=>'c']); verify($g['pending']['kind']==='attack','virtual attack offers defense');
act($g,'c',['type'=>'respond','choice'=>'damage']); verify($g['players']['a']['attacks']===2,'virtual attacks do not consume ordinary quota');
$g=setup([skill('active',[effect('attack',1,'target'),effect('discard_hand',1,'target')])]);
$defense=takeType($g,'b','defense');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
verify(count($g['players']['b']['hand'])===1,'virtual attack must wait before the next discard effect');
act($g,'b',['type'=>'respond','choice'=>'defend','card'=>$defense]);
verify($g['players']['b']['marks']['neutral']===0&&$g['pending']===null,'defense can respond before following discard resolves');

// Losing health does not consume shields or grant damage rewards, but does eliminate.
$g=setup([skill('active',[effect('lose_health')]),skill('after_damage',[effect('draw',2)])]);
$g['players']['a']['shield']=3; act($g,'a',['type'=>'skill','index'=>0]);
verify($g['players']['a']['marks']['neutral']===1&&$g['players']['a']['shield']===3&&count($g['players']['a']['hand'])===0,'health loss bypasses damage pipeline');
$g=setup([skill('active',[effect('lose_health',2)])]); $g['players']['a']['marks']['neutral']=3; takeType($g,'a','defense');
act($g,'a',['type'=>'skill','index'=>0]); verify(!$g['players']['a']['alive']&&$g['turn']==='b','health loss uses death cleanup');

// Conversion is authoritative for normal play and response, including physical ownership and costs.
$g=setup([conversion('defense','attack_neutral')]); $card=takeType($g,'a','defense');
verify(count(array_filter(Engine::legalActions($g,'a'),function($x){return isset($x['action']['conversion']);}))===2,'conversion attacks listed per valid target');
act($g,'a',['type'=>'play','card'=>$card,'target'=>'b','conversion'=>0]); act($g,'b',['type'=>'respond','choice'=>'damage']);
verify($g['discard'][count($g['discard'])-1]['type']==='defense','converted card keeps printed type in discard');
$g=setup([], [conversion('attack','defense',2,1,1)]);
$attack=takeType($g,'a','attack_neutral'); $defend=takeType($g,'b','attack_warm'); $cost=takeType($g,'b','life');
act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);
atomic($g,'b',['type'=>'respond','choice'=>'defend','card'=>$defend,'conversion'=>0,'costCards'=>[$defend]],'conversion material cannot also pay cost');
act($g,'b',['type'=>'respond','choice'=>'defend','card'=>$defend,'conversion'=>0,'costCards'=>[$cost]]);
verify($g['players']['b']['marks']['neutral']===0&&count($g['players']['b']['mind'])===12,'conversion defense pays additional costs');
$g=setup([], [conversion('hand','defense')]); $attack=takeType($g,'a','attack_neutral');
$mindCard=array_shift($g['players']['c']['mind']); $g['players']['b']['hand'][]=$mindCard;
act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']); act($g,'b',['type'=>'respond','choice'=>'defend','card'=>$mindCard['uid'],'conversion'=>0]);
verify(in_array($mindCard['uid'],array_column($g['players']['c']['spent'],'uid'),true),'converted borrowed mind returns to original owner');
$g=setup([], [conversion('hand','defense')]); $attack=takeType($g,'a','attack_neutral'); $material=takeType($g,'b','life');
$g['players']['b']['spent']=$g['players']['b']['mind']; $g['players']['b']['mind']=[];
act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']); atomic($g,'b',['type'=>'respond','choice'=>'defend','card'=>$material,'conversion'=>0],'broken mind disables conversion');
$g=setup([conversion('red','attack_neutral')]); $black=null;
foreach($g['deck'] as $i=>$c) if(in_array($c['suit'],['♠','♣'],true)) { $black=$c['uid']; array_splice($g['deck'],$i,1); $g['players']['a']['hand'][]=$c; break; }
atomic($g,'a',['type'=>'play','card'=>$black,'target'=>'b','conversion'=>0],'red conversion rejects black');

// Role-bound signatures fall back within their IP when held by a different character.
$g=setup(); $special=$g['players']['a']['mind'][4];
$special['custom']=['name'=>'绑定技能','series'=>$g['players']['a']['series'],'characterId'=>$g['players']['b']['character']['id'],'fallback'=>'defense','effects'=>[effect('draw')]];
array_splice($g['players']['a']['mind'],4,1); $g['players']['a']['hand'][]=$special;
atomic($g,'a',['type'=>'play','card'=>$special['uid']],'same-IP wrong-character card falls back');

// Empty hand is observed after complete immediate groups, not between a cost and draw.
$g=setup([skill('active',[effect('draw')],1),skill('hand_empty',[effect('draw',2)])]); $cost=takeType($g,'a','defense');
act($g,'a',['type'=>'skill','index'=>0,'costCards'=>[$cost]]);
verify(count($g['players']['a']['hand'])===1&&!isset($g['players']['a']['usedSkills'][1]),'temporary empty during cost does not trigger');
$g=setup([skill('active',[effect('shield')],1),skill('hand_empty',[effect('draw',2)])]); $cost=takeType($g,'a','defense');
act($g,'a',['type'=>'skill','index'=>0,'costCards'=>[$cost]]); verify(count($g['players']['a']['hand'])===2,'true hand depletion triggers refill once');
$g=setup([skill('after_play_attack',[effect('draw')]),skill('after_play_event',[effect('draw')])]); $attack=takeType($g,'a','attack_neutral');
act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']); verify(count($g['players']['a']['hand'])===1,'attack play trigger before defense');
act($g,'b',['type'=>'respond','choice'=>'damage']); $mana=takeType($g,'a','mana');
act($g,'a',['type'=>'play','card'=>$mana,'mode'=>'draw']); verify(count($g['players']['a']['hand'])===4,'event use trigger and original card both resolve');

// Private scry is an ordered, atomic response and resumes subsequent effects only afterwards.
$g=setup([skill('active',[effect('scry',3),effect('draw')])]); $before=count($g['players']['a']['hand']);
act($g,'a',['type'=>'skill','index'=>0]); verify($g['pending']['kind']==='scry'&&count($g['players']['a']['hand'])===$before,'scry pauses its effect chain');
$private=Engine::view($g,'a'); $outside=Engine::view($g,'b');
verify(count($private['pending']['cards'])===3&&!isset($outside['pending']['cards'])&&$outside['legalActions']===[],'scry cards private');
$order=array_reverse(array_column($g['pending']['cards'],'uid'));
atomic($g,'a',['type'=>'respond','choice'=>'confirm','cards'=>[$order[0],$order[0],$order[2]]],'scry duplicate order');
act($g,'a',['type'=>'respond','choice'=>'confirm','cards'=>$order]);
verify($g['players']['a']['hand'][0]['uid']===$order[0]&&$g['deck'][0]['uid']===$order[1],'scry order controls following draw');
$g=setup([skill('turn_end',[effect('scry',2)])]);
act($g,'a',['type'=>'end']); verify($g['turn']==='a'&&$g['pending']['kind']==='scry','end trigger pauses before advancing turn');
act($g,'a',['type'=>'respond','choice'=>'confirm','cards'=>array_column($g['pending']['cards'],'uid')]); verify($g['turn']==='b','end turn continues after choice');

// Ordinary discard recovery uses top order, never the private mind spent zones.
$g=setup([skill('active',[effect('draw_discard',2)])]);
$one=takeType($g,'b','defense'); $two=takeType($g,'b','defense');
$g['discard']=$g['players']['b']['hand']; $g['players']['b']['hand']=[];
act($g,'a',['type'=>'skill','index'=>0]); verify(array_column($g['players']['a']['hand'],'uid')===[$two,$one],'discard retrieval uses top first');
$g=setup([skill('active',[effect('draw_discard'),effect('draw_discard'),effect('give_hand',2,'target')])]);
$discarded=takeType($g,'b','defense'); $g['discard']=$g['players']['b']['hand']; $g['players']['b']['hand']=[];
atomic($g,'a',['type'=>'skill','index'=>0,'target'=>'b'],'sequential discard draws cannot reuse one predicted card');
$g=setup([skill('active',[effect('draw_discard'),effect('give_hand',1,'target')],1)]); $cost=takeType($g,'a','defense');
act($g,'a',['type'=>'skill','index'=>0,'target'=>'b','costCards'=>[$cost]]);
verify(array_column($g['players']['b']['hand'],'uid')===[$cost],'ordinary hand costs become available to the following discard draw');

// Double defense requires two responses; only the complete defense earns on_defend.
$g=setup([skill('active',[effect('double_defense')])],[skill('on_defend',[effect('draw')])]);
$attack=takeType($g,'a','attack_neutral'); $first=takeType($g,'b','defense'); $second=takeType($g,'b','defense');
act($g,'a',['type'=>'skill','index'=>0]); act($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);
verify($g['pending']['defenses']===2,'double defense attack carries requirement');
act($g,'b',['type'=>'respond','choice'=>'defend','card'=>$first]);
verify($g['pending']['defenses']===1&&count($g['players']['b']['hand'])===1&&!isset($g['players']['b']['usedSkills'][0]),'one defense does not grant a defense reward');
act($g,'b',['type'=>'respond','choice'=>'defend','card'=>$second]);
verify($g['pending']===null&&count($g['players']['b']['hand'])===1&&$g['players']['b']['marks']['neutral']===0,'two defenses cancel and then grant reward');

echo 'PASS '.$checks." puzzle assertions\n";
