// 电拟神召 first set: historical selection and original compositions of shipped 0.5 mechanics.
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const series='电拟神召', expansion='电拟神召 · 第一弹', version='0.5.0-alpha';
const E=(op,amount=1,target='self',extra={})=>({op,amount,target,color:'neutral',...extra});
const T=(op,amount=1,extra={})=>E(op,amount,'target',extra);
const A=(color='warm',amount=1)=>T('attack',amount,{color});
const S=(name,trigger,effects,extra={})=>({name,trigger,condition:'always',cost:{hand:0,mind:0},effects,limit:1,limitScope:'owner_turn',...extra});
const cost=(hand=0,mind=0)=>({cost:{hand,mind}});
const P=(name,effects,condition='always')=>S(name,'passive',effects,{limit:0,condition});
const C=(name,from,to,extra={})=>S(name,'convert',[],{conversion:{from,to},...extra});
const Q=(...options)=>E('choose',1,'self',{options:options.map(([label,effects])=>({label,effects}))});
const M=(key,amount=1)=>E('add_mark',amount,'self',{key});
const R=(key,amount=1)=>E('remove_mark',amount,'self',{key});
const test=(value,cmp,amount,key)=>({value,cmp,amount,...(key?{key}:{})});
const has=(key,n=1)=>test('mark','ge',n,key);
const pile=(key,n=1)=>test('pile','ge',n,key);
const B=(condition,then,otherwise=[])=>E('branch',1,'self',{condition,then,else:otherwise});
const G=(n,pool,effects)=>E('choose_targets',n,'self',{pool,effects});
const J=(filter,then,otherwise=[],obtain=false)=>E('judge',1,'self',{filter,then,else:otherwise,obtain,repeat:false});
const D=(then,otherwise=[])=>T('pindian',1,{then,else:otherwise});
const store=(key,n=1,visibility='private')=>E('store_pile',n,'self',{key,visibility});
const take=(key,n=1)=>E('take_pile',n,'self',{key});
const gain=(key,skill,target='self',duration='owner_turn')=>E('gain_skill',1,target,{key,skill,duration});
const card=(name,effects,reason,extra={})=>({name,effects,reason,...extra});
const gear=(name,slot,effects,reason)=>card(name,effects,reason,{kind:'equipment',slot});
const holo=slug=>'https://hololive.hololivepro.com/en/talents/'+slug+'/';
const niji=slug=>'https://www.nijisanji.jp/talents/l/'+slug;
// Identity facts and era evidence are distinct from our editorial selection and game interpretation.
const people=[
 ['绊爱','Kizuna AI / キズナアイ','先驱视频势','2016—2022','https://kizunaai.com/biography/','2016 年末开始活动，2017 年开设游戏频道，随后拓展演出与音乐；在本弹时间窗内推动了 VTuber 这一表达形式的传播。','以“连接”为核心的早期行业象征；作为本弹开篇。'],
 ['辉夜月','Kaguya Luna / 輝夜月','先驱视频势','2017—2020','https://www.sonymusic.co.jp/artist/kaguyaluna/profile/','2017 年 12 月登场，2018 年发行首曲并举办 VR 演出；高能量的说话与演出风格是鲜明记忆点。','代表早期短视频爆发力及 VR 演唱会探索。'],
 ['未来明','Mirai Akari / ミライアカリ','先驱视频势','2017—2022','https://akari-mir.ai/','2017 年 10 月开始活动；官方设定包含失忆，活动涵盖视频、歌舞与电视。','以明亮互动和早期跨媒介活动入选，取失忆与未来之光作为设计线索。'],
 ['电脑少女小白','Siro / 電脳少女シロ','先驱视频势','2017—2022','https://prtimes.jp/main/html/rd/p/000000177.000014685.html','2018 年即参与射击游戏官方联动；柔和形象、海豚般笑声和游戏时的反差为广泛记忆点。','代表早期视频、游戏与电视综艺之间的连接。'],
 ['巴恰鲁','Baacharu / ばあちゃる','先驱视频势','2017—2022','https://prtimes.jp/main/html/rd/p/000000177.000014685.html','马头面具与西装是标志；2018 年与小白共同参与游戏联动，也长期参与主持与企划。','纳入早期男性虚拟表演者与主持型角色。'],
 ['猫宫日向','Nekomiya Hinata / 猫宮ひなた','先驱游戏势','2018—2022','https://prtimes.jp/main/html/rd/p/000000017.000013057.html','2018 年开始活动，2019 年与未来明成为日本游戏大奖官方大使；游戏与射击主题鲜明。','以游戏表现和游戏行业联动为入选依据，不讨论表演者身份。'],
 ['Azuma Lim','アズマリム / Azurim / 阿兹利姆','个人与户外探索','2018—2022','https://alive-project.com/streamer-magazine/11240/','2018 年活动起步；本人访谈回顾了 2021 年取得小型二轮驾照并开始摩托旅行的经历。','以早期活动、自主创作和走向户外的积极探索入选。'],
 ['月之美兔','Tsukino Mito / 月ノ美兎','直播拓荒 · NIJISANJI','2018—2022',niji('mito-tsukino'),'学级委员形象，2018 年作为早期 NIJISANJI 成员活动，2021 年举办个人演唱会。','代表直播谈话、奇想企划与日常虚拟形象的拓展。'],
 ['樋口枫','Higuchi Kaede / 樋口楓','直播拓荒 · NIJISANJI','2018—2022',niji('kaede-higuchi'),'早期 NIJISANJI 成员；官方角色设定为喜爱小号的吹奏乐部成员。','用铜管、应援与合奏表现她的音乐和协作形象。'],
 ['静凛','Shizuka Rin / 静凛','直播拓荒 · NIJISANJI','2018—2022',niji('rin-shizuka'),'早期 NIJISANJI 成员；官方设定为温和、指导后辈的可靠前辈，游戏直播是其活动特色。','纳入早期长时游戏陪伴与稳健前辈型角色。'],
 ['叶','Kanae / 叶','游戏与联动 · NIJISANJI','2018—2022',niji('kanae'),'2018 年开始活动；柔和言谈与猫抱枕是官方人物介绍的核心意象，2022 年参与 Aim Higher 演出。','代表男性游戏直播、联动与温和交流。'],
 ['葛叶','Kuzuha / 葛葉','游戏与联动 · NIJISANJI','2018—2022',niji('kuzuha'),'吸血鬼游戏玩家形象，2018 年开始活动，2021 年举办 Bloody Alexandrite，2022 年参与 Aim Higher。','以竞技游戏、男性直播势和音乐舞台的跨界影响入选。'],
 ['时乃空','Tokino Sora / ときのそら','虚拟偶像 · hololive','2017—2022',holo('tokino-sora'),'2017 年 9 月首次直播，是 hololive 最早的虚拟偶像；2019 年签约唱片公司并举办 Dream!。','以持续歌唱和最初舞台的积累作为本弹另一条起点。'],
 ['樱巫女','Sakura Miko / さくらみこ','综艺与游戏 · hololive','2018—2022',holo('sakuramiko'),'2018 年出道，虚拟神社巫女与自称精英、常有笨拙一面的反差来自官方简介。','以游戏综艺和挫折后的努力作为积极记忆点。'],
 ['白上吹雪','Shirakami Fubuki / 白上フブキ','综艺与游戏 · hololive','2018—2022',holo('shirakami-fubuki'),'2018 年出道，白发狐耳、爱好交流与宅文化；白上与黑上的反差形象也见官方推荐视频。','代表跨成员互动、轻快内容传播与狐狸角色的多面性。'],
 ['凑阿库娅','Minato Aqua / 湊あくあ','综艺与游戏 · hololive','2018—2022',holo('minato-aqua'),'2018 年出道，海洋女仆设定；官方推荐同时包括原创歌与单排游戏挑战。','取游戏专注和女仆偶像之间的反差，避免只做攻击型角色。'],
 ['大空昴','Oozora Subaru / 大空スバル','综艺与游戏 · hololive','2018—2022',holo('oozora-subaru'),'2018 年出道，官方设定为格斗队与电竞部经理，开朗且容易与人交流。','以应援、管理和富感染力的互动入选。'],
 ['戌神沁音','Inugami Korone / 戌神ころね','综艺与游戏 · hololive','2019—2022',holo('inugami-korone'),'2019 年出道，面包店看门犬与爱游戏的设定；“手指”招呼与耐久游戏是鲜明观众记忆。','以复古游戏、长时挑战与跨语言幽默入选。'],
 ['星街彗星','Hoshimachi Suisei / 星街すいせい','音乐舞台 · hololive','2018—2022',holo('hoshimachi-suisei'),'2018 年出道，2021 年发行 Still Still Stellar 并举办首次个人演唱会。','取自主起步、不断打磨歌唱与星光舞台；技能连段是玩法诠释。'],
 ['AZKi','AZKi / アズキ','音乐舞台 · INNK / hololive','2018—2022','https://hololive.hololivepro.com/events/stand-at-the-crossroads/','2018 年开始活动；2021 年“不要停止音乐”企划连接许多虚拟音乐创作者。','以音乐共同体与开拓为核心，不使用 2023 年以后才突出的地图游戏经历。'],
 ['兔田佩克拉','Usada Pekora / 兎田ぺこら','综艺与游戏 · hololive','2019—2022',holo('usada-pekora'),'2019 年出道，爱胡萝卜的兔耳角色；游戏企划、恶作剧与戏剧性反应形成鲜明风格。','用短时陷阱与有失败可能的回避表现观众熟悉的游戏反差。'],
 ['宝钟玛琳','Houshou Marine / 宝鐘マリン','综艺与游戏 · hololive','2019—2022',holo('houshou-marine'),'2019 年出道，官方设定是为获得海盗船积蓄的海盗装扮女孩，喜爱宝藏。','取绘画、谈话、音乐与海盗寻宝形象中的积极创作面。'],
 ['桐生可可','Kiryu Coco / 桐生ココ','跨语言交流 · hololive','2019—2021',holo('kiryu-coco'),'2019 年出道，龙与异文化交流设定；晨间节目和跨语言社群互动是这一时期的重要记忆。','选择节目创意与连接不同语言观众的贡献，技能不涉及现实争议。'],
 ['角卷绵芽','Tsunomaki Watame / 角巻わため','音乐陪伴 · hololive','2019—2022',holo('tsunomaki-watame'),'2019 年末出道；歌唱、每周音乐直播与猜拳短片见官方简介。','用绵羊意象与安可表现柔和陪伴和舞台韧性。'],
 ['Gawr Gura','がうる・ぐら / 噶呜·古拉','英语圈 · hololive Myth','2020—2022',holo('gawr-gura'),'2020 年 9 月出道，来自亚特兰蒂斯的鲨鱼少女；首批 Myth 成员。','作为英语 VTuber 扩大受众的重要代表，以海潮与灵活进攻入选。'],
 ['Mori Calliope','森カリオペ / 森美声','英语圈 · hololive Myth','2020—2022',holo('mori-calliope'),'2020 年 9 月出道，死神学徒与强烈歌唱风格；官方简介同时强调对伙伴的温柔。','以英语原创音乐与说唱创作为入选依据。'],
 ['Amelia Watson','ワトソン・アメリア / 华生·阿米莉亚','英语圈 · hololive Myth','2020—2022',holo('watson-amelia'),'2020 年 9 月出道，侦探设定、FPS 与解谜游戏；时间旅行和自制虚拟企划形成社群记忆。','以侦探调查、技术企划与时间题材构成玩法，不复写整局历史。'],
 ["Ninomae Ina’nis","一伊那尔栖 / Ninomae Inanis / 一伊那尓栖",'英语圈 · hololive Myth','2020—2022',holo('ninomae-inanis'),'2020 年 9 月出道，古神祭司、神秘书本与触手设定，绘画直播与安静交流为活动特色。','取绘画、收纳和温和防守，与其他 Myth 成员区分。'],
 ['花谱','KAF / 花譜','虚拟音乐 · KAMITSUBAKI','2018—2022','https://kaf.kamitsubaki.jp/about/','2018 年开始活动，2022 年 8 月在日本武道馆举办“不可解参（狂）”。','代表虚拟歌手进入大型音乐舞台；只采用 2022 年前的花谱形象与记忆。'],
 ['田中姬','Tanaka Hime / 田中ヒメ','双人音乐 · HIMEHINA','2018—2022','https://old.himehina.jp/contents/topics/profile','2018 年开始的双人虚拟组合成员，以高音和高能量谈话见长。','拆分为独立角色；热唱与资源交付表现高音声部，不要求另一人必须在场。'],
 ['铃木雏','Suzuki Hina / 鈴木ヒナ','双人音乐 · HIMEHINA','2018—2022','https://old.himehina.jp/contents/topics/profile','2018 年加入 HIMEHINA 的另一声部，官方简介突出柔和歌声与和声。','与田中姬形成互补，突出倾听、和声和防守节奏。'],
 ['甲贺流忍者狸猫','Ponpoko / 甲賀流忍者ぽんぽこ','个人企划 · ぽこピー','2018—2022','https://ponpoko24.com/','2018 年开始活动；长期跨创作者节目“ぽんぽこ24”在 2022 年举办第六回。','纳入个人势的企划组织、地方日常和跨团体联动。'],
 ['花生君','Peanuts-kun / ピーナッツくん','个人企划 · ぽこピー','2017—2022','https://osharepeanuts.com/','2017 年开始短篇动画活动，之后发展直播、ぽんぽこ24 和原创音乐。','代表非美少女角色、短篇动画与个人音乐创作的可能性。'],
 ['Ironmouse','アイアンマウス / 铁鼠','英语圈 · 独立与 VShojo','2017—2022','https://streamscharts.com/news/ironmouses-31-days-long-twitch-subathon','2017 年开始直播；2022 年的长时订阅直播显著扩大了其在 Twitch 的影响。','取歌唱、幽默与社群陪伴，不把表演者现实健康状况写成游戏惩罚。'],
 ['Vox Akuma','ヴォックス・アクマ / 沃克斯','英语圈 · NIJISANJI EN','2021—2022','https://www.anycolor.co.jp/news/6t3dj7ovb7j8','2021 年末出道，2022 年 7 月官方宣布 YouTube 订阅达百万。','纳入英语男性 VTuber 的受众拓展与声音表演；选择型技能保留对手决策。'],
 ['名取纱那','Natori Sana / 名取さな','个人创作与日常','2018—2022','https://www.zan-live.com/ja/live/detail/10143','2018 年开始活动的个人势虚拟护士，2022 年举行“さなのばくたん。-メチャ・ハッピー・ショー-”。','以个人创作、日常交流和生日舞台的积极记忆入选；治疗只是游戏题材。']
];
// Each row: title, color, HP, role, two skills, two cards, design and balance rationale.
const designs=[
 ['与你连结','warm',6,'支援',[
  S('连结','active',[T('give_hand'),T('shield'),E('draw',2)]),
  S('共鸣','after_play_event',[G(1,'allies',[T('draw')])])],[
  card('连接的光线',[T('give_hand'),G(2,'everyone',[T('draw')]),E('shield')],'交出真实手牌后分配补给；不能复制手牌。'),
  card('初次见面的世界',[G(2,'others',[T('heal'),T('draw')]),E('recover_mind')],'至多帮助两位角色，以有限连结表现起点。')],
  '赠予实体手牌后才能补牌；联动奖励每个自身回合周期一次，只选一位同阵营伙伴。'],
 ['越过月面的音量','warm',6,'爆发',[
  S('爆音','active',[E('lose_health'),E('extra_attacks'),E('draw')]),
  S('大开大合','after_play_card',[E('attack_bonus'),E('draw')],{condition:'same_rank_or_suit'})],[
  gear('月面音箱','weapon',[E('equip_range'),E('equip_damage')],'装备增伤遵守每周期首个结算攻击的共用次数。'),
  card('超速开场白',[E('lose_health'),E('attack_bonus'),E('extra_attacks'),E('draw')],'自损换短时节奏，仍需拿出攻击牌。')],
  '攻击额度不等于免费攻击；连牌必须同点数或花色，自损不触发受伤收益。'],
 ['把碎片照向未来','warm',6,'储备',[
  S('记忆碎片','active',[store('明日'),E('draw')],{condition:test('hand','ge',1)}),
  S('重见光明','turn_start',[take('明日'),E('recover_mind')],{condition:pile('明日')})],[
  gear('明日记录器','gadget',[E('equip_draw')],'下个自身回合才产出普通牌，入场不补牌。'),
  card('归来的未来',[take('明日',2),E('draw'),E('recover_mind')],'取回先前储存的实体记忆，不从空牌堆生牌。')],
  '记忆私密储存且保留归属；回收需牌堆非空，每周期取一张，无法零投入循环。'],
 ['白色海豚的反差','cool',6,'应变',[
  S('海豚回声','on_defend',[E('scry',2),E('draw')]),
  S('清澈锋芒','active',[Q(['安静观察',[E('draw'),E('shield')]],['认真游戏',[A('cool'),E('draw')]])],cost(1))],[
  card('海豚的频率',[E('scry',3),E('draw'),E('shield')],'牌顶整理后才补牌，保留私密选择窗口。'),
  card('白色虚拟舞台',[Q(['守护笑声',[E('shield',2)]],['投入游戏',[A('cool'),E('draw')]]),E('recover_mind')],'攻守二择一，不同时取得两边收益。')],
  '防御必须完整成功才补给；主动攻守二选一都要弃一张牌。'],
 ['马上开场','warm',6,'联动',[
  S('串场','turn_start',[G(2,'others',[T('draw')])],cost(1)),
  S('接话','active',[T('copy_skill',1,{duration:'turn'})],cost(1,1))],[
  card('开场的铃声',[G(2,'others',[T('draw'),T('shield')])],'向至多两位不同角色分配舞台资源。'),
  card('虚拟英雄入场',[T('copy_skill',1,{duration:'turn'}),E('draw')],'借来的技能只保留到当前全局回合结束，重复借用不刷新次数。')],
  '串场以一张手牌换他人收益；复制需手牌与心象双费用，只在当回合使用且继承累计次数。'],
 ['镜头前的准星','warm',6,'竞技',[
  S('准星','active',[D([A('warm'),E('draw')],[T('draw')])]),
  C('猫步快反','black','defense')],[
  gear('小小瞄准镜','weapon',[E('equip_range',2)],'只延伸射程，不直接加伤。'),
  card('最后一发的判断',[E('scry',2),D([A('warm',2)],[E('draw',2)])],'先整理公共牌顶，拼点仍须拿出自己的真实手牌；失败只补给。')],
  '双方真实手牌拼点，平局不胜；黑牌防御每个自身回合周期一次。'],
 ['沿着海风重新出发','warm',5,'旅程',[
  S('旅程','after_play_card',[M('旅程'),E('scry_mind',2)],{condition:{all:['first_card',test('mark','lt',3,'旅程')]}}),
  S('人类皆前辈','active',[R('旅程',2),Q(['独自探路',[E('range',2),E('draw',2)]],['与前辈同行',[T('give_hand'),T('heal',2),T('shield')]])],{...cost(1),condition:has('旅程',2)})],[
  gear('海风中的小蓝车','gadget',[E('equip_range',2),E('equip_draw')],'摩托旅行意象提供路线与下回合补给，未复制车型商标。'),
  card('沿海公路的下一站',[M('旅程',2),E('scry_mind',3),E('draw'),E('heal')],'加快旅程积累；标记本身没有攻击或额外回合收益。')],
  '旅程依赖每周期第一张主动牌，常规至多积三；花两标记和一手牌，在自我探索与赠牌援助间选择。'],
 ['放学后的不可思议','cool',6,'企划',[
  S('奇想企划','active',[Q(['采访',[T('inspect_hand',2),E('scry')]],['剪辑',[E('scry_mind',2),E('draw')]])],cost(1)),
  S('话题续篇','turn_end',[B('played_two',[E('draw'),E('scry',2)],[E('scry_mind',2)])])],[
  card('课后企划书',[E('scry',2),T('inspect_hand',2),E('draw')],'调查为随机样本，仅发动者可见。'),
  card('委员长的小剧场',[Q(['独角戏',[T('steal_hand'),E('draw')]],['群像剧',[G(2,'others',[T('draw'),T('shield')])]])],'选择拿取线索或组织群像，不能兼得。')],
  '企划要弃牌，信息收益不等于夺牌；回合末必须实际使用至少两张手牌才能补普通牌。'],
 ['把风吹成号角','warm',6,'应援',[
  S('吹奏接力','active',[T('give_hand'),gain('vt09_号角',P('号角余响',[E('passive_range')]),'target','source_turn')]),
  S('枫声','after_play_attack',[T('break_shield'),E('draw')])],[
  card('铜管部的乐谱',[T('give_hand'),E('draw',2),E('range',2)],'送出乐谱后整备，不直接攻击。'),
  card('枫红的安可',[G(2,'allies',[T('draw'),T('shield')]),E('scry_mind',2)],'只照应至多两位伙伴，同系列对抗中仍按现行阵营判断。')],
  '送出手牌才能授予到自己下回合开始的临时射程；枫声只削已有护盾，不能化为额外伤害。'],
 ['夜深仍有一盏灯','cool',6,'储备',[
  P('长夜值守',[E('passive_hand'),E('passive_draw')]),
  S('存档点','active',[Q(['留下存档',[B(test('hand','ge',1),[store('夜航'),E('shield')])]],['继续旅途',[B(pile('夜航'),[take('夜航'),E('draw')])]])],cost(1))],[
  card('夜班备忘录',[E('draw',3),E('shield')],'先放置，等一个自身回合后再主动卸除兑现。',{kind:'persistent',maturityTurns:1}),
  card('漫长旅途的存档',[B(test('hand','ge',2),[store('夜航',2),E('draw',2)],[E('scry',2)])],'有两张手牌才储存并补两张，否则仅观星二。')],
  '被动多摸一张但没有免费攻击；存档或读取都弃一张手牌，同回合不能两边都做。'],
 ['柔声中的可靠伙伴','cool',6,'支援',[
  S('抚声','active',[T('give_hand'),T('heal'),E('draw')]),
  S('伴眠','before_take_damage',[E('event_reduce_damage')],{...cost(1),optional:true})],[
  gear('熟悉的猫抱枕','armor',[E('equip_shield'),E('equip_distance')],'每周期一盾与距离门槛，不是免疫。'),
  card('晚安电台',[G(2,'everyone',[T('heal'),T('shield')]),E('draw')],'轻量群体支援有两目标上限。')],
  '治疗要真实赠牌；减伤在扣血前选择发动并弃手牌，每个自身回合周期一次。'],
 ['胜负未分的红瞳','warm',7,'竞技',[
  S('竞技邀约','active',[T('duel',1,{color:'warm'})],cost(1)),
  S('越战越醒','after_deal_damage',[E('draw'),E('range')])],[
  card('红蝠筹码',[D([E('extra_attacks'),E('draw')],[E('scry',2)])],'拼点赢取的是攻击额度，仍需后续攻击牌。'),
  card('不屈的赛点',[E('lose_health'),T('duel',1,{color:'warm'}),E('draw',2)],'自损再决斗；交替出攻击，有明确放弃出口。')],
  '决斗先弃一张手牌，伤害后补给每周期一次；七体力补偿必须近身消耗攻击资源的玩法。'],
 ['最初舞台的星愿','cool',6,'支援',[
  P('启程之星',[E('passive_hand',1,'allies')]),
  S('舞台灯火','turn_start',[G(1,'everyone',[T('draw'),T('shield')])])],[
  gear('星愿话筒','gadget',[E('equip_draw')],'从下次自身回合起获得一张普通牌。'),
  card('起点之光',[G(2,'everyone',[T('heal'),T('draw')]),E('shield')],'帮助两个不同角色，不因人数多而扩大到全桌。')],
  '光环只增加伙伴的手牌上限；每周期单目标补给，角色心坏或离场时光环立即失效。'],
 ['精英的樱色偶然','warm',6,'判定',[
  S('精英直觉','active',[J('red',[E('draw',2)],[E('shield')])]),
  P('再来一次喵',[E('passive_retrial',1,'self',{filter:'black',zone:'hand',exchange:false})])],[
  card('樱色御守',[E('scry',2),J('heart',[E('heal',2)],[E('draw')])],'先排两张普通牌，之后的判定仍可被其他角色改判。'),
  card('精英偶发事件',[gain('vt14_灵光',P('偶然的灵光',[E('passive_range',2)]),'self','turn'),E('draw')],'短时射程在当前全局回合末到期，不提供永久能力。')],
  '红判定才多摸牌；改判必须付出黑色手牌且不拿回旧判定牌，不能无成本决定所有结果。'],
 ['好友与狐影之间','cool',6,'应变',[
  S('好狐守望','before_take_damage',[E('event_reduce_damage')],{...cost(1),optional:true,condition:'hand_low'}),
  S('白上与狐影','active',[Q(['白上好友',[gain('vt15_白',P('白狐掩护',[E('passive_distance_in')]))]],['黑上认真',[gain('vt15_黑',P('黑狐锐意',[E('passive_attacks')]))]])],cost(1,1))],[
  card('朋友的徽章',[T('give_hand'),E('draw',2),T('shield')],'交付一张手牌获得双方整备。'),
  card('狐与影的捉迷藏',[Q(['追逐',[T('steal_hand'),E('shield')]],['歇息',[E('draw'),E('recover_mind')]]),E('scry',2)],'两种狐狸姿态对应资源或守备，不能同时拿取。')],
  '低手牌减伤仍需弃牌；临时白/黑能力付双资源，下一自身回合开始失效，基础颜色不因狐影自动改变。'],
 ['一人的海色舞台','cool',5,'竞技',[
  S('独自专注','active',[D([E('extra_attacks'),E('draw')],[E('scry',2)])],cost(1)),
  S('海洋女仆','on_defend',[E('recover_mind'),E('draw')])],[
  gear('海洋缎带','armor',[E('equip_distance'),E('equip_shield')],'持续距离配合下回合护盾，入场不立即获盾。'),
  card('独舞开场',[gain('vt16_独舞',P('独舞节奏',[E('passive_attacks')]),'self','turn'),E('scry',2)],'当前回合临时多一次攻击，仍受手牌和距离限制。')],
  '拼点另付一张手牌，输了只整理牌顶；防御成功回收记忆但每周期仅一次，以五体力换取防守收益。'],
 ['把掌声传到场边','warm',6,'应援',[
  S('全力应援','active',[G(2,'others',[T('draw'),T('shield')])],cost(1)),
  P('经理视野',[E('passive_range',1,'allies')])],[
  gear('场边应援哨','gadget',[E('equip_range',2)],'让自己的支援位置更灵活，不改变座次。'),
  card('经理的战术板',[G(2,'others',[T('draw',2)]),E('shield')],'至多给两位他人补牌，不能自取四张。')],
  '主动为别人补给且最多两位；光环只有射程，没有增伤或额外攻击。'],
 ['面包店外的闯关夜','warm',7,'耐久',[
  S('耐久开播','turn_end',[E('draw',2)],{condition:'hand_low'}),
  S('手指借一下','active',[T('sequester_hand'),A('warm')],cost(1,1))],[
  card('烘焙后的纸袋',[E('recover_mind'),E('draw',2)],'回收一张既有心象并补普通牌。'),
  card('不睡的闯关者',[E('draw',3),E('extra_attacks')],'等待一个自身回合，主动卸除换后续攻势。',{kind:'persistent',maturityTurns:1})],
  '耐久只在弃牌后手牌不超过二时补到最多四；暂置仅一张且全局回合末归还，发动付双费用。'],
 ['彗星划过连成星轨','cool',5,'连段',[
  S('星轨','after_play_card',[M('星轨'),E('draw')],{condition:'same_rank_or_suit'}),
  S('彗星一闪','active',[R('星轨',2),E('extra_attacks'),E('attack_bonus'),E('range')],{...cost(1),condition:has('星轨',2)})],[
  card('彗星轨道图',[E('scry',3),E('range',2),E('draw')],'整理与射程服务于之后的真实连牌。'),
  card('天球仍在闪耀',[M('星轨',2),E('scry_mind',2),E('extra_attacks')],'专属牌只提供标记和额度，不直接制造命中。')],
  '每周期最多通过连牌得一标记，积两枚再弃一牌爆发；五体力、实际攻击牌与颜色规则共同限制峰值。'],
 ['不要停止这首歌','cool',6,'联动',[
  S('开拓接力','after_play_event',[G(1,'others',[T('draw')])]),
  S('不停的歌','turn_end',[E('recover_mind'),E('shield')])],[
  card('开拓者的合唱',[G(2,'others',[T('draw')]),E('shield')],'将资源分给不同创作者，以有限目标表达联动。'),
  card('分岔路仍有歌',[Q(['继续开拓',[E('recover_mind',2),E('scry',2)]],['停驻休整',[E('draw',2),E('heal')]])],'选择储备或即时补给，取材时间止于 2022 年。')],
  '自己用事件才为他人补牌；稳定回收仅一张，没有主动多段进攻或无限音符循环。'],
 ['兔洞另一端的笑声','warm',5,'扰动',[
  S('兔式恶作剧','active',[T('sequester_hand'),E('range'),E('draw')],cost(1)),
  S('因果回旋','before_take_damage',[J('red',[E('event_cancel')],[E('draw')])],{...cost(1),condition:'event_attack',optional:true})],[
  gear('胡萝卜工房','gadget',[E('equip_range'),E('equip_draw')],'准备下一轮的工具，没有入场循环收益。'),
  card('兔洞连环计',[T('steal_hand'),A('warm'),E('scry',2)],'随机取得一张手牌后才攻击，仍允许正常防御。')],
  '暂置仅一张且会归还；攻击前判定付牌且可能失败，整周期最多一次，五体力保留被集火的风险。'],
 ['还没出海的船长','warm',7,'储备',[
  S('船长密藏','active',[store('宝藏',1,'public'),E('draw')],{condition:test('hand','ge',1)}),
  S('扬帆','active',[take('宝藏'),E('range',2),A('warm')],{...cost(1),condition:pile('宝藏')})],[
  card('海盗藏宝图',[E('scry',3),E('draw_discard',2)],'取得既有普通弃牌，牌堆不足时取尽。'),
  card('船长的收集册',[B(test('hand','ge',2),[store('宝藏',2,'public'),E('draw',2)],[E('scry',2)])],'有两张手牌才公开储存并补牌，否则仅观星二；不凭空制造财宝。')],
  '先存牌再出航，取回牌要额外弃手牌；两项主动各周期一次，储存牌可被公开检查。'],
 ['把早安带到彼岸','warm',6,'联动',[
  S('晨间栏目','turn_start',[G(2,'others',[T('draw')]),E('scry',2)],cost(1)),
  S('跨语之桥','active',[T('give_hand'),gain('vt23_桥',P('彼岸的余裕',[E('passive_hand')]),'target','source_turn')],cost(0,1))],[
  card('晨间新闻台',[E('scry',3),E('draw',2)],'整理节目顺序再取资源。'),
  card('跨海的握手',[T('give_hand'),G(2,'others',[T('draw')]),E('shield',2)],'真实赠牌与最多两位听众，不按全桌人数无限增益。')],
  '晨间栏目先支付一张手牌，桥梁再付心象和赠牌；临时技能下回合到期，只增加容量。'],
 ['晚安之后仍有安可','warm',6,'耐久',[
  S('绵云','before_take_damage',[E('event_reduce_damage')]),
  S('再唱一首','dying',[E('heal',2),E('draw')],{...cost(1,1),limitScope:'game',optional:true})],[
  gear('绵云外套','armor',[E('equip_shield',2)],'每周期二盾，不是每次受伤减二。'),
  card('夜色中的安可',[E('recover_mind',2),E('heal'),E('draw')],'回收与治疗保留现有心坏、上限和已耗区规则。')],
  '绵云每个自身回合周期只挡一点；自救整局一次且付双费用，不能突破无色伤害削去的身体上限。'],
 ['从亚特兰蒂斯游来','cool',7,'机动',[
  S('鲨浪','active',[A('cool'),E('cycle_mind')],cost(1)),
  P('潜潮',[E('passive_distance_out'),E('passive_hand')])],[
  gear('潮蓝三叉戟','weapon',[E('equip_range',2),E('equip_damage')],'射程和周期一次增伤共用装备次数，不因重新装备重置。'),
  card('浪头的回应',[T('break_shield',2),A('cool'),E('draw')],'先削现有护盾再发动可防御的一点冷色攻击。')],
  '机动只改变攻击距离和手牌上限；鲨浪弃牌且遵守冷色合法目标，每周期只发动一次。'],
 ['把灵魂写成韵脚','cool',6,'连段',[
  S('押韵','after_play_card',[E('draw',2)],{condition:'same_rank_or_suit'}),
  S('魂音采样','active',[E('draw_discard'),A('cool')],cost(1))],[
  gear('低频录音机','gadget',[E('equip_draw')],'素材在下次回合开始才出现。'),
  card('魂之押韵',[E('draw_discard',2),E('extra_attacks'),E('scry',2)],'弃牌采样与攻击额度需后续真实出牌来兑现。')],
  '押韵须连续两张同点数或花色，每周期一次；采样只取已有普通弃牌，不制造新牌或复活角色。'],
 ['下一秒的侦探','cool',7,'时间',[
  S('细查','active',[T('inspect_hand',2),E('scry',2)],cost(1)),
  S('时间回拨','active',[E('extra_turn')],{...cost(1,2),limitScope:'game',condition:'played_two'})],[
  card('侦探的怀表',[E('scry',3),E('draw',2)],'先装备并等待一个自身回合，再主动兑现线索。',{kind:'persistent',maturityTurns:1}),
  card('时间线上的足迹',[T('inspect_hand',2),E('cycle_mind',2),E('draw_mind')],'调整自己的心象路线，抽取仍消耗储备。')],
  '全局回合内实际用两张牌后才能付一手牌与两心象；额外回合整局一次，之后恢复正常座次，重连不刷新。'],
 ['温柔涂满深海','cool',6,'储备',[
  S('绘卷','active',[store('绘卷',2),E('draw')],{condition:test('hand','ge',2)}),
  C('古书的余白','hand','defense',{limit:0,conversion:{from:'hand',to:'defense',zone:'pile',pile:'绘卷'}})],[
  card('未完成的绘本',[E('scry_mind',3),E('draw_mind'),E('draw')],'先安排记忆，再把一张加入手牌；抽空仍会心坏。'),
  card('深海画室',[B(test('hand','ge',2),[store('绘卷',2),E('draw',2),E('shield')],[E('scry',2)])],'有两张真牌才储存并补给，否则仅观星二；不能空放素材。')],
  '绘卷一次需要两张实体手牌，换一张普通牌；不限次数防御只能消耗绘卷库存，不能空放。'],
 ['不可解的羽化','cool',6,'成长',[
  S('声纹','after_play_event',[M('声纹')]),
  S('羽化','turn_start',[E('lose_max_hp'),R('声纹',3),gain('vt29_羽化',P('羽化之声',[E('passive_draw')]),'self','permanent')],{condition:has('声纹',3),limitScope:'game'})],[
  card('鸟羽讯号',[E('scry',3),E('recover_mind'),E('shield')],'整理与回收表现歌声留下的线索。'),
  card('不可解的回声',[M('声纹'),G(2,'everyone',[T('draw'),T('heal')])],'加速一枚声纹并有限照应，不直接完成觉醒。')],
  '每周期事件最多积一声纹；三枚后整局只觉醒一次，失去一点体力上限才获得长期额外摸牌。'],
 ['红色声部跃出舞台','warm',6,'连段',[
  S('热唱','after_play_card',[E('extra_attacks'),E('shield')],{condition:'same_rank_or_suit'}),
  S('绯愿','active',[T('give_hand'),E('draw',2)])],[
  card('绯红节拍',[E('range',2),E('draw',2),E('shield')],'准备舞台节奏，不自动造成伤害。'),
  card('热源变奏',[T('give_hand'),E('draw',2),E('extra_attacks')],'送出一张牌才能获得连段资源。')],
  '热唱必须连续匹配两张实体牌且每周期一次；绯愿给别人一张才摸二，不强制绑定组合搭档。'],
 ['蓝色声部托住和声','cool',6,'判定',[
  S('共振','active',[J('red',[G(2,'others',[T('draw')])],[E('shield')])]),
  S('沉静和声','before_take_damage',[E('event_reduce_damage')],{...cost(1),optional:true})],[
  card('苍蓝声部',[J('black',[E('shield',2)],[E('heal')],true)],'黑色成功才获得判定牌；没有重复判定链。'),
  card('并行歌声',[G(2,'others',[T('shield'),T('draw')]),E('cycle_mind',2)],'帮助他人后改变自己的记忆顺序。')],
  '共振成功为他人补牌，失败只有自盾；和声减伤付手牌并每周期一次，与红色声部互补但可独立使用。'],
 ['里山连起二十四时','warm',7,'联动',[
  S('企划接力','active',[G(2,'others',[T('draw')]),E('draw')],cost(1)),
  P('里山忍法',[E('passive_distance_in')],'no_equipment')],[
  card('里山便当',[E('draw',2),E('heal')],'自身休整不额外操纵对手。'),
  card('二十四时接力',[G(3,'others',[T('draw'),T('shield')])],'至多三位不同角色；十二点预算为本弹单牌上限。')],
  '企划付牌、最多两名他人受益；无装备时才有距离掩护，装入强器材会失去忍法保护。'],
 ['小小花生的大韵律','warm',5,'换牌',[
  S('豆式重混','active',[E('draw','paid_hand'),B('paid_all_hand',[E('extra_attacks')],[E('scry')])],{cost:{hand:'chosen',mind:0}}),
  S('升拍','after_play_card',[E('shield'),E('draw')],{condition:'same_rank_or_suit'})],[
  card('豆式涂鸦',[E('scry',3),E('draw_discard',2)],'从现成素材取样，不创建新的普通牌。'),
  card('韵脚大交换',[T('exchange_hands'),E('draw')],'整体交换保留心象原主人，不能挑走特定暗牌。')],
  '弃几张只摸几张，弃掉支付前全部非空手牌才加攻击额度；五体力与每周期一次限制换牌发动。'],
 ['让陪伴继续发光','warm',5,'支援',[
  S('心灯','after_damage',[M('心灯'),E('draw')]),
  S('连播的约定','active',[R('心灯',2),G(2,'everyone',[T('heal'),T('draw')])],{...cost(1,1),condition:has('心灯',2)})],[
  gear('粉色舞台灯','gadget',[E('equip_draw')],'稳定补给从下个自己回合才生效。'),
  card('心灯不灭',[B(has('心灯',2),[R('心灯',2),E('heal',3)],[E('draw',2)]),E('shield')],'满足标记门槛才强化治疗，否则仅普通整备。')],
  '实际受伤后每周期才得到一灯；两灯加双资源换两目标照应，五体力防止无代价堆资源。'],
 ['古城传来的声音','cool',6,'谈判',[
  S('言灵抉择','active',[E('choose',1,'target',{options:[{label:'暂置一张至回合末',effects:[T('sequester_hand')]},{label:'随机弃一张',effects:[T('discard_hand')]}]}),E('draw')],cost(1)),
  S('旧世回声','turn_end',[E('recover_mind'),E('scry_mind',2)])],[
  card('古城的低语',[T('inspect_hand',2),T('sequester_hand')],'调查和暂置分别随机，不保证暂置看见的牌。'),
  card('声音的契约',[E('choose',1,'target',{options:[{label:'让来源摸二张',effects:[E('draw',2)]},{label:'自己弃二张，来源摸一张',effects:[T('discard_hand',2),E('draw')]}]}),E('scry',2)],'由目标选择付出方式，来源不能替对方决定。')],
  '对手在暂置与弃牌中选择；主动先弃一张牌，暂置会归还。回合末回收仅一张，不存在永久封手。'],
 ['日常里的一点关怀','cool',6,'看护',[
  S('虚拟看护','active',[T('heal',2),E('draw')],cost(1)),
  P('红色药笺',[E('passive_retrial',1,'self',{filter:'red',zone:'hand',exchange:false})])],[
  gear('兔兔医箱','armor',[E('equip_shield'),E('equip_distance')],'防护只有回合盾和距离，不替代回复规则。'),
  card('小小生日宴',[G(2,'everyone',[T('heal'),T('draw')]),E('scry')],'只照应两位存活角色，不复活已离场者。')],
  '治疗支付手牌且不能恢复无色伤害损失的身体上限；红色改判消耗真牌，不回收旧判定牌。']
];
if(people.length!==36||designs.length!==people.length)throw Error('Roster/design count mismatch');
const raw={version,contentPacks:[],presets:[],skillTemplates:[],mindTemplates:[],arts:[],characterNotes:{}};
const research={checkedAt:'2026-09-30',window:{from:2017,to:2022},policy:'按 2017—2022 年的公开创作、正面观众记忆或行业影响选取，非排名、非穷尽名单。绊爱在 2016 年末出道，但入选依据落在时间窗内。现有资料页面可能更新到 2026 年，本文不将后来的成就追记进本弹。仅使用公开虚拟身份，不搜集表演者真实身份。',records:[]};
for(let i=0;i<people.length;i++){
 const [name,aliases,branch,era,sourceUrl,summary,selectionReason]=people[i];
 const [title,color,hp,role,skills,cards,designReason]=designs[i];
 const id='vt_'+String(i+1).padStart(4,'0');
 const support=['支援','应援','联动','看护'].includes(role);
 const base=[support?'recover':'attack_cool',support?'life':'attack_cool','attack_warm',support?'mana':'attack_warm','evade','haste','evade','miracle','mana','life','amplify','energy','recover'];
 const deck=base.map((type,j)=>({rank:j+1,type}));
 const skillTemplateIds=[],mindTemplateIds=[];
 skills.forEach((skill,j)=>{
  skill.key=id+'_s'+(j+1);
  const tid=id+'_skill_'+(j+1);skillTemplateIds.push(tid);
  raw.skillTemplates.push({id:tid,name:skill.name,game:'原创设计',expansion,reference:designReason,designReason,description:designReason,tags:[series,name,aliases,branch,role],skill});
 });
 cards.forEach(({reason,...body},j)=>{
  const custom={...body,series,fallback:j?'mana':'recover',...(j?{characterId:id}:{})};
  const tid=id+'_mind_'+(j+1);mindTemplateIds.push(tid);
  raw.mindTemplates.push({id:tid,name:custom.name,series,expansion,reference:reason,designReason:reason,description:name+'主题心象。'+(j?'绑定此角色。':'同系列共享。')+reason,tags:[series,name,aliases,branch,role,custom.kind??'event'],card:custom});
  deck[j?8:4]={rank:j?9:5,type:'custom',custom:structuredClone(custom)};
 });
 raw.presets.push({id:'preset_'+id,rulesVersion:version,name:series+'·'+name,character:{id,name,title,series,color,hp,art:id,flipColor:null,skills},deck});
 raw.arts.push({id,name,url:`assets/characters/${id}.png`});
 raw.characterNotes[id]={summary:summary+' 入选：'+selectionReason,reason:designReason,designReason,sourceUrl,sourceUrls:[sourceUrl],branch,aliases,era,role,selectionReason,skillReferences:[designReason],cardReferences:cards.map(c=>c.reason),skillTemplateIds,mindTemplateIds,researchTags:[aliases,branch,role,'VTuber','2017—2022'],sourcePath:'docs/content/vtuber-research.json#'+id};
 research.records.push({id,name,aliases,branch,era,summary,selectionReason,sourceUrls:[sourceUrl],visualPolicy:'参考公开虚拟形象重新生成独立构图；资料包含历史设计图与官网标准形象。2017—2022 是选角和事迹的时间窗，不将现行官网立绘的服装发布日期一概断言为该时期。场景、动作和道具组合为本项目创作诠释。'});
}
const moreSources={
 vt_0004:['https://zh.wikipedia.org/wiki/電腦少女小白'],vt_0005:['https://bangumi.tv/character/61401'],vt_0006:['https://kyodonewsprwire.jp/release/201907299189'],
 vt_0007:['https://www.tunecore.co.jp/artists/azuma_lim'],vt_0008:['https://prtimes.jp/a/?c=60989&f=d60989-115-abecc42af576534e38c1eeaf5c8824ae.pdf&r=115'],
 vt_0011:['https://prtimes.jp/a/?c=5875&f=d5875-4832-7d441245124d15f3d9e4fc7fe27bc828.pdf&r=4832'],vt_0012:['https://www.nijisanji.jp/events/kuzuha_bloodyalexandrite/'],
 vt_0019:['https://stellarintothegalaxy.hololive.tv/news'],vt_0020:['https://game.watch.impress.co.jp/docs/news/1313278.html'],vt_0029:['https://kaf.kamitsubaki.jp/history/'],
 vt_0032:['https://osharepeanuts.com/'],vt_0034:['https://www.ironmousemodelindex.com/model0001'],vt_0035:['https://www.nijisanji.jp/en/talents/l/vox-akuma'],vt_0036:['https://natorisana.com/about/']
};
for(const [id,urls] of Object.entries(moreSources)){raw.characterNotes[id].sourceUrls.push(...urls);research.records.find(r=>r.id===id).sourceUrls.push(...urls);}
raw.contentPacks.push({id:'vtuber-summons-01',name:expansion,description:'2017—2022 年记忆中的 36 位 VTuber，含 Azuma Lim；72 组原创角色技能、72 张主题心象。复用 0.5 实装拼图，系列阵营统一为“电拟神召”。',characterIds:raw.presets.map(p=>p.character.id),sources:[...new Set(research.records.flatMap(r=>r.sourceUrls))]});
const validation=spawnSync('php',[path.join(root,'tools/normalize-vtuber-content.php')],{input:JSON.stringify(raw),encoding:'utf8',maxBuffer:16*1024*1024});
if(validation.status!==0)throw Error(validation.stderr||validation.stdout);
const {pack,summaries}=JSON.parse(validation.stdout);
for(const r of research.records)r.balance={role:raw.characterNotes[r.id].role,characterBudget:summaries[r.id].budget.character,cardBudgets:summaries[r.id].budget.cards,reason:raw.characterNotes[r.id].designReason};
const write=(file,data)=>fs.writeFileSync(path.join(root,file),typeof data==='string'?data:JSON.stringify(data,null,2)+'\n');
write('src/content/vtuber-summons.json',pack);write('docs/content/vtuber-research.json',research);
const ops=new Set();const walk=list=>list.forEach(e=>{ops.add(e.op);for(const k of ['then','else','effects'])if(e[k])walk(e[k]);for(const o of e.options??[])walk(o.effects);if(e.skill)walk(e.skill.effects);});
pack.presets.forEach(p=>p.character.skills.forEach(s=>walk(s.effects)));pack.mindTemplates.forEach(t=>walk(t.card.effects));
write('docs/content/VTUBER_SUMMONS.md',[
 '# 电拟神召 · 第一弹','',pack.contentPacks[0].description,'',
 '## 选角与时代边界','',research.policy,'',
 '这是一份策展式名单，不将不同观众的好感假定为一致。所属分类仅方便检索，不构成游戏中的公司阵营。田中姬、铃木雏各算一人；形象版本与同一虚拟角色不重复计数。配图为 AI 二次创作，未直接把官方立绘当卡图。','',
 '| 角色 | 活动截面 | 入选理由 | 资料 |','| --- | --- | --- | --- |',
 ...research.records.map(r=>`| ${r.name} | ${r.era} | ${r.selectionReason} | ${r.sourceUrls.map((u,i)=>`[${i+1}](${u})`).join(' / ')} |`),'',
 '## 技能与强度约束','',
 `本弹复用 ${ops.size} 种既有效果积木，不增加专用解释器或角色硬编码。全部人物预算 ≤18，单张心象 ≤12、两张合计 ≤24；以 PHP 服务端实际校验值为准。默认触发与主动技能均按“自己的回合周期”计一次，持续技能及注明的不限次转化除外；这避免六人桌反应技每个对手回合反复获得收益。`,'',
 '预算只是准入线。五体力角色承载较强连段、干扰或资源发动，六体力角色承担一般支援与储备，七体力角色以更直接或整局限定的能力为主；费用、等待时间、合法目标、心坏与普通牌供给共同限制强度。没有以机器人胜率冒充真人平衡结论。','',
 '| 角色 | 定位 / 体力 | 两项技能 | 人物预算 | 心象预算 | 设计与反制 |','| --- | --- | --- | --- | --- | --- |',
 ...pack.presets.map(p=>{const c=p.character,n=pack.characterNotes[c.id],b=summaries[c.id].budget;return `| ${c.name} | ${n.role} / ${c.hp} | ${c.skills.map(s=>s.name).join(' / ')} | ${b.character}/18 | ${b.cards.join(' + ')} | ${n.designReason} |`;}),'',
 '## 逐人可执行规则','',
 ...pack.presets.flatMap(p=>{const n=pack.characterNotes[p.character.id];return [`### ${p.character.name}（${n.aliases}）`,'',...summaries[p.character.id].skills.map((s,i)=>`- **${p.character.skills[i].name}**：${s}`),''];}),
 '## 配套心象','',
 '每人第一张可在“电拟神召”系列内共享，第二张绑定稳定角色 ID。绑定的标记与牌堆不能靠改显示名偷换；借用别人的立绘也不取得该身份。失配按 fallback 普通牌使用。涉及专属牌堆的牌只移动已有实体牌，保留原主人。','',
 '| 角色 | 共享心象 | 角色绑定心象 |','| --- | --- | --- |',
 ...pack.presets.map(p=>{const cards=p.deck.filter(c=>c.type==='custom').map(c=>c.custom);return `| ${p.character.name} | ${cards[0].name} | ${cards[1].name} |`;}),'',
 ...pack.mindTemplates.map(t=>`- **${t.name}**：${t.designReason}`),'',
 '## 特别需要观察的组合','',
 '- Azuma Lim 的旅程只在第一张主动牌后积累，赠予路线必须真实交牌；没有把失败经历做成惩罚。',
 '- 花谱觉醒需要三枚声纹并失去一点体力上限；获得技能整局一次，移除再获得和重连均不刷新次数。',
 '- Amelia 的额外回合整局一次，支付两张心象可能接近心坏；复制者按引擎记录保有自己的限定次数，不能借复制刷新同源技能。',
 '- Ina 的不限次数防御消耗私密绘卷库存；未来明、静凛和玛琳的专属牌堆同样保存真实牌与可核对的数量。',
 '- 判定与改判会打开真实响应窗；佩克拉在伤害前取消本次攻击伤害，判定失败不会假装已经闪避。',
 '- 多目标支援有两至三人上限；支援给敌方也可能有策略用途，由玩家自行选择。',
 '- 回收心象、治疗、无色伤害、颜色合法目标和系列胜负都沿用基础规则。角卷绵芽的濒死技能无法保证在身体上限已归零或心坏时救回。','',
 '## 重建与美术','',
 '- `node tools/build-vtuber-content.mjs` 重建内容、研究索引与本文；生成时调用 PHP 验证与规范化，失败不写入输出。',
 '- 全部技能可从“电拟神召 · 第一弹”拼图库载入并继续编辑；没有新增游戏运行依赖。',
 '- 本地图路径为 `public/assets/characters/vt_0001.png` 至 `vt_0036.png`。逐人提示词、公开参考网址与生成记录见 `vtuber-art-prompts.json`，尺寸与哈希见 `vtuber-art-manifest.json`。',
 '- 自动验收见 `tests/vtuber-content.php` 与 `tests/vtuber-mechanics.php`；实际运行结果另记入 `docs/TESTING.md`。',''
].join('\n'));
console.log(`Built ${pack.presets.length} VTubers, ${pack.skillTemplates.length} skills, ${pack.mindTemplates.length} mind cards; ${ops.size} effect operations; character budgets ${Math.min(...Object.values(summaries).map(s=>s.budget.character))}–${Math.max(...Object.values(summaries).map(s=>s.budget.character))}.`);
