<?php
namespace Imaginary;

use InvalidArgumentException;
require_once __DIR__.'/SkillBlocks.php';

/** Whitelisted, bounded effect trees. No executable user text. */
final class Rules
{
    private static function fail(string $s): void { throw new InvalidArgumentException($s); }
    private static function text($v,string $label,int $max=48): string
    {
        if(!is_string($v)||trim($v)===''||strlen($v)>$max*4||preg_match('/[\x00-\x1F\x7F]/',$v)||!preg_match('//u',$v)) self::fail($label.'不能为空、过长或含控制字符');
        return trim($v);
    }
    private static function number($v,int $min,int $max,string $label): int
    {
        if(!is_int($v)||$v<$min||$v>$max) self::fail($label.'超出允许范围'); return $v;
    }
    private static function choice($v,array $allowed,string $label): string
    {
        if(!is_string($v)||!in_array($v,$allowed,true)) self::fail($label.'不受支持'); return $v;
    }
    private static function keys(array $v,array $allowed): void
    {
        foreach(array_keys($v) as $key) if(!in_array($key,$allowed,true)) self::fail('未知字段：'.(string)$key);
    }
    public static function effects($effects,bool $equipment=false,bool $passive=false,int $depth=0): array
    {
        if($depth>12) self::fail('效果嵌套超过 12 层');
        if(!is_array($effects)||count($effects)<1||count($effects)>RuleConfig::get('maxEffects')||array_keys($effects)!==range(0,count($effects)-1)) self::fail('效果列表为空或超出服务器资源上限');
        $out=[];
        foreach($effects as $e) {
            if(!is_array($e)) self::fail('效果结构不正确');
            $meta=SkillBlocks::metadata()['effectMeta'];
            $op=self::choice($e['op']??null,array_keys($meta),'效果积木');
            $extra=['choose'=>['options'],'branch'=>['condition','then','else'],'choose_targets'=>['pool','effects'],'judge'=>['filter','then','else','repeat','obtain'],'pindian'=>['then','else'],'add_mark'=>['key'],'remove_mark'=>['key'],'store_pile'=>['key','visibility'],'take_pile'=>['key'],'passive_retrial'=>['filter','zone','exchange']];
            $extra+=['gain_skill'=>['key','skill','duration'],'lose_skill'=>['key'],'seal_skill'=>['key','duration'],'copy_skill'=>['duration']];
            self::keys($e,array_merge(['op','target','amount','color'],$extra[$op]??[]));
            if($meta[$op]['equipmentOnly']!==$equipment) self::fail($equipment?'装备心象只能使用持续装备积木':'持续装备积木只能用于装备心象');
            if($meta[$op]['passiveOnly']!==$passive) self::fail('持续光环积木必须放在持续生效技能中');
            $target=self::choice($e['target']??'self',array_keys(SkillBlocks::metadata()['targets']),'目标');
            if(!in_array($target,$meta[$op]['targets'],true)) self::fail('该积木不支持这个目标');
            $amount=$e['amount']??null;
            if(is_string($amount)&&!$equipment&&$meta[$op]['max']>1) { if(!preg_match('/\A(?:mark|pile):[a-zA-Z0-9_\x{4e00}-\x{9fff}]{1,24}\z/uD',$amount)) $amount=self::choice($amount,array_keys(SkillBlocks::metadata()['amounts']),'动态效果数量'); }
            else $amount=self::number($amount,$meta[$op]['min'],$meta[$op]['max'],'效果数值');
            $item=['op'=>$op,'target'=>$target,'amount'=>$amount,'color'=>self::choice($e['color']??'neutral',['cool','warm','neutral'],'伤害颜色')];
            foreach(['key','visibility','pool','filter','repeat','obtain','condition'] as $field) if(in_array($field,$extra[$op]??[],true)) {
                if($field==='key') { $item[$field]=self::text($e[$field]??null,'标记 / 牌堆名称',24); if(!preg_match('/\A[a-zA-Z0-9_\x{4e00}-\x{9fff}]+\z/uD',$item[$field])) self::fail('标记名称仅允许字母、数字、汉字及下划线'); }
                elseif($field==='condition') $item[$field]=self::predicate($e[$field]??'always',$depth+1);
                elseif(in_array($field,['repeat','obtain'],true)) { if(isset($e[$field])&&!is_bool($e[$field]))self::fail('判定开关必须为布尔值');$item[$field]=$e[$field]??false; }
                else $item[$field]=self::choice($e[$field]??($field==='visibility'?'public':null),$field==='visibility'?['public','private']:($field==='pool'?['everyone','others','allies','enemies']:['any','red','black','heart','not_heart','spade','club','diamond','attack','defense','basic','event','equipment']),$field);
            }
            foreach(['then','else','effects'] as $field) if(in_array($field,$extra[$op]??[],true)) $item[$field]=empty($e[$field])?[]:self::effects($e[$field],false,false,$depth+1);
            if($op==='choose') {
                $options=$e['options']??null;
                if(!is_array($options)||count($options)<2||count($options)>RuleConfig::get('maxEffects')||array_keys($options)!==range(0,count($options)-1)) self::fail('选择分支至少需要两项');
                $item['options']=[];
                foreach($options as $option) { if(!is_array($option))self::fail('选择分支结构无效'); self::keys($option,['label','effects']); $item['options'][]=['label'=>self::text($option['label']??null,'选项名称'),'effects'=>empty($option['effects'])?[]:self::effects($option['effects'],false,false,$depth+1)]; }
            }
            if($op==='passive_retrial') {
                $item['zone']=self::choice($e['zone']??'hand',['hand','hand_equipment'],'改判素材区域');
                if(isset($e['exchange'])&&!is_bool($e['exchange']))self::fail('交换旧判定牌必须为布尔值');$item['exchange']=$e['exchange']??false;
            }
            if(in_array('duration',$extra[$op]??[],true))$item['duration']=self::choice($e['duration']??'permanent',['permanent','turn','owner_turn','source_turn'],'技能持续时间');
            if($op==='gain_skill')$item['skill']=self::normalizeSkills([$e['skill']??null],$depth+1)[0];
            $out[]=$item;
        }
        if($depth===0&&self::effectNodes($out)>RuleConfig::get('maxEffects')) self::fail('整组效果超过服务器资源上限');
        return $out;
    }
    private static function effectNodes(array $effects): int
    {
        $n=count($effects);foreach($effects as $e){foreach(['then','else','effects'] as $key)if(isset($e[$key]))$n+=self::effectNodes($e[$key]);foreach($e['options']??[] as $o)$n+=self::effectNodes($o['effects']);if(isset($e['skill']))$n+=1+self::effectNodes($e['skill']['effects']);}return $n;
    }
    public static function predicate($condition,int $depth=0)
    {
        if($depth>12)self::fail('条件嵌套过深');
        if(is_string($condition))return self::choice($condition,array_keys(SkillBlocks::metadata()['conditions']),'条件');
        if(!is_array($condition))self::fail('条件结构无效');
        foreach(['all','any'] as $join)if(isset($condition[$join])){self::keys($condition,[$join]);if(!is_array($condition[$join])||!$condition[$join]||count($condition[$join])>RuleConfig::get('maxEffects'))self::fail('复合条件列表无效');return [$join=>array_map(function($c)use($depth){return self::predicate($c,$depth+1);},$condition[$join])];}
        self::keys($condition,['value','cmp','amount','key','subject']);
        $c=['value'=>self::choice($condition['value']??null,['hp','max_hp','lost_hp','hand','mind','mark','pile','played','damage_taken','pindian_win','event_amount','event_original_amount','event_point'],'比较量'),'cmp'=>self::choice($condition['cmp']??null,['eq','ne','lt','le','gt','ge'],'比较方式'),'amount'=>self::number($condition['amount']??null,0,RuleConfig::get('maxAmount'),'比较值')];
        if(isset($condition['subject']))$c['subject']=self::choice($condition['subject'],['self','event_source','event_target','turn_player'],'条件角色');
        if(in_array($c['value'],['mark','pile'],true))$c['key']=self::text($condition['key']??null,'资源名',24);
        elseif(isset($condition['key']))self::fail('这个条件不使用资源名');
        return $c;
    }
    private static function normalizeSkills($skills,int $depth=0): array
    {
        if($depth>12)self::fail('技能定义嵌套超过 12 层');
        if(!is_array($skills)||count($skills)>RuleConfig::get('maxSkills')||($skills&&array_keys($skills)!==range(0,count($skills)-1)))self::fail('技能列表无效或超出资源上限');
        $normalized=[];
        $keys=[];
        foreach($skills as $s) {
            if(!is_array($s)) self::fail('技能结构不正确');
            self::keys($s,['name','trigger','condition','cost','effects','limit','conversion','limitScope','optional','key']);
            $cost=$s['cost']??['hand'=>0,'mind'=>0];
            if(!is_array($cost)) self::fail('技能费用不正确'); self::keys($cost,['hand','mind','zone']);
            $meta=SkillBlocks::metadata(); $trigger=self::choice($s['trigger']??null,array_keys($meta['triggers']),'触发时机');
            $ns=['name'=>self::text($s['name']??null,'技能名称'),'trigger'=>$trigger,
                'condition'=>self::predicate($s['condition']??'always'),
                'cost'=>['hand'=>is_string($cost['hand']??0)?self::choice($cost['hand'],['all','chosen'],'手牌费用'):self::number($cost['hand']??0,0,RuleConfig::get('maxAmount'),'手牌费用'),'mind'=>self::number($cost['mind']??0,0,RuleConfig::get('maxAmount'),'心象费用')],
                'effects'=>[],'limit'=>self::number($s['limit']??0,0,RuleConfig::get('unlimitedUses'),'次数上限')];
            if(isset($s['limitScope'])) $ns['limitScope']=self::choice($s['limitScope'],array_keys($meta['limitScopes']),'次数周期');
            if(isset($s['key']))$ns['key']=self::text($s['key'],'技能稳定标识',24);
            if(isset($ns['key'])) { if(isset($keys[$ns['key']]))self::fail('技能稳定标识不能重复');$keys[$ns['key']]=true; }
            if(isset($cost['zone']))$ns['cost']['zone']=self::choice($cost['zone'],['hand','hand_equipment'],'费用区域');
            if(isset($s['optional'])) { if(!is_bool($s['optional'])) self::fail('可选发动必须为布尔值'); $ns['optional']=$s['optional']; }
            if($trigger==='convert') {
                if(isset($s['effects'])&&$s['effects']!==[]) self::fail('转化技能不能同时携带效果积木');
                if(!is_array($s['conversion']??null)) self::fail('缺少转化定义');
                self::keys($s['conversion'],['from','to','zone','pile','count','match']);
                $ns['conversion']=['from'=>self::choice($s['conversion']['from']??null,array_keys($meta['conversions']['from']),'转化来源'),'to'=>self::choice($s['conversion']['to']??null,array_keys($meta['conversions']['to']),'转化结果')];
                if(isset($s['conversion']['zone']))$ns['conversion']['zone']=self::choice($s['conversion']['zone'],['hand','hand_equipment','pile'],'转化素材区域');
                if(($ns['conversion']['zone']??'hand')==='pile')$ns['conversion']['pile']=self::text($s['conversion']['pile']??null,'素材牌堆名',24);
                elseif(isset($s['conversion']['pile']))self::fail('非专属牌堆转化不能指定牌堆名');
                if(isset($s['conversion']['count']))$ns['conversion']['count']=self::number($s['conversion']['count'],1,RuleConfig::get('maxAmount'),'素材数量');
                if(isset($s['conversion']['match']))$ns['conversion']['match']=self::choice($s['conversion']['match'],['any','same_suit','same_color'],'素材匹配');
            } else {
                if(isset($s['conversion'])) self::fail('只有转化技能允许转化定义');
                $ns['effects']=self::effects($s['effects']??null,false,$trigger==='passive',$depth);
                if(self::hasEventModifier($ns['effects'])&&!in_array($trigger,['before_deal_damage','before_take_damage','any_before_damage'],true))self::fail('伤害事件修改只能用于伤害发生前的技能');
            }
            if($trigger==='passive'&&($ns['cost']['hand']!==0||$ns['cost']['mind']!==0||$ns['limit']!==0||!empty($ns['optional']))) self::fail('持续技能不支付发动费用、不计次数且不能选择发动');
            $normalized[]=$ns;
        }
        return $normalized;
    }
    public static function validateBuild(array $b): array
    {
        self::keys($b,['id','name','character','deck','budget','createdAt','updatedAt','rulesVersion']);
        if(array_key_exists('rulesVersion',$b)) self::choice($b['rulesVersion'],['0.1.0-alpha','0.2.0-alpha','0.3.0-alpha','0.4.0-alpha',SkillBlocks::VERSION],'规则版本');
        if(!is_array($b['character']??null)) self::fail('缺少人物'); $c=$b['character'];
        self::keys($c,['id','name','title','series','color','hp','art','flipColor','skills']);
        $skills=$c['skills']??[];
        if(!is_array($skills)||count($skills)>RuleConfig::get('maxSkills')||($skills&&array_keys($skills)!==range(0,count($skills)-1))) self::fail('技能列表无效或超出服务器资源上限');
        $normalized=self::normalizeSkills($skills);
        $color=self::choice($c['color']??null,['cool','warm','neutral'],'人物颜色');
        $flip=$c['flipColor']??null;
        if($flip!==null) { self::choice($flip,['cool','warm'],'翻面颜色'); if($flip===$color||$color==='neutral') self::fail('仅允许冷暖之间翻面'); }
        $art=$c['art']??0;
        if(is_string($art)) { if(!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D',$art)) self::fail('插图编号不合法'); }
        else $art=self::number($art,0,3,'插图');
        $nc=['id'=>self::text($c['id']??'custom','人物编号'),'name'=>self::text($c['name']??null,'人物名称'),'title'=>self::text($c['title']??null,'人物称号'),
            'series'=>self::text($c['series']??null,'系列'),'color'=>$color,'hp'=>self::number($c['hp']??null,1,RuleConfig::get('maxHp'),'初始体力'),'art'=>$art,'flipColor'=>$flip,'skills'=>$normalized];
        if(!is_array($b['deck']??null)||count($b['deck'])!==13||array_keys($b['deck'])!==range(0,12)) self::fail('心象必须恰好 13 张，按列表顺序从顶到底');
        $deck=[]; $ranks=[]; $cards=Catalog::cards(); $customCount=0;
        foreach($b['deck'] as $entry) {
            if(!is_array($entry)) self::fail('心象牌结构不正确'); self::keys($entry,['type','rank','custom','maturityTurns']);
            $r=self::number($entry['rank']??null,1,13,'点数'); if(isset($ranks[$r])) self::fail('心象点数 A～K 必须各一张'); $ranks[$r]=true;
            $type=self::choice($entry['type']??null,array_merge(Catalog::mindOptions()[$r],['custom']),'该点数心象牌类型'); $d=['type'=>$type,'rank'=>$r];
            if($type==='custom') {
                $customCount++; $x=$entry['custom']??null; if(!is_array($x)) self::fail('缺少限定牌定义'); self::keys($x,['name','series','fallback','effects','characterId','kind','slot','maturityTurns']);
                $kind=self::choice($x['kind']??'event',['event','equipment','persistent','delayed'],'心象类别');
                $d['custom']=['name'=>self::text($x['name']??null,'限定牌名称'),'series'=>self::text($x['series']??null,'限定系列'),
                    'fallback'=>self::choice($x['fallback']??null,array_keys($cards),'非限定系列替代牌'),'effects'=>self::effects($x['effects']??null,$kind==='equipment')];
                if(self::hasEventModifier($d['custom']['effects']))self::fail('伤害事件修改只能用于伤害发生前的技能');
                if($kind==='equipment') {
                    $meta=SkillBlocks::metadata(); $slot=self::choice($x['slot']??null,array_keys($meta['equipmentSlots']),'装备槽');
                    $ops=[];
                    foreach($d['custom']['effects'] as $e) {
                        if(!in_array($e['op'],$meta['equipmentEffects'][$slot],true)) self::fail('该持续效果不支持这个装备槽');
                        if(isset($ops[$e['op']])) self::fail('同一装备不能重复堆叠相同效果');
                        $ops[$e['op']]=true;
                    }
                    $d['custom']['kind']='equipment'; $d['custom']['slot']=$slot;
                } elseif(isset($x['slot'])) self::fail('只有装备心象可指定装备槽');
                if(in_array($kind,['persistent','delayed'],true)) { $d['custom']['kind']=$kind; $d['custom']['maturityTurns']=self::number($x['maturityTurns']??0,0,RuleConfig::get('maxAmount'),'成熟回合'); }
                elseif(isset($x['maturityTurns'])) self::fail('只有延时基本牌 / 事件可以设置成熟回合');
                if(isset($x['characterId'])) $d['custom']['characterId']=self::text($x['characterId'],'限定角色编号');
            } elseif(isset($entry['custom'])) self::fail('普通牌不能携带自定义效果');
            if(isset($entry['maturityTurns'])) {
                if(!isset($cards[$type])||!in_array($cards[$type]['kind'],['persistent','delayed'],true)) self::fail('这张牌没有成熟回合');
                $d['maturityTurns']=self::number($entry['maturityTurns'],0,RuleConfig::get('maxAmount'),'成熟回合');
            }
            $deck[]=$d;
        }
        $result=['rulesVersion'=>SkillBlocks::VERSION,'name'=>self::text($b['name']??null,'构筑名称'),'character'=>$nc,'deck'=>$deck];
        if(isset($b['id'])) $result['id']=self::text($b['id'],'构筑编号');
        $budget=self::budget($result);
        if($budget['character']>$budget['characterMax']) self::fail('人物预算超限：'.$budget['character'].' / '.$budget['characterMax']);
        if($budget['custom']>$budget['customMax']) self::fail('限定牌总预算超限：'.$budget['custom'].' / '.$budget['customMax']);
        foreach($budget['cards'] as $v) if($v>RuleConfig::get('customCardBudget')) self::fail('单张限定牌预算超过 '.RuleConfig::get('customCardBudget'));
        return $result;
    }
    public static function effectCost(array $e): int
    {
        $cost=SkillBlocks::metadata()['effectMeta'][$e['op']]['cost'];
        if($e['op']==='gain_skill')return $cost+max(0,self::budget(['character'=>['hp'=>4,'flipColor'=>null,'skills'=>[$e['skill']]],'deck'=>[]])['character']);
        $sum=function($list){return array_sum(array_map([self::class,'effectCost'],$list));};
        if($e['op']==='choose') return max(array_map(function($option)use($sum){return $sum($option['effects']);},$e['options']));
        if(in_array($e['op'],['branch','pindian','judge'],true))return $cost+max(0,$sum($e['then'])+(!empty($e['obtain'])?2:0),$sum($e['else']))*(($e['repeat']??false)?RuleConfig::get('unlimitedBudgetWeight'):1);
        if($e['op']==='choose_targets')return $sum($e['effects'])*(is_int($e['amount'])?$e['amount']:3);
        $amount=is_int($e['amount'])?$e['amount']:3;
        if($cost<0&&!is_int($e['amount']))return 0;
        if(in_array($e['op'],['lose_health','lose_max_hp'],true)&&$e['target']==='self') return is_int($e['amount'])?-$cost*$amount:0;
        return $cost*$amount+((in_array($e['op'],['damage','attack'],true)&&($e['color']??'neutral')==='neutral')?$amount:0);
    }
    private static function hasEventModifier(array $effects): bool
    {
        foreach($effects as $e){if(strpos($e['op'],'event_')===0)return true;foreach(['then','else','effects'] as $key)if(isset($e[$key])&&self::hasEventModifier($e[$key]))return true;foreach($e['options']??[] as $option)if(self::hasEventModifier($option['effects']))return true;}return false;
    }
    public static function budget(array $b): array
    {
        $c=$b['character']; $sum=($c['hp']-4)*2+($c['flipColor']!==null?2:0); $cardCosts=[];
        $negative=0;
        foreach($c['skills'] as $s) {
            $v=$s['trigger']==='convert'?(($s['conversion']['from']??'hand')==='hand'?4:3):array_sum(array_map([self::class,'effectCost'],$s['effects']));
            $forced=in_array($s['trigger'],['passive','turn_start','turn_end'],true)&&empty($s['optional'])&&in_array($s['condition'],['always','hp_not_lowest'],true)&&$s['cost']['hand']===0&&$s['cost']['mind']===0;
            if($v>=0) $v+=(!in_array($s['trigger'],['active','convert'],true)?2:0);
            $hand=is_int($s['cost']['hand'])?$s['cost']['hand']:1;
            $v-=$v>0?min(4,$hand+$s['cost']['mind']*2):0;
            $weight=$s['trigger']==='passive'?1:(($s['limit']??0)===0?RuleConfig::get('unlimitedBudgetWeight'):$s['limit']);
            if($v<0&&$forced) $negative+=$v*$weight; else $sum+=max(1,$v)*$weight;
        }
        $sum+=max(RuleConfig::get('negativeBudgetFloor'),$negative);
        foreach($b['deck'] as $d) if($d['type']==='custom') $cardCosts[]=max(0,array_sum(array_map([self::class,'effectCost'],$d['custom']['effects'])));
        $limits=RuleConfig::all();
        return ['used'=>$sum+array_sum($cardCosts),'max'=>$limits['characterBudget']+$limits['customBudget'],'character'=>$sum,'characterMax'=>$limits['characterBudget'],'custom'=>array_sum($cardCosts),'customMax'=>$limits['customBudget'],'cards'=>$cardCosts,
            'warnings'=>['无限制按服务器配置计价并设运行保险上限。强制、无费用的自身负面技能可返还预算；可不发动的纯负面技能不返还预算。心象负面只抵扣同张牌的正面效果。预算不代表强度相等。']];
    }
    public static function describeSkill(array $s,bool $technical=false): string
    {
        $meta=SkillBlocks::metadata();
        $effect=self::describeEffects($s['effects']);
        if($s['trigger']==='convert') {
            $c=$s['conversion'];$zone=['hand'=>'手牌','hand_equipment'=>'手牌或装备','pile'=>'专属牌堆「'.($c['pile']??'').'」'][$c['zone']??'hand'];
            $effect='将'.($c['count']??1).'张'.$meta['conversions']['from'][$c['from']].'（来自'.$zone.(['same_suit'=>'，花色相同','same_color'=>'，红黑颜色相同'][$c['match']??'any']??'').'）作为'.$meta['conversions']['to'][$c['to']].'使用或打出';
        }
        $limit=($s['limit']??0)===0?'不限次数':($meta['limitScopes'][$s['limitScope']??'turn'].'限 '.$s['limit'].' 次');
        $hand=$s['cost']['hand']; $hand=is_int($hand)?$hand:($hand==='all'?'全部':'任意数量');
        if($technical) return $meta['triggers'][$s['trigger']].'，'.self::describePredicate($s['condition']).'；'.$limit.'。弃 '.$hand.' 手牌、'.$s['cost']['mind'].' 心象顶牌；'.$effect.'。';
        $cost=[];
        if($s['cost']['hand']!==0) $cost[]=$hand.($s['cost']['hand']==='all'?'':'张').(($s['cost']['zone']??'hand')==='hand_equipment'?'手牌或装备':'手牌');
        if($s['cost']['mind']>0) $cost[]=$s['cost']['mind'].'张心象顶牌';
        $text=$s['trigger']==='active'?'出牌阶段':($s['trigger']==='convert'?'需要使用或打出对应牌时':$meta['triggers'][$s['trigger']]);
        if($s['condition']!=='always') $text.='，若'.self::describePredicate($s['condition']);
        $text.='，'.(!in_array($s['trigger'],['active','convert'],true)&&empty($s['optional'])?'锁定触发：':'你可以');
        if($cost) $text.='弃置'.implode('和',$cost).'，然后';
        if(in_array($s['trigger'],['active','convert'],true)||!empty($s['optional']))$effect=preg_replace('/^(由你|你)/u','',$effect);
        return $text.$effect.'。'.(($s['limit']??0)===0?'':$limit.'。');
    }
    public static function describeEffects(array $effects): string
    {
        $meta=SkillBlocks::metadata(); $bits=[];
        $phrases=['draw'=>'摸{n}张牌','heal'=>'回复{n}点体力','lose_health'=>'失去{n}点体力','shield'=>'获得{n}点护盾','damage'=>'受到{n}点{color}伤害','attack'=>'受到一次{n}点{color}攻击（可以防御）','give_hand'=>'获得你交给其的{n}张手牌','discard_hand'=>'随机弃置{n}张手牌','steal_hand'=>'被你随机获得{n}张手牌','draw_to'=>'将手牌补至{n}张','recover_mind'=>'将{n}张已耗心象放回心象底','range'=>'本回合攻击范围增加{n}','extra_attacks'=>'本回合可额外使用{n}次攻击','attack_bonus'=>'本回合攻击伤害增加{n}','scry'=>'观看并排列牌堆顶的{n}张牌','double_defense'=>'本回合的攻击需要连续防御两次','hand_lock'=>'本回合不能使用或打出手牌'];
        $phrases=array_merge($phrases,['lose_max_hp'=>'失去{n}点体力上限','gain_max_hp'=>'增加{n}点体力上限','turn_over'=>'翻转行动面；背面角色跳过下个自己的回合','extra_turn'=>'在当前回合后获得一个额外回合','skip_draw'=>'跳过下一次摸牌阶段','skip_play'=>'跳过下一次出牌阶段','skip_discard'=>'跳过下一次弃牌阶段','add_mark'=>'获得{n}个标记','remove_mark'=>'移去{n}个标记','store_pile'=>'选择{n}张手牌放到专属牌堆','take_pile'=>'从专属牌堆选择{n}张牌加入手牌','exchange_hands'=>'与你交换全部手牌','exchange_equipment'=>'与你交换全部装备','duel'=>'与你决斗，首先不能或不愿打出攻击的角色受到另一方造成的{n}点伤害','passive_attacks'=>'每回合可额外使用{n}次攻击','passive_range'=>'攻击范围增加{n}','passive_hand'=>'手牌上限增加{n}','passive_hand_penalty'=>'手牌上限减少{n}','passive_draw'=>'摸牌阶段多摸{n}张牌','passive_draw_penalty'=>'摸牌阶段少摸{n}张牌','passive_guard'=>'每次受到的伤害减少{n}','passive_distance_out'=>'计算与其他角色的攻击距离时减少{n}','passive_distance_in'=>'被其他角色计算攻击距离时增加{n}','passive_no_attack_target'=>'不能成为攻击目标','passive_double_defense'=>'使用的攻击需要额外一次防御']);
        $phrases+=['scry_mind'=>'观看并排列心象顶的{n}张牌','draw_mind'=>'抽取{n}张心象','cycle_mind'=>'将心象顶的{n}张牌移到底部','damage_guard'=>'每次受到的伤害减少{n}','discard_equipment'=>'弃置{n}张装备','sequester_hand'=>'暂置{n}张手牌，在回合结束时归还','inspect_hand'=>'被你私下查看{n}张随机手牌','draw_discard'=>'获得弃牌堆顶的{n}张牌','recall_equipment'=>'将最早的{n}张装备收回手牌','break_shield'=>'失去{n}点护盾','equip_range'=>'攻击范围增加{n}','equip_damage'=>'每个自己的回合周期内，首次结算攻击伤害时额外造成{n}点伤害','equip_shield'=>'在自己的回合开始时获得{n}点护盾','equip_draw'=>'在自己的回合开始时摸{n}张牌','equip_distance'=>'被其他角色计算攻击距离时增加{n}'];
        foreach($effects as $e) {
            if($e['op']==='gain_skill') { $bits[]=($e['target']==='self'?'你':($meta['targets'][$e['target']]??'角色')).'获得「'.$e['skill']['name'].'」：'.self::describeSkill($e['skill']).'（'.self::describeDuration($e['duration']).'，标识：'.$e['key'].'）';continue; }
            if(in_array($e['op'],['lose_skill','seal_skill','copy_skill'],true)) { $bits[]=($meta['targets'][$e['target']]??'角色').($e['op']==='copy_skill'?'的一项有效技能由你选择并获得':($e['op']==='lose_skill'?'失去':'封锁').'标识为「'.$e['key'].'」的技能').(isset($e['duration'])?'（'.self::describeDuration($e['duration']).'）':'');continue; }
            if($e['op']==='choose') { $bits[]='由'.($e['target']==='self'?'你':'目标').'选择：'.implode('；或',array_map(function($o){return $o['label'].'（'.self::describeEffects($o['effects']).'）';},$e['options']));continue; }
            if(in_array($e['op'],['branch','judge','pindian'],true)) {
                $filters=['any'=>'任意牌','red'=>'红色','black'=>'黑色','heart'=>'红桃','not_heart'=>'非红桃','spade'=>'黑桃','club'=>'梅花','diamond'=>'方块','attack'=>'攻击牌','defense'=>'防御牌','basic'=>'基本牌','event'=>'事件牌','equipment'=>'真正装备牌'];
                $start=$e['op']==='branch'?'若'.self::describePredicate($e['condition']):($e['op']==='judge'?'进行判定，若符合'.$filters[$e['filter']].'条件':'与目标拼点，若你赢');
                $bits[]=$start.'，'.(self::describeEffects($e['then'])?:'不执行额外效果').'；否则'.(self::describeEffects($e['else'])?:'不执行额外效果').(!empty($e['obtain'])||!empty($e['repeat'])?'；判定成功时，'.implode('，',array_filter([!empty($e['obtain'])?'获得判定牌':'',!empty($e['repeat'])?'可以继续判定':''])):'');continue;
            }
            if($e['op']==='choose_targets') { $bits[]='选择至多'.$e['amount'].'名角色，分别令其：'.self::describeEffects($e['effects']);continue; }
            if($e['op']==='passive_retrial') { $filters=['any'=>'任意牌','red'=>'红色牌','black'=>'黑色牌','heart'=>'红桃牌','not_heart'=>'非红桃牌','spade'=>'黑桃牌','club'=>'梅花牌','diamond'=>'方块牌','attack'=>'攻击牌','defense'=>'防御牌','basic'=>'基本牌','event'=>'事件牌','equipment'=>'真正装备牌'];$bits[]='任意角色的判定生效前，你可以用'.($e['zone']==='hand_equipment'?'手牌或装备区':'手牌区').'的一张'.$filters[$e['filter']].'替换判定牌'.($e['exchange']?'，并获得原判定牌':'');continue; }
            if($e['op']==='passive_attacks'&&$e['amount']==='all') { $bits[]=($e['target']==='self'?'你':$meta['targets'][$e['target']]).'使用攻击没有次数限制';continue; }
            $n=is_int($e['amount'])?(string)$e['amount']:($e['amount']==='all'?'全部':($meta['amounts'][$e['amount']]??$e['amount']));
            if(is_string($e['amount'])&&strpos($e['amount'],'mark:')===0)$n='「'.substr($e['amount'],5).'」标记数';
            if(is_string($e['amount'])&&strpos($e['amount'],'pile:')===0)$n='「'.substr($e['amount'],5).'」牌堆张数';
            if(strpos($e['op'],'event_')===0) {
                $to=$meta['targets'][$e['target']];
                $bits[]=['event_add_damage'=>'令本次伤害增加'.$n.'点','event_reduce_damage'=>'令本次伤害减少'.$n.'点','event_set_damage'=>'将本次伤害改为'.$n.'点','event_cancel'=>'取消本次伤害','event_redirect'=>'将本次伤害转移给'.$to,'event_source'=>'将本次伤害来源改为'.$to][$e['op']];continue;
            }
            $phrase=$phrases[$e['op']]??($meta['effects'][$e['op']].'（{n}）');
            $dynamic=!is_int($e['amount'])&&$e['amount']!=='all';
            $phrase=strtr($phrase,['{n}'=>$dynamic?'X':$n,'{color}'=>['cool'=>'冷色','warm'=>'暖色','neutral'=>'无色'][$e['color']??'neutral']]);
            $phrase.=($dynamic?'（X为'.$n.'）':'');
            if($e['amount']==='all'&&!in_array($e['op'],['give_hand','discard_hand','steal_hand','recover_mind','store_pile','take_pile','discard_equipment','recall_equipment','sequester_hand'],true))$phrase=str_replace('全部',(string)RuleConfig::get('maxAmount'),$phrase).'（服务器数量上限）';
            $phrase=str_replace('全部张','全部',$phrase);
            if(in_array($e['op'],['range','extra_attacks','attack_bonus','double_defense','damage_guard','shield'],true))$phrase=str_replace('本回合','',$phrase).'（至下个自己的回合开始）';
            $bits[]=($e['target']==='self'?'你':($meta['targets'][$e['target']]??'目标角色')).$phrase.(isset($e['key'])?'「'.$e['key'].'」':'');
        }
        return implode('，',$bits);
    }
    public static function describePredicate($c): string
    {
        if(is_string($c))return SkillBlocks::metadata()['conditions'][$c]??$c;
        foreach(['all','any'] as $join)if(isset($c[$join]))return '（'.implode($join==='all'?'且':'或',array_map([self::class,'describePredicate'],$c[$join])).'）';
        $values=['hp'=>'体力','max_hp'=>'体力上限','lost_hp'=>'已损失体力','hand'=>'手牌数','mind'=>'心象数','mark'=>'标记数','pile'=>'专属牌堆张数','played'=>'本回合用牌数','damage_taken'=>'本回合受伤次数','pindian_win'=>'上次拼点获胜'];
        $values+=['event_amount'=>'本次事件数量','event_original_amount'=>'本次事件初始数量','event_point'=>'当前伤害点数序号'];
        return (isset($c['subject'])?(SkillBlocks::metadata()['targets'][$c['subject']].'的'):'').($values[$c['value']]??$c['value']).(isset($c['key'])?'「'.$c['key'].'」':'').['eq'=>'等于','ne'=>'不等于','lt'=>'小于','le'=>'不超过','gt'=>'大于','ge'=>'至少'][$c['cmp']].$c['amount'];
    }
    private static function describeDuration(string $duration): string
    {
        return ['permanent'=>'持续保留','turn'=>'至当前回合结束','owner_turn'=>'至获得者下个回合开始','source_turn'=>'至发动者下个回合开始'][$duration];
    }
}
