<?php
require_once __DIR__.'/../src/bootstrap.php';
use Imaginary\{Catalog,Engine,SkillBlocks,RuleConfig};
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$checks=0;
function ic(bool $ok,string $message): void { global $checks; $checks++; if(!$ok)throw new RuntimeException($message); }
function invokeIdentity(string $method,array &$g,...$args) { $m=new ReflectionMethod(Engine::class,$method);$m->setAccessible(true);return $m->invokeArgs(null,array_merge([&$g],$args)); }
function identityPlayers(int $n,bool $bots=false): array {
    $out=[];$base=Catalog::presets()[0];$base['character']['skills']=[];
    for($i=0;$i<$n;$i++)$out[]=['id'=>'p'.$i,'name'=>'席位'.$i,'bot'=>$bots,'build'=>$base];
    return $out;
}
function ig(): array {
    $g=Engine::create(identityPlayers(5),'identity');$roles=['mayor','villager','wolf','wolf','fox'];
    foreach($g['players'] as $id=>&$p){$i=(int)substr($id,1);$p['role']=$roles[$i];$p['maxHp']=$p['character']['hp']+($i===0?1:0);$g['deck']=array_merge($g['deck'],$p['hand']);$p['hand']=[];$p['turns']=$i===0?1:0;}unset($p);
    $g['mayor']='p0';$g['turn']='p0';$g['phase']=$g['resumePhase']='play';return $g;
}
function takeIdentity(array &$g,string $id,string $type,string $zone='hand'): string {
    foreach($g['deck'] as $i=>$card)if($card['type']===$type){array_splice($g['deck'],$i,1);if($zone==='equipment')$card['readyAt']=$g['players'][$id]['turns'];$g['players'][$id][$zone][]=$card;return $card['uid'];}
    throw new RuntimeException('Missing card '.$type);
}
function rejectIdentity(array &$g,string $id,array $action): void {
    $old=$g;try{Engine::act($g,$id,$action);}catch(InvalidArgumentException $e){ic($old===$g,'Rejected action is atomic');return;}throw new RuntimeException('Expected rejection');
}
function passRescue(array &$g): void {
    for($i=0;$i<30&&($g['pending']['kind']??'')==='identity_rescue';$i++)Engine::act($g,$g['pending']['player'],['type'=>'respond','choice'=>'pass']);
    ic(($g['pending']['kind']??'')!=='identity_rescue','Rescue terminates');
}
function woundIdentity(array &$g,string $target,int $n=99,?string $source='p0'): void {
    invokeIdentity('damage',$g,$source,$target,$n,'neutral');invokeIdentity('progress',$g);
}
function identityPhysical(array $g): array {
    $cards=array_merge($g['deck'],$g['discard'],$g['draft']??[]);
    foreach($g['players'] as $p){foreach(['hand','mind','spent','equipment','delayed'] as $zone)$cards=array_merge($cards,$p[$zone]);foreach($p['piles']??[] as $pile)$cards=array_merge($cards,$pile['cards']);foreach($p['sequestered']??[] as $s)$cards[]=$s['card'];}
    if(($g['pending']['kind']??'')==='scry')$cards=array_merge($cards,$g['pending']['cards']);
    if(($g['pending']['event']['effect']??'')==='delayed')$cards[]=$g['pending']['event']['card'];
    foreach($g['queue'] as $e)if(($e['effect']??'')==='delayed')$cards[]=$e['card'];
    return [count($cards),count(array_unique(array_column($cards,'uid')))];
}

foreach(range(4,7) as $n)for($sample=0;$sample<6;$sample++){
    $g=Engine::create(identityPlayers($n),'identity');$counts=array_count_values(array_column($g['players'],'role'));
    foreach(Catalog::identityCounts($n) as $role=>$count)ic(($counts[$role]??0)===$count,'Role distribution '.$n);
    ic($g['turn']===$g['mayor']&&$g['players'][$g['mayor']]['maxHp']===7,'Mayor starts with +1 max health');
    ic(identityPhysical($g)===[104+13*$n,104+13*$n],'Initial physical cards conserved');
    foreach($g['players'] as $id=>$p){$v=Engine::view($g,$id);ic($v['identity']['role']===$p['role'],'Own identity available');foreach($v['players'] as $other)ic($other['role']===($other['id']===$id||$other['id']===$g['mayor']?$g['players'][$other['id']]['role']:null),'Other live roles hidden');}
}
foreach([2,3,8] as $n){try{Engine::create(identityPlayers($n),'identity');throw new RuntimeException('Invalid size accepted');}catch(InvalidArgumentException $e){ic(true,'Invalid size rejected');}}
$counts=array_count_values(array_column(Catalog::ordinary('identity'),'type'));
ic(count(Catalog::ordinary('identity'))===104&&$counts['heal']===8&&$counts['medicine']===4&&$counts['defense']===16,'Identity deck includes dedicated healing');
ic($counts['attack_cool']+$counts['attack_warm']+$counts['attack_neutral']===32&&$counts['evade']===6,'Identity attack/defense balance');
ic(array_count_values(array_column(Catalog::ordinary(),'type'))['defense']===20,'Existing deck preserved');
foreach(['attack_cool','attack_warm','attack_neutral'] as $type){
    $g=ig();$hit=takeIdentity($g,'p0',$type);rejectIdentity($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p0']);
    Engine::act($g,'p0',['type'=>'play','card'=>$hit,'target'=>'p1']);Engine::act($g,'p1',['type'=>'respond','choice'=>'damage']);
    ic($g['players']['p1']['marks']['neutral']===1&&$g['players']['p1']['maxHp']===6,'Every attack damages same-colored target without max loss');
}
$g=ig();$heal=takeIdentity($g,'p0','heal');rejectIdentity($g,'p0',['type'=>'play','card'=>$heal]);$g['players']['p0']['marks']['neutral']=2;
rejectIdentity($g,'p0',['type'=>'play','card'=>$heal,'target'=>'p1']);Engine::act($g,'p0',['type'=>'play','card'=>$heal]);ic($g['players']['p0']['marks']['neutral']===1,'Rest heals only self');
$g=ig();$med=takeIdentity($g,'p0','medicine');$med2=takeIdentity($g,'p0','medicine');$g['players']['p1']['marks']['neutral']=2;
Engine::act($g,'p0',['type'=>'play','card'=>$med]);Engine::act($g,'p0',['type'=>'play','card'=>$med2]);ic(count($g['players']['p0']['equipment'])===2,'Medicines stack independently');
rejectIdentity($g,'p0',['type'=>'equip_use','card'=>$med,'target'=>'p1']);$g['players']['p0']['turns']++;
Engine::act($g,'p0',['type'=>'equip_use','card'=>$med,'target'=>'p1']);ic($g['players']['p1']['marks']['neutral']===1&&count($g['players']['p0']['equipment'])===1,'Mature medicine heals another person');

// Excess damage requires multiple heals; rescue survives serialization and cannot be used by another seat.
$g=ig();$heal=takeIdentity($g,'p1','heal');$heal2=takeIdentity($g,'p1','heal');woundIdentity($g,'p1',7);
ic($g['pending']['kind']==='identity_rescue'&&$g['pending']['player']==='p1','Dying player rescues first');
rejectIdentity($g,'p2',['type'=>'respond','choice'=>'rescue_hand','card'=>$heal]);$g=json_decode(json_encode($g),true);
Engine::act($g,'p1',['type'=>'respond','choice'=>'rescue_hand','card'=>$heal]);ic($g['pending']['player']==='p1','One heal is insufficient after excess damage');
Engine::act($g,'p1',['type'=>'respond','choice'=>'rescue_hand','card'=>$heal2]);ic($g['pending']===null&&invokeIdentity('hp',$g,'p1')===1&&$g['players']['p1']['alive'],'Two heals prevent death without flipping');
ic(identityPhysical($g)===[169,169],'Rescue conserves physical cards');
$g=ig();$heal=takeIdentity($g,'p1','heal');$g['players']['p1']['spent']=$g['players']['p1']['mind'];$g['players']['p1']['mind']=[];
woundIdentity($g,'p1',6);Engine::act($g,'p1',['type'=>'respond','choice'=>'rescue_hand','card'=>$heal]);ic(invokeIdentity('hp',$g,'p1')===1,'Rest can rescue a broken mind');
$g=ig();takeIdentity($g,'p1','heal');$med=takeIdentity($g,'p2','medicine','equipment');$g['players']['p1']['maxHp']=0;woundIdentity($g,'p1',1);
$opts=Engine::legalActions($g,'p1');ic(count($opts)===1&&$opts[0]['action']['choice']==='pass','Rest cannot restore zero max health');
Engine::act($g,'p1',['type'=>'respond','choice'=>'pass']);rejectIdentity($g,'p2',['type'=>'respond','choice'=>'rescue_equipment','card'=>$med]);passRescue($g);
$g=ig();$miracle=takeIdentity($g,'p1','miracle','equipment');$g['players']['p1']['maxHp']=0;woundIdentity($g,'p1',1);
Engine::act($g,'p1',['type'=>'respond','choice'=>'rescue_equipment','card'=>$miracle]);ic($g['players']['p1']['maxHp']===2&&invokeIdentity('hp',$g,'p1')===2,'Miracle retains max-health rescue exception');
$g=ig();$heal=takeIdentity($g,'p1','heal');woundIdentity($g,'p1',6);$g['deadline']=0;Engine::tick($g);
ic($g['pending']['player']==='p2'&&count($g['players']['p1']['hand'])===1,'Human timeout passes without spending a rescue card');
$g=ig();$heal=takeIdentity($g,'p2','heal');$med=takeIdentity($g,'p2','medicine','equipment');woundIdentity($g,'p1',6);Engine::act($g,'p1',['type'=>'respond','choice'=>'pass']);
rejectIdentity($g,'p2',['type'=>'respond','choice'=>'rescue_hand','card'=>$heal]);Engine::act($g,'p2',['type'=>'respond','choice'=>'rescue_equipment','card'=>$med]);ic(invokeIdentity('hp',$g,'p1')===1,'Other player uses mature medicine, never ordinary rest');
$g=ig();$miracle=takeIdentity($g,'p2','miracle','equipment');woundIdentity($g,'p1',6);ic(count($g['players']['p2']['equipment'])===1,'Miracle is not consumed automatically in identity mode');passRescue($g);
ic(!$g['players']['p1']['alive']&&!$g['players']['p1']['flipped']&&$g['players']['p1']['color']==='cool','Death never flips color');
ic(Engine::view($g,'p3')['players'][1]['role']==='villager','Dead role is public');

// Elimination outcomes include fallen teammates, and the fox must leave the mayor for last.
$g=ig();woundIdentity($g,'p2');passRescue($g);ic(count($g['players']['p0']['hand'])===3,'Killing a wolf rewards three cards');
woundIdentity($g,'p3');passRescue($g);ic($g['status']==='playing','Fox keeps village game running');woundIdentity($g,'p4');passRescue($g);
ic($g['status']==='finished'&&$g['winningTeam']==='village'&&$g['winningIds']===['p0','p1'],'Village wins after wolves and fox die');
ic(empty($g['eventStack'])&&empty($g['eventFrames'])&&!$g['pending']&&!$g['queue'],'Immediate identity victory clears nested frames and continuations');
foreach(Engine::view($g,'p0')['players'] as $p)ic($p['role']!==null,'All final roles revealed');
$g=ig();takeIdentity($g,'p0','defense');takeIdentity($g,'p0','medicine','equipment');takeIdentity($g,'p0','fortune','delayed');woundIdentity($g,'p1');passRescue($g);
ic(!$g['players']['p0']['hand']&&!$g['players']['p0']['equipment']&&!$g['players']['p0']['delayed'],'Mayor friendly fire discards hand and all cards in play');
foreach(['p2','p3','p4'] as $id){woundIdentity($g,$id);passRescue($g);}ic(in_array('p1',$g['winningIds'],true),'Dead villager shares victory');
$g=ig();foreach(['p2','p3','p0'] as $id){woundIdentity($g,$id,99,'p4');passRescue($g);}ic($g['winningTeam']==='wolf'&&$g['winningIds']===['p2','p3'],'Dead wolves win if mayor dies before fox is sole survivor');
$g=ig();foreach(['p1','p2','p3','p0'] as $id){woundIdentity($g,$id,99,'p4');passRescue($g);}ic($g['winningTeam']==='fox'&&$g['winningIds']===['p4'],'Fox sole survivor wins');
$g=ig();takeIdentity($g,'p0','treasure','equipment');$g['players']['p0']['marks']['neutral']=6;woundIdentity($g,'p1');passRescue($g);
ic($g['status']==='finished'&&$g['winningTeam']==='wolf'&&!$g['players']['p0']['alive'],'Mayor penalty resolves treasure loss and nested dying');
ic(identityPhysical($g)===[169,169],'Penalty and nested dying conserve cards');
$g=ig();$g['players']['p0']['maxHp']=99;invokeIdentity('effects',$g,'p0','p0',[Catalog::effect('gain_max_hp')]);ic($g['players']['p0']['maxHp']===99,'Increasing max health does not undo the mayor bonus at the configured cap');
$g=ig();$g['players']['p0']['marks']['neutral']=7;invokeIdentity('stopResolution',$g);
ic($g['status']==='finished'&&$g['winningTeam']==='wolf'&&!$g['queue']&&empty($g['eventFrames']),'Resolution limit cannot reopen an endless identity rescue chain');
$g=ig();$g['players']['p0']['character']['skills']=[['name'=>'后续不执行','trigger'=>'active','condition'=>'always','cost'=>['hand'=>0,'mind'=>0],'effects'=>[Catalog::effect('damage',98,'target'),Catalog::effect('draw',5)],'limit'=>1]];
Engine::act($g,'p0',['type'=>'skill','index'=>0,'target'=>'p0']);passRescue($g);ic(!$g['players']['p0']['hand']&&$g['status']==='finished','Game-winning elimination cancels later effects');

// Color predicates / candidate actions / bot choices cannot be an oracle for hidden identities.
$g=ig();$g['players']['p0']['bot']=true;takeIdentity($g,'p0','attack_cool');$copy=$g;
$copy['players']['p1']['role']='wolf';$copy['players']['p2']['role']='villager';
$view=Engine::view($g,'p0');$other=Engine::view($copy,'p0');unset($view['serverTime'],$other['serverTime']);
ic($view===$other,'Swapping concealed roles leaves the full viewer response unchanged');
ic(invokeIdentity('chooseAuto',$g,'p0')===invokeIdentity('chooseAuto',$copy,'p0'),'Bots do not see hidden roles');
$g=ig();$g['rulesVersion']='0.6.0-alpha';$med=takeIdentity($g,'p0','evade','equipment');$g['players']['p0']['equipment'][0]['readyAt']=5;unset($g['players']['p0']['equipment'][0]['maturityTurns']);
Engine::act($g,'p0',['type'=>'end']);ic($g['rulesVersion']===SkillBlocks::VERSION&&$g['players']['p0']['equipment'][0]['readyAt']===5,'0.6 migration does not subtract maturity again');

// Real multi-seat bot games: all supported sizes finish, conserve physical cards and reconnect safely.
RuleConfig::configure(['maxTurns'=>100]);
$simulationLimit=(RuleConfig::get('maxTurns')+1)*(RuleConfig::get('maxActionsPerTurn')+1)*8;
foreach(range(4,7) as $n){
    $g=Engine::create(identityPlayers($n,true),'identity');$steps=0;
    while($g['status']==='playing'&&$steps<$simulationLimit){ic(Engine::botStep($g),'Bot has a legal continuation');$steps++;if($steps%25===0){ic(identityPhysical($g)===[104+13*$n,104+13*$n],'Cards conserved in bot game');$g=json_decode(json_encode($g),true);}}
    ic($g['status']==='finished','Bot match finishes for '.$n.' seats');ic(identityPhysical($g)===[104+13*$n,104+13*$n],'Finished cards conserved');
    echo 'Identity '.$n.' seats: '.$steps.' actions, '.$g['winner']."\n";
}
// Existing complex character skills exercise damage frames, event choices and death triggers.
$presets=Catalog::presets();$indexes=[1,0,47,79,105,11,132];
foreach(range(4,7) as $n){
    $players=identityPlayers($n,true);foreach($players as $i=>&$p)$p['build']=$presets[$indexes[$i]];unset($p);
    $g=Engine::create($players,'identity');$steps=0;
    while($g['status']==='playing'&&$steps<$simulationLimit){ic(Engine::botStep($g),'Content bot has a legal continuation');$steps++;if($steps%25===0){ic(identityPhysical($g)===[104+13*$n,104+13*$n],'Content game conserves cards');$g=json_decode(json_encode($g),true);}}
    ic($g['status']==='finished','Content match finishes for '.$n.' seats');ic(identityPhysical($g)===[104+13*$n,104+13*$n],'Content final cards conserved');
    echo 'Identity content '.$n.' seats: '.$steps.' actions, '.$g['winner']."\n";
}
echo 'PASS '.$checks." identity assertions\n";
