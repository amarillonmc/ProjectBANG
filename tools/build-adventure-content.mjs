// Reviewed literary identities and original, budgeted game designs. No network at runtime.
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const version='0.4.0-alpha', series='冒险王';
const E=(op,amount=1,target='self',color='neutral')=>({op,amount,target,color});
const T=(op,amount=1,color='neutral')=>E(op,amount,'target',color);
const S=(name,trigger,effects,hand=0,mind=0,condition='always')=>({name,trigger,condition,cost:{hand,mind},effects,limit:1});
const gear=(name,slot,effects,reason,bound=false)=>({name,kind:'equipment',slot,effects,reason,bound});
const event=(name,effects,reason,bound=false)=>({name,effects,reason,bound});
const wiki=title=>'https://zh.wikipedia.org/wiki/'+encodeURIComponent(title);
const sources={
 wesley:wiki('衛斯理系列角色列表'), wen:wiki('溫寶裕'), hu:wiki('瘟神_(小說)'),
 yuan:wiki('原振俠系列'), ma:'https://www.newton.com.tw/wiki/%E7%91%AA%E4%BB%99/3325027',
 kars:wiki('迷路_(小说)'), youth:wiki('手套_(小說)'), princess:'https://www.wiselypuzzle.com/introduction/youth.html',
 eagle:'https://www.wiselypuzzle.com/introduction/eagle.html',
 diana:'https://www.newton.com.tw/wiki/%E9%BB%9B%E5%A8%9C/10348',
 conway:'https://www.xuoda.com/kh/nk/yzx27/index.htm',
 angel:'https://abyss101.blogspot.com/2016/04/asian-eagle-1.html', clock:wiki('鬼鐘'),
 gao:'https://www.wiselypuzzle.com/introduction/kotat.html',
 katherine:'https://www.hetubook.com/book2/3211/91544.html',
 magnolia:wiki('女黑俠木蘭花系列'), annie:'https://www.kingstone.com.tw/basic/freeread/2018563344972',
 yun:'https://m.gulongbbs.com/wuxia/nikuang/halx/10854.html'
};
const companion={
 path:[S('探路','turn_start',[E('range')]),'提前取得一点范围，仍需要实际攻击牌和正常攻击额度。'],
 guard:[S('守望','ally_targeted',[T('shield')]),'只保护距离一内受攻的同伴，每个全局回合一次，不能覆盖全队。'],
 engineer:[S('整备','after_equip',[E('shield'),E('draw')]),'装备投入后补回一张手牌与一点护盾；所有装备共用技能次数。'],
 promise:[S('守约','turn_end',[E('shield')]),'结束行动后留一道薄防线，护盾在下次自己的回合开始清空。'],
 clue:[S('追索','after_play_event',[E('scry',2)]),'事件后整理两张公共牌顶，只改变未来顺序而不额外摸牌。'],
 records:[S('余证','after_damage',[E('draw')]),'只在实际受到伤害后补一张，完全被防护抵消时不触发。'],
 spark:[S('灵光','after_play_card',[E('draw')],0,0,'first_card'),'每回合第一张主动手牌后补牌；响应与虚拟攻击不累计。'],
 pursue:[S('追踪','after_attack',[E('cycle_mind'),E('draw')]),'命中后调整自身路线，不能通过被挡住的攻击获得收益。'],
 observe:[S('复核','turn_end',[E('scry_mind',2)]),'回合末整理两张心象，不增加资源，奖励提前规划。'],
 composure:[S('定心','on_defend',[E('shield'),E('recover_mind')]),'完整防御成功才回收一张已耗心象，仍须先交出有效防御。'],
 resolve:[S('应变','on_defend',[E('draw')]),'成功防御后补一张普通牌，保留防御牌本身的消耗。'],
 salvage:[S('留用','after_lose_equipment',[E('draw'),E('shield')]),'失去装备后保留零件与防线；同槽换装也触发，但一回合仅一次。']
};
// name / branch / title / color / HP / source / identity / unique primary / secondary / rationale / two cards
const rows=[
 ['卫斯理','卫斯理系列','向不可思议追问','cool',6,'wesley','跨越怪异事件的冒险者，武艺与好奇心并重。',
 S('问证','active',[T('inspect_hand',2),E('scry',2)],1),'path','调查只获得信息，整理牌顶才把线索变成计划；弃一张手牌限制免费窥探。',[
 gear('白金丝软鞭','weapon',[E('equip_range',2)],'取材于卫斯理的随身软鞭，以持续射程表现应变，不叠加无限攻击。'),
 event('天外金球的回声',[E('scry',3),E('draw_mind'),E('shield')],'外来启示让人提前抽取自己的心象，付出储备变薄的代价。')]],
 ['白素','卫斯理系列','细处见真章','cool',6,'wesley','沉着、善推理，是卫斯理的伴侣与冒险搭档。',
 S('抽丝','turn_start',[E('scry_mind',3)]),'guard','先整理心象再决定本回合摸牌来源；守望体现她在冒险中的可靠照应。',[
 gear('静心护符','armor',[E('equip_shield')],'原创防具意象：稳健的守护在自己的回合开始兑现，入场不立刻获盾。'),
 event('等你归来',[T('heal',2),E('recover_mind'),T('shield')],'漫长守候化为治疗、记忆回收和短时保护；不复活出局角色。')]],
 ['白奇伟','卫斯理系列','把险境架成通途','warm',6,'wesley','白素的兄长，擅长工程技术，也参与冒险。',
 S('架桥','active',[E('range',2),E('draw')],1),'engineer','工程师用准备换通路，架桥本身没有攻击，整备鼓励实际安装器材。',[
 gear('便携测距仪','gadget',[E('equip_range',2)],'以测距器材表现工程规划；失去装置立即失去范围。'),
 event('跨越断层',[E('range',2),T('draw',2),E('shield')],'给同伴补给并拓展自己的射程，不改变座次与邻近支援距离。')]],
 ['白老大','卫斯理系列','一诺仍镇江湖','warm',7,'wesley','青帮前辈，以江湖阅历和人脉提供帮助。',
 S('公议','active',[T('discard_equipment'),T('draw')],1),'promise','卸下对方装备却补偿一张手牌，表现有规则的仲裁，避免纯收益强拆。',[
 event('旧约重提',[T('give_hand'),T('damage_guard'),E('draw',2)],'必须送出真实手牌才能换取双方整备，江湖关系有成本。'),
 event('故人来访',[E('draw'),T('draw'),E('recover_mind')],'人脉提供双向资源，旧事只回收自己的已耗心象。')]],
 ['小郭','卫斯理系列','把蛛丝连成证据','cool',6,'wesley','郭则清，后来成为知名私家侦探。',
 S('循迹','active',[T('inspect_hand',2),E('draw')],1),'clue','用一张手牌换调查与新线索；调查随机抽查，不允许点名偷看整手牌。',[
 gear('袖珍照相机','gadget',[E('equip_draw')],'侦探的长期记录提供每轮一张资源，必须先安装并撑到自己的回合。'),
 event('封存的委托',[T('inspect_hand',2),T('sequester_hand'),E('draw')],'先查看再短暂限制随机手牌，两次随机独立，不保证封存看见的牌。')]],
 ['陈长青','卫斯理系列','凡俗之外还有什么','neutral',5,'wesley','醉心奇异知识与求仙之事的卫斯理友人。',
 S('求异','active',[E('lose_health'),E('scry',3),E('recover_mind')]),'records','求索以失去体力为代价，既不能被盾抵消，也不能靠余证从自损中额外摸牌。',[
 event('未归的仙途',[E('lose_health'),E('draw_mind',2),E('recover_mind')],'抽取与回收构成资源搬运，不能复制或无限生成心象。'),
 event('书斋里的异物',[E('scry',3),E('draw_discard',2),E('shield')],'从现有普通弃牌取样，弃牌不足时取尽，不凭空发牌。')]],
 ['温宝裕','卫斯理系列','先让想象越界','warm',6,'wen','思路跳脱、好奇心旺盛的青年，小名小宝。',
 S('大胆假设','active',[E('cycle_mind',2),E('draw_mind')]),'spark','先跳过两段记忆再抽取新的心象；不增加总牌数，贸然抽空会心坏。',[
 event('这一定不是巧合',[E('cycle_mind',3),E('draw_mind'),E('draw')],'大胆换路线会加快消耗心象储备，不能随意检索整副牌。'),
 event('第十三种解释',[E('scry',3),E('draw',2),E('range')],'想象力提供牌序与行动范围，仍遵守三积木及预算上限。')]],
 ['卫红绫','卫斯理系列','山野与星空的女儿','warm',6,'wesley','卫斯理与白素的女儿，在山野成长，力量过人。',
 S('山野直觉','on_targeted',[E('shield'),E('range')]),'pursue','被攻击时先建立薄盾，命中后的追踪才补资源，体现直觉与行动力。',[
 gear('山行护具','armor',[E('equip_shield',2)],'以适应野外的韧性设计防具，每轮两盾而非每次伤害减二。'),
 event('越过树梢',[E('range',2),T('attack',1,'warm'),E('cycle_mind',2)],'越距之后只产生一次可防御攻击，随后调整自己的行进路线。')]],
 ['胡说','卫斯理系列','从微小处实证','cool',6,'hu','务实的青年昆虫学家；名字的“说”读作“悦”。',
 S('虫谱','active',[T('inspect_hand'),E('draw_discard')]),'observe','观察一张线索并回收现有样本，不把科学研究写成无条件大范围控制。',[
 gear('标本匣','gadget',[E('equip_draw')],'长期采集转为每轮一张普通牌，不直接夺取调查到的手牌。'),
 event('怪蛹的答案',[E('scry_mind',3),E('draw_mind'),E('damage_guard')],'先确认自己的样本序列，再抽取一张并获得临时减伤。')]],
 ['原振侠','原振侠系列','星海尽头仍是医者','cool',6,'yuan','医生与冒险者，面对异事也常受情感困扰。',
 S('仁术','active',[T('heal',2),E('draw')],1),'guard','医疗有手牌成本，治疗不会抹去无色伤害造成的体力上限损失。',[
 gear('随行诊疗箱','armor',[E('equip_shield')],'急救准备表现为每轮护盾，与真正治疗分开结算。'),
 event('宇宙中的求援',[T('heal',2),T('shield',2),E('recover_mind')],'一次明确的救护组合，心坏者仍不能被治疗，不能救回已经出局者。')]],
 ['黄绢','原振侠系列','野心写在军图上','warm',6,'yuan','投身权力角逐并成为女将军的重要人物。',
 S('军图','active',[T('sequester_hand'),E('range',2)],1),'engineer','借短暂调度打开进攻距离，不凭空获得攻击或永久封锁对手。',[
 gear('远征指挥镜','weapon',[E('equip_range'),E('equip_damage')],'精准火力的伤害加成只用于本周期第一次结算的命中攻击。'),
 event('战线重划',[T('break_shield',2),T('attack',1,'warm'),E('draw')],'削盾后进行有色可防御攻击，不能绕过颜色与攻击距离。')]],
 ['海棠','原振侠系列','身份之外的自由','cool',5,'yuan','女特工，后来以玫瑰身份出现，追求脱离控制。',
 S('脱壳','active',[E('lose_health'),E('recall_equipment'),E('shield',2)],0,1),'records','收回旧装备建立临时掩护，但必须承担体力与心象代价；海棠和玫瑰不重复算成两人。',[
 gear('匿名披风','armor',[E('equip_distance')],'伪装表现为别人攻击她的距离加一，对事件与邻近救援没有影响。'),
 event('玫瑰的新身份',[E('cycle_mind',3),E('recover_mind'),E('draw')],'换身份用记忆路线变化表现，不更换阵营，也不清除既有伤害。')]],
 ['玛仙','原振侠系列','以心念回应群星','neutral',5,'ma','拥有强大脑能量、与爱神星相关的超级女巫。',
 S('心念','active',[T('inspect_hand',2),E('scry_mind',2),E('shield')],0,1),'records','心念先调查再安排自己的记忆；强信息能力支付一张心象而非无成本读全场。',[
 gear('心念晶环','gadget',[E('equip_draw'),E('equip_range')],'原创精神接口意象，稳定资源来自下一轮，无法入场即刷牌。'),
 event('爱神星的呼唤',[E('scry_mind',3),E('draw_mind'),T('heal',2)],'宇宙联系转为心象选择与援助，不赋予无限复生。',true)]],
 ['卡尔斯','原振侠系列','把意志铸成铁令','warm',6,'kars','与黄绢关联的将军和统治者，权势欲强烈。',
 S('铁令','active',[T('break_shield',2),T('attack',1,'warm')],1),'engineer','先削盾再发动一次攻击，对没有护盾者不会多造成伤害。',[
 gear('重型指挥甲','armor',[E('equip_shield',2)],'重装用每轮两盾表示，拆甲后未来回合不再供应护盾。'),
 event('最后通牒',[T('sequester_hand',2),T('attack',1,'warm')],'压迫限于两张随机牌与一击，暂置牌在全局回合结束归还。')]],
 ['年轻人','年轻人系列','不可能的锁也有解','warm',6,'youth','由叔叔训练的冒险者，与公主共同经历奇遇。',
 S('巧取','active',[E('recall_equipment'),E('draw',2)],1),'path','回收一件器材重新规划，必须真有装备；不无中生有套取补牌。',[
 gear('玲珑手套','gadget',[E('equip_range'),E('equip_draw')],'手套是偷盗故事的设计意象，既给路线也给长期准备，不复制小说机关。'),
 event('保险箱外的答案',[T('inspect_hand',2),T('discard_equipment'),E('draw')],'了解线索与解除器械分别结算，不能直接挑走暗手牌。')]],
 ['黑纱公主','年轻人系列','跨过空间仍相认','cool',5,'princess','奥丽卡公主在跨系列经历后改名奥丽卡·黑纱。',
 S('穿界','active',[E('range',2),T('attack',1,'cool'),E('cycle_mind')],0,1),'composure','空间能力被约束成临时距离和一次攻击，回收记忆仍以成功防御为前提。',[
 gear('黑纱之衣','armor',[E('equip_distance'),E('equip_shield')],'薄纱造成攻击距离干扰并每轮给一盾，不使角色无法被指定。'),
 event('灵魂仍记得你',[E('recover_mind',2),T('shield'),E('cycle_mind',2)],'灵魂主题回收两张已耗心象并重排路线，不倒退整局时间。',true)]],
 ['中国人','年轻人系列','老练胜过奇迹','cool',6,'youth','年轻人的叔叔与导师，经验丰富的盗术高手。',
 S('锁艺','active',[T('discard_equipment'),E('draw')],1),'observe','熟练手艺对应拆解装备，事后复核心象体现经验，不增加攻击火力。',[
 event('老手的忠告',[E('scry_mind',3),T('draw',2),E('shield')],'为下一次行动先排顺序，再把资源交给同伴。'),
 event('不必打开的锁',[E('recall_equipment'),E('scry',3),E('draw')],'先回收自己的工具再整理线索，不允许越权收走他人装备。')]],
 ['罗开','亚洲之鹰罗开系列','自由高于命令','warm',7,'eagle','被称为亚洲之鹰的冒险者，以独立意志对抗操控。',
 S('鹰击','after_play_attack',[E('range'),T('break_shield')]),'path','只有实体攻击使用后才能削盾，增加的范围只帮助后续攻击，不能追溯修正本次目标。',[
 gear('高原行装','weapon',[E('equip_range',2),E('equip_damage')],'每周期一击获得增伤，高原意象不意味着无视所有距离。'),
 event('不向鬼钟低头',[E('scry_mind',3),E('recover_mind'),E('shield',2)],'集中意志用整理、回收与护盾表现，不取消对手技能或改变回合。')]],
 ['黛娜','亚洲之鹰罗开系列','烈性炸药的判断','cool',5,'diana','代号“烈性炸药”的情报与安全人员。',
 S('破局','active',[T('hand_lock'),E('draw')],1,1),'clue','强控制必须同时付出手牌和心象，只持续当前全局回合，装备响应仍可使用。',[
 gear('侦防干扰器','armor',[E('equip_distance')],'安全技术转为一道攻击距离门槛，范围装备可以反制。'),
 event('撤离倒计时',[T('sequester_hand',2),E('range',2),E('shield')],'两张暂置牌会归还，撤离不允许跳过整回合或改变座次。')]],
 ['康维十七世','亚洲之鹰罗开系列','机械之中有人心','cool',6,'conway','生活在地球的高等外星机械人，协助众人探索宇宙。',
 S('自检','active',[E('recall_equipment'),E('draw')]),'engineer','收回再装是可复用的技术循环，但主动和入场技能各限一次，装备本身不提供入场补牌。',[
 gear('三晶接口','gadget',[E('equip_draw'),E('equip_range')],'把外星技术压缩成有限射程与每轮一牌；失去装置即停止供应。'),
 event('驶向观察地带',[E('scry',3),E('draw_mind'),T('shield',2)],'远行增加决策和有限防护，心象抽取仍会消耗自己的储备。',true)]],
 ['天使','亚洲之鹰罗开系列','雾中画像的来客','neutral',5,'angel','与《魔像》及齐亚尔星相关的神秘来客。',
 S('雾像','on_targeted',[E('cycle_mind'),E('shield')]),'guard','魔像主题表现为路线偏移与保护，而非直接控制对方意志。',[
 event('画像背后的星球',[E('scry',3),T('inspect_hand',2),E('draw')],'先整理普通牌顶，再调查目标，两个私密窗口依序结束。'),
 event('浓雾彼端',[T('sequester_hand'),E('scry_mind',3),E('shield')],'雾只遮住一张随机手牌一个回合，仍留下明确反制窗口。')]],
 ['时间大神','亚洲之鹰罗开系列','借钟声窥伺人心','neutral',5,'clock','鬼钟与妖偶故事中施加控制的异质存在。',
 S('停摆','active',[T('sequester_hand',2),E('scry_mind')],1,1),'observe','强大的时间题材落在短时暂置上；绝不额外回合、永久封手或操控玩家。',[
 gear('鬼钟残响','gadget',[E('equip_draw')],'异质时钟仅在自己的回合开始提供一张资源，避免时间题材无限连动。'),
 event('妖偶的空白记忆',[T('inspect_hand',3),T('sequester_hand'),E('cycle_mind',2)],'调查和暂置是独立随机；不复制对方人物、技能或实体牌。',true)]],
 ['高达','浪子高达系列','筹码总在旅途中','warm',7,'gao','潇洒冒险、追逐财富的浪子，也与罗开相交。',
 S('浪迹','active',[T('steal_hand'),E('cycle_mind')],1),'path','弃一换一的随机夺取配合换路，不能无成本净赚手牌。',[
 event('邮轮上的白金谜',[T('steal_hand'),E('scry',2),E('draw')],'资源转移保留心象原主人，对不匹配的专属牌仍按替代效果使用。'),
 event('浪子的退路',[E('recall_equipment'),E('range',2),E('shield',2)],'先撤回最早的器械再脱离险境；必须有装备，不能空发取盾。')]],
 ['凯德琳','浪子高达系列','契约背后的流亡者','cool',5,'katherine','《红粉妙贼》中牵涉契约与险局的公主。',
 S('密约','active',[T('give_hand'),E('draw',3)]),'promise','送出一张实体手牌才换来三张新牌；对手也可以成为契约对象。',[
 event('未签完的契约',[T('give_hand'),T('draw'),E('draw',2)],'给与受形成明确交换，不以魅惑替代对方的玩家选择。'),
 event('带离金色牢笼',[T('shield',2),E('range',2),E('recover_mind')],'援助、距离和记忆组成脱困，不能清空敌方整个装备区。')]],
 ['木兰花','女黑侠木兰花系列','黑衣之下的精密推理','cool',6,'magnolia','以智勇破解奇案的女侠，与伙伴对抗犯罪组织。',
 S('夜访','active',[T('inspect_hand'),T('discard_equipment'),E('draw')],1),'clue','调查和拆械各一，先看线索再处理公开装备；不给无条件命中伤害。',[
 gear('死光表','weapon',[E('equip_range'),E('equip_damage')],'取材于《巧夺死光表》，改为周期一次加伤，避免一击必杀。'),
 event('黑夜里的证词',[T('inspect_hand',2),T('discard_equipment'),E('shield')],'先调查后拆械并获得一点掩护，完整允许原有事件反制。',true)]],
 ['穆秀珍','女黑侠木兰花系列','引擎声中的行动派','warm',6,'magnolia','木兰花的勇敢伙伴，行事爽快、富有行动力。',
 S('试飞','after_equip',[E('range'),E('extra_attacks')]),'resolve','装好器械后获得一回合的进攻机会，仍要拿出实际攻击手牌。',[
 gear('飞行员护具','armor',[E('equip_shield')],'临险经验以每轮护盾表达，不把飞行等同永久闪避。'),
 event('现在就出发',[E('range',2),E('extra_attacks'),E('draw',2)],'提供额度和手牌，不直接造成伤害，也不免除普通攻击的距离规则。')]],
 ['高翔','女黑侠木兰花系列','守在行动的另一端','warm',6,'magnolia','警方人员，也是木兰花的可靠伙伴。',
 S('警戒','ally_targeted',[T('shield'),E('draw')]),'resolve','必须与同阵营受攻者邻近，体现协作位置；不能保护任意远处全队。',[
 gear('警用防护衣','armor',[E('equip_shield',2)],'防护是有限护盾，来源改变不改写伤害颜色。'),
 event('封锁线的缺口',[T('sequester_hand'),T('break_shield',2),E('draw')],'警戒封锁只限制一部分资源，不会让对手永久丧失行动。')]],
 ['安妮','女黑侠木兰花系列','轮上亦能追风','cool',6,'annie','使用轮椅、参与破解险案的“天使侠女”。',
 S('机动','active',[E('recall_equipment'),E('range',2)]),'engineer','以自制器械与机动性参与战斗，强调主动解决问题；必须先装设备。',[
 gear('机巧轮椅','gadget',[E('equip_range',2)],'机巧载具提供持续范围，角色不因轮椅被扣除行动能力。'),
 event('掌中的遥控器',[T('discard_equipment'),E('scry',2),T('shield')],'同一目标先拆装再保护，可用于帮助伙伴卸掉宝藏，也可拆敌方装备；副作用不免除。',true)]],
 ['云四风','女黑侠木兰花系列','把图纸变成奇器','cool',5,'yun','云家兄弟之一，与穆秀珍相伴，擅长技术与器械。',
 S('改装','active',[E('discard_equipment'),E('draw',3)],1),'salvage','弃掉现有装备与一张手牌才大幅补给；留用能减少损失，但每回合一次。',[
 gear('多用途工具架','gadget',[E('equip_draw')],'器械师的生产能力是每轮一张，入场、拆除和重新安装不重复生产。'),
 event('试作零号机',[E('recall_equipment'),E('shield',2),E('draw',2)],'以真实收回装备为前提换取临时资源，宝藏仍承受卸除副作用。')]],
 ['云五风','女黑侠木兰花系列','下一程由我接应','warm',6,'yun','云四风的弟弟，参与木兰花一行的冒险。',
 S('接应','active',[T('give_hand'),T('shield',2),E('draw')]),'path','把真实资源交给伙伴并提供防护，自己只补回一张，强调合作而非白赚。',[
 event('双引擎协同',[E('range',2),T('draw',2),T('shield')],'路线和补给围绕一个伙伴，牌名不赋予额外两个目标。'),
 event('留给伙伴的钥匙',[T('give_hand'),T('damage_guard'),E('scry_mind',3)],'交付钥匙需要一张实体手牌，御伤与自己的调序有各自时限。')]]
];
const costs={draw:2,heal:2,damage:4,recover_mind:3,shield:2,range:1,attack_bonus:3,steal_hand:3,discard_hand:2,give_hand:1,draw_to:2,extra_attacks:3,lose_health:2,attack:3,scry:1,draw_discard:2,double_defense:4,sequester_hand:3,discard_equipment:3,damage_guard:4,hand_lock:6,inspect_hand:2,scry_mind:1,cycle_mind:1,draw_mind:3,recall_equipment:3,break_shield:1,equip_range:2,equip_damage:6,equip_shield:4,equip_draw:7,equip_distance:4};
const cost=e=>costs[e.op]*e.amount+(['damage','attack'].includes(e.op)&&e.color==='neutral'?e.amount:0);
const skillCost=s=>Math.max(1,s.effects.reduce((n,e)=>n+cost(e),0)+(s.trigger==='active'?0:2)-Math.min(4,s.cost.hand+2*s.cost.mind));
const base=['attack_cool','attack_cool','attack_warm','attack_warm','evade','haste','evade','miracle','mana','life','amplify','energy','recover'];
const pack={version,contentPacks:[],presets:[],skillTemplates:[],mindTemplates:[],arts:[],characterNotes:{}};
const research={checkedAt:'2026-09-28',scope:'倪匡六个小说系列；排除影视新增设定与有争议的高达增补人物。摘要仅概括身份；技能、配色、服装和大多数器材为本游戏原创设计。',sources,records:[]};
const addSkill=(id,skill,reason,tags)=>pack.skillTemplates.push({id,name:skill.name,game:'原创设计',expansion:'冒险王 · 原创',reference:reason,designReason:reason,description:reason,tags,skill});
for(const [key,[skill,reason]] of Object.entries(companion)) addSkill('av_shared_'+key,skill,reason,['冒险王','通用搭档',skill.trigger,...skill.effects.map(e=>e.op)]);
rows.forEach(([name,branch,title,color,hp,sourceKey,summary,primary,secondary,reason,cards],i)=>{
 const id='av_'+String(i+1).padStart(4,'0');
 const secondaryId='av_shared_'+secondary,primaryId=id+'_skill',sourceUrl=sources[sourceKey];
 const skills=[primary,structuredClone(companion[secondary][0])];
 const character={id,name,title,series,color,hp,art:id,flipColor:null,skills};
 const budget=(hp-4)*2+skills.reduce((n,s)=>n+skillCost(s),0);
 if(budget>18)throw Error(name+' character budget '+budget);
 addSkill(primaryId,primary,reason,[name,branch,...primary.effects.map(e=>e.op)]);
 const deck=base.map((type,n)=>({rank:n+1,type}));
 const mindIds=[];
 cards.forEach((design,j)=>{
  const {reason:designReason,bound,...body}=design;
  const card={...body,series,fallback:j?'mana':'recover',...(bound?{characterId:id}:{})};
  const budget=card.effects.reduce((n,e)=>n+cost(e),0); if(budget>12)throw Error(name+'/'+card.name+' card budget '+budget);
  const cardId=id+'_mind_'+(j+1); mindIds.push(cardId);
  pack.mindTemplates.push({id:cardId,name:card.name,series,expansion:'冒险王 · 原创',reference:designReason,designReason,description:name+'主题心象。'+(bound?'额外绑定此角色。':'同 IP 共享。')+designReason,tags:[name,branch,card.kind==='equipment'?'装备心象':'事件心象',...card.effects.map(e=>e.op)],card});
  // Equipment is accessible early; event sits later. Ranks remain A–K exactly once.
  deck[j?8:4]={rank:j?9:5,type:'custom',custom:structuredClone(card)};
 });
 pack.presets.push({id:'preset_'+id,rulesVersion:version,name:series+'·'+name,character,deck});
 pack.arts.push({id,name,url:`assets/characters/${id}.png`});
 pack.characterNotes[id]={summary:branch+' · '+summary,reason,designReason:reason,sourceUrl,sourceUrls:[sourceUrl],branch,skillReferences:[reason,companion[secondary][1]],cardReferences:cards.map(c=>c.reason),skillTemplateIds:[primaryId,secondaryId],mindTemplateIds:mindIds,researchTags:[branch],sourcePath:'docs/content/adventure-research.json#'+id};
 research.records.push({id,name,branch,summary,sourceUrl,visualPolicy:'形象为基于人物身份的原创诠释；未核实的外貌、服装和配色不宣称为原著定貌。'});
});
pack.contentPacks.push({id:'adventure-king-01',name:'冒险王 · 异闻与奇器',description:`六个倪匡小说系列共 ${rows.length} 位角色、60 张原创心象（${pack.mindTemplates.filter(t=>t.card.kind==='equipment').length} 张真正装备）、${pack.skillTemplates.length} 组可编辑技能拼图。调查、心象调序与三个装备槽向所有创作者开放；系列阵营统一为“冒险王”。`,characterIds:pack.presets.map(p=>p.character.id),sources:[...new Set(Object.values(sources))]});
const write=(file,data)=>fs.writeFileSync(path.join(root,file),typeof data==='string'?data:JSON.stringify(data,null,2)+'\n');
write('src/content/adventure-king.json',pack); write('docs/content/adventure-research.json',research);
write('docs/content/ADVENTURE_KING.md',[
 '# 冒险王 · 异闻与奇器','',
 pack.contentPacks[0].description,'',
 '## 选角与研究边界','',
 '资料查阅于 2026-09-28，以小说身份为准。海棠与玫瑰合并为一个角色；黑纱公主采用跨系列经历后的身份，不把幽灵使者黑纱与她混为同一个原生人物。高达只选高达、凯德琳，不采用有争议增补本中的杜雪、克鲁斯。六分支是检索标签，游戏 IP 与系列阵营均为“冒险王”。','',
 '所有技能都是本项目设计，不冒称原作已有卡牌技能。卡面中服装、配色、器材造型是创作诠释；技能中的非原著物品同样是主题设计。人设来源和设计理由分开记录。','',
 '| 角色 | 小说分支 | 技能 | 设计理由 | 人设资料 |','| --- | --- | --- | --- | --- |',
 ...pack.presets.map(p=>{const n=pack.characterNotes[p.character.id];return `| ${p.character.name} | ${n.branch} | ${p.character.skills.map(s=>s.name).join(' / ')} | ${n.reason} | [来源](${n.sourceUrl}) |`;}),'',
 '## 装备规则与平衡界限','',
 '- 武器、防具、装置各一槽，与现有延时基本牌共用装备区域。同槽使用新装备会将旧件送回原心象主人的已耗区并触发失去装备。',
 '- 武器提供范围与每个自己回合周期首次结算攻击时的一点增伤；被护盾或御伤全抵消也消耗增伤次数，防御取消攻击则不消耗。拆装不重置次数。',
 '- 防具每个自己的回合开始产生 1～2 点护盾，或让他人攻击自己的距离 +1。距离修正不改变座次、邻近支援或事件目标。',
 '- 装置可增加范围或每个自己的回合开始摸 1 张普通牌。装备入场不会触发周期护盾、补牌；回合开始先清除旧护盾，再获得装备护盾，再触发人物技能。',
 '- 范围、攻击距离与下一次增伤离场即停止；已经获得的手牌、护盾不因拆除追溯扣除。心坏仍把攻击范围固定为 0；真实装备的非角色能力继续有效。',
 '- 系列或可选角色绑定不符时，一律按 fallback 使用；装备牌被偷后也不能越过限制。收回装备保留实体牌与原归属，宝藏的卸除伤害照常生效。',
 '- 每项技能每个全局回合 1 次，人物预算 ≤18，单张限定牌 ≤12，两张合计 ≤24。三槽和槽位效果白名单同样约束自创者。预算仅是准入边界，实战平衡仍需持续采样。','',
 '## 新的创作积木','',
 '私密调查、心象顶调序、心象顶置底、心象抽取、收回装备、削盾均可放进角色技能和事件心象。持续范围、周期增伤、周期护盾、周期摸牌、受攻距离五种装备积木仅能放进相应槽位的装备心象。工坊可直接选择事件/装备与槽位，不需要编辑 JSON。','',
 '调查随机抽查至多三张手牌，不公开给其他玩家，也不搬走实体；调查窗口结束后继续下一积木。心象调序始终保留原牌区，避免调序期间误判心坏。虚拟攻击仍可防御，所有私密选择都有合法确认与超时默认。','',
 '| 心象 | 类别 | 设计理由 |','| --- | --- | --- |',
 ...pack.mindTemplates.map(t=>`| ${t.name}${t.card.characterId?'〔角色绑定〕':''} | ${t.card.kind==='equipment'?({weapon:'武器',armor:'防具',gadget:'装置'}[t.card.slot]):'事件'} | ${t.designReason} |`),'',
 '## 复用与重建','',
 '- 界面原技能来源位置显示“设计理由”；人物资料链接仍单独保留。',
 '- 在拼图库筛选“冒险王 · 原创”，可载入、编辑、收藏、导出和导入；改名、改系列与移除角色绑定沿用现有工坊。',
 '- `node tools/build-adventure-content.mjs` 重建数据与本文。源数据见本生成器、`adventure-research.json`；完整卡图提示词见 `adventure-art-prompts.json`，每人单独调用内置 image_gen。',
 '- 规则版本 0.4.0-alpha，兼容读取 0.1/0.2/0.3 构筑与旧房间；固定 104 张普通牌及既有内容包保持有效。',''
].join('\n'));
console.log(`Built ${pack.presets.length} characters, ${pack.mindTemplates.length} mind cards, ${pack.skillTemplates.length} skills; equipment=${pack.mindTemplates.filter(t=>t.card.kind==='equipment').length}.`);
