'use strict';
function createClassroom({S,esc,btn,api,run,ensureSession,navigate,editorStart,renderGame,skillSentence,cardName,toast}) {
  let answers=Array(16).fill(null),page=0,result=null;
  try { const saved=JSON.parse(localStorage.getItem('imaginary.virtue.v1'));if(Array.isArray(saved)&&saved.length===16)answers=saved.map(x=>Number.isInteger(x)&&x>=0&&x<4?x:null); } catch (_) {}
  const remember=()=>{try{localStorage.setItem('imaginary.virtue.v1',JSON.stringify(answers));}catch(_) {}};
  const resetSelection=()=>{S.selectedCard=null;S.selectedSkill=null;S.selectedEquipment=null;S.selectedTarget='';S.selection=[];S.costSelection=[];S.giveSelection=[];S.conversionSelection=[];};
  function entry(){return `<section class="learning-entry"><div><span class="eyebrow">从第一步开始</span><h2>学会玩，也做出属于你的角色。</h2><p>九节可操作的短课，或用十六个选择描绘你的心象。</p></div><div class="button-row">${btn('如何玩 · 实操教学','learn-start','')}${btn('如何做 · 16 题心象问答','virtue-start','secondary')}</div></section>`;}
  function renderLesson(){if(S.lesson){renderGame();return;}document.querySelector('#main').innerHTML=entry()+`<section class="panel"><h2>在牌桌上学会规则</h2><p>从心象摸牌开始，逐步体验自我回复、两种损伤、复数回避、判定与心坏恢复。每一课都可以重试，进度随你的身份保存。</p>${btn('开始 / 继续教学','learn-start','')}</section>`;}
  function renderQuiz(){
    const root=document.querySelector('#main');
    if(result){const b=result.build,p=result.profile;root.innerHTML=`<div class="page-heading"><div><span class="eyebrow">十六个选择，一份完整构筑</span><h1>${esc(p.title)}</h1><p>这是你的初稿。技能、费用和心象顺序都可以继续修改。</p></div>${btn('返回答案','virtue-review','secondary small')}</div><div class="virtue-result"><section class="panel"><label>给角色起名<input id="virtue-name" maxlength="40" value="${esc(b.character.name)}"></label><p><span class="color-label ${b.character.color}">${b.character.color==='cool'?'冷色':'暖色'}</span> · 身体 ${b.character.hp}${b.character.flipColor?' · 可异色翻面':' · 不翻面'}</p>${b.character.skills.map(s=>`<article class="result-skill"><h3>${esc(s.name)}</h3><p>${esc(skillSentence(s))}</p></article>`).join('')}<div class="budget-summary">人物 ${result.budget.character} / ${result.budget.characterMax} · 限定心象 ${result.budget.custom} / ${result.budget.customMax}</div></section><section class="panel"><h2>怎么打出你的特色</h2><p>${esc(p.play)}</p><h3>需要留意</h3><p>${esc(p.weakness)}</p><h3>你的选择如何影响构筑</h3>${p.choices.map(x=>`<p>${esc(x)}</p>`).join('')}<p class="help">构筑已通过当前预算。实际强度还取决于对手、模式与操作。</p></section><section class="panel virtue-deck"><h2>十三张心象 · 从左向右抽取</h2><div class="mind-strip">${b.deck.map((c,i)=>`<div class="mind-token"><b>${i+1}</b><small>${esc(cardName(c))}</small><span>${c.rank===1?'A':c.rank===11?'J':c.rank===12?'Q':c.rank===13?'K':c.rank}</span></div>`).join('')}</div><p>开局可优先考虑：${p.opening.map(esc).join(' → ')}。</p>${btn('带入工坊 · 编辑、保存与试用','virtue-edit','')}</section></div>`;return;}
    const questions=S.catalog.creationTest.questions,q=questions[page],answered=answers.filter(x=>x!==null).length;
    root.innerHTML=`<section class="virtue-test"><div class="page-heading"><div><span class="eyebrow">心象问答 / VIRTUE TEST</span><h1>在选择里，遇见你的角色。</h1><p>没有标准答案。前十二题探索你的倾向，最后四题决定构筑取舍。</p></div></div><div class="question-progress"><span>第 ${page+1} / 16 题</span><span>已回答 ${answered} 题 · 自动保留答案</span></div><progress max="16" value="${answered}" aria-label="问答完成进度"></progress>${answered===16&&page<15?btn('根据已保存答案生成角色','virtue-generate','secondary small'):''}<section class="question-card" aria-labelledby="question-title"><span class="eyebrow">${page<12?'旅途中的抉择':'让心象成为构筑'}</span><h2 id="question-title">${esc(q.prompt)}</h2><div class="virtue-options" role="group" aria-label="本题选项">${q.options.map((o,i)=>`<button class="virtue-option ${answers[page]===i?'selected':''}" data-action="virtue-answer" data-index="${i}" aria-pressed="${answers[page]===i}"><span>${['A','B','C','D'][i]}</span>${esc(o.text)}</button>`).join('')}</div><div class="question-actions">${btn('上一题','virtue-back','secondary',page===0?'disabled':'')}${page<15?btn('下一题 →','virtue-next','',answers[page]===null?'disabled':''):btn('生成我的角色与心象','virtue-generate','',answered!==16?'disabled':'')}</div></section><p class="help">这是创作情境问答。答案会组合已有技能拼图，而非评定你的性格或好坏。你随时可以去工坊自由创作。</p></section>`;
  }
  function workshopGuide(){return `<details class="creator-guide" ${S.creatorGuide?'open':''}><summary>如何做 · 从这份构筑开始</summary><ol><li>给角色命名，读懂技能的触发时机、费用与次数。</li><li>从现有拼图载入或修改一项技能，观察预算和说明如何变化。</li><li>排列十三张心象：最上面的会先抽到；每个点数各一张。</li><li>保存构筑，再到大厅的练习配置里选用它。</li></ol>${S.creatorGuide?`<p><b>你的玩法：</b>${esc(S.creatorGuide.play)}</p><p><b>你的弱点：</b>${esc(S.creatorGuide.weakness)}</p>`:''}<div class="button-row">${btn('重新做 16 题问答','virtue-start','secondary small')}${btn('去大厅选用已保存构筑','go-home','secondary small')}</div></details>`;}
  async function command(command,action,step){await ensureSession();try{S.lesson=await api('tutorial',{command,revision:S.lesson?.revision,...(action?{action}:{}),...(step!==undefined?{step}:{})});}catch(error){if(error.status===409)S.lesson=await api('tutorial');throw error;}finally{resetSelection();if(S.view==='learn')renderLesson();}}
  function handle(action,el){
    if(!action.startsWith('virtue-')&&!action.startsWith('learn-'))return false;
    if(S.busy)return true;
    if(action==='virtue-start'){result=null;page=Math.max(0,answers.findIndex(x=>x===null));navigate('virtue');}
    if(action==='virtue-answer'){answers[page]=Number(el.dataset.index);remember();renderQuiz();}
    if(action==='virtue-back'){page=Math.max(0,page-1);renderQuiz();}
    if(action==='virtue-next'&&answers[page]!==null){page=Math.min(15,page+1);renderQuiz();}
    if(action==='virtue-review'){result=null;page=0;renderQuiz();}
    if(action==='virtue-generate')run(async()=>{await ensureSession();result=await api('creation_test',{answers});renderQuiz();});
    if(action==='virtue-edit'&&result){const b=JSON.parse(JSON.stringify(result.build));b.character.name=(document.querySelector('#virtue-name').value.trim()||b.character.name).slice(0,40);b.name=b.character.name+' · 心象问答';S.creatorGuide=result.profile;editorStart(b);S.editorDirty=true;toast('已带入工坊，保存后即可选入练习。');}
    if(action==='learn-start')run(async()=>{await command('resume');navigate('learn');});
    if(action==='learn-next')run(()=>command('next'));
    if(action==='learn-restart')run(()=>command('restart'));
    if(action==='learn-jump'){const step=Number(document.querySelector('#lesson-picker').value);run(()=>command('lesson',undefined,step));}
    if(action==='learn-exit')navigate('home');
    return true;
  }
  return {entry,renderLesson,renderQuiz,workshopGuide,handle,act:action=>command('act',action)};
}
