(() => {
  const root = document.getElementById('wpd-widget'); if (!root) return;
  const dialog = root.querySelector('.wpd-dialog'), launch = root.querySelector('.wpd-launch');
  const views = ['start','progress','result','lead']; let report = null;
  const show = name => views.forEach(v => root.querySelector('.wpd-'+v).hidden = v !== name);
  launch.addEventListener('click', () => { dialog.hidden = !dialog.hidden; launch.setAttribute('aria-expanded', String(!dialog.hidden)); });
  root.querySelector('.wpd-close').addEventListener('click', () => dialog.hidden = true);
  root.querySelector('.wpd-back').addEventListener('click', () => show('result'));
  root.querySelector('.wpd-scan-form').addEventListener('submit', async e => {
    e.preventDefault(); show('progress');
    const stages=['Validating website URL','Checking server response','Testing WordPress endpoints','Preparing your report']; let i=0;
    const ticker=setInterval(()=>{ if(i<stages.length) root.querySelector('.wpd-stage').textContent=stages[i++]; },650);
    try { const res=await fetch(WPDWidget.root+'scan',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':WPDWidget.nonce},body:JSON.stringify({url:root.querySelector('#wpd-url').value})}); const data=await res.json(); if(!res.ok) throw new Error(data.message); report=data; render(data); }
    catch(err){ renderError(err.message||'The scan could not be completed.'); } finally { clearInterval(ticker); }
  });
  function render(data){
    const result=root.querySelector('.wpd-result'); const title=data.online?'Website is online':'Website needs attention';
    result.innerHTML=`<span class="wpd-kicker">SCAN COMPLETE · ${escape(data.scan_id)}</span>${data.fun_message?`<div class="wpd-fun ${data.is_jawad?'jawad':''}"><span>${data.is_jawad?'⌁':'✦'}</span><p>${escape(data.fun_message)}</p></div>`:''}<div class="wpd-status ${data.online?'healthy':'critical'}"><i>${data.online?'✓':'!'}</i><div><h2>${title}</h2><p>${data.online?'No public homepage server error was detected.':'The homepage did not return HTTP 200.'}</p></div></div><div class="wpd-evidence"><b>Public evidence</b>${data.results.map(r=>`<div><span>${escape(r.name)}</span><strong class="${r.state}">${escape(r.status)}</strong><small>${escape(r.finding)}</small></div>`).join('')}</div><div class="wpd-caveat">${!data.is_wordpress?'WordPress-specific diagnosis is limited for this website. ':''}A public scan cannot confirm an exact plugin, theme, file, or line number. Backend logs may be required.</div><button class="wpd-send" type="button">Send Report to Jawad <span>→</span></button><button class="wpd-again" type="button">Scan another website</button>`;
    result.querySelector('.wpd-send').onclick=()=>show('lead'); result.querySelector('.wpd-again').onclick=()=>show('start'); show('result');
  }
  function renderError(message){ const result=root.querySelector('.wpd-result'); result.innerHTML=`<div class="wpd-status critical"><i>!</i><div><h2>Scan could not finish</h2><p>${escape(message)}</p></div></div><button class="wpd-again" type="button">Try again</button>`; result.querySelector('button').onclick=()=>show('start'); show('result'); }
  root.querySelector('.wpd-lead-form').addEventListener('submit', async e => {
    e.preventDefault(); const btn=e.target.querySelector('button'); btn.disabled=true; btn.textContent='Sending…'; const values=Object.fromEntries(new FormData(e.target)); values.report=report; values.consent=!!values.consent;
    try { const res=await fetch(WPDWidget.root+'lead',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':WPDWidget.nonce},body:JSON.stringify(values)}); const data=await res.json(); if(!res.ok) throw new Error(data.message); e.target.innerHTML=`<div class="wpd-success"><i>✓</i><h3>Report sent</h3><p>${escape(data.message)}</p></div>`; }
    catch(err){ btn.disabled=false; btn.textContent='Send Report & Request Help →'; alert(err.message); }
  });
  function escape(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }
})();
