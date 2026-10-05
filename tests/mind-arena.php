<?php
require_once __DIR__.'/../src/bootstrap.php';
use Imaginary\{Catalog,Engine,Rules,RuleConfig,Workshop,SkillBlocks};
set_error_handler(function($n,$s,$f,$l){throw new ErrorException($s,0,$n,$f,$l);});
$checks=0;
function ac(bool $ok,string $message): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function arReject(callable $fn): void {try{$fn();}catch(InvalidArgumentException $e){ac(true,'Rejected invalid input');return;}throw new RuntimeException('Expected rejection');}
function arBuild(string $suit='♠'): array {
    $b=Catalog::presets()[0];$b['character']['skills']=[];$b['character']['flipColor']=null;$b['character']['hp']=4;
    $b['deck']=array_reverse($b['deck']);foreach($b['deck'] as &$c)$c['suit']=$suit;unset($c);return $b;
}
function arPlayers(int $n,bool $bots=true): array {
    $out=[];for($i=0;$i<$n;$i++){$b=arBuild($i%2?'♥':'♠');$b['character']['series']='系列'.($i%2);$b['character']['color']=$i%2?'warm':'cool';$out[]=['id'=>'p'.$i,'name'=>'机器人'.$i,'bot'=>$bots,'build'=>$b];}return $out;
}
function arSkill(array $effect,string $trigger='active'): array {return ['name'=>'判定测试','trigger'=>$trigger,'condition'=>'always','cost'=>['hand'=>0,'mind'=>0],'limit'=>1,'effects'=>[$effect]];}
$base=Rules::validateBuild(arBuild());
ac(array_column($base['deck'],'rank')===range(13,1),'Rank order preserved from top to bottom');
ac(array_unique(array_column($base['deck'],'suit'))===['♠'],'All thirteen spades accepted');
foreach(['♠','♥','♣','♦'] as $suit)ac(Rules::validateBuild(arBuild($suit))['deck'][0]['suit']===$suit,'Each suit validates');
foreach(['spade','',null,1,[],false,'🃏'] as $invalid){$b=arBuild();$b['deck'][0]['suit']=$invalid;arReject(function()use($b){Rules::validateBuild($b);});}
$b=arBuild();$b['deck'][1]['rank']=13;arReject(function()use($b){Rules::validateBuild($b);});
$legacy=Catalog::presets()[0];$legacy['rulesVersion']='0.7.0-alpha';$normal=Rules::validateBuild($legacy);
ac(!isset($normal['deck'][0]['suit']),'Missing legacy suits remain suitless');
$other=$base;$other['deck'][0]['suit']='♥';ac(Workshop::fingerprint($base)!==Workshop::fingerprint($other),'Suit changes affect trial fingerprints');
$other=$base;$other['deck']=array_reverse($other['deck']);ac(Workshop::fingerprint($base)!==Workshop::fingerprint($other),'Order changes affect trial fingerprints');
$g=Engine::create(arPlayers(2,false),'series');Engine::act($g,'p0',['type'=>'draw','mind'=>2]);
ac(array_column(array_slice($g['players']['p0']['hand'],-2),'rank')===[13,12],'Draw uses customized top order');
ac(array_column(array_slice($g['players']['p0']['hand'],-2),'suit')===['♠','♠'],'Draw retains suits');
// Real skill judgment, persistence, success/failure branches, ownership and obtaining the card.
foreach(['♠'=>true,'♥'=>false] as $suit=>$success){
    $players=arPlayers(2,false);$players[0]['build']=arBuild($suit);
    $effect=Catalog::effect('judge')+['filter'=>'spade','then'=>[Catalog::effect('shield')],'else'=>[],'obtain'=>true,'repeat'=>false];
    $players[0]['build']['character']['skills']=[arSkill($effect)];
    $g=Engine::create($players,'series');Engine::act($g,'p0',['type'=>'draw','mind'=>0]);$hand=count($g['players']['p0']['hand']);$uid=$g['players']['p0']['mind'][0]['uid'];
    Engine::act($g,'p0',['type'=>'skill','index'=>0]);ac($g['pending']['kind']==='judge','Skill allows mind or normal judgment source');
    $g=json_decode(json_encode($g),true);Engine::act($g,'p0',['type'=>'respond','choice'=>'mind']);
    ac($g['players']['p0']['shield']===($success?1:0),'Suit controls judgment branch');
    ac(count($g['players']['p0']['hand'])===$hand+($success?1:0),'Obtaining success obeys definition');
    $zone=$g['players']['p0'][$success?'hand':'spent'];$card=end($zone);
    ac($card['uid']===$uid&&$card['suit']===$suit&&$card['rank']===13&&$card['owner']==='p0','Judgment preserves physical mind card');
    ac(count($g['players']['p0']['mind'])===12&&$g['pending']===null,'Judgment consumes top exactly once');
}
// A black mind card is a legal conversion material and returns to its original owner after use.
$players=arPlayers(2,false);$skill=arSkill(Catalog::effect('draw'),'convert');$skill['effects']=[];$skill['conversion']=['from'=>'black','to'=>'attack_neutral'];$players[0]['build']['character']['skills']=[$skill];
$g=Engine::create($players,'series');Engine::act($g,'p0',['type'=>'draw','mind'=>1]);$card=array_values(array_filter($g['players']['p0']['hand'],function($c){return $c['origin']==='mind';}))[0];
Engine::act($g,'p0',['type'=>'play','card'=>$card['uid'],'conversion'=>0,'target'=>'p1']);Engine::act($g,'p1',['type'=>'respond','choice'=>'damage']);
ac(end($g['players']['p0']['spent'])['suit']==='♠','Converted card retains its custom suit in spent zone');
// Spectator views cannot expose hands, private piles, choices, or living secret roles.
$g=Engine::create(arPlayers(5),'identity');$g['players']['p0']['piles']['secret']=['visibility'=>'private','cards'=>[array_pop($g['players']['p0']['hand'])]];
$view=Engine::spectatorView($g);ac($view['me']===null&&$view['spectator']&&$view['legalActions']===[],'Observer is not a player');
foreach($view['players'] as $p){ac(!isset($p['hand'])&&!isset($p['mind']),'No private card arrays');ac($p['role']===($p['id']===$g['mayor']?'mayor':null),'Only mayor public initially');if($p['id']==='p0')ac($p['piles']['secret']['cards']===[],'Private piles hidden');}
$old=$g;ac(!Engine::advanceArena($g,'paused')&&$old===$g,'Pause leaves serialized state untouched');
$human=Engine::create(arPlayers(2,false),'series');arReject(function()use($human){Engine::spectatorView($human);});arReject(function()use(&$human){Engine::advanceArena($human,'fast');});
// 0.7 rooms do not receive the historical maturity adjustment a second time.
$old=Engine::create(arPlayers(2),'series');$old['rulesVersion']='0.7.0-alpha';$card=array_shift($old['players']['p0']['mind']);$card['type']='evade';$card['readyAt']=5;unset($card['maturityTurns']);$old['players']['p0']['equipment'][]=$card;
Engine::advanceArena($old,'normal');ac($old['rulesVersion']===SkillBlocks::VERSION&&$old['players']['p0']['equipment'][0]['readyAt']===5,'Old live rooms preserve maturity');
RuleConfig::configure(['maxTurns'=>100]);
foreach(['color'=>[2,6],'series'=>[2,6],'identity'=>[4,5,6,7]] as $mode=>$counts)foreach($counts as $n){
    $players=arPlayers($n);
    if($mode==='series')foreach($players as &$p)$p['build']['character']['color']='cool';unset($p);
    if($mode==='color'&&$n===2)foreach($players as &$p)$p['build']['character']['color']='neutral';unset($p);
    $g=Engine::create($players,$mode);$batches=0;
    while($g['status']==='playing'&&$batches<1500){ac(Engine::advanceArena($g,'fast'),'All-bot match always advances');$g=json_decode(json_encode($g),true);$batches++;}
    ac($g['status']==='finished'&&$g['winner']!==null,'All supported modes and sizes finish');
    $uids=[];foreach(['deck','discard','draft'] as $z)foreach($g[$z]??[] as $c)$uids[]=$c['uid'];foreach($g['players'] as $p)foreach(['hand','mind','spent','equipment','delayed'] as $z)foreach($p[$z] as $c)$uids[]=$c['uid'];
    ac(count($uids)===104+13*$n&&count(array_unique($uids))===count($uids),'Finished arena conserves physical cards');
    if($mode==='identity')foreach(Engine::spectatorView($g)['players'] as $p)ac($p['role']!==null,'Finished observer sees all identities');
    echo "$mode $n seats: $batches batches, {$g['winner']}\n";
}
echo "PASS $checks mind deck / arena assertions\n";
