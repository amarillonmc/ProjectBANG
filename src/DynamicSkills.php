<?php
namespace Imaginary;

/** Skill slots keep their identity, so removing and regaining a limited skill cannot reset it. */
trait DynamicSkills
{
    private static function skillEnabled(array $g,string $id,int $i): bool
    {
        if(!empty($g['players'][$id]['disabledSkills'][$i]))return false;
        foreach($g['players'][$id]['skillSeals']??[] as $seal)if($seal['index']===$i)return false;
        return true;
    }
    private static function skillExpiry(array $g,string $source,string $target,string $duration): array
    {
        $owner=$duration==='source_turn'?$source:$target;
        return ['duration'=>$duration,'owner'=>$owner,'at'=>$duration==='turn'?$g['turnNumber']:$g['players'][$owner]['turns']+1];
    }
    private static function skillSlot(array $g,string $id,string $key): ?int
    {
        foreach($g['players'][$id]['character']['skills'] as $i=>$skill)if(($skill['key']??$skill['name'])===$key)return $i;
        return null;
    }
    private static function grantSkill(array &$g,string $source,string $target,string $key,array $skill,string $duration): void
    {
        $i=self::skillSlot($g,$target,$key);$skill['key']=$key;
        $ownership=['native'=>false,'leases'=>[]];
        if($i===null) {
            if(count($g['players'][$target]['character']['skills'])>=RuleConfig::get('maxSkills')) { self::log($g,'技能槽达到服务器资源上限，未获得新技能。');return; }
            $i=count($g['players'][$target]['character']['skills']);$g['players'][$target]['character']['skills'][]=$skill;
        } else {
            $existing=$g['players'][$target]['character']['skills'][$i];$compare=$skill;unset($existing['key'],$compare['key']);
            if($existing!=$compare) { self::log($g,'同一稳定标识已有不同技能定义，保留原技能。');return; }
            $ownership=$g['players'][$target]['skillGrants'][$i]??['native'=>empty($g['players'][$target]['disabledSkills'][$i]),'leases'=>[]];
            if(isset($ownership['duration']))$ownership=['native'=>false,'leases'=>[$ownership]];
        }
        unset($g['players'][$target]['disabledSkills'][$i]);
        $lease=self::skillExpiry($g,$source,$target,$duration)+['source'=>$source];$updated=false;
        foreach($ownership['leases'] as &$old)if(($old['source']??null)===$source&&$old['duration']===$duration) { $old=$lease;$updated=true;break; }unset($old);
        if(!$updated)$ownership['leases'][]=$lease;
        $g['players'][$target]['skillGrants'][$i]=$ownership;
        self::log($g,$g['players'][$target]['name'].'获得技能「'.$skill['name'].'」。');
    }
    private static function expireSkills(array &$g,string $boundary,?string $owner=null): void
    {
        $expired=function($entry)use($g,$boundary,$owner){
            if($entry['duration']==='permanent')return false;
            if($entry['duration']==='turn')return $boundary==='turn_end'&&$g['turnNumber']>=$entry['at'];
            return $boundary==='turn_start'&&$entry['owner']===$owner&&$g['players'][$owner]['turns']>=$entry['at'];
        };
        foreach($g['players'] as $id=>&$p) {
            foreach($p['skillGrants']??[] as $i=>$grant) {
                if(isset($grant['duration']))$grant=['native'=>false,'leases'=>[$grant]];
                $grant['leases']=array_values(array_filter($grant['leases'],function($lease)use($expired){return !$expired($lease);}));
                if(!$grant['native']&&!$grant['leases'])$p['disabledSkills'][$i]=true;
                $p['skillGrants'][$i]=$grant;
            }
            if(isset($p['skillSeals']))$p['skillSeals']=array_values(array_filter($p['skillSeals'],function($seal)use($expired){return !$expired($seal);}));
        }unset($p);
    }
    private static function dynamicSkillEffect(array &$g,string $id,string $target,array $e,array $remaining,array $selection): bool
    {
        $to=self::eventTarget($g,$id,$target,$e['target']);
        if($e['op']==='gain_skill')self::grantSkill($g,$id,$to,$e['key'],$e['skill'],$e['duration']);
        elseif($e['op']==='lose_skill'||$e['op']==='seal_skill') {
            $i=self::skillSlot($g,$to,$e['key']);if($i===null)return false;
            if($e['op']==='lose_skill') { $g['players'][$to]['disabledSkills'][$i]=true;unset($g['players'][$to]['skillGrants'][$i]); }
            else $g['players'][$to]['skillSeals'][]=self::skillExpiry($g,$id,$to,$e['duration'])+['index'=>$i];
            self::log($g,$g['players'][$to]['name'].($e['op']==='lose_skill'?'失去':'暂时封锁').'技能「'.$g['players'][$to]['character']['skills'][$i]['name'].'」。');
        } elseif($e['op']==='copy_skill') {
            $choices=[];foreach($g['players'][$to]['character']['skills'] as $i=>$skill)if(self::skillEnabled($g,$to,$i))$choices[]=['index'=>$i,'skill'=>$skill];
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            if($choices)array_unshift($g['queue'],['kind'=>'mechanic_copy_skill','player'=>$id,'source'=>$id,'target'=>$to,'choices'=>$choices,'duration'=>$e['duration']]);return true;
        }
        return false;
    }
}
