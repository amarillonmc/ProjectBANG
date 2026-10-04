<?php
/** Included by api.php's isolated real-HTTP harness; no production data is used. */
function workshopHttpTests(Imaginary\Store $store, array $preset, array $opponent): void
{
    $author = request('guest', ['name'=>'作品作者']); $stranger = request('guest', ['name'=>'作品读者']);
    $token = $author['token']; $other = $stranger['token'];
    $build = $preset; unset($build['id']); $build['name']='第一份作品';
    $build['character']['skills'] = []; $build['character']['hp']=4; $build['character']['flipColor']=null;
    $saved = request('save_build', ['build'=>$build,'expectedVersion'=>null], $token);
    $id=$saved['build']['id']; $v1=$saved['version']['id'];
    check($saved['version']['number']===1 && !$saved['version']['trial'], 'New work starts private and untested');
    request('shared_work',['id'=>$v1],'',404);
    request('work_history',['id'=>$id],$other,404);
    request('save_build',['build'=>$saved['build']],$other,403);
    request('set_visibility',['id'=>$v1,'visibility'=>'published','trial'=>true],$token,400);
    request('set_visibility',['id'=>$v1,'visibility'=>'link'],$token);
    $public = request('shared_work',['id'=>$v1],'',200,'GET');
    check($public['version']['build']['name']==='第一份作品', 'Anonymous snapshot sharing works');
    request('set_visibility',['id'=>$v1,'visibility'=>'private'],$other,403);
    $again=request('save_build',['build'=>$saved['build'],'expectedVersion'=>$v1],$token);
    check($again['version']['id']===$v1, 'Identical retry does not multiply versions');
    $build=$saved['build'];$build['name']='第二版作品';
    $next=request('save_build',['build'=>$build,'expectedVersion'=>$v1],$token);$v2=$next['version']['id'];
    request('save_build',['build'=>$saved['build'],'expectedVersion'=>$v1],$token,409);
    check(request('shared_work',['id'=>$v1])['version']['build']['name']==='第一份作品', 'Later editing does not alter a shared version');
    $collection=request('save_collection',['name'=>'试验系列','description'=>'两版之间的快照','buildIds'=>[$id]],$token)['collection'];
    request('save_collection',['id'=>$collection['id'],'name'=>'盗改','buildIds'=>[]],$other,403);
    request('save_collection',['name'=>'错误归属','buildIds'=>[$id]],$other,400);
    $share=request('share_collection',['id'=>$collection['id']],$token)['id'];
    $build['name']='第三版作品';$third=request('save_build',['build'=>$build,'expectedVersion'=>$v2],$token);
    $shared=request('shared_collection',['id'=>$share],'',200,'GET');
    check(count($shared['versions'])===1&&$shared['versions'][0]['id']===$v2,'Series link pins the selected version');
    request('revoke_collection_share',['id'=>$share],$other,404);
    request('revoke_collection_share',['id'=>$share],$token);
    request('shared_collection',['id'=>$share],'',404);

    // Migration keeps the same user ID, works, collection and tutorial state.
    $lesson=request('tutorial',[],$token);
    request('register_account',['handle'=>'author_test','password'=>'short'],$token,400);
    $account=request('register_account',['handle'=>'Author_Test','password'=>'test-password-12345'],$token);
    check($account['user']['id']===$author['user']['id']&&$account['user']['handle']==='author_test','Account binds the original identity');
    request('me',[],$token,401); $token=$account['token'];
    $me=request('me',[],$token);check(count($me['builds'])===1,'Guest works survive registration');
    check(request('tutorial',[],$token)['revision']===$lesson['revision'],'Guest tutorial survives registration');
    check(count(request('portfolio',[],$token)['collections'])===1,'Guest collection survives registration');
    request('register_account',['handle'=>'AUTHOR_TEST','password'=>'test-password-12345'],$other,409);
    request('login',['handle'=>'author_test','password'=>'wrong'],'',401);
    $login=request('login',['handle'=>'AUTHOR_TEST','password'=>'test-password-12345']);
    check($login['user']['id']===$author['user']['id'],'Cross-device login resolves same identity');
    request('logout',[],$login['token']);request('me',[],$login['token'],401);
    request('recover_account',['handle'=>'author_test','recoveryCode'=>'wrong','password'=>'replacement-12345'],'',401);
    $restored=request('recover_account',['handle'=>'author_test','recoveryCode'=>$account['recoveryCode'],'password'=>'replacement-12345']);
    request('me',[],$token,401);$token=$restored['token'];
    request('recover_account',['handle'=>'author_test','recoveryCode'=>$account['recoveryCode'],'password'=>'replacement-12345'],'',401);
    request('login',['handle'=>'author_test','password'=>'test-password-12345'],'',401);
    check($restored['recoveryCode']!==$account['recoveryCode'],'Recovery rotates code and revokes old sessions');
    $login=request('login',['handle'=>'author_test','password'=>'replacement-12345']);
    check($login['user']['id']===$author['user']['id'],'New password works');
    $expired=hash('sha256',$login['token']);$store->execute('UPDATE '.$store->table('sessions').' SET expires_at = ? WHERE token_hash = ?',[time()-1,$expired]);
    request('me',[],$login['token'],401);

    // Legacy builds gain a first version without rewriting the original record.
    $legacy=$preset;$legacy['id']=str_repeat('c',32);$legacy['name']='旧版存档';
    $store->execute('INSERT INTO '.$store->table('builds').' (id,user_id,data,updated_at) VALUES (?,?,?,?)',[$legacy['id'],$stranger['user']['id'],json_encode($legacy),time()]);
    $oldData=$store->one('SELECT data FROM '.$store->table('builds').' WHERE id = ?',[$legacy['id']])['data'];
    $legacyWorks=request('portfolio',[],$other);
    check(count($legacyWorks['works'])===1&&$legacyWorks['works'][0]['number']===1,'Existing builds lazily receive version one');
    check($store->one('SELECT data FROM '.$store->table('builds').' WHERE id = ?',[$legacy['id']])['data']===$oldData,'Migration leaves original stored build untouched');

    // A legal, deliberately losing active skill provides a short real match, not forged evidence.
    $trial=$preset;unset($trial['id']);$trial['name']='败局也算试用';$trial['character']['hp']=20;$trial['character']['flipColor']=null;
    $trial['character']['skills']=[['name'=>'消耗自身上限','trigger'=>'active','effects'=>[['op'=>'lose_max_hp','target'=>'self','amount'=>30]],'limit'=>1]];
    request('validate_build',['build'=>$trial],$token,400);
    $draft=request('validate_build',['build'=>$trial,'draft'=>true],$token);
    check($draft['tier']==='extended','Draft validation separates budget from structure');
    $invalid=$trial;$invalid['character']['hp']=999;request('save_build',['build'=>$invalid],$token,400);
    $invalid=$trial;$invalid['deck'][0]['rank']=$invalid['deck'][1]['rank'];request('save_build',['build'=>$invalid],$token,400);
    $saved=request('save_build',['build'=>$trial],$token);$trialId=$saved['version']['id'];
    request('create_room',['versionId'=>$trialId],$token,400);
    $limits=['characterBudget'=>100,'customBudget'=>100,'customCardBudget'=>100];
    request('create_room',['versionId'=>$trialId,'budgetLimits'=>$limits+['maxHp'=>999]],$token,400);
    $room=request('create_room',['versionId'=>$trialId,'budgetLimits'=>$limits,'bots'=>[['presetId'=>$opponent['id']]]],$token);
    request('set_visibility',['id'=>$trialId,'visibility'=>'published'],$token,400);
    $room=request('start',['code'=>$room['code']],$token);
    check($room['status']==='playing','Extended budget room starts through the engine');
    request('set_visibility',['id'=>$trialId,'visibility'=>'published'],$token,400);
    $room=request('act',['code'=>$room['code'],'revision'=>$room['revision'],'requestId'=>'trial-draw-001','action'=>['type'=>'draw','mind'=>0]],$token);
    $skill=null;foreach($room['game']['legalActions'] as $candidate)if(($candidate['action']['type']??'')==='skill'){$skill=$candidate['action'];break;}
    check($skill!==null,'Trial skill is an authoritative legal action');
    $room=request('act',['code'=>$room['code'],'revision'=>$room['revision'],'requestId'=>'trial-skill-001','action'=>$skill],$token);
    check($room['status']==='finished','Actual legal action completes a losing match');
    $published=request('set_visibility',['id'=>$trialId,'visibility'=>'published'],$token)['version'];
    check($published['trial']['roomCode']===$room['code']&&$published['tier']==='extended','Server certifies a loss with its real room and budget');
    $n=$store->one('SELECT COUNT(*) AS n FROM '.$store->table('trials').' WHERE version_id = ?',[$trialId]);request('room',['code'=>$room['code']],$token);
    check((int)$n['n']===1,'Trial evidence is recorded once');
    $standard=request('gallery',['tier'=>'standard']);$extended=request('gallery',['tier'=>'extended']);
    check(!in_array($trialId,array_column($standard['versions'],'id'),true)&&in_array($trialId,array_column($extended['versions'],'id'),true),'Standard and over-budget submissions are separate');
    request('create_room',['versionId'=>$trialId],$token,400);
    $normal=request('create_room',['presetId'=>$preset['id']],$token);
    request('choose_build',['code'=>$normal['code'],'versionId'=>$trialId],$token,400);
    request('add_bot',['code'=>$normal['code'],'versionId'=>$trialId],$token,400);
    request('join_room',['code'=>$normal['code'],'versionId'=>$trialId],$other,400);
    request('leave_room',['code'=>$normal['code']],$token);
    $cosmetic=$saved['build'];$cosmetic['name']='同规则新封面';$cosmetic['character']['title']='仅改称号';
    $cosmeticSaved=request('save_build',['build'=>$cosmetic,'expectedVersion'=>$trialId],$token);
    check($cosmeticSaved['version']['trial']!==null,'Cosmetic-only revision retains gameplay proof');
    request('set_visibility',['id'=>$cosmeticSaved['version']['id'],'visibility'=>'published'],$token);
    check(request('shared_work',['id'=>$trialId])['version']['visibility']==='link','Replacing submission preserves the previous fixed share');
    $changed=$cosmeticSaved['build'];$changed['deck']=array_reverse($changed['deck']);
    $changed=request('save_build',['build'=>$changed,'expectedVersion'=>$cosmeticSaved['version']['id']],$token);
    check($changed['version']['trial']===null,'Changing card order invalidates proof');
    request('set_visibility',['id'=>$changed['version']['id'],'visibility'=>'published'],$token,400);
    $copy=$cosmetic;unset($copy['id']);$copySaved=request('save_build',['build'=>$copy],$other);
    check($copySaved['version']['trial']===null,'Copy cannot inherit another author trial');
    // Reader's own completed game using the author's shared version cannot certify a new draft.
    $readerRoom=request('create_room',['versionId'=>$trialId,'budgetLimits'=>$limits,'bots'=>[['presetId'=>$opponent['id']]]],$other);
    $readerRoom=request('start',['code'=>$readerRoom['code']],$other);
    $readerRoom=request('act',['code'=>$readerRoom['code'],'revision'=>$readerRoom['revision'],'requestId'=>'reader-draw-001','action'=>['type'=>'draw','mind'=>0]],$other);
    foreach($readerRoom['game']['legalActions'] as $candidate)if(($candidate['action']['type']??'')==='skill'){$skill=$candidate['action'];break;}
    $readerRoom=request('act',['code'=>$readerRoom['code'],'revision'=>$readerRoom['revision'],'requestId'=>'reader-skill-001','action'=>$skill],$other);
    check($readerRoom['status']==='finished'&&!$store->one('SELECT id FROM '.$store->table('trials').' WHERE room_code = ?',[$readerRoom['code']]),'Other humans using a shared build do not certify its author');

    // An entirely normal-budget match can also submit; bot seats never earn proof.
    $short=$trial;unset($short['id']);$short['name']='标准预算试用';$short['character']['hp']=4;$short['character']['skills'][0]['trigger']='turn_start';
    $shortSaved=request('save_build',['build'=>$short],$token);$shortVersion=$shortSaved['version']['id'];
    $botBuild=$opponent;unset($botBuild['id']);$botBuild['name']='机器人代用不能认证';
    $botSaved=request('save_build',['build'=>$botBuild],$token);
    $botRoom=request('create_room',['versionId'=>$shortVersion,'bots'=>[['versionId'=>$botSaved['version']['id']]]],$token);
    $botRoom=request('start',['code'=>$botRoom['code']],$token);
    check($botRoom['status']==='finished','Opening rule resolution is persisted as a complete legal match');
    request('set_visibility',['id'=>$shortVersion,'visibility'=>'published'],$token);
    request('set_visibility',['id'=>$botSaved['version']['id'],'visibility'=>'published'],$token,400);
    check(in_array($shortVersion,array_column(request('gallery',['tier'=>'standard'])['versions'],'id'),true),'Standard submission appears in standard gallery');
    check(!in_array($shortVersion,array_column(request('gallery',['tier'=>'extended'])['versions'],'id'),true),'Standard submission is absent from extended gallery');
    require_once __DIR__.'/../src/bootstrap.php';
    $workshop=new Imaginary\Workshop($store,Imaginary\configuration());
    $stored=$workshop->version($shortVersion,$author['user']['id']);
    check($workshop->describe($stored)['trial']!==null,'Current rule signature matches the proof');
    Imaginary\RuleConfig::configure(['characterBudget'=>19]);
    check($workshop->describe($stored)['trial']===null,'Changing server rule configuration invalidates trial qualification');
    Imaginary\RuleConfig::configure(null);

    if (function_exists('imagecreatetruecolor')) {
        $image=imagecreatetruecolor(8,8);imagefill($image,0,0,imagecolorallocate($image,70,100,80));ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
        $upload=request('upload_portrait',['image'=>'data:image/png;base64,'.base64_encode($png)],$token);
        check(preg_match('/^upload_[a-f0-9]{32}$/D',$upload['art'])===1,'Image uploaded as an opaque art reference');
        check(request('upload_portrait',['image'=>'data:image/png;base64,'.base64_encode($png)],$token)['art']===$upload['art'],'Identical portrait upload deduplicates');
        request('upload_portrait',['image'=>'data:image/svg+xml;base64,'.base64_encode('<svg/>')],$token,400);
        request('upload_portrait',['image'=>'data:image/png;base64,'.base64_encode('<?php die();')],$token,400);
        request('upload_portrait',['image'=>'data:image/png;base64,'.base64_encode(str_repeat('x',2097153))],$token,400);
        request('upload_portrait',[],$token,413,'POST',str_repeat('x',2900001));
        $portraitBuild=$third['build'];$portraitBuild['character']['art']=$upload['art'];
        $portraitSaved=request('save_build',['build'=>$portraitBuild],$token);
        check($portraitSaved['build']['character']['art']===$upload['art'],'Saved versions preserve uploaded art');
        global $base;
        $curl=curl_init($base.'/portrait.php?id='.substr($upload['art'],7));curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true]);$imageResponse=curl_exec($curl);$imageStatus=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        check($imageStatus===200&&stripos($imageResponse,'Content-Type: image/png')!==false&&stripos($imageResponse,'nosniff')!==false,'Portrait endpoint serves safe image MIME and nosniff');
        $portraitBuild['character']['art']='upload_'.str_repeat('f',32);request('save_build',['build'=>$portraitBuild],$token,400);
    }
    request('set_visibility',['id'=>$v2,'visibility'=>'private'],$token);request('shared_work',['id'=>$v2],'',404);
    request('delete_build',['id'=>$id],$token);request('shared_work',['id'=>$v1],'',404);
    check(request('portfolio',[],$token)['collections'][0]['buildIds']===[],'Deleting a work cleans live collections and revokes version shares');
}
