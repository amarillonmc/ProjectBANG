<?php
namespace Imaginary;

use InvalidArgumentException;
require_once __DIR__.'/SkillBlocks.php';

/** Small, total, whitelisted effect language. No executable text or recursion. */
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
    public static function effects($effects): array
    {
        if(!is_array($effects)||count($effects)<1||count($effects)>3||array_keys($effects)!==range(0,count($effects)-1)) self::fail('每组效果必须包含 1～3 个积木');
        $out=[];
        foreach($effects as $e) {
            if(!is_array($e)) self::fail('效果结构不正确');
            self::keys($e,['op','target','amount','color']);
            $meta=SkillBlocks::metadata()['effectMeta'];
            $op=self::choice($e['op']??null,array_keys($meta),'效果积木');
            $target=self::choice($e['target']??'self',['self','target'],'目标');
            if(!in_array($target,$meta[$op]['targets'],true)) self::fail('该积木不支持这个目标');
            $out[]=['op'=>$op,'target'=>$target,'amount'=>self::number($e['amount']??null,$meta[$op]['min'],$meta[$op]['max'],'效果数值'),'color'=>self::choice($e['color']??'neutral',['cool','warm','neutral'],'伤害颜色')];
        }
        return $out;
    }
    public static function validateBuild(array $b): array
    {
        self::keys($b,['id','name','character','deck','budget','createdAt','updatedAt','rulesVersion']);
        if(array_key_exists('rulesVersion',$b)) self::choice($b['rulesVersion'],['0.1.0-alpha',SkillBlocks::VERSION],'规则版本');
        if(!is_array($b['character']??null)) self::fail('缺少人物'); $c=$b['character'];
        self::keys($c,['id','name','title','series','color','hp','art','flipColor','skills']);
        $skills=$c['skills']??[];
        if(!is_array($skills)||count($skills)>2||($skills&&array_keys($skills)!==range(0,count($skills)-1))) self::fail('人物最多拥有两个技能');
        $normalized=[];
        foreach($skills as $s) {
            if(!is_array($s)) self::fail('技能结构不正确');
            self::keys($s,['name','trigger','condition','cost','effects','limit','conversion']);
            $cost=$s['cost']??['hand'=>0,'mind'=>0];
            if(!is_array($cost)) self::fail('技能费用不正确'); self::keys($cost,['hand','mind']);
            $meta=SkillBlocks::metadata(); $trigger=self::choice($s['trigger']??null,array_keys($meta['triggers']),'触发时机');
            $ns=['name'=>self::text($s['name']??null,'技能名称'),'trigger'=>$trigger,
                'condition'=>self::choice($s['condition']??'always',array_keys($meta['conditions']),'条件'),
                'cost'=>['hand'=>self::number($cost['hand']??0,0,2,'手牌费用'),'mind'=>self::number($cost['mind']??0,0,2,'心象费用')],
                'effects'=>[],'limit'=>self::number($s['limit']??1,1,2,'次数上限')];
            if($trigger==='convert') {
                if(isset($s['effects'])&&$s['effects']!==[]) self::fail('转化技能不能同时携带效果积木');
                if(!is_array($s['conversion']??null)) self::fail('缺少转化定义');
                self::keys($s['conversion'],['from','to']);
                $ns['conversion']=['from'=>self::choice($s['conversion']['from']??null,array_keys($meta['conversions']['from']),'转化来源'),'to'=>self::choice($s['conversion']['to']??null,array_keys($meta['conversions']['to']),'转化结果')];
            } else {
                if(isset($s['conversion'])) self::fail('只有转化技能允许转化定义');
                $ns['effects']=self::effects($s['effects']??null);
            }
            $normalized[]=$ns;
        }
        $color=self::choice($c['color']??null,['cool','warm','neutral'],'人物颜色');
        $flip=$c['flipColor']??null;
        if($flip!==null) { self::choice($flip,['cool','warm'],'翻面颜色'); if($flip===$color||$color==='neutral') self::fail('仅允许冷暖之间翻面'); }
        $art=$c['art']??0;
        if(is_string($art)) { if(!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D',$art)) self::fail('插图编号不合法'); }
        else $art=self::number($art,0,3,'插图');
        $nc=['id'=>self::text($c['id']??'custom','人物编号'),'name'=>self::text($c['name']??null,'人物名称'),'title'=>self::text($c['title']??null,'人物称号'),
            'series'=>self::text($c['series']??null,'系列'),'color'=>$color,'hp'=>self::number($c['hp']??null,4,7,'初始体力'),'art'=>$art,'flipColor'=>$flip,'skills'=>$normalized];
        if(!is_array($b['deck']??null)||count($b['deck'])!==13||array_keys($b['deck'])!==range(0,12)) self::fail('心象必须恰好 13 张，按列表顺序从顶到底');
        $deck=[]; $ranks=[]; $cards=Catalog::cards(); $customCount=0;
        foreach($b['deck'] as $entry) {
            if(!is_array($entry)) self::fail('心象牌结构不正确'); self::keys($entry,['type','rank','custom']);
            $r=self::number($entry['rank']??null,1,13,'点数'); if(isset($ranks[$r])) self::fail('心象点数 A～K 必须各一张'); $ranks[$r]=true;
            $type=self::choice($entry['type']??null,array_merge(Catalog::mindOptions()[$r],['custom']),'该点数心象牌类型'); $d=['type'=>$type,'rank'=>$r];
            if($type==='custom') {
                $customCount++; $x=$entry['custom']??null; if(!is_array($x)) self::fail('缺少限定牌定义'); self::keys($x,['name','series','fallback','effects','characterId']);
                $d['custom']=['name'=>self::text($x['name']??null,'限定牌名称'),'series'=>self::text($x['series']??null,'限定系列'),
                    'fallback'=>self::choice($x['fallback']??null,array_keys($cards),'非限定系列替代牌'),'effects'=>self::effects($x['effects']??null)];
                if(isset($x['characterId'])) $d['custom']['characterId']=self::text($x['characterId'],'限定角色编号');
            } elseif(isset($entry['custom'])) self::fail('普通牌不能携带自定义效果');
            $deck[]=$d;
        }
        if($customCount>4) self::fail('内测每副心象最多 4 张限定牌');
        $result=['rulesVersion'=>SkillBlocks::VERSION,'name'=>self::text($b['name']??null,'构筑名称'),'character'=>$nc,'deck'=>$deck];
        if(isset($b['id'])) $result['id']=self::text($b['id'],'构筑编号');
        $budget=self::budget($result);
        if($budget['character']>18) self::fail('人物预算超限：'.$budget['character'].' / 18');
        if($budget['custom']>24) self::fail('限定牌总预算超限：'.$budget['custom'].' / 24');
        foreach($budget['cards'] as $v) if($v>12) self::fail('单张限定牌预算超过 12');
        return $result;
    }
    public static function effectCost(array $e): int
    {
        $cost=SkillBlocks::metadata()['effectMeta'][$e['op']]['cost'];
        return $cost*$e['amount']+((in_array($e['op'],['damage','attack'],true)&&($e['color']??'neutral')==='neutral')?$e['amount']:0);
    }
    public static function budget(array $b): array
    {
        $c=$b['character']; $sum=($c['hp']-4)*2+($c['flipColor']!==null?2:0); $cardCosts=[];
        foreach($c['skills'] as $s) {
            $v=$s['trigger']==='convert'?(($s['conversion']['from']??'hand')==='hand'?4:3):array_sum(array_map([self::class,'effectCost'],$s['effects']));
            $v+=(!in_array($s['trigger'],['active','convert'],true)?2:0);
            $sum+=max(1,$v-min(4,$s['cost']['hand']+$s['cost']['mind']*2))*($s['limit']??1);
        }
        foreach($b['deck'] as $d) if($d['type']==='custom') $cardCosts[]=array_sum(array_map([self::class,'effectCost'],$d['custom']['effects']));
        return ['used'=>$sum+array_sum($cardCosts),'max'=>42,'character'=>$sum,'characterMax'=>18,'custom'=>array_sum($cardCosts),'customMax'=>24,'cards'=>$cardCosts,
            'warnings'=>['预算是内测准入上限，并不证明强度相等。次数按全局回合限制；被动费用从手牌左侧与心象顶支付。转化另需消耗一张符合来源的实体手牌；花色转化只识别有花色的牌。']];
    }
    public static function describeSkill(array $s): string
    {
        $meta=SkillBlocks::metadata();
        $effect=$s['trigger']==='convert'?'将一张'.$meta['conversions']['from'][$s['conversion']['from']].'作为'.$meta['conversions']['to'][$s['conversion']['to']].'使用或响应':self::describeEffects($s['effects']);
        return $meta['triggers'][$s['trigger']].'，'.$meta['conditions'][$s['condition']].'；每个全局回合至多 '.($s['limit']??1).' 次。弃 '.$s['cost']['hand'].' 手牌、'.$s['cost']['mind'].' 心象顶牌；'.$effect.'。';
    }
    public static function describeEffects(array $effects): string
    {
        $meta=SkillBlocks::metadata(); $bits=[];
        foreach($effects as $e) $bits[]=($e['target']==='self'?'自己':'指定/关联角色').$meta['effects'][$e['op']].' '.$e['amount'].(in_array($e['op'],['damage','attack'],true)?'（'.['cool'=>'冷色','warm'=>'暖色','neutral'=>'无色'][$e['color']].'）':'');
        return implode('，',$bits);
    }
}
