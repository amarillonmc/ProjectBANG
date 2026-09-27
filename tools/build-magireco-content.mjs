// Rebuild this independent pack from reviewed character and SGS expansion research.
// Runtime deployment needs the generated JSON, not Node, research files, or remote sites.
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const read=relative=>JSON.parse(fs.readFileSync(path.join(root,relative),'utf8'));
const research=read('docs/content/magireco-research.json');
const expansion=read('docs/content/sgs-expansion-templates.json');
const E=(op,amount=1,target='self',color='neutral')=>({op,amount,target,color});
const T=(op,amount=1,color='neutral')=>E(op,amount,'target',color);
const templates=expansion.skillTemplates;
const byTemplate=Object.fromEntries(templates.map(t=>[t.id,t]));
const byCharacter=Object.fromEntries(research.records.map(r=>[r.id,r]));
const rulesVersion=expansion.rulesVersion;
// Each row is an explicit, reviewed match: ID / title / color / preferred HP /
// [template, in-world name, template, in-world name] / rationale / two themed events.
// Event tuple: [name, effects, adaptation, optionally bound-to-this-character].
const rows=[
 [1,'沿着希望寻找归途','warm',6,['huaiju','护住尚存的希望','jujian','把希望交给你'],'伊吕波以治疗与结识伙伴推进寻找妹妹的旅程；怀橘式自我守备让她能够继续援助，举荐的主动补给与治疗表现柔和而坚持的行动。',[
  ['牵起小小的手',[T('damage_guard'),T('heal'),E('draw')],'将橘标记的防伤收益改为主动交给伙伴的减伤与治疗；摸牌是分享后的整备。'],
  ['不会消失的归处',[E('give_hand',1,'target'),T('draw',2),E('heal')],'以赠牌支持伙伴并照顾自己，不复制原作连携的全部效果。']]],
 [2,'三日月庄的灯火','cool',5,['fuyin','不会再次失去','jingce','七年的步调'],'八千代的老练来自长期生存与失去伙伴的经历；父荫式受攻保护表现警戒，精策式阶段总结表现稳健地规划行动。',[
  ['三日月庄的晚灯',[E('damage_guard'),T('damage_guard'),E('draw')],'减伤照料自己和一名伙伴，不是全队免伤。'],
  ['贯穿过去的长枪',[E('range',2),T('attack',1,'cool'),E('scry',2)],'长枪攻势之后整理后续牌序，不操纵已经结算的历史。',true]]],
 [3,'万万岁的最强招牌','warm',5,['wanglie','最强的一息','jianying','越战越炽热'],'鹤乃以最强为目标，又持续回应家庭与伙伴的期待；往烈提供一次集中的压制，渐营要求连贯用牌，表现热情背后的练习。',[
  ['最强少女的应援',[E('extra_attacks'),T('draw'),E('range')],'给予额外攻击额度和协作补牌，仍需实际攻击手牌。'],
  ['万万岁的热气',[E('heal'),T('heal'),E('damage_guard')],'将战后的家庭温度做成双人恢复和一层减伤，不是全场恢复。']]],
 [4,'盾后有人看见你','cool',6,['zhichi','透明的防线','juzhan','盾后的安宁'],'莎奈的透明处境与巨盾形成对照：她擅长承受压力，也希望被伙伴理解。智迟和拒战让保护来自受创、被针对后的响应，而非主动爆发。',[
  ['看见彼此的世界',[T('damage_guard',2),E('draw')],'给予选中伙伴两点御伤：其下个自己的回合开始前，每次伤害均先减免两点。'],
  ['透明之盾',[T('sequester_hand'),E('damage_guard'),E('shield')],'暂置一张对方手牌争取喘息，使用者获得两种独立防护资源。',true]]],
 [5,'将恶梦击成碎片','warm',5,['pojun','粉碎恶梦','xueji','怒意不是终点'],'菲莉希亚以巨锤与对魔女的强烈敌意战斗；破军的暂置手牌表现打乱阵脚，血祭的受伤进攻保留冒进与代价。',[
  ['铁锤落下的声音',[T('sequester_hand',2),T('attack',1,'warm')],'暂置两张随机手牌后发起一次可防御攻击，暂置不等于弃置。'],
  ['给同伴留的一份',[E('lose_health'),T('draw',2),E('damage_guard')],'承担体力代价换取伙伴资源，并让自己在下个回合开始前每次受伤少一点。']]],
 [6,'梦境边缘的牵挂','cool',5,['tianming','梦境的退路','zhiyu','温柔的判断'],'美冬的幻觉能力与对现实的疲惫对应临危换取出路；天命的受攻整备、智愚的受伤反思，使她在压力中重新寻找判断。',[
  ['恍惚的避难所',[T('sequester_hand'),E('scry',3),E('draw')],'暂置与观星表达制造空隙，不控制对手的选择或读取暗手牌。'],
  ['醒来时还有人等你',[T('heal'),T('damage_guard'),E('recover_mind')],'双人照料与心象回收表现从梦境返回，不是原地复活。']]],
 [7,'为奇迹写下算式','cool',5,['qizhi','转化式演算','chenglue','解放的设计图'],'灯花以天才式推演和能量转化推进自己的方案；奇制的拆补交换对应改变资源形式，成略的取舍对应为下一轮计划重配手牌。',[
  ['宇宙与小小的算式',[T('discard_hand'),T('draw'),E('scry',3)],'先拆后补并整理自己的牌顶，保留奇制的交换思想，不自由挑选暗手牌。'],
  ['解放系统的试算',[T('hand_lock'),E('draw',2)],'封手限于规则规定的当前回合窗口，补牌为执行方案的资源，不改写胜利条件。',true]]],
 [8,'在边界中完成作品','cool',5,['zhengrong','框中的世界','xiongluan','绿色的边界'],'阿莉娜将生死与空间作为艺术素材；征荣表现通过行动夺取筹码，雄乱的定时封手则把结界的压迫限定在可核查的时间内。',[
  ['未完成的画框',[T('sequester_hand',2),E('draw')],'两张随机暂置手牌成为作品的边界，归还规则不随角色名称改变。'],
  ['绿色结界的闭幕',[T('hand_lock'),E('range',2),T('attack',1,'cool')],'封手加一次有色虚拟攻击；成熟装备仍按合法响应使用，不是无条件命中。',true]]],
 [9,'镜面之后的真心','cool',5,['shicai','镜面的自尊','chongzhen','说不出口的关心'],'玲奈的变身与强烈自尊都与如何被看见有关；恃才奖励谨慎的起手，冲阵让防御后的反击带来资源，表现别扭而敏锐的节奏。',[
  ['水面的另一张脸',[E('scry',2),E('draw'),E('damage_guard')],'看清牌序后再整备并防护，不冒充另一角色或复制其技能。'],
  ['没说出口的道谢',[T('give_hand'),E('draw',2),T('damage_guard')],'把心意变成明确可赠送的手牌和防护，赠牌仍需足量实体。']]],
 [10,'总会伸出的手','warm',6,['mingce','先跨出这一步','hongyuan','把心情接住'],'桃子的爽朗与顾全同伴对应明策、弘援的支援资源；她为别人创造行动机会，而不是靠复制攻击者的技能。',[
  ['时机不巧也要前进',[T('give_hand'),T('draw'),T('damage_guard')],'送出一张真实手牌，再给同一伙伴补牌与持续到其下个自己回合开始的御伤。'],
  ['三个人的放学路',[E('draw'),T('draw'),E('shield')],'以双人补牌表示团队配合，牌面虽写三人故事，规则只涉及自己和一名目标。']]],
 [11,'守住一片小小森林','cool',6,['huaiju','森林的庇佑','juzhan','别踩坏花圃'],'枫珍视植物与日常安稳；怀橘的回合开始守备表现庇护，拒战在被针对时让双方补牌，与她谨慎争取喘息的防守更相符。',[
  ['小庭院的雨后',[T('heal'),T('damage_guard'),E('recover_mind')],'照料一个伙伴并整理自己的心象，回收只作用自己的已耗区。'],
  ['根系织成的屏障',[T('sequester_hand'),E('damage_guard'),E('shield')],'随机暂置限制一部分手牌，同时自己建立两类防线；没有无限束缚。']]],
 [12,'月夜怪盗的灵感','warm',5,['xuanfeng','怪盗的谢幕','feijun','月下的预告'],'花凛以怪盗形象激励自己，创作与窃盗魔法相连；旋风让他人移除装备后出现反制，飞军以公开拆装制造下一次行动的机会。',[
  ['今晚的预告函',[T('discard_equipment'),E('draw_discard'),E('scry',2)],'只拆公开装备，再从普通弃牌堆取牌，不秘密选走对手暗手牌。'],
  ['怪盗的战利品',[T('sequester_hand'),E('draw',2)],'以暂置与补牌表现得手，不把暂置牌直接据为己有。']]],
 [13,'薙刀道场的直心','warm',5,['jueyan','薙刀的决意','jingce','每天的修行'],'明日香以薙刀与直率的责任心面对事情；决堰把放弃装备换成当回合进攻，精策则鼓励充分行动之后再整理收获。',[
  ['道场的晨练',[E('discard_equipment'),E('extra_attacks',2),E('range',2)],'主动弃自己的装备获得有限攻击额度和范围；没有装备时按积木规则处理。'],
  ['挥向迷惘的一刀',[E('range',2),T('attack',1,'warm'),E('damage_guard')],'推进距离后进行一次可防御攻击，再整备防护。']]],
 [14,'把故事写进世界','cool',5,['shicai','书页的创造','zuilun','结局之前'],'音梦能让故事具象化，也必须承担创作的代价；恃才组织第一张牌，罪论的整理与取舍则对应在结局前审视自己写下的道路。',[
  ['尚未写完的页角',[E('scry',3),E('draw'),T('draw')],'先重排普通牌顶再分别补牌，不创造新的普通牌类型。'],
  ['从书中走来的守护',[T('damage_guard',2),E('recover_mind')],'借故事保护一名伙伴并收回自己的记忆，不能召唤额外玩家或永久傀儡。',true]]],
 [15,'把痛苦留给自己','warm',5,['yili','愿望的容器','shibei','不能独自承受'],'忧与吸收污秽、为他人承担风险相连；遗礼把自损转成援助，矢北把受伤后的韧性限制为明确次数，不复制无限承载。',[
  ['小小容器中的星光',[E('lose_health'),T('heal',2),T('damage_guard')],'先承受体力代价，再治疗和保护一名伙伴；先死亡则遵守后续效果存活规则。'],
  ['姐姐牵着的那只手',[E('heal'),E('recover_mind'),E('damage_guard')],'自我恢复、回收心象和减伤三者各自有界，不承担全场伤害。',true]]],
 [16,'东区夜色中的秩序','cool',5,['qizhi','读取心声','pojun','不退让的界线'],'十七夜的读心与秩序意识表现为对局势的准确干预；奇制以拆补调整资源，破军用暂置手牌暂时制止压力，不公开他人暗手牌。',[
  ['听见沉默的回答',[T('discard_hand'),T('draw'),E('draw')],'随机拆补后自己获取资源，明确不提供读心式的暗手牌展示。'],
  ['夜色下的警戒线',[T('sequester_hand',2),E('damage_guard')],'暂置两张随机手牌并保护自己；受限制牌在规定回合归还。']]],
 [17,'调整屋的代价','cool',5,['shenxing','调整的代价','chenglue','命运再配色'],'御魂的调整并非没有代价的祝福；慎行、成略围绕支付与重配资源，让创作者看到她的核心是改造条件，而不是直接复制任何技能。',[
  ['调整屋的下午茶',[E('draw',2),T('damage_guard')],'补充手牌并给一人调整后的减伤，不修改其技能数据。'],
  ['一次特别的调整',[T('discard_equipment'),T('heal'),T('damage_guard')],'移除一件公开装备后给予同一目标治疗与防护，属于可选择的风险交换。']]],
 [18,'月下悠长的笛音','cool',6,['bingyi','笛声相和','juzhan','月色的间奏'],'月夜在约束与姐妹的联结间寻找平衡；秉壹要求手牌保持协调，拒战则在被攻击时拉开间奏，符合沉静而谨慎的气质。',[
  ['月夜的二重奏',[E('damage_guard'),T('damage_guard'),E('scry',2)],'双人防护与有限牌序整理，不要求另一名角色必须是月咲。'],
  ['笛音留下的空白',[T('sequester_hand'),T('draw'),E('draw')],'用一段暂置与双方补牌模拟交错乐句，不使暂置牌永久消失。']]],
 [19,'晨光轻快的笛音','warm',6,['hongyuan','把笛声送出去','kangkai','姐妹的应答'],'月咲以明快、直接的行动回应姐妹关系；弘援为别人提供资源，慷忾在邻近伙伴受攻时支援，区别于月夜的协调与拒战。',[
  ['月咲的二重奏',[E('draw'),T('draw'),T('damage_guard')],'给自己和一人补牌，再让目标在下个自己的回合开始前，每次伤害先减免一点。'],
  ['约好一起回家的路',[T('give_hand'),T('heal'),E('range',2)],'送出一张牌和治疗后自己提高范围，表达会合而不是瞬间移动座次。']]],
 [20,'把见证写成记录','cool',6,['zuilun','记录每一次相遇','shenxing','微小声音的重量'],'笼目的见证与记录需要仔细收集和反省；罪论提供回合末的牌序思考，慎行用可见成本换取新线索，避免赋予她不相干的重火力。',[
  ['尚未合上的笔记本',[E('scry',3),E('draw'),E('recover_mind')],'重排未来资源并保存已耗心象，记录不倒带既有对局。'],
  ['送给未来的证词',[T('draw',2),T('damage_guard')],'把记录转成一人的后续资源与防护，不为全场自动发动。']]],
 [21,'血盟之中的归还','warm',5,['enyuan','血盟的索还','xiongluan','红色的归还'],'结菜的复仇与领袖责任都由深重失去驱动；恩怨的受伤索偿对应对敌意的回报，雄乱要求付出体力和心象才带来短时强压。',[
  ['红色盟约的回声',[E('lose_health'),T('sequester_hand',2),E('draw')],'以自己体力为代价暂置对方两张手牌，不把复仇扩为全体直接伤害。'],
  ['无法忘却的名字',[T('hand_lock'),T('attack',1,'warm'),E('draw')],'一段封手与单次攻击表现复仇窗口；封手结束后恢复正常规则。',true]]],
 [22,'闻声即至的近侍','warm',6,['kangkai','听见召唤','mingce','近侍的接力'],'光的忠诚与迅捷适合贴近领队支援；慷忾响应邻近伙伴受攻，明策把手牌转成帮助，强调可靠的行动位置。',[
  ['在你身侧的光',[T('damage_guard'),T('draw'),E('range',2)],'帮助一人减伤并补牌，御伤持续到其下个自己的回合开始；自己的移动性由攻击范围表示。'],
  ['马上就到',[T('give_hand'),T('draw',2),E('shield')],'交出资源后让目标充分整备，使用者获得有限护盾。']]],
 [23,'下一关的地图','cool',5,['qizhi','读懂这场游戏','jianying','连胜的节奏'],'青习惯以游戏与关卡观察局面；奇制控制对手的资源节奏，渐营要求相同点数或花色的衔接，让胜利来自具体的手牌规划。',[
  ['下一关的攻略',[E('scry',3),T('discard_hand'),T('draw')],'整理自己的后续牌序并对目标进行随机拆补，不窥视整副牌堆。'],
  ['继续游戏',[E('draw_to',3),E('damage_guard')],'低资源时重新整备并保护下一次行动，不重新开始对局。']]],
 [24,'烧穿僵局的火焰','warm',5,['pojun','烧穿防线','wanglie','灼热的进逼'],'树里的火力与好战倾向适合破军、往烈的压力组合；先破坏短期防御资源再推进，但所有攻击仍服从本项目响应与颜色规则。',[
  ['燃烧的突破口',[T('discard_equipment'),E('range',2),T('attack',1,'warm')],'先拆一件公开装备再攻击，不能指定并焚毁整只手牌。'],
  ['熄火前的余温',[T('sequester_hand'),E('attack_bonus'),E('damage_guard')],'暂置一牌，使用者蓄势并保留减伤，不附加持续灼烧状态。']]],
 [25,'背负一族的正心','warm',6,['huaiju','一族的希望','fuyin','不改的正心'],'静香将一族使命与对外界的理解一并背负；怀橘式持续自我守备支撑领导责任，父荫在低手牌受攻时保护自己，主题心象再把防护交给伙伴。',[
  ['时女一族的同行',[T('damage_guard',2),T('draw')],'把传承化作一名伙伴的减伤与补给，不自动覆盖整队。'],
  ['剑锋上的澄明',[E('damage_guard'),E('range'),T('attack',1,'warm')],'建立自我防护后进行有限范围的可防御攻击。']]],
 [26,'不会错过的微光','warm',6,['qizhi','恶意的微光','zhiyu','温柔的追问'],'千春以嗅觉感应恶意，并怀有侦探式的正义感；奇制表示干预可疑行动后的资源变化，智愚表示承受压力后的判断，均不向玩家泄露暗手牌内容。',[
  ['藏不住的恶意',[T('discard_hand'),T('draw'),E('scry',2)],'随机拆补并观星，不能凭人物设定直接查看目标暗手牌。'],
  ['听完再下结论',[T('damage_guard'),T('heal'),E('draw')],'先给伙伴喘息的机会，再以补牌继续交流。']]],
 [27,'愿意挽回的双手','cool',6,['yili','医者的选择','jujian','双手仍能挽回'],'沙绪经历的责任与照料伙伴的行动适合自付代价的援助；遗礼和举荐都需要主动取舍，保留她温柔与决断并存的一面。',[
  ['请不要独自受伤',[E('lose_health'),T('heal',2),T('draw')],'先支付自己的体力，再给一个伙伴恢复与资源，不代替濒死救援。'],
  ['诊疗室外的约定',[T('damage_guard'),T('heal'),E('recover_mind')],'治疗与防护之外回收自己的心象，保留个人经历而非抹除伤害记录。']]],
 [28,'理想中心的号令','warm',5,['xiongluan','理想的中心','mingce','同步的号令'],'姬奈善于把他人卷入自己的理想与计划；雄乱提供一段压制，明策把协作落实为资源传递，而非无条件复制别人的整套能力。',[
  ['让大家看向这里',[T('hand_lock'),E('draw'),E('range',2)],'用有时限的封手和自己的整备表现注意力聚集，不永久控制对方。'],
  ['与你同步的颜色',[T('give_hand'),T('draw'),T('damage_guard')],'同步在这里表现为赠牌与防护，不读取或改写目标技能。']]],
 [29,'雨中的小小庇护','cool',6,['fuyin','雨中的庇护','shenxing','小小的反论'],'时雨的不安与对认同的渴望让她倾向保守地积累资源；父荫提供受攻保护，慎行要求拿出具体代价再补牌。',[
  ['雨滴敲响的屋檐',[E('damage_guard',2),E('draw')],'为自己设置持续到下个自己回合开始的两点御伤，不让目标无法选择攻击她。'],
  ['给自己的鼓励',[E('scry',2),E('draw_to',3)],'先整理后补至固定手牌数，不因已有更多手牌获得额外抽取。']]],
 [30,'走出配角的位置','warm',5,['jueyan','舞台之外的决意','shibei','别再当配角'],'育梦想挣脱自我怀疑并以剑证明价值；决堰把舍弃装备化作一回合决心，矢北表现承受伤害后的韧性。',[
  ['主角的一次挥剑',[E('discard_equipment'),E('range',2),T('attack',1,'warm')],'放下自己的装备后推进一次进攻，不永久提高基础体力或攻击次数。'],
  ['未落幕的练习',[E('heal'),E('damage_guard'),E('draw')],'把站稳脚步转为恢复、防护和新资源，不取消此前受到的伤害事件。']]],
 [31,'静谧尽头的守望','cool',6,['huaiju','止息的祈愿','bingyi','静谧的监护'],'拉比以克制和观察面对严酷处境；怀橘提供延续守望的自我防护，秉壹要求手牌协调后才能与一人共同补牌，对应概念强化和耐心照料。',[
  ['万籁之间的庇护',[T('damage_guard',2),E('scry',2)],'保护一人并调整自己的未来牌序，不冻结时间或全场伤害。'],
  ['仍然留在这里',[E('recover_mind'),T('heal'),E('draw')],'保存个人心象、治疗伙伴并继续行动，无法复活退场者。']]],
 [32,'每一个愿望的方向','warm',6,['hongyuan','希望的接力','jujian','每个人的愿望'],'圆的核心是对他人的共情与希望；弘援、举荐都围绕让别人获得生存和行动资源，避免把终极形态的宇宙改写直接塞进一张角色卡。',[
  ['朝着同一片天空',[T('draw',2),T('damage_guard')],'将希望具体化为一人的两张牌与持续到其下个自己回合开始的御伤。'],
  ['不让祈愿独自消失',[T('heal',2),E('recover_mind'),E('damage_guard')],'治疗一名伙伴，自己回收心象并整备保护；不重写出局和胜利条件。',true]]],
 [33,'回溯之后仍然前行','cool',5,['tianming','回溯后的准备','zuilun','仅此一次守护'],'焰反复规划与承受失去的经历，可由天命的危机整备和罪论的回合末牌序选择表达；“时间”只作为风味，不提供撤销既有操作的权限。',[
  ['下一次也会找到你',[E('scry',3),E('draw',2)],'通过重排未来牌顶进行计划；不能读取未来随机数或倒退已结算回合。'],
  ['静止的一瞬',[T('hand_lock'),E('range'),T('attack',1,'cool')],'短时封手与单次攻击表现行动空隙；装备响应依旧存在，封手不会冻结服务器。',true]]],
 [34,'仍不折断的蓝色剑','cool',5,['shibei','伤口终会愈合','chongzhen','不会折断的剑'],'沙耶香的再生与正义感并存；矢北的首段受伤应对强调有限韧性，冲阵让完成防御后的反击回到具体手牌资源。',[
  ['雨后依旧举起的剑',[E('heal',2),E('damage_guard')],'恢复和持续御伤分别结算；御伤在自己的下个回合开始清空，心坏时仍不能治疗。'],
  ['蓝色的反响',[T('attack',1,'cool'),T('sequester_hand'),E('draw')],'先等待攻击结算，再暂置一张随机手牌并整备；不要求该攻击命中。']]],
 [35,'缎带缠绕的终幕','warm',5,['juzhan','金线的结界','pojun','终幕的余裕'],'麻美的缎带束缚与精密射击适合拒战、破军的节奏控制；把对手手牌暂时移出与自我整备结合，不直接宣布任何攻击必中。',[
  ['缎带编成的茶会',[T('sequester_hand'),E('heal'),E('draw')],'暂置一张随机手牌创造休整空间，不剥夺其全部响应。'],
  ['终幕之前的金线',[T('sequester_hand',2),E('range',2),T('attack',1,'warm')],'先暂置两张手牌，再增加范围并进行一次可防御攻击。',true]]],
 [36,'独行者分出的苹果','warm',5,['xueji','独行的法则','jueyan','决断的一枪'],'杏子把生存原则和仍愿意分享的心意藏在强硬作风之后；血祭表现带伤反击，决堰把牺牲装备换成一轮决意，卡组另保留分享的一面。',[
  ['分给你的半只苹果',[T('give_hand'),E('heal'),T('heal')],'需要交出一张实体手牌，随后各自恢复；无法从没有手牌中凭空分享。'],
  ['红枪划开的道路',[E('discard_equipment'),E('extra_attacks'),E('range',3)],'主动放弃装备获得有限射程和一次额外普通攻击，仍需攻击手牌。']]],
];
if(rows.length!==36||research.records.length!==36)throw Error('Exactly 36 reviewed character records are required.');
const weights={draw:2,heal:2,damage:4,recover_mind:3,shield:2,range:1,attack_bonus:3,steal_hand:3,discard_hand:2,give_hand:1,draw_to:2,extra_attacks:3,lose_health:2,attack:3,scry:1,draw_discard:2,double_defense:4,sequester_hand:3,discard_equipment:3,damage_guard:4,hand_lock:6};
const effectCost=e=>{if(!(e.op in weights))throw Error('Unknown cost '+e.op);return weights[e.op]*e.amount+(['damage','attack'].includes(e.op)&&e.color==='neutral'?e.amount:0);};
const skillCost=s=>Math.max(1,(s.trigger==='convert'?(s.conversion.from==='hand'?4:3):s.effects.reduce((n,e)=>n+effectCost(e),0))+(!['active','convert'].includes(s.trigger)?2:0)-Math.min(4,s.cost.hand+s.cost.mind*2))*(s.limit||1);
const base=['attack_cool','attack_cool','attack_warm','attack_warm','evade','haste','evade','miracle','mana','life','amplify','energy','recover'];
const presets=[],mindTemplates=[],arts=[],characterNotes={},pairs=new Set(),usage={};
// A themed event may combine several mechanisms. Attribute its actual blocks,
// rather than labelling it with an unrelated skill merely because slots match.
const mechanismSources={sequester_hand:'pojun',damage_guard:'huaiju',hand_lock:'xiongluan',give_hand:'mingce',scry:'zuilun',heal:'jujian',range:'chenglue',extra_attacks:'chenglue',discard_hand:'qizhi',shield:'fuyin',lose_health:'yili',damage:'xueji'};
const genericNames={attack:'可防御攻击',recover_mind:'回收心象',draw_discard:'普通弃牌取牌',draw_to:'补至指定手牌数',attack_bonus:'攻击增伤'};
function eventSources(effects,fallback){
 const ids=[...new Set(effects.map(e=>e.op==='discard_equipment'?(e.target==='self'?'jueyan':'feijun'):mechanismSources[e.op]).filter(Boolean))].map(id=>'sgsx_'+id);
 if(!ids.length&&effects.some(e=>e.op==='draw'))ids.push(fallback.id);
 const refs=ids.map(id=>byTemplate[id]);
 const generic=[...new Set(effects.map(e=>genericNames[e.op]).filter(Boolean))];
 return {refs,generic,reference:[...refs.map(t=>t.reference),...(generic.length?['通用积木：'+generic.join('、')]:[])].join('；')};
}
for(const [num,title,color,preferredHP,skills,reason,cards] of rows){
 const id='mr_'+String(num).padStart(4,'0'),source=byCharacter[id];if(!source)throw Error('Missing research for '+id);
 const templateIds=[skills[0],skills[2]].map(s=>'sgsx_'+s),refs=templateIds.map(t=>{if(!byTemplate[t])throw Error('Missing expansion template '+t);usage[t]=(usage[t]||0)+1;return byTemplate[t];});
 const pair=[...templateIds].sort().join('/');if(pairs.has(pair))throw Error('Duplicate skill pairing '+pair);pairs.add(pair);
 const actualSkills=refs.map((t,i)=>({...structuredClone(t.skill),name:skills[i*2+1]}));
 const costs=actualSkills.reduce((n,s)=>n+skillCost(s),0);if(costs>18)throw Error('Skill pair over budget '+source.name+': '+costs);
 const hp=Math.min(preferredHP,4+Math.floor((18-costs)/2));if(hp<4)throw Error('HP below legal minimum');
 const character={id,name:source.name,title,series:'魔法纪录',color,hp,art:id,flipColor:null,skills:actualSkills};
 const deck=base.map((type,i)=>({rank:i+1,type}));
 cards.forEach(([name,effects,adaptation,bound],i)=>{
  const budget=effects.reduce((n,e)=>n+effectCost(e),0);if(budget>12)throw Error('Mind card over budget '+id+'/'+name+': '+budget);
  const card={name,series:'魔法纪录',fallback:i?'mana':'recover',effects,...(bound?{characterId:id}:{})};
  const sources=eventSources(effects,refs[i]),sourceRefs=sources.refs;
  mindTemplates.push({id:`${id}_mind_${i+1}`,name,series:'魔法纪录',reference:sources.reference,expansion:sourceRefs[0]?.expansion||'通用组合',sourceUrl:sourceRefs[0]?.sourceUrl,sourceTemplateIds:sourceRefs.map(t=>t.id),sourceUrls:[...new Set(sourceRefs.map(t=>t.sourceUrl))],description:`${source.name}主题心象。${bound?'同系列且角色编号匹配时生效。':'同系列角色可共享。'}${adaptation}`,adaptation,tags:[source.name,...new Set(sourceRefs.map(t=>t.expansion)),...new Set(effects.map(e=>e.op))],card,...(bound?{characterId:id}:{})});
  deck[i?10:4]={rank:i?11:5,type:'custom',custom:structuredClone(card)};
 });
 presets.push({id:'preset_'+id,rulesVersion,name:'神滨·'+source.name,character,deck});
 arts.push({id,name:source.name,url:`assets/characters/${id}.png`});
 characterNotes[id]={summary:source.summary,reason,sourceUrl:source.sourceUrl,sourcePath:'docs/content/magireco-research.json#'+id,skillReferences:refs.map(t=>t.reference),cardReferences:mindTemplates.slice(-2).map(t=>t.reference),skillTemplateIds:templateIds,mindTemplateIds:[`${id}_mind_1`,`${id}_mind_2`],adaptations:refs.map(t=>t.description),researchTags:source.tags||[]};
}
const missing=templates.filter(t=>!usage[t.id]);if(missing.length)throw Error('Unused researched template(s): '+missing.map(t=>t.id).join(','));
const counts={characters:presets.length,mindCards:mindTemplates.length,skillTemplates:templates.length,boundCards:mindTemplates.filter(t=>t.card.characterId).length};
const sources=expansion.sources||expansion.packageSources;
const result={version:rulesVersion,contentPacks:[{id:'magireco-expansions-01',name:'神滨 · 四篇愿望',description:`${counts.characters}位魔法少女、${counts.mindCards}张主题心象、${counts.skillTemplates}组来自一将成名、SP、阴、雷的技能范式；${counts.boundCards}张心象额外绑定角色。与加帕里内容并存，系列对抗可进行跨IP对局。`,characterIds:presets.map(p=>p.character.id),sources:[research.sourceIndexUrl,...new Set(sources.map(s=>s.url))].filter(Boolean)}],presets,skillTemplates:templates,mindTemplates,arts,characterNotes};
fs.mkdirSync(path.join(root,'src/content'),{recursive:true});fs.writeFileSync(path.join(root,'src/content/magireco-expansions.json'),JSON.stringify(result,null,2)+'\n');
const guide=[
 '# 神滨 · 四篇愿望','',
 `本包新增《魔法纪录》${counts.characters}位角色、${counts.mindCards}张IP心象、${counts.skillTemplates}组技能模板，使用规则${rulesVersion}。原有38位《动物朋友3》角色、76张心象和4位原创示例继续保留。技能选自《三国杀》一将成名、SP、阴、雷四类来源，每个模板写明版本与改编范围。`,'',
 '## 角色、技能与匹配理由','','| 角色 | 角色技能与来源 | 配套心象 | 匹配理由 |','| --- | --- | --- | --- |',
 ...presets.map(p=>{const n=characterNotes[p.character.id];return `| ${p.character.name} | ${p.character.skills.map((s,i)=>s.name+'（'+n.skillReferences[i]+'）').join(' / ')} | ${p.deck.filter(d=>d.type==='custom').map(d=>d.custom.name+(d.custom.characterId?'〔绑定〕':'')).join(' / ')} | ${n.reason} |`;}),'',
 '36人的技能组合逐项匹配，没有按角色序号循环派发同一套能力。预设体力按两项技能的实际预算在4～6之间确定；未隐式改写模板费用或效果。角色内的技能名称是人物风味，结构与来源模板一致。','',
 '## 四包技能与有界改编','','| 模板 | 出处 | 本版可执行规则与边界 |','| --- | --- | --- |',
 ...templates.map(t=>`| \`${t.id}\` · ${t.name} | ${t.reference} | ${t.description}${t.adaptation&&t.adaptation!==t.description?' '+t.adaptation:''} |`),'',
 '## 心象牌的独立创作','','所有主题牌以“魔法纪录”为精确系列名称。同IP角色可使用共享牌；只有标明绑定的第二张心象还检查角色ID。绑定不是稀有度或权限机制。牌名与剧情意象不赋予额外规则：事件心象从相关武将范式中抽出机制，并结合通用资源积木，不能被视作原武将技能的逐字复刻。','',
 '| 心象 | 机制来源 | 明确差异 |','| --- | --- | --- |',...mindTemplates.map(t=>`| ${t.name}${t.card.characterId?'〔绑定 '+t.card.characterId+'〕':''} | ${t.reference} | ${t.adaptation} |`),'',
 '暂置手牌保留实体牌及原心象归属，在当前全局回合结束时归还，不能假装弃置或永久夺走。拆装备按最早进入装备区的顺序移除公开装备。御伤上限为两点，在受益者下个自己的回合开始前，每次伤害均先减御伤再扣护盾；御伤不被一击消耗，也不减免失去体力。封手到当前全局回合结束，限制手牌使用和响应，但仍可弃牌、赠牌、支付费用及使用合法装备响应。它不是结束回合、永久沉默或修改胜负。','',
 '## 来源与重建','',
 '- [角色研究](magireco-research.json)逐人保留资料URL、角色摘要、标签与卡面参考路径；名字采用本次指定的中文名。',
 '- [四包技能研究](sgs-expansion-templates.json)记录官方来源、采用版本、技能结构与改编差异。',
 ...sources.map(s=>`- [${s.title||s.expansion+'官方资料'}](${s.url})${s.package?'：'+s.package:''}${s.version?' / '+s.version:''}${s.note?'：'+s.note:''}`),
 '- 修改本生成器的显式角色搭配后运行 `node tools/build-magireco-content.mjs`；技能研究和角色资料是构建输入，运行时不访问这些网站。',
 '- 输出为 `src/content/magireco-expansions.json`，由 `ContentPack::all()`与KF3包合并。`ContentPack::data()`继续返回KF3包，`data("magireco-expansions")`显式读取本包。',
 '- 图像路径为 `public/assets/characters/mr_0001.png`～`mr_0036.png`，图像由主任务单独生成和验收，不由内容生成器伪造。',
 '- 结构与整包回归：`php tests/magireco-content.php --skip-art`可在图像齐备前校验规则；最终运行不带该参数，并执行 `php -d extension=pdo_sqlite tools/doctor.php`检查全部两包资源。',''
];fs.writeFileSync(path.join(root,'docs/content/MAGIRECO_EXPANSIONS.md'),guide.join('\n'));
console.log(`Built ${counts.characters} Magireco characters, ${counts.mindCards} mind cards, ${counts.skillTemplates} expansion templates, ${counts.boundCards} bound cards.`);
