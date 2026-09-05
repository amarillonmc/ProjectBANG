# Internal implementation contract

PHP 7.4+ compatible (deployment recommended PHP 8.2+), no Composer/Node/server daemon required. Document root public/. Root src/, var/, config.php private. UTF-8 Chinese UI. Namespace Imaginary.

## Engine/catalog (owned by engine agent)
Files src/Catalog.php, src/Rules.php, src/Engine.php, tests/engine.php.
Catalog::all(): array with keys cards (map card type id => definition {id,name,kind,tags,description}), characters (array), presets (array), blocks (metadata), rulesVersion.
Character {id,name,title,series,color:cool|warm|neutral,hp,art (0..3),flipColor:null|cool|warm,skills:array}. Skill {name,trigger:active|turn_start|after_damage|after_attack|on_defend,condition:always|wounded|hand_low,cost:{hand:int,mind:int},effects:[{op:draw|heal|damage|recover_mind|shield|range|attack_bonus,target:self|target,amount:1..3,color:cool|warm|neutral}],limit:1}.
Deck entry {type:ordinary type id OR 'custom',rank:1..13,custom?:{name,series,fallback:type id,effects:[...]}}. A build {id?,name,character,deck:[13 entries]}; ranks must uniquely cover 1..13. Custom card effects use same interpreter. Budget enforced server-side. Rules::validateBuild(array): normalized build or throws InvalidArgumentException; Rules::describeSkill(array):string; Rules::budget(array):array (used,max,warnings). Catalog provides ready legal presets.
Engine::create(array $players,string $mode): array game. Each supplied player {id,name,build,bot:bool}; 2..6 players. mode 'color' or 'series'. Engine::act(array &$game,string $playerId,array $action):void or throw InvalidArgumentException. Engine::view(array $game,string $playerId):array safe perspective. Engine::tick(array &$game):bool advances expired response/turn and bounded bots, returns changed. Engine::botStep(array &$game):bool optional. Engine::finished(array):bool optional, API can inspect status.

Game view {status:'playing'|'finished',mode,turn:player id,phase:'draw'|'play'|'discard'|'response',turnNumber,players:[{id,name,character,hp,maxHp,color,series,alive,broken,mindCount,spentCount,handCount,equipment:[],delayed:[],shield,range}],me:{id,hand:[],mind:[],spent:[],skills:[]},pending:null|object,log:[{text,...}],winner:null|string,deckCount,discardCount,...}. Every physical card {uid,type,name,rank,suit?,origin:'normal'|'mind',owner?,description?,...}; mind hidden to opponents. Response pending identifies who responds and options. View should include legalActions array or actions hints if possible. Engine agent may extend schema and documents changes to others.
Actions: {type:'draw',mind:0|1|2}; {type:'play',card:uid,target:player id?,mode?:string,costCard?:uid,selection?:array}; {type:'skill',index:int,target?:id,costCards?:[uid]}; {type:'equip_use',card:uid,target?:id}; {type:'respond',choice:string,card?:uid}; {type:'end'}; {type:'discard',cards:[uid]}. Responses / subchoices can extend; send changes to UI agent.

## HTTP API/persistence (owned by API agent)
Files src/Store.php, src/bootstrap.php, public/api.php, config.example.php, var/.htaccess, tests/api.php. API JSON POST public/api.php?action=..., header Authorization: Bearer guest token (no cookie auth). GET allowed only for reads. JSON envelope {ok:true,data:{...}} or {ok:false,error:string}. JSON request <=128KiB. SQLite default var/game.sqlite; MySQL config support. Table rows for users, builds, rooms, events/feedback; schema auto-init. Hash guest bearer secrets, no plaintext server token. No public list of rooms or users. Private six-char room code plus user identity. Identity ownership and host authorization on all writes. Token issued by guest action. Lobby membership only returns public builds (not ordered mind decks of others). Game view through Engine::view. Transaction/row locks serialize game operations, expected revision on act, idempotency requestId. Existing own guest token needed to reconnect; users store localStorage.
API actions and payloads:
- guest {name} => {token,user:{id,name}}
- catalog => Catalog::all()
- me => {user,builds:[...]}
- save_build {build} => {build,budget}; validate_build {build} => {build,budget}; delete_build {id} => {}
- create_room {name,mode,buildId,presetId,allowCustom:bool,turnSeconds?:int} => room snapshot
- join_room {code,buildId,presetId} => room snapshot
- room {code} => room snapshot, tick processes bounded auto/bot activity
- choose_build {code,buildId,presetId} => room snapshot (before start)
- add_bot {code,presetId?} => room snapshot (host only)
- leave_room {code} => {} (lobby only; host leaving closes room)
- start {code} => room snapshot (host only, >=2; compatible opponents required)
- act {code,revision,requestId,action} => room snapshot
- feedback {code?,text} => {}; export {code} => {room,event log}, only participants, redact game secrets if unfinished
Room snapshot {code,name,mode,hostId,status:'lobby'|'playing'|'finished',allowCustom,revision,players:[{id,name,bot,character}],game:null|safe view}. API errors HTTP 400/401/403/404/409/429 as appropriate. Exception failures rollback. Rate bounds and no state mutation on unauthorized/stale actions.

## Frontend (owned by frontend agent)
public/index.html, public/assets/app.js, public/assets/style.css only (no framework/bundler). Premium light paper / ink blue & coral editorial game workshop style, Chinese. Brand 时空并错 / 心象实验室. Views lobby/dashboard, room/game, visual build workshop, reference/rules, feedback. Supports real games all engine actions. Intuitive 13-slot ordered deck editor (reorder buttons), character & skills & custom card visual effect blocks, server preview validation, import/export JSON via textarea/file advanced only, examples loading. Save custom, use in test lobby. Session resilient to reload, polling 2s, handle stale revisions/reconnect, identity name. One-click local practice creates room + 1-3 bots then starts. Multiplayer create/join code + bot + start. Selected card details/action mode controls; actual statuses, targeting, responses, discard. Reference covers actual implemented rules. Prevent XSS via textContent/escaping. Mobile responsive, accessible labels.
Artwork will be generated by root as public/assets/mind-atlas.png (2x2 portrait atlas; CSS object-position or background-size:200% 200%; quadrant 0 cool archivist woman,1 warm wanderer man,2 neutral masked performer,3 green engineer) and public/assets/mindscape.png (wide illustration). Graceful gradient until available.

## Scope/rulings
Implement original fixed ordinary 104 cards distribution (typo 出奇不意 normalized 出其不意), attributes defense/evade (防御 is 防守, 回避 is 躲避). Exactly ordered 13 mind ranks A-K. 5 starting normal hand, draw 2 with up to2 mind replacement, play, discard to current effective hp; one attack per turn default range1 seats among alive. Persistent basics mature next own turn. Color damage opposite counters; neutral decreases max body; either zero kills; optional once flip preserve color counters (effective against new color), no flip for body0. Broken disables skills/range/heal until mind replenished. Two fixed modes color/series. Normal deck recycle discard; mind played/spent follows owner. Details absent/ambiguous in original explicitly documented as alpha rulings, not silently attributed to original.
