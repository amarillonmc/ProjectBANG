<?php
/** Real HTTP identity isolation, capacity, reconnect, rescue and workshop evidence. */
function identityHttpTests(Imaginary\Store $store,array $preset): void
{
    $seats=[];$versions=[];
    for($i=0;$i<5;$i++){
        $guest=request('guest',['name'=>'身份测试'.$i]);$seats[$guest['user']['id']]=$guest;
        $build=$preset;unset($build['id']);$build['name']='身份构筑'.$i;
        $build['character']['hp']=4;$build['character']['flipColor']='warm';
        $build['character']['skills']=[['name'=>'离场测试','trigger'=>'active','effects'=>[['op'=>'lose_max_hp','target'=>'self','amount'=>30]],'limit'=>1]];
        $saved=request('save_build',['build'=>$build],$guest['token']);$versions[$guest['user']['id']]=$saved['version']['id'];
    }
    $ids=array_keys($seats);$host=$seats[$ids[0]];$outsider=request('guest',['name'=>'身份旁观者']);
    $room=request('create_room',['mode'=>'identity','versionId'=>$versions[$ids[0]]],$host['token']);
    check($room['modeRules']===['minPlayers'=>4,'maxPlayers'=>7],'Identity room exposes mode-specific capacity');
    request('start',['code'=>$room['code']],$host['token'],400);
    foreach(array_slice($ids,1) as $id)$room=request('join_room',['code'=>$room['code'],'versionId'=>$versions[$id]],$seats[$id]['token']);
    $room=request('start',['code'=>$room['code']],$host['token']);
    $row=$store->one('SELECT data FROM '.$store->table('rooms').' WHERE code = ?',[$room['code']]);$stored=json_decode($row['data'],true);$mayor=$stored['game']['mayor'];
    check($room['game']['turn']===$mayor,'Server-selected mayor begins the real room');
    foreach($seats as $id=>$seat){
        $view=request('room',['code'=>$room['code']],$seat['token']);
        check($view['game']['identity']['role']===$stored['game']['players'][$id]['role'],'Authenticated role is correct');
        foreach($view['game']['players'] as $p)check($p['role']===($p['id']===$id||$p['id']===$mayor?$stored['game']['players'][$p['id']]['role']:null),'HTTP hides other living identities');
        foreach($view['players'] as $p)check(!isset($p['role']),'Lobby player list does not leak identities');
        $reconnect=request('join_room',['code'=>$room['code']],$seat['token']);check($reconnect['game']['identity']['role']===$view['game']['identity']['role'],'Rejoining retains assigned identity');
        $export=request('export',['code'=>$room['code']],$seat['token']);check($export['replay']===null,'Unfinished export excludes authoritative hidden state');
        foreach($export['events'] as $event)check(!$event['data'],'Live export does not expose private command payloads');
    }
    request('room',['code'=>$room['code']],$outsider['token'],403);
    // Mayor legally loses their own max HP; each authenticated seat decides on rescue.
    $actor=$seats[$mayor];$room=request('act',['code'=>$room['code'],'revision'=>$room['revision'],'requestId'=>'identity-draw-001','action'=>['type'=>'draw','mind'=>0]],$actor['token']);
    $room=request('act',['code'=>$room['code'],'revision'=>$room['revision'],'requestId'=>'identity-skill-001','action'=>['type'=>'skill','index'=>0]],$actor['token']);
    check($room['game']['pending']['kind']==='identity_rescue','HTTP exposes a manual dying window');
    $reconnect=request('room',['code'=>$room['code']],$actor['token']);check($reconnect['game']['pending']===$room['game']['pending'],'Dying window survives database reconnect');
    $step=0;
    while($room['status']==='playing'&&$step<8){$pending=$room['game']['pending'];check($pending['kind']==='identity_rescue','No unrelated actions during rescue');$room=request('act',['code'=>$room['code'],'revision'=>$room['revision'],'requestId'=>'identity-pass-'.(++$step),'action'=>['type'=>'respond','choice'=>'pass']],$seats[$pending['player']]['token']);}
    check($room['status']==='finished'&&$room['game']['identity']['winningTeam']==='wolf','Mayor death settles wolf win through HTTP');
    foreach($room['game']['players'] as $p)check($p['role']!==null,'Finished snapshot reveals every role');
    foreach($versions as $id=>$version){$trial=$store->one('SELECT data FROM '.$store->table('trials').' WHERE version_id = ?',[$version]);check($trial!==null,'Identity completion certifies an author build');}
    $export=request('export',['code'=>$room['code']],$host['token']);check($export['replay']['mode']==='identity'&&isset($export['replay']['players'][$mayor]['role']),'Completed identity replay contains assigned roles');
    $room=request('create_room',['mode'=>'identity','bots'=>array_fill(0,6,['presetId'=>$preset['id']])],$host['token']);
    check(count($room['players'])===7,'Identity accepts six bots plus host');
    request('add_bot',['code'=>$room['code']],$host['token'],400);request('join_room',['code'=>$room['code']],$outsider['token'],400);
    request('leave_room',['code'=>$room['code']],$host['token']);
    request('create_room',['mode'=>'color','bots'=>array_fill(0,6,['presetId'=>$preset['id']])],$host['token'],400);
    request('create_room',['mode'=>'identity','bots'=>array_fill(0,7,['presetId'=>$preset['id']])],$host['token'],400);
}
