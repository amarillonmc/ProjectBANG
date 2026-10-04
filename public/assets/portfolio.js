/* Saved works, immutable sharing, and portable player accounts. */
'use strict';
function createPortfolio({S,esc,btn,api,run,ensureSession,refreshMe,navigate,editorStart,renderWorkshop,portrait,skillSentence,cardDescription,showModal,toast,writeLocal,download,useRoom,uid,schedulePreview}) {
  const $ = q => document.querySelector(q);
  const states = {private:'仅自己可见',link:'链接分享中',published:'已投稿'};
  let serial=0, galleryTier='standard', galleryData=null, currentVersion=null, recovery=null;
  const route = v => v==='portfolio'||/^work\/[a-f0-9]{32}$/.test(v)||/^series\/[a-f0-9]{32}$/.test(v);
  const stamp = n => new Date(n*1000).toLocaleString('zh-CN');
  const button = (label,action,id,cls='secondary small',extra='') => btn(label,'works-'+action,cls,`${id?`data-id="${esc(id)}"`:''} ${extra}`);
  const owner = v => S.builds.some(b=>b.id===v.buildId);
  const link = (kind,id) => location.origin+location.pathname+'#'+kind+'/'+id;
  const tierLabel = v => v.tier==='extended'?'超预算创作':'标准预算';
  const budgetText = v => `人物 ${v.budget.character} / ${v.budget.characterMax} · 限定牌 ${v.budget.custom} / ${v.budget.customMax} · 单牌最高 ${Math.max(0,...v.budget.cards)} / ${v.budget.cardMax}`;
  function tags(v) { return `<div class="work-tags"><span class="${v.tier==='extended'?'extended':''}">${tierLabel(v)}</span><span>${v.trial?'✓ 已完成对局':'尚未完成试用'}</span><span>${states[v.visibility]}</span></div>`; }
  function card(v, own=false) {
    return `<article class="work-card">${portrait(v.character,'work-portrait')}<div class="work-copy"><small>VERSION ${v.number} · ${esc(v.author)}</small><h2>${esc(v.character.name)}</h2><p>${esc(v.name)}</p>${tags(v)}<p class="work-cost">${esc(budgetText(v))}</p><div class="button-row">${button(own?'版本与分享':'查看作品','open',v.id)}${own?button('编辑最新版','edit',v.buildId):''}</div></div></article>`;
  }
  function accountBanner() {
    return `<section class="account-banner"><div><b>${S.user?.handle?'账号 @'+esc(S.user.handle):'让角色跟你一起换设备。'}</b><p>${S.user?.handle?'作品已绑定账号，登录即可继续创作。':'作品已存于服务器。绑定账号后，可在其他设备登录找回；现有角色和房间会一并保留。'}</p></div>${button(S.user?.handle?'账号管理':'绑定账号','account')}</section>`;
  }
  async function load() { await ensureSession(); await refreshMe(); return S.portfolioData; }
  function drawPortfolio() {
    const data=S.portfolioData||{works:[],collections:[],shares:[]};
    const tab=S.portfolioTab||'works';
    $('#main').innerHTML=`<div class="page-heading"><div><span class="eyebrow">YOUR CHARACTERS, TOGETHER</span><h1>把心象，留成作品。</h1><p>保存角色，整理系列，把一个确定的版本交给朋友。</p></div>${btn('创作新角色','new-build','small')}</div>${accountBanner()}<div class="work-tabs" role="tablist" aria-label="作品分类">${[['works','我的作品',data.works.length],['collections','作品系列',data.collections.length],['gallery','投稿库','']].map(([key,label,n])=>button(`${label} ${n}`,'tab',key,tab===key?'small':'ghost small',`role="tab" aria-selected="${tab===key}"`)).join('')}</div><div id="portfolio-content"></div>`;
    const content=$('#portfolio-content');
    if(tab==='works') content.innerHTML=data.works.length?`<div class="works-grid">${data.works.map(v=>card(v,true)).join('')}</div>`:`<section class="work-empty"><h2>第一位角色，从这里开始。</h2><p>去工坊保存一个构筑，就能在这里管理版本、安排试用和分享。</p>${btn('进入角色工坊','new-build')}</section>`;
    else if(tab==='collections') content.innerHTML=`<div class="section-heading"><p>把多位角色编入同一组。作品系列不改变对局中的角色所属系列。</p>${button('＋ 新建系列','collection-new')}</div>${data.collections.map(c=>`<section class="panel collection-card"><div class="panel-title"><h2>${esc(c.name)}</h2><span>${c.buildIds.length} 位角色</span></div><p>${esc(c.description||'还没有系列介绍。')}</p><div class="collection-names">${c.buildIds.map(id=>`<span>${esc(data.works.find(w=>w.buildId===id)?.character.name||'已删除作品')}</span>`).join('')}</div><div class="button-row">${button('编辑系列','collection-edit',c.id)}${button('分享当前版本组合','collection-share',c.id)}${button('删除系列','collection-delete',c.id,'ghost small')}</div>${data.shares.filter(s=>s.collectionId===c.id).map(s=>`<div class="series-share"><span>${s.versionIds.length} 位角色的固定快照</span>${button('查看链接','collection-link',s.id,'ghost small')}${button('撤销链接','collection-revoke',s.id,'ghost small')}</div>`).join('')}</section>`).join('')}`;
    else { content.innerHTML=`<p>作者使用此构筑完成过合法对局，胜负不限。试用记录不代表强度平衡。</p><div class="button-row">${button('标准预算','tier','standard',galleryTier==='standard'?'small':'secondary small')}${button('超预算创作','tier','extended',galleryTier==='extended'?'small':'secondary small')}</div><p class="help">超预算作品可以收藏和试用；入场须符合房间声明的三项预算。</p><div id="gallery-results" aria-live="polite"></div>`; drawGallery(); }
  }
  function drawGallery() {
    const el=$('#gallery-results');if(!el)return;
    if(!galleryData) { el.innerHTML='<p>正在读取投稿…</p>';return; }
    el.innerHTML=`<div class="works-grid">${galleryData.versions.map(v=>card(v)).join('')}</div>${!galleryData.versions.length?'<p class="work-empty">这一分类暂时没有投稿。</p>':''}${galleryData.cursor?button('继续加载','gallery-more'):''}`;
  }
  async function fetchGallery(more=false) {
    const tier=galleryTier, data=await api('gallery',{tier,...(more&&galleryData?.cursor?{cursor:galleryData.cursor}:{})});
    if(tier!==galleryTier)return;
    galleryData=more&&galleryData?{...data,versions:[...galleryData.versions,...data.versions]}:data;drawGallery();
  }
  function render() {
    const seq=++serial, view=S.view;
    $('#main').innerHTML='<section class="panel" aria-live="polite">正在整理作品…</section>';
    (async()=>{
      if(view==='portfolio'){await load();if(seq!==serial||view!==S.view)return;drawPortfolio();if(S.portfolioTab==='gallery')await fetchGallery();return;}
      const [kind,id]=view.split('/');
      const data=await api(kind==='work'?'shared_work':'shared_collection',{id},{keepAuth:true});
      if(seq!==serial||view!==S.view)return;
      if(kind==='work'){currentVersion=data.version;drawWork(currentVersion);}
      else $('#main').innerHTML=`<div class="page-heading"><div><span class="eyebrow">A SHARED COLLECTION</span><h1>${esc(data.name)}</h1><p>${esc(data.description)}</p></div>${button('我的作品','home')}</div><p class="help">这个链接保留分享时的角色版本。${data.unavailable?`${data.unavailable} 个版本已被作者撤回或删除。`:''}</p><div class="works-grid">${data.versions.map(v=>card(v)).join('')}</div>`;
    })().catch(e=>{if(seq===serial&&view===S.view)$('#main').innerHTML=`<section class="panel"><h2>暂时无法打开作品</h2><p>${esc(e.message)}</p>${button('重试','reload')}${button('返回我的作品','home')}</section>`;});
  }
  function drawWork(v) {
    const mine=owner(v), b=v.build;
    $('#main').innerHTML=`<div class="page-heading"><div><span class="eyebrow">SAVED CHARACTER · V${v.number}</span><h1>${esc(v.name)}</h1><p>${esc(v.author)} · ${stamp(v.createdAt)} · 规则 ${esc(v.rulesVersion)}</p></div>${button('返回作品库','home')}</div><div class="shared-work"><section class="panel"><div class="shared-character">${portrait(v.character,'shared-portrait')}<div><h2>${esc(v.character.name)}</h2><p>${esc(v.character.title)}<br>${esc(v.character.series)} · ${v.character.hp} 体力</p>${tags(v)}</div></div><p class="work-cost">${esc(budgetText(v))}</p><div class="shared-skills">${v.character.skills.map(s=>`<article><h3>${esc(s.name)}</h3><p>${esc(skillSentence(s))}</p></article>`).join('')}</div></section><aside class="panel work-actions"><h2>这个版本的下一步</h2><p>${v.trial?`已完成对局 ${esc(v.trial.roomCode)}。<br>${stamp(v.trial.completedAt)} · ${v.trial.withBots?'含练习机器人':'玩家对局'} · ${esc(v.trial.winner||'已结束')}`:'先亲自使用它完成一局对局，再投稿到公开作品库。输赢都可以。'}</p>${mine?`${button('用这个版本试用','trial',v.id,'')}${button(v.visibility==='private'?'生成分享链接':'查看分享链接','share',v.id)}${button('投稿到'+tierLabel(v)+'区','publish',v.id,'secondary',v.trial&&v.valid?'':'disabled')}${v.visibility!=='private'?button('撤回投稿与链接','private',v.id,'ghost'):''}${button('版本历史','history',v.buildId)}${button('编辑最新版','edit',v.buildId)}`:`${button('复制到我的作品','copy',v.id,'')}${button('用这个版本试用','trial',v.id)}`}<p class="help">修改技能、牌序等规则内容后，需重新完成试用。只改人物名字、称号或图片可沿用记录。分享链接固定指向本版。</p>${button('下载构筑 JSON','download',v.id,'ghost')}</aside><section class="panel shared-deck"><h2>十三张心象 · 由上至下抽取</h2><ol>${b.deck.map(c=>`<li><b>${({1:'A',11:'J',12:'Q',13:'K'})[c.rank]||c.rank} · ${esc(c.custom?.name||S.catalog.cards[c.type]?.name||c.type)}</b><p>${esc(cardDescription(c))}</p></li>`).join('')}</ol></section></div>`;
  }
  function shareModal(kind,id) { showModal('分享固定版本',`<p>拥有链接的人可以查看并复制这一版作品。之后的修改会另存为新版本。</p><label>分享链接<input id="work-share-url" readonly value="${esc(link(kind,id))}" aria-label="分享链接"></label>`,button('复制链接','copy-link')); }
  function identity() {
    showModal(S.user?.handle?'账号与身份':'保存你的旅人身份',S.user?.handle?`<p>昵称：${esc(S.user.name)}<br>账号：@${esc(S.user.handle)}</p><p>角色、版本与作品系列已保存在服务器。换设备时使用账号和密码登录；忘记密码时使用注册时保存的恢复码。</p>`:`<p>当前昵称：${esc(S.user?.name||'未登记')}</p><p>绑定账号会保留当前身份下的全部角色、作品系列与房间记录。访客凭据在绑定后失效，请保存新的恢复码。</p>`,S.user?.handle?button('退出此设备','logout'):`${button('绑定新账号','register-form',null,'')}${button('登录已有账号','login-form')}${S.token?btn('备份访客身份','export-identity'):''}${btn('导入访客身份','import-identity')}`);
  }
  function authForm(mode) {
    if(S.editorDirty&&mode!=='register'){toast('请先保存或导出工坊中的修改，再切换账号。',true);return;}
    const changing=mode!=='register'&&S.user&&!S.user.handle&&S.builds.length>0;
    showModal(mode==='register'?'为当前旅人绑定账号':mode==='recover'?'用恢复码重设密码':'登录你的账号',`<p>${mode==='register'?'现有作品会自动归入这个账号。账号名用于登录，角色署名仍使用当前昵称。':mode==='recover'?'恢复后会退出所有旧设备，并换发新的恢复码。':'登录后切换到该账号的作品库。'}</p><div class="account-form"><label>账号名<input id="account-handle" aria-label="账号名" autocomplete="username" minlength="3" maxlength="24" placeholder="3～24 位英文字母、数字或下划线"></label>${mode==='recover'?'<label>恢复码<input id="account-recovery" aria-label="恢复码" autocomplete="off" maxlength="48"></label>':''}<label>${mode==='recover'?'新密码':'密码'}<input id="account-password" aria-label="密码" type="password" autocomplete="${mode==='login'?'current-password':'new-password'}"></label>${mode!=='login'?'<label>再次输入密码<input id="account-repeat" aria-label="再次输入密码" type="password" autocomplete="new-password"></label><p class="help">至少 10 个字符，最多 72 字节（中文约 24 字）。</p>':''}${changing?`<p>当前访客有 ${S.builds.length} 份作品。请先备份访客身份，以便日后找回。</p>${btn('下载访客身份备份','export-identity')}<label class="checkbox-row"><input id="account-backed-up" type="checkbox"><span>已备份当前访客身份</span></label>`:''}</div>`,button(mode==='register'?'绑定并保存恢复码':mode==='recover'?'重设密码':'登录','auth-submit',mode,'')+(mode==='login'?button('忘记密码','recover-form'):button('已有账号，去登录','login-form')));
  }
  function clearIdentityState() {
    S.room=null;S.code=null;S.editor=null;S.editorDirty=false;S.editorVersion=null;S.editorEpoch++;S.selectedBuild=null;S.lesson=null;S.portfolioData=null;S.builds=[];S.rooms=[];S.selection=[];S.selectedCard=null;S.selectedSkill=null;S.selectedEquipment=null;S.createRequest=null;
    writeLocal('imaginary.room',null);writeLocal('imaginary.build',null);galleryData=null;currentVersion=null;
  }
  async function authSubmit(mode) {
    const handle=$('#account-handle').value,password=$('#account-password').value;
    if($('#account-repeat')&&password!==$('#account-repeat').value)throw Error('两次密码输入不一致。');
    if($('#account-backed-up')&&!$('#account-backed-up').checked)throw Error('请先备份访客身份，并勾选已备份。');
    if(mode==='register')await ensureSession();
    const response=await api(mode==='register'?'register_account':mode==='recover'?'recover_account':'login',{handle,password,...(mode==='recover'?{recoveryCode:$('#account-recovery').value.trim()}:{})},{keepAuth:true});
    if(mode!=='register')clearIdentityState();
    S.token=response.token;S.user=response.user;writeLocal('imaginary.token',S.token);writeLocal('imaginary.name',S.user.name);await refreshMe();
    if(mode!=='register')navigate('portfolio');
    if(response.recoveryCode){recovery={format:'imaginary-account-recovery-v1',handle:S.user.handle,recoveryCode:response.recoveryCode};showModal('请保存你的账号恢复码',`<p>忘记密码时，用账号名和这个恢复码找回。此码只在现在显示；每次恢复账号后会换发新码。</p><label>恢复码<input readonly value="${esc(response.recoveryCode)}" aria-label="新恢复码"></label><p class="help">请妥善保存，不要放进作品分享或内测反馈。</p>`,button('下载恢复码文件','recovery-download',null,'')+btn('已保存，关闭','close-modal'));}
    else {$('#modal').close();toast('已登录 '+S.user.name+'。');}
    if(S.view==='portfolio')drawPortfolio();
  }
  function collectionForm(id) {
    const c=S.portfolioData.collections.find(x=>x.id===id)||{name:'',description:'',buildIds:[]};
    showModal(id?'编辑作品系列':'建立一个作品系列',`<div class="account-form"><label>系列名称<input id="collection-name" aria-label="系列名称" value="${esc(c.name)}" maxlength="60"></label><label>系列介绍<textarea id="collection-description" aria-label="系列介绍" rows="3" maxlength="1000">${esc(c.description)}</textarea></label></div><p>选择收入系列的作品。这里的分组不改变角色在对局中的所属系列。</p><div class="collection-picker">${S.portfolioData.works.map(w=>`<label class="checkbox-row"><input type="checkbox" name="collection-build" value="${w.buildId}" ${c.buildIds.includes(w.buildId)?'checked':''}><span>${esc(w.character.name)} · ${esc(w.name)}</span></label>`).join('')||'<p>请先到工坊保存角色。</p>'}</div>`,button('保存系列','collection-save',id,''));
  }
  function limitsFields(value=null) {
    const limits=value||S.catalog.limits;
    return `<details class="room-budget"><summary>房间预算 · 可为超预算试用单独调高</summary><p class="help">这些上限只适用于本房间。标准投稿区仍按服务器默认预算分类。</p><div class="form-grid three">${[['characterBudget','人物预算'],['customBudget','限定牌总额'],['customCardBudget','单张限定牌']].map(([key,name])=>`<label>${name}<input type="number" data-room-budget="${key}" aria-label="房间${name}" min="1" max="100000" value="${limits[key]}"></label>`).join('')}</div></details>`;
  }
  function readLimits(){const result={};document.querySelectorAll('#modal [data-room-budget]').forEach(el=>result[el.dataset.roomBudget]=Number(el.value));return result;}
  async function trialForm(id) {
    await ensureSession();const {version:v}=await api('shared_work',{id});currentVersion=v;
    const limits={characterBudget:Math.max(S.catalog.limits.characterBudget,v.budget.character),customBudget:Math.max(S.catalog.limits.customBudget,v.budget.custom),customCardBudget:Math.max(S.catalog.limits.customCardBudget,...v.budget.cards)};
    showModal('试用 V'+v.number+' · '+v.character.name,`<p>将使用这一固定版本，创建一个带机器人的练习房间。请完整打完对局，服务器会记录试用结果。</p><p>${esc(budgetText(v))}</p>${limitsFields(limits)}<p class="help">${owner(v)?'你是作者，本局结束后即可申请投稿。':'这是其他作者的作品。你可以先复制到自己的作品库，再为自己的版本完成试用。'}</p>`,button('创建并开始试用','trial-start',id,''));
  }
  async function startTrial(id) {
    const v=currentVersion;if(!v||v.id!==id)throw Error('请重新打开试用窗口。');
    const opponent=S.catalog.presets.find(b=>b.character.color!==v.character.color);
    const payload={name:`${v.character.name} · V${v.number} 试用`.slice(0,40),mode:'color',allowCustom:true,turnSeconds:120,versionId:id,budgetLimits:readLimits(),bots:[{presetId:opponent.id}]};
    const key=JSON.stringify(payload);if(!S.createRequest||S.createRequest.key!==key)S.createRequest={key,id:uid()};
    const room=await api('create_room',{...payload,requestId:S.createRequest.id});S.createRequest=null;useRoom(room);$('#modal').close();navigate('room');useRoom(await api('start',{code:room.code}));
  }
  async function upload(file) {
    if(!file)return;if(!['image/png','image/jpeg'].includes(file.type)||file.size>2*1024*1024)throw Error('请选择不超过 2 MiB 的 PNG 或 JPEG。');
    const epoch=S.editorEpoch;await ensureSession();
    const image=await new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(r.result);r.onerror=()=>reject(Error('图片无法读取。'));r.readAsDataURL(file);});
    const data=await api('upload_portrait',{image});if(epoch!==S.editorEpoch||!S.editor){toast('图片已上传；编辑中的角色已切换，请重新选择。');return;}
    S.editor.character.art=data.art;S.editorDirty=true;renderWorkshop();schedulePreview();toast('图片已上传，保存构筑后会加入这个版本。');
  }
  function handle(action,el) {
    if(action==='identity'){run(async()=>{await ensureSession();identity();});return true;}
    if(action==='import-identity'&&S.editorDirty){toast('请先保存或导出当前工坊修改。',true);return true;}
    if(!action.startsWith('works-'))return false;
    const a=action.slice(6),id=el.dataset.id;
    if(a==='home'){navigate('portfolio');return true;}
    if(a==='reload'){render();return true;}
    if(a==='open'){navigate('work/'+id);return true;}
    if(a==='account'){identity();return true;}
    if(['register-form','login-form','recover-form'].includes(a)){authForm(a.split('-')[0]);return true;}
    if(a==='recovery-download'){if(recovery)download('心象账号恢复码-请勿分享.json',recovery);return true;}
    if(a==='collection-new'||a==='collection-edit'){collectionForm(id);return true;}
    if(a==='collection-link'){shareModal('series',id);return true;}
    if(a==='collection-delete'){showModal('删除作品系列',`<p>删除这个分组及它的分享链接，角色作品仍保留在我的作品中。</p>`,button('确认删除系列','collection-delete-confirm',id,'danger'));return true;}
    if(a==='copy-link'){const value=$('#work-share-url').value;navigator.clipboard?.writeText(value).then(()=>toast('链接已复制。')).catch(()=>{$('#work-share-url').select();toast('请复制已选中的链接。');});if(!navigator.clipboard){$('#work-share-url').select();toast('请复制已选中的链接。');}return true;}
    if(a==='private'){showModal('撤回这个版本',`<p>此版本将从投稿库移除，已发出的版本链接也无法再被他人查看。系列快照会显示该版本已撤回。</p>`,button('确认撤回','private-confirm',id,'danger'));return true;}
    run(async()=>{
      if(a==='auth-submit')await authSubmit(id);
      else if(a==='logout'){if(S.editorDirty)throw Error('请先保存或导出工坊中的修改。');await api('logout');clearIdentityState();S.token=null;S.user=null;writeLocal('imaginary.token',null);$('#modal').close();navigate('home');toast('已退出此设备。');}
      else if(a==='tab'){S.portfolioTab=id;drawPortfolio();if(id==='gallery')await fetchGallery();}
      else if(a==='tier'){galleryTier=id;galleryData=null;drawPortfolio();await fetchGallery();}
      else if(a==='gallery-more')await fetchGallery(true);
      else if(a==='edit'){if(S.editorDirty)throw Error('请先回到工坊保存或导出尚未保存的修改。');await load();const b=S.builds.find(b=>b.id===id);if(!b)throw Error('这份作品已不存在。');editorStart(b);}
      else if(a==='share'){const data=await api('shared_work',{id});if(data.version.visibility==='private')await api('set_visibility',{id,visibility:'link'});shareModal('work',id);if(S.view==='work/'+id)render();}
      else if(a==='publish'||a==='private-confirm'){await api('set_visibility',{id,visibility:a==='publish'?'published':'private'});$('#modal').close();render();toast(a==='publish'?'已投稿到对应预算分类。':'已撤回此版本。');}
      else if(a==='history'){const data=await api('work_history',{id});showModal('作品版本历史',`<div class="version-history">${data.versions.map(v=>`<article><b>V${v.number} · ${esc(v.name)}</b><small>${stamp(v.createdAt)}</small>${tags(v)}${button('打开此版','history-open',v.id)}</article>`).join('')}</div>`);}
      else if(a==='history-open'){$('#modal').close();navigate('work/'+id);}
      else if(a==='copy'){await ensureSession();const {version:v}=await api('shared_work',{id});const b=JSON.parse(JSON.stringify(v.build));delete b.id;const saved=await api('save_build',{build:b,expectedVersion:null});await refreshMe();navigate('work/'+saved.version.id);toast('已保存为你的独立作品，需要自行完成试用。');}
      else if(a==='download'){const {version:v}=await api('shared_work',{id});download(v.name+'.json',v.build);}
      else if(a==='trial')await trialForm(id);
      else if(a==='trial-start')await startTrial(id);
      else if(a==='collection-save'){const buildIds=[...document.querySelectorAll('[name="collection-build"]:checked')].map(e=>e.value);await api('save_collection',{...(id?{id}:{}),name:$('#collection-name').value,description:$('#collection-description').value,buildIds});$('#modal').close();await load();drawPortfolio();}
      else if(a==='collection-share'){const data=await api('share_collection',{id});await load();drawPortfolio();shareModal('series',data.id);}
      else if(a==='collection-delete-confirm'||a==='collection-revoke'){await api(a==='collection-revoke'?'revoke_collection_share':'delete_collection',{id});$('#modal').close();await load();drawPortfolio();}
    });return true;
  }
  return {route,render,handle,limitsFields,readLimits,clearIdentityState,upload};
}
