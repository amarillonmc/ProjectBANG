<?php
namespace Imaginary;

/** Hidden roles are server-owned. Only public actions inform the bot policy. */
trait Identity
{
    private static function dealIdentities(array &$g): string
    {
        $roles=[];
        foreach(Catalog::identityCounts(count($g['order'])) as $role=>$count) for($i=0;$i<$count;$i++) $roles[]=$role;
        self::shuffleCards($roles); $mayor='';
        foreach($g['order'] as $i=>$id) {
            $g['players'][$id]['role']=$roles[$i];
            if($roles[$i]==='mayor') { $mayor=$id; $g['players'][$id]['maxHp']++; }
        }
        $g['mayor']=$mayor; $g['identityAttitude']=[];
        self::log($g,$g['players'][$mayor]['name'].'是村长，体力上限 +1，并首先行动。其余身份保密。');
        return $mayor;
    }
    private static function identityOutcome(array $g): ?array
    {
        $alive=self::alive($g);
        if(!$g['players'][$g['mayor']]['alive']) {
            $fox=count($alive)===1&&$g['players'][$alive[0]]['role']==='fox';
            $team=$fox?'fox':'wolf'; $name=$fox?'妖狐独胜':'狼人阵营';
        } else {
            foreach($alive as $id) if(in_array($g['players'][$id]['role'],['wolf','fox'],true)) return null;
            $team='village'; $name='村庄阵营（村长与村民）';
        }
        $winners=[];
        foreach($g['players'] as $id=>$p) if($team==='village'?in_array($p['role'],['mayor','villager'],true):$p['role']===$team) $winners[]=$id;
        return ['name'=>$name,'team'=>$team,'players'=>$winners];
    }
    private static function identityDeath(array &$g,string $target,?string $source): void
    {
        $role=$g['players'][$target]['role'];
        self::log($g,$g['players'][$target]['name'].'的身份揭示为「'.Catalog::identityRoles()[$role]['name'].'」。');
        if($source===null||!isset($g['players'][$source])||!$g['players'][$source]['alive']) return;
        // Once a victory condition is met there is no further kill reward or penalty.
        if(self::identityOutcome($g)!==null) return;
        if($role==='wolf') {
            self::draw($g,$source,3);
            self::log($g,$g['players'][$source]['name'].'击败狼人，摸三张牌。');
        } elseif($role==='villager'&&$source===$g['mayor']) {
            self::log($g,'村长误杀村民，弃掉全部手牌及场上的牌。');
            foreach(['hand','delayed'] as $zone) {
                $cards=$g['players'][$source][$zone]; $g['players'][$source][$zone]=[];
                foreach($cards as $c) self::spend($g,$c);
            }
            foreach($g['players'][$source]['equipment'] as $c) self::removeEquipment($g,$source,$c['uid']);
        }
    }
    private static function identityRescueStep(array &$g,int $key): void
    {
        $frame=&$g['eventFrames'][$key]; $target=$frame['target'];
        if(self::hp($g,$target)>0||!$g['players'][$target]['alive']) { self::queueEventStep($g,$key,'close'); return; }
        if(!isset($frame['rescuers'])) {
            $at=array_search($target,$g['order'],true);
            $frame['rescuers']=array_merge(array_slice($g['order'],$at),array_slice($g['order'],0,$at)); $frame['rescueIndex']=0;
        }
        while(isset($frame['rescuers'][$frame['rescueIndex']])) {
            $id=$frame['rescuers'][$frame['rescueIndex']];
            if(!$g['players'][$id]['alive']) { $frame['rescueIndex']++; continue; }
            // Every living seat receives the window, even without a usable card.
            self::pending($g,['kind'=>'identity_rescue','player'=>$id,'target'=>$target,'source'=>$frame['source'],'frame'=>$key,
                'prompt'=>$g['players'][$target]['name'].'濒死：'.($g['players'][$target]['maxHp']<=0?'体力上限归零，仅奇迹能尝试救援。':'需要回复 '.max(1,1+$g['players'][$target]['marks']['neutral']-$g['players'][$target]['maxHp']).' 点体力。').'可救援或放弃；休养只能自救，援药与奇迹须已成熟。']);
            return;
        }
        self::queueEventStep($g,$key,'death');
    }
    private static function identityRescueActions(array $g,string $id,array $p): array
    {
        $out=[['label'=>'放弃本次救援','action'=>['type'=>'respond','choice'=>'pass']]];
        $target=$p['target']; $hasBody=$g['players'][$target]['maxHp']>0;
        if($id===$target&&$hasBody&&!self::handLocked($g,$id)) foreach($g['players'][$id]['hand'] as $c) {
            if(self::effective($g,$id,$c)['type']==='heal') $out[]=['label'=>'休养：自救 1 点体力','action'=>['type'=>'respond','choice'=>'rescue_hand','card'=>$c['uid']]];
        }
        foreach($g['players'][$id]['equipment'] as $c) {
            if(!self::mature($g,$id,$c)||!in_array($c['type'],['medicine','miracle'],true)||(!$hasBody&&$c['type']!=='miracle')) continue;
            $out[]=['label'=>'卸除'.$c['name'].'：救援'.$g['players'][$target]['name'],'action'=>['type'=>'respond','choice'=>'rescue_equipment','card'=>$c['uid']]];
        }
        return $out;
    }
    private static function identityRescueRespond(array &$g,string $id,array $p,array $a): void
    {
        $valid=false;
        foreach(self::identityRescueActions($g,$id,$p) as $item) if($a['choice']===$item['action']['choice']&&($a['card']??null)===($item['action']['card']??null)) $valid=true;
        self::check($valid,'请选择可用的救援牌或放弃救援');
        $key=$p['frame']; $target=$p['target'];
        self::check(isset($g['eventFrames'][$key])&&$g['eventFrames'][$key]['target']===$target,'濒死窗口已结束');
        if($a['choice']==='pass') $g['eventFrames'][$key]['rescueIndex']++;
        else {
            if($a['choice']==='rescue_hand') { $card=self::take($g['players'][$id]['hand'],$a['card']); self::spend($g,$card); }
            else $card=self::removeEquipment($g,$id,$a['card']);
            self::heal($g,$target,$card['type']==='miracle'&&$id===$target?2:1,true);
            self::log($g,$g['players'][$id]['name'].'使用'.$card['name'].'救援'.$g['players'][$target]['name'].'。');
            self::identitySignal($g,$id,$target,1);
        }
        self::queueEventStep($g,$key,'identity_rescue');
    }
    private static function identitySignal(array &$g,?string $source,string $target,int $sign): void
    {
        if($g['mode']!=='identity'||$source===null||$source===$target||$target!==$g['mayor']) return;
        $g['identityAttitude'][$source]=max(-5,min(5,($g['identityAttitude'][$source]??0)+$sign));
    }
    private static function identityBotOpponent(array $g,string $id,string $target): bool
    {
        if($id===$target) return false;
        $role=$g['players'][$id]['role']; $mayor=$g['mayor'];
        if($target===$mayor) return $role==='wolf'||($role==='fox'&&count(self::alive($g))===2);
        // No inspection of another player's concealed role, including fellow wolves.
        $attitude=$g['identityAttitude'][$target]??0;
        return $role==='wolf'?$attitude>=0:($role==='fox'||$attitude<=0);
    }
    private static function identityBotRescues(array $g,string $id,string $target): bool
    {
        if($id===$target) return true;
        $role=$g['players'][$id]['role'];
        if($target===$g['mayor']) return $role==='villager'||($role==='fox'&&count(self::alive($g))>2);
        return in_array($role,['mayor','villager'],true)&&($g['identityAttitude'][$target]??0)>0;
    }
}
