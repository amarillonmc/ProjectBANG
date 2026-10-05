'use strict';
function createArenaUI({S,esc,btn,opt,colors,phases,rank,cardName,portrait,allBuilds,selectedBuild,botBuildOptions,buildPayload,identitySetup,portfolio,tableUI,showModal,api,run,ensureSession,useRoom,navigate,uid}) {
  const $=q=>document.querySelector(q);
  let picks=[];
  function form(){
    picks=[];
    showModal('AI 斗蛐蛐 · 配置参赛角色',`<p>你来选角，机器人完成全部行动与响应。冷暖、系列、人狼身份均可观战。</p><div class="form-grid"><label>对局模式<select id="arena-mode" aria-label="斗蛐蛐模式">${opt(S.catalog.modes,'color')}</select></label><label>机器人数量<select id="arena-count" aria-label="斗蛐蛐机器人数量"></select></label></div><div id="arena-bots" class="practice-bots"></div><p id="arena-setup-hint" class="notice" role="status"></p><p class="help">自创角色请先保存。观战不会产生作者的实战验证记录。</p>${portfolio.limitsFields()}`,btn('开始斗蛐蛐 →','arena-create',''));
    modeChanged();
  }
  function modeChanged(){
    const mode=$('#arena-mode').value,limits=S.catalog.modeRules[mode],count=mode==='identity'?5:2;
    $('#arena-count').innerHTML=opt(Object.fromEntries(Array.from({length:limits.maxPlayers-limits.minPlayers+1},(_,i)=>[i+limits.minPlayers,`${i+limits.minPlayers} 名机器人`])),count);
    picks=[];renderBots();
  }
  function renderBots(){
    const n=Number($('#arena-count').value),mode=$('#arena-mode').value,first=selectedBuild();
    const opponent=S.catalog.presets.find(b=>mode==='series'?b.character.series!==first.character.series:b.character.color!==first.character.color)||S.catalog.presets[0];
    for(let i=0;i<n;i++)if(!picks[i])picks[i]=(mode==='identity'?S.catalog.presets[i%S.catalog.presets.length]:i%2?opponent:first).id;
    $('#arena-bots').innerHTML=Array.from({length:n},(_,i)=>`<div class="bot-setup"><label>机器人 ${i+1}<select data-arena-seat="${i}" aria-label="斗蛐蛐机器人 ${i+1} 构筑">${botBuildOptions(picks[i])}</select></label><div class="bot-preview" data-arena-preview="${i}"></div></div>`).join('');
    summary();
  }
  function summary(){
    const n=Number($('#arena-count').value),mode=$('#arena-mode').value;
    for(let i=0;i<n;i++){const c=allBuilds().find(b=>b.id===picks[i]).character;$(`[data-arena-preview="${i}"]`).innerHTML=`${portrait(c,'seat-portrait')}<div><span class="color-label ${c.color}">${colors[c.color]}</span><p>${esc(c.series)} · ${c.hp} 体力</p></div>`;}
    $('#arena-setup-hint').textContent=mode==='identity'?identitySetup(n)+'。机器人只知道自身身份，依据公开行动判断。':mode==='series'?'按系列分队，至少选择两个不同系列；同色的不同系列也会相互对抗。':'按冷暖分队，至少选择两个对抗阵营；无色角色各自独立。';
  }
  async function create(){
    const payload={arena:true,name:'AI 斗蛐蛐',mode:$('#arena-mode').value,bots:picks.slice(0,Number($('#arena-count').value)).map(buildPayload),budgetLimits:portfolio.readLimits()};
    await ensureSession();const key=JSON.stringify(payload);
    if(!S.createRequest||S.createRequest.key!==key)S.createRequest={key,id:uid()};
    const room=await api('create_room',{...payload,requestId:S.createRequest.id});S.createRequest=null;
    S.selectedCard=S.selectedEquipment=S.selectedSkill=null;S.selectedTarget='';S.selection=[];
    useRoom(room);$('#modal').close();navigate('room');
  }
  function render(){
    const r=S.room,g=r.game,finished=g.status==='finished',speed=r.arenaSpeed;
    const actor=g.players.find(p=>p.id===(g.pending?.player||g.turn));
    const open=[...document.querySelectorAll('.arena-seat details[open]')].map(el=>[el.closest('.arena-seat').dataset.seat,[...el.parentNode.querySelectorAll('details')].indexOf(el)]);
    const focus=document.activeElement?.dataset.arenaSpeed;
    $('#main').innerHTML=`<div class="match-top compact-match"><div><span class="eyebrow">AI 斗蛐蛐 · ${esc(S.catalog.modes[g.mode])} · ${esc(r.code)}</span><h1>让心象，自行交锋。</h1><p>你正在观战 · ${g.players.length} 名机器人 · ${g.players.filter(p=>p.alive).length} 名存活</p></div><div class="button-row">${btn('另开一局','arena-open','secondary small')}${btn('导出复盘','export-game','secondary small')}${btn('返回大厅','go-home','ghost small')}</div></div>
      ${finished?`<section class="winner-banner" role="status"><h2>${esc(g.winner||'对局结束')}</h2><p>第 ${g.turnNumber} 回合结束 · 可导出完整复盘${g.mode==='identity'?' · 全部身份已公开':''}</p></section>`:''}
      <section class="arena-controls panel"><div class="button-row">${Object.entries({paused:'暂停',normal:'正常观战',fast:'快速结算'}).map(([value,label])=>btn(label,'arena-speed',speed===value?'small':'secondary small',`data-arena-speed="${value}" aria-pressed="${speed===value}" ${finished?'disabled':''}`)).join('')}<strong role="status">${finished?'已结算':speed==='paused'?'已暂停':speed==='fast'?'正在快速推进…':'自动进行中'}</strong></div><p class="help">保持页面开启即可自动战至结束；关闭后可从大厅重连续战。当前展示公开信息，手牌和未公开身份仍保密。</p></section>
      <div class="arena-layout"><section><div class="table-status"><strong>${finished?'对局结束':esc(actor?.name||'')+(g.pending?'正在响应':'的回合')}</strong><span>第 ${g.turnNumber} 回合 · ${finished?'已结算':esc(phases[g.phase]||g.phase)}</span><span>牌池 ${g.deckCount} · 弃牌 ${g.discardCount}</span></div>${!finished&&g.pending?`<p class="notice">${esc(g.pending.prompt)}${g.pending.revealed?` · ${esc(rank(g.pending.revealed.rank)+' '+(g.pending.revealed.suit||'◇')+' '+cardName(g.pending.revealed))}`:''}</p>`:''}<div class="arena-seats">${g.players.map(p=>`<div class="arena-seat" data-seat="${esc(p.id)}">${tableUI.player(p,g)}</div>`).join('')}</div></section><aside>${tableUI.ruleHelp(g)}<details class="table-log" open><summary>结算记录 · 第 ${g.turnNumber} 回合</summary><div>${g.log.slice(-40).reverse().map(l=>`<p>${esc(typeof l==='string'?l:l.text)}</p>`).join('')}</div></details></aside></div>`;
    for(const [id,i] of open){const seat=[...document.querySelectorAll('.arena-seat')].find(el=>el.dataset.seat===id);const detail=seat?.querySelectorAll('details')[i];if(detail)detail.open=true;}
    if(focus)$(`[data-arena-speed="${focus}"]`)?.focus({preventScroll:true});
  }
  function handle(action,el){
    if(action==='arena-open'){form();return true;}
    if(action==='arena-create'){run(create);return true;}
    if(action==='arena-speed'){
      const code=S.room.code,speed=el.dataset.arenaSpeed;
      run(async()=>{
        // An already-running poll can finish between the click and this request.
        // Refresh without advancing bots, then retry the same explicit speed choice.
        for(let attempt=0;attempt<3;attempt++){
          try{useRoom(await api('arena_control',{code,revision:S.room.revision,speed}));return;}
          catch(error){
            if(error.status!==409||attempt===2)throw error;
            useRoom(await api('join_room',{code}));
            if(S.room.status==='finished')return;
          }
        }
      });return true;
    }
    return false;
  }
  function change(el){
    if(el.id==='arena-mode'){modeChanged();return true;}
    if(el.id==='arena-count'){renderBots();return true;}
    if(el.dataset.arenaSeat!==undefined){picks[Number(el.dataset.arenaSeat)]=el.value;summary();return true;}
    return false;
  }
  return {render,handle,change};
}
