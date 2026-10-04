<?php
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
require_once __DIR__.'/../src/Tutorial.php';
use Imaginary\{Catalog,Rules,Engine,Tutorial,CreationTest,SkillBlocks};
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$checks=0;
function onboardingCheck($ok,string $message): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function onboardingReject(callable $fn): void {try{$fn();}catch(InvalidArgumentException $e){onboardingCheck(true,'rejected');return;}throw new RuntimeException('Expected rejection');}
function onboardingCard(array &$g,string $type): array {foreach($g['deck'] as $i=>$c)if($c['type']===$type){array_splice($g['deck'],$i,1);return $c;}throw new RuntimeException('No fixture card');}

onboardingCheck(count(CreationTest::questions())===16,'Sixteen questions');
onboardingReject(function(){CreationTest::compose([0]);});
onboardingReject(function(){CreationTest::compose(array_fill(0,16,'0'));});
$profiles=[];$fingerprints=[];$examples=[];mt_srand(61004);
for($n=0;$n<160;$n++){
    $answers=[];for($i=0;$i<16;$i++)$answers[]=mt_rand(0,3);
    $result=CreationTest::compose($answers);$b=$result['build'];$profiles[$result['profile']['primary']]=true;
    $examples[$result['profile']['primary']]=$b;
    $fingerprints[json_encode([$b['character']['skills'],$b['deck']])]=true;
    onboardingCheck(count($b['deck'])===13&&count(array_unique(array_column($b['deck'],'rank')))===13,'Complete ordered mind deck');
    onboardingCheck(count($b['character']['skills'])>=2&&count($b['character']['skills'])<=3,'Defining skills and optional synergy preserved');
    onboardingCheck($result['budget']['character']<=18&&$result['budget']['custom']<=24,'Server budget accepted');
    onboardingCheck(Rules::validateBuild($b)===$b,'Canonical composition');
    onboardingCheck($result===CreationTest::compose($answers),'Answers reproduce exactly');
}
onboardingCheck(count($profiles)===8,'All eight profiles reachable');
onboardingCheck(count($fingerprints)>100,'Answers produce distinct playable configurations');

for($step=0;$step<count(Tutorial::lessons());$step++){
    $state=Tutorial::start('student','学习者',$step);$before=serialize($state);
    onboardingReject(function()use($state){Tutorial::act($state,'student',['type'=>'unknown']);});
    onboardingCheck($before===serialize($state),'Wrong teaching action does not mutate progress');
    $initial=Tutorial::view($state,'student')['game'];
    for($i=0;$i<4&&!$state['done'];$i++){
        $view=Tutorial::view($state,'student');$actions=$view['game']['legalActions'];$chosen=null;
        foreach($actions as $item){$a=$item['action'];
            if(($step===0||($step===6&&$state['stage']===0))&&$a['type']==='draw'&&$a['mind']===1)$chosen=$a;
            if(in_array($step,[1,2,3,8],true)&&$a['type']==='play'&&($a['target']??'')===($step===1?'student':'lesson_partner'))$chosen=$a;
            if($step===4&&(($state['stage']===0&&$a['type']==='play')||($a['choice']??'')==='evade'))$chosen=$a;
            if($step===5&&($a['choice']??'')===($state['stage']===0?'mind':'draw'))$chosen=$a;
            if($step===6&&($state['stage']===1?($a['mode']??'')==='reset':($state['stage']===2&&($a['choice']??'')==='confirm')))$chosen=$a;
            if($step===7&&$a['type']===($state['stage']===0?'end':'discard'))$chosen=$a;
        }
        onboardingCheck($chosen!==null,'Lesson '.$step.' has required legal action at stage '.$state['stage']);
        $state=Tutorial::act(json_decode(json_encode($state),true),'student',$chosen);
    }
    onboardingCheck($state['done'],'Lesson completes '.$step);
    $end=Tutorial::view($state,'student')['game'];
    if($step===1)onboardingCheck($end['players'][0]['hp']===5&&$end['players'][0]['maxHp']===6&&$end['players'][0]['attacksRemaining']===1,'Self healing improves spirit only and uses no attack');
    if($step===2)onboardingCheck($end['players'][1]['hp']===5&&$end['players'][1]['maxHp']===6,'Colored damage');
    if($step===3)onboardingCheck($end['players'][1]['hp']===4&&$end['players'][1]['maxHp']===5,'Colorless damage');
    if($step===4)onboardingCheck(count($end['players'][0]['equipment'])===1&&count($end['me']['hand'])===1,'Evades are independent physical cards');
    if($step===5)onboardingCheck($end['me']['spent'][0]['rank']===13&&count($end['me']['hand'])===3,'Judgment consumes exact known top');
    if($step===6)onboardingCheck(!$end['players'][0]['broken']&&count($end['me']['mind'])===13,'Mind reset restores all original cards');
    if($step===7)onboardingCheck(count($end['me']['hand'])===2&&$end['turn']==='lesson_partner','Ending and discarding advances seat');
    if($step===8)onboardingCheck($end['status']==='finished'&&$end['winner']==='冷色阵营','Lesson reaches actual victory');
}

$g=Tutorial::start('student','学习者')['game'];$g['phase']=$g['resumePhase']='play';
foreach([0,1] as $wait){$c=onboardingCard($g,'punch');$c['maturityTurns']=$wait;$g['players']['student']['hand'][]=$c;Engine::act($g,'student',['type'=>'play','card'=>$c['uid']]);}
$v=Engine::view($g,'student');
onboardingCheck(count($v['players'][0]['equipment'])===2&&$v['players'][0]['equipment'][0]['ready']&&!$v['players'][0]['equipment'][1]['ready'],'Stacked Punch tracks readiness per card');
$unready=$v['players'][0]['equipment'][1]['uid'];
onboardingCheck(strpos(implode(' ',$v['actionHints']['equip_use:'.$unready]['reasons']),'成熟')!==false,'Unavailable action has authoritative reason');
$old=$g;$old['rulesVersion']='0.5.0-alpha';$ready=$old['players']['student']['equipment'][1]['readyAt'];Engine::tick($old);
onboardingCheck($old['rulesVersion']===SkillBlocks::VERSION&&$old['players']['student']['equipment'][1]['readyAt']===$ready,'0.5 migration preserves maturity');
$card=onboardingCard($g,'defense');$g['players']['student']['hand'][]=$card;$snapshot=$g;$v=Engine::view($g,'student');
onboardingCheck($snapshot===$g,'Diagnostics do not mutate game');
onboardingCheck(isset($v['actionHints']['play:'.$card['uid']]),'Defense explains response-only use');

$g=Tutorial::start('student','学习者',3)['game'];
foreach([1,2] as $n){$c=onboardingCard($g,'treasure');$g['players']['student']['hand'][]=$c;Engine::act($g,'student',['type'=>'play','card'=>$c['uid']]);}
$attack=$g['players']['student']['hand'][0]['uid'];
Engine::act($g,'student',['type'=>'play','card'=>$attack,'target'=>'lesson_partner']);
Engine::act($g,'lesson_partner',['type'=>'respond','choice'=>'damage']);
onboardingCheck($g['players']['lesson_partner']['maxHp']===3,'Two mature treasures each add damage');
foreach([4,2] as $body){$c=onboardingCard($g,'surprise');$g['players']['student']['hand'][]=$c;$uid=$g['players']['student']['equipment'][0]['uid'];Engine::act($g,'student',['type'=>'play','card'=>$c['uid'],'target'=>'student','mode'=>'outside','selection'=>[$uid]]);onboardingCheck($g['players']['student']['maxHp']===$body,'Each treasure removal applies its own backlash');}
$g=Tutorial::start('student','学习者',1)['game'];$g['players']['student']['marks']['warm']=9;
foreach([1,2] as $n){$c=onboardingCard($g,'miracle');$c['readyAt']=0;$g['players']['student']['equipment'][]=$c;}
$method=new ReflectionMethod(Engine::class,'dying');$method->setAccessible(true);$method->invokeArgs(null,[&$g,'student']);
onboardingCheck($g['players']['student']['alive']&&$g['players']['student']['marks']['warm']===5&&count($g['players']['student']['equipment'])===0,'Multiple miracles rescue sequentially until spirit positive');
$g=Tutorial::start('student','学习者',1)['game'];
foreach([1,2] as $n){$c=onboardingCard($g,'calamity');$g['players']['student']['hand'][]=$c;$a=['type'=>'play','card'=>$c['uid'],'target'=>'student'];if($n===1)Engine::act($g,'student',$a);else{ $before=$g;onboardingReject(function()use(&$g,$a){Engine::act($g,'student',$a);});onboardingCheck($g===$before,'Duplicate delayed event rejected atomically');}}
$g=Tutorial::start('student','学习者',1)['game'];$series=$g['players']['student']['character']['series'];
foreach([1,2] as $n){$c=onboardingCard($g,'evade');$c['type']='custom';$c['name']='并存的护符';$c['custom']=['name'=>'并存的护符','series'=>$series,'kind'=>'persistent','maturityTurns'=>0,'fallback'=>'evade','effects'=>[Catalog::effect('draw',1)]];$g['players']['student']['hand'][]=$c;Engine::act($g,'student',['type'=>'play','card'=>$c['uid']]);}
onboardingCheck(count($g['players']['student']['equipment'])===2,'Same-name custom persistent cards coexist');
$uid=$g['players']['student']['equipment'][0]['uid'];Engine::act($g,'student',['type'=>'equip_use','card'=>$uid,'target'=>'student']);
onboardingCheck(count($g['players']['student']['equipment'])===1&&count($g['players']['student']['hand'])===2,'Custom persistent use removes one copy and resolves its effect');

$games=0;$totalSteps=0;$presets=Catalog::presets();
$simulationLimit=(\Imaginary\RuleConfig::get('maxTurns')+1)*(\Imaginary\RuleConfig::get('maxActionsPerTurn')+1)*8;
foreach($examples as $profile=>$build){
    $opponent=$presets[$build['character']['color']==='cool'?1:0];
    $g=Engine::create([['id'=>'created','name'=>$profile,'bot'=>true,'build'=>$build],['id'=>'opponent','name'=>'对手','bot'=>true,'build'=>$opponent]],'color');
    for($step=0;$step<$simulationLimit&&$g['status']==='playing';$step++){
        onboardingCheck(Engine::botStep($g),'Generated '.$profile.' can advance');
        foreach($g['players'] as $player)onboardingCheck($player['maxHp']>=0,'Generated build body remains nonnegative');
    }
    if($g['status']!=='finished')file_put_contents(__DIR__.'/../var/creation-failure.json',json_encode($g,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    onboardingCheck($g['status']==='finished','Generated '.$profile.' reaches real game end');$games++;$totalSteps+=$step;
}
echo "Onboarding: $checks checks passed; $games generated-profile games completed, $totalSteps actions.\n";
