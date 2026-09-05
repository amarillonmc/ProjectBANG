<?php
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Rules;
use Imaginary\Engine;

$checks=0;
function ok($test,$label) { global $checks; $checks++; if(!$test) throw new RuntimeException('FAIL '.$label); }
function rejects(callable $f,$label) { try {$f();} catch(InvalidArgumentException $e) {ok(true,$label);return;} throw new RuntimeException('FAIL should reject: '.$label); }
function game($mode='color',$bots=false) {
    $p=Catalog::presets(); return Engine::create([
        ['id'=>'a','name'=>'甲','bot'=>$bots,'build'=>$p[0]],['id'=>'b','name'=>'乙','bot'=>$bots,'build'=>$p[1]],['id'=>'c','name'=>'丙','bot'=>$bots,'build'=>$p[2]],
    ],$mode);
}
function plain(&$g) {
    foreach($g['players'] as &$p) {$p['character']['skills']=[]; $p['character']['flipColor']=null; $p['hand']=[];$p['equipment']=[];$p['delayed']=[];}
    unset($p); $g['queue']=[];$g['pending']=null;$g['phase']='play';$g['resumePhase']='play';
}
function card($type,$uid='test',$origin='normal',$owner='a',$rank=9) {
    return array_merge(Catalog::cards()[$type],['uid'=>$uid,'type'=>$type,'rank'=>$rank,'origin'=>$origin,'owner'=>$owner]);
}
function give(&$g,$id,$type,$uid='test') {$g['players'][$id]['hand'][]=card($type,$uid);return $uid;}
function play(&$g,$id,$type,$target=null,$extra=[]) {
    $uid='test_'.count($g['players'][$id]['hand']).'_'.$g['turnNumber'].'_'.$type; give($g,$id,$type,$uid);
    Engine::act($g,$id,array_merge(['type'=>'play','card'=>$uid,'target'=>$target??$id],$extra));
}
function response(&$g,$id,$choice,$extra=[]) {Engine::act($g,$id,array_merge(['type'=>'respond','choice'=>$choice],$extra));}
function conservation($g) {
    $uids=[]; foreach(['deck','discard','draft'] as $zone) foreach($g[$zone]??[] as $c) $uids[]=$c['uid'];
    foreach($g['players'] as $p) foreach(['hand','mind','spent','equipment','delayed'] as $zone) foreach($p[$zone] as $c) $uids[]=$c['uid'];
    foreach($g['queue'] as $e) if(($e['effect']??'')==='delayed') $uids[]=$e['card']['uid'];
    if(($g['pending']['event']['effect']??'')==='delayed') $uids[]=$g['pending']['event']['card']['uid'];
    return [count($uids),count(array_unique($uids))];
}

ok(count(Catalog::ordinary())===104,'104 ordinary cards');
$counts=array_count_values(array_column(Catalog::ordinary(),'type')); ok($counts['surprise']===6&&$counts['defense']===20,'original distribution and typo normalization');
foreach(Catalog::presets() as $build) { $b=Rules::validateBuild($build); ok(count($b['deck'])===13,'preset legal'); }
$bad=Catalog::presets()[0]; $bad['deck'][0]['rank']=2; rejects(function()use($bad){Rules::validateBuild($bad);},'duplicate ranks');
$bad=Catalog::presets()[0]; $bad['character']['skills'][0]['effects'][0]['op']='eval'; rejects(function()use($bad){Rules::validateBuild($bad);},'no arbitrary op');
$bad=Catalog::presets()[0]; $bad['character']['skills'][0]['effects'][0]['code']='phpinfo();'; rejects(function()use($bad){Rules::validateBuild($bad);},'unknown fields');
$bad=Catalog::presets()[0]; $bad['deck'][0]['type']='mana'; rejects(function()use($bad){Rules::validateBuild($bad);},'common mind must match rank options');
$g=game(); ok(conservation($g)===[143,143],'initial card conservation');
$v=Engine::view($g,'a'); ok(count($v['me']['hand'])===5&&count($v['me']['mind'])===13,'private view');
foreach($v['players'] as $p) ok(!isset($p['mind'])&&!isset($p['hand'])&&isset($p['spent']),'hidden zones private, spent public');
$before=$g; rejects(function()use(&$g){Engine::act($g,'b',['type'=>'draw','mind'=>0]);},'wrong turn');ok($g===$before,'invalid action atomic');
Engine::act($g,'a',['type'=>'draw','mind'=>2]);ok(count($g['players']['a']['mind'])===11&&count($g['players']['a']['hand'])===7,'ordered mind replacement');

$g=game();plain($g);
rejects(function()use(&$g){play($g,'a','attack_warm','b');},'cannot attack same color');
play($g,'a','attack_cool','b'); response($g,'b','damage');
ok($g['players']['b']['marks']['cool']===1&&$g['players']['b']['maxHp']===6,'colored damage uses marks');
rejects(function()use(&$g){play($g,'a','attack_neutral','b');},'one attack per turn');
$g=game();plain($g);play($g,'a','attack_neutral','b');response($g,'b','damage');ok($g['players']['b']['maxHp']===5,'neutral body loss');
$g=game('series');plain($g);play($g,'a','attack_neutral','b');response($g,'b','damage');ok($g['players']['b']['maxHp']===6&&$g['players']['b']['marks']['neutral']===1,'series all damage same');
$g=game();plain($g);give($g,'b','defense','guard');play($g,'a','attack_neutral','b');response($g,'b','defend',['card'=>'guard']);ok($g['players']['b']['maxHp']===6,'defense cancels');

$g=game();plain($g);play($g,'a','haste');$equip=$g['players']['a']['equipment'][0]['uid'];
rejects(function()use(&$g,$equip){Engine::act($g,'a',['type'=>'equip_use','card'=>$equip]);},'persistent requires next own turn');
$g['players']['a']['turns']++;Engine::act($g,'a',['type'=>'equip_use','card'=>$equip]);ok(count($g['players']['a']['hand'])===3,'haste draws three');
$g=game();plain($g);$g['players']['a']['equipment'][]=array_merge(card('punch','punch'),['readyAt'=>1]);give($g,'b','defense','guard');
Engine::act($g,'a',['type'=>'equip_use','card'=>'punch','target'=>'b']);
rejects(function()use(&$g){response($g,'b','defend',['card'=>'guard']);},'punch rejects ordinary defense');
$g['players']['b']['equipment'][]=array_merge(card('evade','evade'),['readyAt'=>0]);response($g,'b','evade',['card'=>'evade']);ok($g['players']['b']['maxHp']===6&&count($g['players']['b']['hand'])===2,'evade handles punch and draws');

$g=game();plain($g);give($g,'b','alliance','counter');play($g,'a','surprise','b');ok($g['pending']['kind']==='event','event counter window');response($g,'b','alliance',['card'=>'counter']);ok(count($g['players']['a']['hand'])===1,'alliance counter draws source');
$g=game();plain($g);give($g,'a','defense','cost');$m=count($g['players']['b']['mind']);play($g,'a','surprise','b',['mode'=>'mind','costCard'=>'cost']);ok(count($g['players']['b']['mind'])===$m-1,'surprise mind mode');
$g=game();plain($g);play($g,'a','mana',null,['mode'=>'draft']);ok(count($g['draft'])===3&&$g['pending']['player']==='a','mana reveals then ordered draft');
foreach(['a','b','c'] as $id) response($g,$id,'choose',['card'=>$g['draft'][0]['uid']]);ok($g['phase']==='play'&&count($g['draft'])===0,'draft complete');
$g=game();plain($g);play($g,'a','potential');response($g,'b','mind');give($g,'c','defense','gift');response($g,'c','give',['card'=>'gift']);ok(count($g['players']['a']['hand'])===1&&count($g['players']['b']['spent'])===1,'potential choice queue');
$g=game();plain($g);$g['players']['a']['hand'][]=card('energy','blast','mind','a');give($g,'b','evade','guard');
Engine::act($g,'a',['type'=>'play','card'=>'blast']);response($g,'b','defend',['card'=>'guard']);response($g,'c','damage');ok($g['players']['c']['maxHp']===4&&count($g['players']['a']['spent'])===2,'energy costs mind and permits evade hand');

$g=game();plain($g);$g['players']['a']['mind']=[];$g['players']['a']['spent']=[card('mana','spent','mind','a')];
play($g,'a','recover',null,['mode'=>'reset']);response($g,'a','confirm',['cards'=>['spent']]);ok(count($g['players']['a']['mind'])===1,'reset exits broken');
$g=game();plain($g);$g['players']['a']['spent']=[card('mana','spent','mind','a')];play($g,'a','recover');$uid=$g['players']['a']['hand'][0]['uid'];response($g,'a','confirm',['cards'=>[$uid]]);
ok(end($g['players']['a']['mind'])['origin']==='mind'&&end($g['players']['a']['mind'])['owner']==='a','ordinary hand tucked becomes owner mind');
$g=game();plain($g);$g['players']['b']['hand'][]=card('mana','stolen','mind','a');$g['turn']='b';Engine::act($g,'b',['type'=>'play','card'=>'stolen','mode'=>'draw']);
ok(end($g['players']['a']['spent'])['uid']==='stolen','transferred mind returns original owner');

$g=game();plain($g);$g['players']['b']['mind']=[];$g['players']['b']['marks']['cool']=2;$g['turn']='b';play($g,'b','attack_warm','b');ok($g['players']['b']['marks']['cool']===2,'broken cannot heal');
$g=game();plain($g);$g['players']['b']['character']['flipColor']='cool';$g['players']['b']['marks']['cool']=5;play($g,'a','attack_cool','b');response($g,'b','damage');
ok($g['players']['b']['flipped']&&$g['players']['b']['color']==='cool'&&$g['players']['b']['alive']&&$g['players']['b']['marks']['cool']===6,'flip keeps counters and survives');
$g=game();plain($g);$g['players']['b']['maxHp']=1;$g['players']['b']['equipment'][]=array_merge(card('miracle','miracle'),['readyAt'=>0]);play($g,'a','attack_neutral','b');response($g,'b','damage');ok($g['players']['b']['alive']&&$g['players']['b']['maxHp']===2,'miracle explicit body rescue');

$g=game();plain($g);play($g,'a','calamity','b');Engine::act($g,'a',['type'=>'end']);ok($g['pending']['kind']==='judge'&&$g['pending']['player']==='b','delayed before draw');
$g['players']['b']['mind'][0]['rank']=2;response($g,'b','mind');ok($g['pending']['kind']==='calamity','calamity threshold');response($g,'b','skip');ok($g['turn']==='c','skip whole turn');
$g=game();plain($g);play($g,'a','fortune','a');Engine::act($g,'a',['type'=>'end']);$g['turn']='c';$g['phase']='play';Engine::act($g,'c',['type'=>'end']);$g['players']['a']['mind'][0]['rank']=13;response($g,'a','mind');response($g,'a','draw');ok($g['phase']==='draw'&&count($g['players']['a']['hand'])===3,'fortune judge K and draws before phase');

$g=game();plain($g);$g['players']['a']['equipment'][]=array_merge(card('treasure','treasure'),['readyAt'=>1]);play($g,'a','attack_neutral','b');response($g,'b','damage');ok($g['players']['b']['maxHp']===4,'treasure damage bonus');
$g['players']['a']['attacks']=0;play($g,'a','surprise','a',['mode'=>'outside','selection'=>['treasure']]);ok($g['players']['a']['maxHp']===4,'treasure removal backlash');
$g=game();$g['deadline']=time()-1;$before=$g;rejects(function()use(&$g){Engine::act($g,'a',['type'=>'draw','mind'=>0]);},'deadline enforced by act');ok($g===$before,'expired act no mutation');ok(Engine::tick($g),'timeout tick advances');
$g=game();$g['turnNumber']=300;$g['phase']='play';$g['players']['a']['hand']=[];Engine::act($g,'a',['type'=>'end']);ok($g['status']==='finished'&&$g['winner']==='平局','hard turn bound');

// Real bot matches exercise authoritative legal-action candidates, queue liveness,
// all physical zones and defeat paths without constructing impossible fixtures.
for($match=0;$match<8;$match++) {
    $g=game($match%2?'series':'color',true); $steps=0;
    while(!Engine::finished($g)&&$steps<4000) {
        ok(Engine::botStep($g),'bot has legal action'); $steps++;
        $counts=conservation($g); ok($counts===[143,143],'physical cards conserved on bot step '.$steps);
        foreach($g['players'] as $p) ok($p['maxHp']>=0&&$p['shield']>=0&&$p['shield']<=6,'bounded state');
    }
    ok(Engine::finished($g),'bot game terminates'); echo 'match '.$match.': '.$steps.' actions, '.$g['turnNumber'].' turns, '.$g['winner'].PHP_EOL;
}
echo 'PASS '.$checks.' assertions'.PHP_EOL;
