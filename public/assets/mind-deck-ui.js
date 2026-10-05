'use strict';
function createMindDeckUI({S,esc,btn,opt,rank,renderWorkshop,schedulePreview}) {
  const suits=()=>({'':'◇ 无花色',...S.catalog.mindSuits});
  function toolbar(){return `<div class="deck-tools"><label>整副花色<select id="deck-all-suit" aria-label="整副心象花色">${opt(S.catalog.mindSuits,'♠')}</select></label>${btn('应用到全部 13 张','deck-all-suit','secondary small')}<div class="button-row">${btn('大点在前 K → A','deck-desc','secondary small')}${btn('小点在前 A → K','deck-asc','secondary small')}</div></div>`;}
  function picker(d,i){return `<select class="slot-suit" data-mind-suit="${i}" aria-label="第 ${i+1} 张心象花色">${opt(suits(),d.suit||'')}</select>`;}
  function position(d,i){return `<label class="slot-position">${rank(d.rank)} 移到<select data-mind-position="${i}" aria-label="点数 ${rank(d.rank)} 移到第几张">${opt(Object.fromEntries(S.editor.deck.map((_,n)=>[n,`第 ${n+1} 张${n===0?' · 牌顶':n===12?' · 牌底':''}`])),i)}</select></label>`;}
  function changed(redraw=true){S.editorDirty=true;if(redraw)renderWorkshop();const state=document.querySelector('#save-state');if(state)state.textContent='有未保存修改';schedulePreview();}
  function handle(action){
    if(!['deck-all-suit','deck-desc','deck-asc'].includes(action))return false;
    if(action==='deck-all-suit'){const suit=document.querySelector('#deck-all-suit').value;S.editor.deck.forEach(c=>c.suit=suit);}
    else S.editor.deck.sort((a,b)=>action==='deck-desc'?b.rank-a.rank:a.rank-b.rank);
    changed();return true;
  }
  function change(el){
    if(el.dataset.mindSuit!==undefined){const c=S.editor.deck[Number(el.dataset.mindSuit)];if(el.value)c.suit=el.value;else delete c.suit;changed(false);return true;}
    if(el.dataset.mindPosition!==undefined){const from=Number(el.dataset.mindPosition),to=Number(el.value);S.editor.deck.splice(to,0,S.editor.deck.splice(from,1)[0]);changed();return true;}
    return false;
  }
  return {toolbar,picker,position,handle,change};
}
