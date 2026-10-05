<?php
/** Runs in the isolated real HTTP / SQLite harness. */
function arenaHttpTests(Imaginary\Store $store,array $preset,array $opponent): void
{
    $host=request('guest',['name'=>'斗蛐蛐观战者']);$outsider=request('guest',['name'=>'无关访客']);$token=$host['token'];
    $build=$preset;unset($build['id']);$build['name']='全黑桃大点在前';$build['character']['skills']=[];$build['character']['hp']=2;$build['character']['flipColor']=null;
    $build['deck']=array_reverse($build['deck']);foreach($build['deck'] as &$c)$c['suit']='♠';unset($c);
    $saved=request('save_build',['build'=>$build,'expectedVersion'=>null],$token);$version=$saved['version']['id'];$id=$saved['build']['id'];
    check(array_column($saved['build']['deck'],'rank')===range(13,1),'HTTP save retains order');
    check(array_unique(array_column($saved['build']['deck'],'suit'))===['♠'],'HTTP save retains all spades');
    $me=request('me',[],$token);check($me['builds'][0]['deck']===$saved['build']['deck'],'Reconnect restores deck settings');
    request('set_visibility',['id'=>$version,'visibility'=>'link'],$token);$shared=request('shared_work',['id'=>$version]);
    check($shared['version']['build']['deck']===$saved['build']['deck'],'Shared export preserves suit/order');
    $bad=$build;$bad['deck'][0]['suit']='invalid';request('validate_build',['build'=>$bad],$token,400);
    $changed=$saved['build'];$changed['deck'][0]['suit']='♥';$new=request('save_build',['build'=>$changed,'expectedVersion'=>$version],$token);
    check($new['version']['id']!==$version,'Changing suit makes a new immutable version');
    check(request('shared_work',['id'=>$version])['version']['build']['deck'][0]['suit']==='♠','Original version stays unchanged');
    $other=$build;$other['name']='另一系列';$other['character']['series']='斗蛐蛐对手';$other['character']['color']=$build['character']['color']==='cool'?'warm':'cool';
    $other=request('save_build',['build'=>$other,'expectedVersion'=>null],$token);
    $a=['versionId'=>$version];$b=['buildId'=>$other['build']['id']];
    $before=(int)$store->one('SELECT COUNT(*) AS n FROM '.$store->table('rooms'))['n'];
    foreach([
        ['arena'=>'true','bots'=>[$a,$b]],['arena'=>true],['arena'=>true,'bots'=>[$a]],
        ['arena'=>true,'bots'=>[$a,$a]],['arena'=>true,'bots'=>[[],$a]],
        ['arena'=>true,'mode'=>'identity','bots'=>[$a,$b,$a]],
        ['arena'=>true,'mode'=>'color','bots'=>array_fill(0,7,$a)],
        ['arena'=>true,'mode'=>'identity','bots'=>array_fill(0,8,$a)],
    ] as $input)request('create_room',$input,$token,400);
    check((int)$store->one('SELECT COUNT(*) AS n FROM '.$store->table('rooms'))['n']===$before,'Failed arena setup is atomic');
    foreach(['color','series','identity'] as $mode){
        $input=['arena'=>true,'name'=>'观战 '.$mode,'mode'=>$mode,'bots'=>$mode==='identity'?[$a,$b,$a,$b,$a]:[$a,$b],'requestId'=>'arena-'.$mode.'-create'];
        $r=request('create_room',$input,$token);$code=$r['code'];
        check($r['arena']&&$r['status']==='playing'&&count($r['players'])===count($input['bots']),'Arena starts with exactly the bot seats');
        foreach($r['players'] as $p)check($p['bot']&&$p['id']!==$host['user']['id'],'Host never occupies a bot seat');
        check($r['game']['spectator']&&$r['game']['me']===null&&$r['game']['legalActions']===[],'Observer has no private hand or actions');
        check(request('create_room',$input,$token)['code']===$code,'Duplicate arena create is idempotent');
        request('room',['code'=>$code],$outsider['token'],403);request('join_room',['code'=>$code],$outsider['token'],403);
        request('act',['code'=>$code],$token,403);request('arena_control',['code'=>$code,'speed'=>'fast','revision'=>$r['revision']],$outsider['token'],403);
        request('arena_control',['code'=>$code,'speed'=>'fast','revision'=>-1],$token,409);
        request('arena_control',['code'=>$code,'speed'=>'unlimited','revision'=>$r['revision']],$token,400);
        $r=request('arena_control',['code'=>$code,'speed'=>'paused','revision'=>$r['revision']],$token);
        $paused=request('room',['code'=>$code],$token);check($paused['revision']===$r['revision']&&$paused['arenaSpeed']==='paused','Polling paused room does not advance');
        $reconnect=request('join_room',['code'=>$code],$token);check($reconnect['game']['me']===null&&$reconnect['arenaSpeed']==='paused','Host reconnects as observer with pause preserved');
        $export=request('export',['code'=>$code],$token);check($export['replay']===null,'Live observer export hides authoritative state');
        if($mode==='identity')foreach($r['game']['players'] as $p)check($p['role']===($p['id']===$r['game']['identity']['mayor']?'mayor':null),'Observer cannot see concealed roles');
        $r=request('arena_control',['code'=>$code,'speed'=>'fast','revision'=>$r['revision']],$token);
        for($i=0;$i<100&&$r['status']==='playing';$i++)$r=request('room',['code'=>$code],$token);
        check($r['status']==='finished','Automatic HTTP arena reaches final result in '.$mode);
        $export=request('export',['code'=>$code],$token);check($export['replay']['status']==='finished','Finished observer exports full replay');
        $stored=$store->one('SELECT data FROM '.$store->table('rooms').' WHERE code = ?',[$code]);$stored=json_decode($stored['data'],true);
        check($stored['players'][0]['build']['deck']===$saved['build']['deck'],'Bot uses requested immutable version, not latest revision');
        check(!$store->one('SELECT id FROM '.$store->table('trials').' WHERE room_code = ?',[$code]),'AI-only matches never certify author play');
        request('arena_control',['code'=>$code,'revision'=>$r['revision'],'speed'=>'fast'],$token,409);
    }
    $history=request('me',[],$token);check(count(array_filter($history['rooms'],function($r){return $r['arena'];}))===3,'All arenas persist in host history');
    foreach(['color'=>6,'series'=>6,'identity'=>7] as $mode=>$n){$bots=array_fill(0,$n,$a);$bots[1]=$b;$r=request('create_room',['arena'=>true,'mode'=>$mode,'bots'=>$bots],$token);check(count($r['players'])===$n,'Arena supports mode maximum '.$mode);}
    // Ordinary participant rooms still include the host and reject spectator controls.
    $r=request('create_room',array_merge(['bots'=>[$b]],$a),$token);check(!$r['arena']&&!$r['players'][0]['bot'],'Normal room keeps human host');
    request('arena_control',['code'=>$r['code'],'speed'=>'fast','revision'=>$r['revision']],$token,409);request('leave_room',['code'=>$r['code']],$token);
}
