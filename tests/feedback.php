<?php
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\{Catalog,Rules,Engine,RuleConfig,SkillBlocks};
$checks=0;
function checkFeedback($ok,string $why): void { global $checks; $checks++; if(!$ok) throw new RuntimeException($why); }
function rejectFeedback(callable $fn,string $why): void { try{$fn();}catch(InvalidArgumentException $e){checkFeedback(true,$why);return;}throw new RuntimeException('Not rejected: '.$why); }
function fe(string $op,$n=1,string $target='self'): array { return ['op'=>$op,'amount'=>$n,'target'=>$target,'color'=>'neutral']; }
function fs(string $trigger,array $effects,int $limit=0,$hand=0,array $extra=[]): array { return array_merge(['name'=>'机制测试','trigger'=>$trigger,'condition'=>'always','cost'=>['hand'=>$hand,'mind'=>0],'effects'=>$effects,'limit'=>$limit],$extra); }
function fb(array $skills=[]): array { $b=Catalog::presets()[0]; $b['character']['skills']=$skills; $b['character']['hp']=4; $b['character']['flipColor']=null; foreach($b['deck'] as &$d) if($d['type']==='custom') $d=['type'=>'evade','rank'=>5]; unset($d); return $b; }
function fg(array $skills=[],string $mode='series'): array {
    $players=[]; foreach(['a','b','c'] as $id) { $b=fb($id==='a'?$skills:[]); $b['character']['series']=$id; $b['character']['color']=$id==='a'?'cool':'warm'; $players[]=['id'=>$id,'name'=>$id,'build'=>$b]; }
    $g=Engine::create($players,$mode); foreach($g['players'] as &$p){foreach($p['hand'] as $c)$g['deck'][]=$c;$p['hand']=[];}unset($p);$g['phase']=$g['resumePhase']='play';return $g;
}
function fc(array &$g,string $id,string $type): string { foreach($g['deck'] as $i=>$c)if($c['type']===$type){array_splice($g['deck'],$i,1);$g['players'][$id]['hand'][]=$c;return $c['uid'];}throw new RuntimeException('fixture card unavailable '.$type); }
function fa(array &$g,string $id,array $a): void { Engine::act($g,$id,$a); }
function callEngine(string $name,array &$g,...$args) { $r=new ReflectionMethod(Engine::class,$name);$r->setAccessible(true);$params=[&$g];foreach($args as $a)$params[]=$a;return $r->invokeArgs(null,$params); }

foreach(['miracle','evade','haste','automaton','treasure','calamity','fortune'] as $type)checkFeedback(Catalog::cards()[$type]['maturityTurns']===0,$type.' defaults to maturity 0');
checkFeedback(Catalog::cards()['punch']['maturityTurns']===1,'punch keeps maturity 1');
foreach(['series','color'] as $mode) {
    $g=fg([],$mode); $miracle=fc($g,'a','miracle'); fa($g,'a',['type'=>'play','card'=>$miracle]);
    if($mode==='series')$g['players']['a']['marks']['neutral']=4;else $g['players']['a']['maxHp']=0;
    callEngine('dying',$g,'a');
    checkFeedback($g['players']['a']['alive']&&callEngine('hp',$g,'a')===2,'maturity 0 miracle self-rescues before next own turn in '.$mode);
    checkFeedback(!$g['players']['a']['equipment'],'miracle consumed exactly once');
}
foreach([0,1,2] as $wait) {
    $g=fg(); $uid=fc($g,'a','haste');$g['players']['a']['hand'][0]['maturityTurns']=$wait;fa($g,'a',['type'=>'play','card'=>$uid]);
    checkFeedback(Engine::view($g,'a')['players'][0]['equipment'][0]['ready']===($wait===0),'configured initial readiness '.$wait);
    if($wait) { $before=$g;rejectFeedback(function()use(&$g,$uid){fa($g,'a',['type'=>'equip_use','card'=>$uid]);},'immature rejected');checkFeedback($g===$before,'immature action rolls back'); }
    $g['players']['a']['turns']+=$wait;fa($g,'a',['type'=>'equip_use','card'=>$uid]);checkFeedback(count($g['players']['a']['hand'])===3,'matures on owner turn '.$wait);
}
$g=fg();$uid=fc($g,'a','calamity');$g['players']['a']['hand'][0]['maturityTurns']=2;fa($g,'a',['type'=>'play','card'=>$uid,'target'=>'a']);
checkFeedback(Engine::view($g,'a')['players'][0]['delayed'][0]['ready']===false,'delayed view reports initial immaturity');
$g['players']['a']['turns']++;checkFeedback(Engine::view($g,'a')['players'][0]['delayed'][0]['ready']===false,'maturity two still waits after one own turn');
$g['players']['a']['turns']++;checkFeedback(Engine::view($g,'a')['players'][0]['delayed'][0]['ready']===true,'delayed view matures at second own turn');
foreach(['series','color'] as $mode)foreach(['body','spirit'] as $zero){
    $g=fg([],$mode);$uid=fc($g,'a','miracle');fa($g,'a',['type'=>'play','card'=>$uid]);$g['players']['a']['spent']=array_merge($g['players']['a']['spent'],$g['players']['a']['mind']);$g['players']['a']['mind']=[];
    if($zero==='body')$g['players']['a']['maxHp']=0;else $g['players']['a']['marks'][$mode==='series'?'neutral':'warm']=4;
    callEngine('dying',$g,'a');checkFeedback($g['players']['a']['alive']&&callEngine('hp',$g,'a')===2,'broken miracle rescues '.$zero.' in '.$mode);
    $old=$g['players']['a'];callEngine('heal',$g,'a',2);checkFeedback($g['players']['a']===$old,'ordinary healing still blocked while broken');
}
$g=fg();$uid=fc($g,'a','miracle');fa($g,'a',['type'=>'play','card'=>$uid]);$g['rulesVersion']='0.4.0-alpha';unset($g['players']['a']['equipment'][0]['maturityTurns']);$g['players']['a']['equipment'][0]['readyAt']=2;
Engine::tick($g);checkFeedback($g['players']['a']['equipment'][0]['readyAt']===1,'legacy readiness migrates once');Engine::tick($g);checkFeedback($g['players']['a']['equipment'][0]['readyAt']===1,'migration idempotent');

$s=fs('active',[fe('draw')]);$g=fg([$s]);for($i=0;$i<98;$i++)fa($g,'a',['type'=>'skill','index'=>0]);
checkFeedback($g['players']['a']['usedSkills'][0]['count']===98,'unlimited executes 98 times, not 1 or 2');
$before=$g;rejectFeedback(function()use(&$g){fa($g,'a',['type'=>'skill','index'=>0]);},'unlimited insurance ceiling');checkFeedback($g===$before,'ceiling rejection atomic');
$g['turnNumber']++;fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback($g['players']['a']['usedSkills'][0]['count']===1,'global turn resets count');
foreach(['owner_turn','game'] as $scope){$g=fg([fs('active',[fe('draw')],1,0,['limitScope'=>$scope])]);fa($g,'a',['type'=>'skill','index'=>0]);$g['turnNumber']++;rejectFeedback(function()use(&$g){fa($g,'a',['type'=>'skill','index'=>0]);},$scope.' not reset by global turn');if($scope==='owner_turn'){$g['players']['a']['turns']++;fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback(true,'owner cycle resets');}}
$g=fg([fs('active',[fe('draw','paid_hand')],1,'chosen')]);foreach(['defense','haste','miracle','punch','treasure'] as $t)fc($g,'a',$t);$uids=array_column($g['players']['a']['hand'],'uid');fa($g,'a',['type'=>'skill','index'=>0,'costCards'=>$uids]);checkFeedback(count($g['players']['a']['hand'])===5&&!array_intersect($uids,array_column($g['players']['a']['hand'],'uid')),'chosen fee supports all five cards and draws actual paid count');
$g=fg([fs('active',[fe('draw','paid_hand')],1,'all')]);checkFeedback(!array_filter(Engine::legalActions($g,'a'),function($x){return $x['action']['type']==='skill';}),'all-hand fee requires a card');

$b=fb([fs('active',[fe('range')],1),fs('active',[fe('range')],1),fs('active',[fe('range')],1),fs('active',[fe('range')],1)]);checkFeedback(count(Rules::validateBuild($b)['character']['skills'])===4,'four skills accepted');
$b['character']['skills']=[fs('active',[fe('range'),fe('range'),fe('range'),fe('range')],1)];checkFeedback(count(Rules::validateBuild($b)['character']['skills'][0]['effects'])===4,'four effects accepted');
$b=fb([fs('turn_end',[fe('lose_health')])]);$b['character']['hp']=8;checkFeedback(Rules::budget(Rules::validateBuild($b))['character']<8,'compulsory drawback gives negative budget at 8 hp');
$b['character']['skills'][0]['optional']=true;checkFeedback(Rules::budget($b)['character']>=8,'optional pure drawback cannot mint budget');
$b=fb([fs('turn_end',[fe('lose_health','mark:never')])]);checkFeedback(Rules::budget($b)['character']>=0,'zero-valued dynamic drawback cannot mint budget');
$b=fb();foreach($b['deck'] as &$d)$d=['rank'=>$d['rank'],'type'=>'custom','custom'=>['name'=>'代价','series'=>'a','fallback'=>'defense','effects'=>[fe('lose_health')]]];unset($d);checkFeedback(count(Rules::validateBuild($b)['deck'])===13&&Rules::budget($b)['custom']===0,'13 custom minds accepted without unused negative-card rebate');
RuleConfig::configure(['characterBudget'=>99,'customBudget'=>70,'customCardBudget'=>50,'unlimitedUses'=>7]);$b=fb([fs('active',[fe('draw',10)],1)]);checkFeedback(Rules::budget(Rules::validateBuild($b))['characterMax']===99&&Catalog::all()['limits']['unlimitedUses']===7,'config shared by validator and catalog');$g=fg([fs('active',[fe('range')])]);for($i=0;$i<7;$i++)fa($g,'a',['type'=>'skill','index'=>0]);rejectFeedback(function()use(&$g){fa($g,'a',['type'=>'skill','index'=>0]);},'configured runtime ceiling');RuleConfig::configure(null);

$g=fg([fs('passive',[fe('passive_attacks','all')])]);checkFeedback(Engine::view($g,'a')['players'][0]['attacksRemaining']===98,'continuous attack allowance uses runtime ceiling');
checkFeedback(!$g['players']['a']['usedSkills'],'continuous passives consume no usage');
$g=fg([fs('passive',[fe('passive_range',2,'allies')])]);$g['players']['b']['series']='a';checkFeedback(Engine::view($g,'b')['players'][1]['range']===3,'ally aura is live');$g['players']['a']['alive']=false;checkFeedback(Engine::view($g,'b')['players'][1]['range']===1,'aura vanishes when owner dies');
$g=fg([fs('passive',[fe('passive_no_attack_target')],0,0,['condition'=>'hand_empty'])]);checkFeedback(callEngine('passiveValue',$g,'a','passive_no_attack_target')===1,'empty hand restriction');fc($g,'a','defense');checkFeedback(callEngine('passiveValue',$g,'a','passive_no_attack_target')===0,'restriction recomputed after gaining hand');
rejectFeedback(function(){Rules::validateBuild(fb([fs('active',[fe('passive_range')])]));},'continuous effect cannot smuggle active no-op');
$g=fg([fs('active',[fe('gain_max_hp')],1)]);$g['players']['a']['marks']['neutral']=2;fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback($g['players']['a']['maxHp']===5&&callEngine('hp',$g,'a')===2,'max hp gain does not heal');
$g=fg([fs('active',[fe('turn_over',1,'target')],1)]);fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);fa($g,'a',['type'=>'end']);checkFeedback($g['turn']==='c'&&empty($g['players']['b']['faceDown']),'turned-over player skips exactly one turn');
$g=fg([fs('active',[fe('extra_turn')],1)]);fa($g,'a',['type'=>'skill','index'=>0]);fa($g,'a',['type'=>'end']);checkFeedback($g['turn']==='a','extra turn inserted');$g['phase']=$g['resumePhase']='play';fa($g,'a',['type'=>'end']);checkFeedback($g['turn']==='b','normal order resumes after extra turn');
$g=fg([fs('active',[fe('skip_draw',1,'target'),fe('skip_play',1,'target')],1)]);fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);fa($g,'a',['type'=>'end']);checkFeedback($g['turn']==='c','skipping draw and play reaches next player');

checkFeedback(strpos(Rules::describeSkill(fs('active',[fe('draw',2)],1,1)),'弃置1张手牌')!==false,'natural text omits zero costs');
checkFeedback(strpos(Rules::describeSkill(fs('active',[fe('draw')]),true),'不限次数')!==false,'structural text reports unlimited');
echo 'PASS '.$checks.' feedback assertions'.PHP_EOL;
