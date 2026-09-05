<?php
namespace Imaginary;

/** The ordinary pool and mode rules are server-owned, never part of a submitted build. */
final class Catalog
{
    public static function cards(): array
    {
        $rows = [
            ['attack_neutral','无色攻击','basic',['attack'],'对攻击范围内另一名角色造成 1 点无色伤害。每回合通常限一次攻击。'],
            ['defense','防御','basic',['defense'],'响应攻击时使用，取消攻击。属于「防守」属性。'],
            ['attack_cool','冷色攻击','basic',['attack'],'对范围内非冷色角色造成 1 点冷色伤害；冷色角色可对自己使用以回复 1 点体力。'],
            ['attack_warm','暖色攻击','basic',['attack'],'对范围内非暖色角色造成 1 点暖色伤害；暖色角色可对自己使用以回复 1 点体力。'],
            ['surprise','出其不意','event',[],'弃掉目标心象区以外的一张牌；或额外弃一张手牌，弃掉目标心象顶牌。'],
            ['exchange','你来我往','event',[],'额外弃一张手牌，随机获得目标心象区以外的一张牌；或摸一张牌，随后可将一张手牌置于自己心象底。'],
            ['alliance','攻守同盟','event',[],'目标摸一张牌，然后自己摸三张；回合外可响应事件，来源摸一张并取消对自己的效果。'],
            ['potential','潜能爆发','event',[],'其他角色依次选择：打出攻击、弃心象顶牌、受到来源同色的 1 点伤害、交给来源一张手牌。'],
            ['life','生命之泉','event',[],'所有角色依次回复 1 点体力。'],
            ['mana','法力之泉','event',[],'展示等同于存活人数的普通牌，从自己起依次选一张；或自己摸两张。'],
            ['amplify','潜能增幅','event',[],'弃一张心象顶牌，本回合攻击范围无限且攻击伤害 +1。'],
            ['energy','能量爆发','event',[],'弃一张心象顶牌，其他角色依次打出防守/躲避牌，或受到 1 点无色伤害。'],
            ['recover','精神回复','event',[],'摸等同于已耗心象数量的牌，然后将相同数量手牌置于心象底；心坏时也可按选定顺序重置已耗心象。'],
            ['punch','拳击','persistent',['attack'],'从下个自己的回合起可卸除：对距离 1 的目标使用无色攻击，只能用「躲避」取消。'],
            ['evade','回避','persistent',['defense','evade'],'从下个自己的回合起可卸除：视为防御，然后摸一张牌。属于「躲避」属性。'],
            ['haste','加速','persistent',[],'从下个自己的回合起可卸除摸三张；成熟后防御时可弃心象顶牌判定，点数 >7 则防御成功。'],
            ['miracle','奇迹','persistent',[],'成熟后，自己濒死自动卸除回复 2；其他角色濒死自动卸除回复 1（内测采用座次顺序自动救援）。'],
            ['treasure','宝藏','persistent',[],'成熟后伤害 +1；任何方式卸除时受到 2 点无色伤害。'],
            ['automaton','自律兵器','persistent',[],'从下个自己的回合起可卸除：使用一次无需心象费用的能量爆发。'],
            ['calamity','飞来横祸','delayed',[],'目标下个回合抽牌前从普通/心象顶判定；点数 ≥2 则弃此牌，选择承受 4 无色伤害或跳过回合，否则保留。'],
            ['fortune','意外之喜','delayed',[],'目标下个回合抽牌前从普通/心象顶判定；点数 K 则弃此牌，选择摸三张或回复四点，否则保留。'],
        ];
        $out=[];
        foreach ($rows as $r) $out[$r[0]]=['id'=>$r[0],'name'=>$r[1],'kind'=>$r[2],'tags'=>$r[3],'description'=>$r[4]];
        return $out;
    }

    public static function ordinary(): array
    {
        $grid=[
            ['recover','punch','automaton','evade'],['attack_warm','attack_cool','attack_neutral','attack_neutral'],
            ['attack_warm','attack_cool','exchange','attack_warm'],['attack_warm','attack_cool','defense','defense'],
            ['attack_neutral','defense','defense','defense'],['surprise','defense','defense','attack_warm'],
            ['defense','evade','evade','attack_warm'],['defense','punch','attack_cool','evade'],
            ['defense','attack_neutral','attack_cool','punch'],['exchange','automaton','miracle','surprise'],
            ['alliance','treasure','haste','alliance'],['life','potential','mana','amplify'],
            ['fortune','calamity','surprise','exchange'],
        ];
        $cards=self::cards(); $deck=[]; $suits=['♥','♠','♣','♦'];
        for($copy=0;$copy<2;$copy++) foreach($grid as $i=>$row) foreach($row as $j=>$type) {
            $deck[]=array_merge($cards[$type],['uid'=>'n'.$copy.'_'.$i.'_'.$j,'type'=>$type,'rank'=>$i+1,'suit'=>$suits[$j],'origin'=>'normal']);
        }
        return $deck;
    }
    public static function mindOptions(): array
    {
        return [1=>['attack_cool','recover'],2=>['attack_cool','life'],3=>['attack_warm','mana'],4=>['attack_warm','mana'],
            5=>['evade','haste'],6=>['evade','haste'],7=>['evade','miracle'],8=>['evade','miracle'],9=>['mana','treasure'],
            10=>['life','punch'],11=>['amplify','punch'],12=>['energy','punch'],13=>['recover','punch']];
    }

    private static function skill(string $name,string $trigger,array $effects,int $hand=0,int $mind=0,string $condition='always'): array
    {
        return ['name'=>$name,'trigger'=>$trigger,'condition'=>$condition,'cost'=>['hand'=>$hand,'mind'=>$mind],'effects'=>$effects,'limit'=>1];
    }

    public static function effect(string $op,int $amount=1,string $target='self',string $color='neutral'): array
    {
        return ['op'=>$op,'amount'=>$amount,'target'=>$target,'color'=>$color];
    }

    public static function presets(): array
    {
        $characters=[
            ['id'=>'archivist','name'=>'澄页','title'=>'雨城档案员','series'=>'雨城档案','color'=>'cool','hp'=>6,'art'=>0,'flipColor'=>'warm','skills'=>[
                self::skill('拾遗','after_damage',[self::effect('draw')]),
                self::skill('校订','active',[self::effect('recover_mind')],1),
            ]],
            ['id'=>'wanderer','name'=>'烬途','title'=>'落日信使','series'=>'落日邮局','color'=>'warm','hp'=>6,'art'=>1,'flipColor'=>null,'skills'=>[
                self::skill('余焰','after_attack',[self::effect('damage',1,'target','warm')],0,1),
                self::skill('远行','turn_start',[self::effect('range')]),
            ]],
            ['id'=>'performer','name'=>'无相','title'=>'白夜演者','series'=>'白夜剧场','color'=>'neutral','hp'=>5,'art'=>2,'flipColor'=>null,'skills'=>[
                self::skill('返场','on_defend',[self::effect('draw')]),
                self::skill('换幕','active',[self::effect('draw',2),self::effect('heal')],1,0,'wounded'),
            ]],
            ['id'=>'engineer','name'=>'苔序','title'=>'庭园机巧师','series'=>'庭园工房','color'=>'cool','hp'=>6,'art'=>3,'flipColor'=>null,'skills'=>[
                self::skill('预装甲','turn_start',[self::effect('shield')]),
                self::skill('超频','active',[self::effect('attack_bonus')],1),
            ]],
        ];
        $base=['attack_cool','attack_cool','attack_warm','attack_warm','evade','haste','evade','miracle','mana','life','amplify','energy','recover'];
        $names=['雨城·循忆','落日·追焰','白夜·返场','庭园·预备']; $customNames=['记忆的索引','落日余温','换幕的面具','庭园蓄电池'];
        $effects=[
            [self::effect('draw',2),self::effect('heal'),self::effect('recover_mind')],
            [self::effect('damage',1,'target','warm'),self::effect('draw')],
            [self::effect('draw',2),self::effect('shield')],
            [self::effect('range',2),self::effect('attack_bonus')],
        ];
        $out=[];
        foreach($characters as $i=>$c) {
            $deck=[]; foreach($base as $k=>$t) $deck[]=['type'=>$t,'rank'=>$k+1];
            $deck[4]=['type'=>'custom','rank'=>5,'custom'=>['name'=>$customNames[$i],'series'=>$c['series'],'fallback'=>'recover','effects'=>$effects[$i]]];
            $out[]=['id'=>'preset_'.$c['id'],'name'=>$names[$i],'character'=>$c,'deck'=>$deck];
        }
        return $out;
    }

    public static function all(): array
    {
        $p=self::presets();
        return ['rulesVersion'=>'0.1.0-alpha','cards'=>self::cards(),'mindOptions'=>self::mindOptions(),'characters'=>array_column($p,'character'),'presets'=>$p,
            'blocks'=>[
                'triggers'=>['active'=>'出牌阶段主动','turn_start'=>'自己的回合开始','after_damage'=>'受到伤害后','after_attack'=>'攻击造成伤害后','on_defend'=>'防御成功后'],
                'conditions'=>['always'=>'总是','wounded'=>'体力受损','hand_low'=>'手牌不超过 2 张'],
                'effects'=>['draw'=>'摸普通牌','heal'=>'回复体力','damage'=>'造成伤害','recover_mind'=>'回收已耗心象到底部','shield'=>'本轮护盾','range'=>'本回合攻击范围增加','attack_bonus'=>'本回合攻击伤害增加'],
                'targets'=>['self'=>'自己','target'=>'指定角色 / 触发关联角色'],
            ],
            'limits'=>['skills'=>2,'effects'=>3,'customCards'=>4,'characterBudget'=>18,'customBudget'=>24,'customCardBudget'=>12],
            'modes'=>['color'=>'冷暖对抗','series'=>'系列对抗'],
        ];
    }
}
