<?php
require_once __DIR__.'/costs.php';
use Imaginary\{Rules,Engine,RuleConfig};
RuleConfig::configure(['characterBudget'=>1000]);
$learned=fs('active',[fe('draw',2)],1,0,['limitScope'=>'game']);$learned['name']='限定补牌';
$gain=fe('gain_skill')+['key'=>'学习','duration'=>'permanent','skill'=>$learned];
$lose=fe('lose_skill')+['key'=>'学习'];
$g=fg([fs('active',[$gain]),fs('active',[$lose])]);
checkFeedback(Rules::validateBuild(fb($g['players']['a']['character']['skills']))['character']['skills'][0]['effects'][0]['skill']['name']==='限定补牌','nested skill definition validates and roundtrips');
fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback(count($g['players']['a']['character']['skills'])===3,'grant appends an actual usable skill');
fa($g,'a',['type'=>'skill','index'=>2]);checkFeedback(count($g['players']['a']['hand'])===2,'acquired limited skill executes');
fa($g,'a',['type'=>'skill','index'=>1]);checkFeedback(Engine::view($g,'a')['me']['skills'][2]['disabled'],'lost skill shows disabled and cannot execute');
$g=json_decode(json_encode($g),true);fa($g,'a',['type'=>'skill','index'=>0]);$before=$g;
rejectFeedback(function()use(&$g){fa($g,'a',['type'=>'skill','index'=>2]);},'regaining cannot reset a game-limited skill');
checkFeedback($before===$g&&count($g['players']['a']['character']['skills'])===3,'stable identity preserves counts and does not duplicate slots');

$range=fs('passive',[fe('passive_range',2)],0,0,['key'=>'范围']);
$g=fg([fs('active',[fe('seal_skill')+['key'=>'范围','duration'=>'turn']],1),$range]);
checkFeedback(Engine::view($g,'a')['players'][0]['range']===3,'initial continuous ability active');
fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback(Engine::view($g,'a')['players'][0]['range']===1,'sealing immediately removes continuous value');
fa($g,'a',['type'=>'end']);checkFeedback(Engine::view($g,'a')['players'][0]['range']===3,'turn seal expires after end-of-turn resolution');

$grant=fe('gain_skill',1,'target')+['key'=>'借用范围','duration'=>'source_turn','skill'=>$range];
$g=fg([fs('active',[$grant],1)]);fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
fa($g,'a',['type'=>'end']);checkFeedback(Engine::view($g,'a')['players'][1]['range']===3,'borrowed skill persists through recipient turn');
$g['phase']=$g['resumePhase']='play';fa($g,'b',['type'=>'end']);$g['phase']=$g['resumePhase']='play';fa($g,'c',['type'=>'end']);
checkFeedback($g['turn']==='a'&&Engine::view($g,'b')['players'][1]['range']===1,'source-turn duration expires at source next own turn');

$g=fg([fs('active',[fe('copy_skill',1,'target')+['duration'=>'permanent']])]);
$g['players']['b']['character']['skills']=[$learned];$g['players']['b']['usedSkills'][0]=['turn'=>-1,'count'=>1];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback($g['pending']['kind']==='mechanic_copy_skill'&&Engine::view($g,'a')['pending']['options'][0]['choice']==='0','copy offers visible active skill definitions');
$g=json_decode(json_encode($g),true);completeMechanic($g,'a','0');fa($g,'a',['type'=>'skill','index'=>1]);
checkFeedback(count($g['players']['a']['hand'])===2&&$g['players']['b']['usedSkills'][0]['count']===1,'copy has an independent counter from original owner');
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);completeMechanic($g,'a','0');
rejectFeedback(function()use(&$g){fa($g,'a',['type'=>'skill','index'=>1]);},'copying same identity again cannot reset limited use');
checkFeedback(countMechanicCards($g)===[143,143],'skill copy preserves physical cards');

$permanent=fe('gain_skill')+['key'=>'多来源','duration'=>'permanent','skill'=>$range];$temporary=$permanent;$temporary['duration']='turn';
$g=fg([fs('active',[$permanent],1),fs('active',[$temporary],1)]);fa($g,'a',['type'=>'skill','index'=>0]);fa($g,'a',['type'=>'skill','index'=>1]);fa($g,'a',['type'=>'end']);
checkFeedback(Engine::view($g,'a')['players'][0]['range']===3&&!Engine::view($g,'a')['me']['skills'][2]['disabled'],'temporary grant expiry preserves permanent grant of same identity');
$g=fg([fs('active',[$temporary],1),$range]);$g['players']['a']['character']['skills'][0]['effects'][0]['key']='范围';fa($g,'a',['type'=>'skill','index'=>0]);fa($g,'a',['type'=>'end']);
checkFeedback(Engine::view($g,'a')['players'][0]['range']===3&&count($g['players']['a']['character']['skills'])===2,'temporary regrant preserves native ability');
$g=fg([fs('active',[$permanent]),fs('active',[$temporary])]);fa($g,'a',['type'=>'skill','index'=>0]);$g['players']['a']['character']['skills'][1]['effects'][0]['skill']['effects'][0]['amount']=50;fa($g,'a',['type'=>'skill','index'=>1]);
checkFeedback(Engine::view($g,'a')['players'][0]['range']===3,'different definition cannot overwrite an existing stable skill identity');

$bad=$gain;$bad['skill']['effects'][0]['op']='execute';rejectFeedback(function()use($bad){Rules::validateBuild(fb([fs('active',[$bad])]));},'embedded arbitrary operation rejected');
$deep=fe('draw');for($i=0;$i<15;$i++)$deep=fe('gain_skill')+['key'=>'嵌套','duration'=>'permanent','skill'=>fs('active',[$deep],1)];
rejectFeedback(function()use($deep){Rules::validateBuild(fb([fs('active',[$deep],1)]));},'nested skill definitions obey same depth guard');
checkFeedback(strpos(Rules::describeSkill(fs('active',[$gain])),'限定补牌')!==false&&Rules::effectCost($gain)>2,'embedded skills have readable names and nonzero skill budget');
RuleConfig::configure(null);echo 'PASS '.$checks.' cumulative dynamic skill assertions'.PHP_EOL;
