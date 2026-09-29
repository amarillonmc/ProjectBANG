<?php
namespace Imaginary;

/** Serializable event windows. Frames contain only data, never callbacks or user code. */
trait Events
{
    private static function eventContext(array $g): array
    {
        $stack=$g['eventStack']??[];$key=$stack?end($stack):null;
        return $key!==null?($g['eventFrames'][$key]??[]):[];
    }
    private static function eventRulesPresent(array $g,string $type): bool
    {
        if(!empty($g['resolutionStopped']))return false;
        $triggers=$type==='damage'?['before_deal_damage','before_take_damage','any_before_damage','damage_point','after_deal_damage','any_after_damage']:['dying','any_dying','death','any_death'];
        foreach($g['players'] as $p)if($p['alive'])foreach($p['character']['skills'] as $s) {
            if(in_array($s['trigger'],$triggers,true))return true;
            if($type==='damage'&&in_array($s['trigger'],['after_damage','after_attack'],true)&&strpos(json_encode($s),'event_')!==false)return true;
        }
        return false;
    }
    private static function openEvent(array &$g,array $event): void
    {
        $g['eventSerial']=($g['eventSerial']??0)+1;
        $event+=['id'=>$g['eventSerial'],'stage'=>'start','cancelled'=>false];
        array_unshift($g['queue'],['kind'=>'frame_open','player'=>$event['target'],'frame'=>$event]);
    }
    private static function queueEventStep(array &$g,int $frame,string $stage): void
    {
        array_unshift($g['queue'],['kind'=>'frame_step','player'=>$g['eventFrames'][$frame]['target'],'frame'=>$frame,'stage'=>$stage]);
    }
    private static function queueEventSkills(array &$g,int $frame,array $groups): void
    {
        $calls=[];$event=$g['eventFrames'][$frame];
        foreach($groups as $group)foreach($group['players'] as $id) {
            if(!isset($g['players'][$id])||!$g['players'][$id]['alive'])continue;
            foreach($g['players'][$id]['character']['skills'] as $i=>$skill)if($skill['trigger']===$group['trigger']) {
                $related=$group['related']??$event['target'];
                if($related===null||!isset($g['players'][$related]))$related=$id;
                $calls[]=['kind'=>'frame_skill','player'=>$id,'source'=>$id,'target'=>$related,'index'=>$i,'frame'=>$frame,'trigger'=>$group['trigger']];
            }
        }
        $g['queue']=array_merge($calls,$g['queue']);
    }
    private static function damageEvent(array &$g,?string $source,string $target,int $n,string $color,bool $attack): void
    {
        self::openEvent($g,['type'=>'damage','source'=>$source,'target'=>$target,'originalTarget'=>$target,'amount'=>$n,'originalAmount'=>$n,'color'=>$color,'attack'=>$attack,'visited'=>[$target],'applied'=>0,'point'=>0]);
    }
    private static function eventProgress(array &$g,array $job): void
    {
        if($job['kind']==='frame_open') {
            $frame=$job['frame'];$key=$frame['id'];$g['eventFrames'][$key]=$frame;$g['eventStack'][]=$key;
            self::queueEventStep($g,$key,$frame['type']==='damage'?'source':'dying');return;
        }
        $key=$job['frame'];if(!isset($g['eventFrames'][$key]))return;
        if($job['kind']==='frame_skill') {
            $id=$job['player'];$i=$job['index'];
            if(!$g['players'][$id]['alive']||!isset($g['players'][$id]['character']['skills'][$i])||$g['players'][$id]['character']['skills'][$i]['trigger']!==$job['trigger']||!self::skillUsable($g,$id,$i))return;
            $skill=$g['players'][$id]['character']['skills'][$i];
            if(!self::effectTargetsValid($g,$id,$job['target'],$skill['effects'],self::handCost($g,$id,$skill),self::skillCostSelection($g,$id,$skill),$skill['cost']['mind']))return;
            if(!empty($skill['optional']))self::pending($g,['kind'=>'skill_offer','player'=>$id,'source'=>$id,'target'=>$job['target'],'index'=>$i,'frame'=>$key,'prompt'=>'是否发动「'.$skill['name'].'」？']);
            else self::executeSkill($g,$id,$i,$job['target'],[]);
            return;
        }
        $g['eventFrames'][$key]['stage']=$job['stage'];$event=$g['eventFrames'][$key];$target=$event['target'];$source=$event['source'];
        if($job['stage']==='close') {
            self::check(end($g['eventStack'])===$key,'事件栈顺序不一致');array_pop($g['eventStack']);unset($g['eventFrames'][$key]);
            self::victory($g);return;
        }
        if($event['type']==='damage') {
            if(in_array($job['stage'],['source','watch','target','commit'],true)&&($event['cancelled']||$event['amount']<=0||!$g['players'][$target]['alive'])) {
                self::log($g,'本次伤害被取消或已无有效目标。');self::queueEventStep($g,$key,'close');return;
            }
            if($job['stage']==='source') {
                self::queueEventStep($g,$key,'watch');
                self::queueEventSkills($g,$key,[['trigger'=>'before_deal_damage','players'=>$source===null?[]:[$source],'related'=>$target]]);
            } elseif($job['stage']==='watch') {
                self::queueEventStep($g,$key,'target');
                self::queueEventSkills($g,$key,[['trigger'=>'any_before_damage','players'=>self::eventOrder($g),'related'=>$target]]);
            } elseif($job['stage']==='target') {
                $g['eventFrames'][$key]['announcedTarget']=$target;
                self::queueEventStep($g,$key,'commit');
                self::queueEventSkills($g,$key,[['trigger'=>'before_take_damage','players'=>[$target],'related'=>$source]]);
            } elseif($job['stage']==='commit') {
                if(($event['announcedTarget']??null)!==$target) { self::queueEventStep($g,$key,'target');return; }
                $n=self::applyDamage($g,$source,$target,$event['amount'],$event['color']);
                $g['eventFrames'][$key]['applied']=$n;$g['eventFrames'][$key]['amount']=$n;
                self::queueEventStep($g,$key,$n>0?'after':'close');
                if($n>0)self::dying($g,$target,$source);
            } elseif($job['stage']==='after') {
                self::queueEventStep($g,$key,'point');
                $groups=[['trigger'=>'after_damage','players'=>[$target],'related'=>$source],['trigger'=>'after_deal_damage','players'=>$source===null?[]:[$source],'related'=>$target]];
                if($event['attack'])$groups[]=['trigger'=>'after_attack','players'=>$source===null?[]:[$source],'related'=>$target];
                $groups[]=['trigger'=>'any_after_damage','players'=>self::eventOrder($g),'related'=>$target];
                self::queueEventSkills($g,$key,$groups);
            } elseif($job['stage']==='point') {
                if($event['point']<$event['applied']&&$g['players'][$target]['alive']) {
                    $g['eventFrames'][$key]['point']++;self::queueEventStep($g,$key,'point');
                    self::queueEventSkills($g,$key,[['trigger'=>'damage_point','players'=>[$target],'related'=>$source]]);
                } else self::queueEventStep($g,$key,'close');
            }
        } else {
            if($job['stage']==='dying') {
                if(self::hp($g,$target)>0||!$g['players'][$target]['alive']) { self::queueEventStep($g,$key,'close');return; }
                self::queueEventStep($g,$key,'rescue');
                self::queueEventSkills($g,$key,[['trigger'=>'dying','players'=>[$target],'related'=>$source],['trigger'=>'any_dying','players'=>self::eventOrder($g),'related'=>$target]]);
            } elseif($job['stage']==='rescue') {
                self::miracleRescue($g,$target);self::tryColorFlip($g,$target);
                if(self::hp($g,$target)>0)self::queueEventStep($g,$key,'close');
                else {
                    $g['eventFrames'][$key]['deathCommitted']=true;
                    self::queueEventStep($g,$key,'eliminate');
                    self::queueEventSkills($g,$key,[['trigger'=>'death','players'=>[$target],'related'=>$source]]);
                }
            } elseif($job['stage']==='eliminate') {
                self::eliminate($g,$target);self::queueEventStep($g,$key,'close');
                self::queueEventSkills($g,$key,[['trigger'=>'any_death','players'=>self::eventOrder($g),'related'=>$target]]);
            }
        }
    }
    private static function eventOrder(array $g): array
    {
        $at=array_search($g['turn'],$g['order'],true);$order=array_merge(array_slice($g['order'],$at),array_slice($g['order'],0,$at));
        return array_values(array_filter($order,function($id)use($g){return $g['players'][$id]['alive'];}));
    }
    private static function eventTarget(array $g,string $id,string $target,string $ref): ?string
    {
        if($ref==='self')return $id;
        if($ref==='turn_player')return $g['turn'];
        if($ref==='event_source'||$ref==='event_target')return self::eventContext($g)[$ref==='event_source'?'source':'target']??null;
        return $target;
    }
    private static function eventEffect(array &$g,string $id,string $target,array $e): void
    {
        $event=self::eventContext($g);
        if(($event['type']??'')!=='damage'||!in_array($event['stage'],['source','watch','target'],true))return;
        $key=$event['id'];$n=$e['amount'];
        switch($e['op']) {
            case 'event_add_damage':$g['eventFrames'][$key]['amount']=min(RuleConfig::get('maxAmount'),$event['amount']+$n);break;
            case 'event_reduce_damage':$g['eventFrames'][$key]['amount']=max(0,$event['amount']-$n);break;
            case 'event_set_damage':$g['eventFrames'][$key]['amount']=$n;break;
            case 'event_cancel':$g['eventFrames'][$key]['cancelled']=true;break;
            case 'event_source':$g['eventFrames'][$key]['source']=self::eventTarget($g,$id,$target,$e['target']);break;
            case 'event_redirect':
                $to=self::eventTarget($g,$id,$target,$e['target']);
                if($to===null||!$g['players'][$to]['alive']||in_array($to,$event['visited'],true))return;
                $g['eventFrames'][$key]['target']=$to;$g['eventFrames'][$key]['visited'][]=$to;
                self::log($g,'本次伤害转移给'.$g['players'][$to]['name'].'。');break;
        }
    }
}
