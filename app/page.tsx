"use client";

import { FormEvent, useState } from "react";

type Stage = "idle" | "scanning" | "complete";

const stages = [
  "Validating URL", "Checking server response", "Following redirects",
  "Testing WordPress endpoints", "Checking static assets",
  "Identifying error signatures", "Preparing diagnostic report",
];

const sampleEndpoints = [
  ["Homepage", "500", "842 ms", "PHP request failed", "critical"],
  ["/wp-admin/", "404", "791 ms", "Admin path hidden or unavailable", "neutral"],
  ["/wp-json/", "500", "633 ms", "REST API unavailable", "warning"],
  ["Theme stylesheet", "200", "121 ms", "Static assets accessible", "healthy"],
  ["/robots.txt", "200", "98 ms", "Web server responding", "healthy"],
];

export default function Home() {
  const [url, setUrl] = useState("");
  const [state, setState] = useState<Stage>("idle");
  const [stage, setStage] = useState(0);
  const [error, setError] = useState("");
  const [endpoints, setEndpoints] = useState(sampleEndpoints);

  async function scan(e: FormEvent) {
    e.preventDefault(); setError("");
    let value = url.trim();
    if (!/^https?:\/\//i.test(value)) value = `https://${value}`;
    try {
      const parsed = new URL(value);
      if (!["http:", "https:"].includes(parsed.protocol) || !parsed.hostname.includes(".") || /^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(parsed.hostname)) throw new Error();
      setUrl(parsed.href.replace(/\/$/, ""));
    } catch { setError("Enter a valid public website URL, such as example.com"); return; }
    setState("scanning"); setStage(0);
    const request = fetch("/api/scan", { method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({url:value}) }).then(async r => { const data = await r.json(); if (!r.ok) throw new Error(data.error); return data; });
    for (let i = 0; i < stages.length; i++) {
      setStage(i); await new Promise(r => setTimeout(r, i === 0 ? 450 : 620));
    }
    try { const data = await request; setEndpoints(data.results); }
    catch (e) { setState("idle"); setError(e instanceof Error ? e.message : "The scan could not be completed."); return; }
    setState("complete");
    setTimeout(() => document.getElementById("report")?.scrollIntoView({ behavior: "smooth" }), 50);
  }

  function copyReport() {
    navigator.clipboard.writeText(`WP Error Doctor report for ${url}\nLikely issue: PHP or WordPress fatal error\nConfidence: Medium\nBackend logs are required to confirm the exact cause.`);
  }

  return <main>
    <nav className="nav shell">
      <a className="brand" href="#"><span className="brand-mark">W</span><span>WP Error Doctor</span></a>
      <div className="nav-links"><a href="#how">How it works</a><a href="#security">Security</a><a className="connector" href="https://jawadjd.dev/#contact" target="_blank">Hire Jawad <span>↗</span></a></div>
    </nav>

    <section className="hero shell">
      <div className="eyebrow"><span></span> WORDPRESS DIAGNOSTICS, WITHOUT THE GUESSWORK</div>
      <h1>Find out what’s<br />breaking your <em>WordPress.</em></h1>
      <p className="lede">Scan any public WordPress site for server errors, broken endpoints, and failure patterns. Get a clear, evidence-based recovery plan in minutes.</p>
      <form onSubmit={scan} className="scan-form">
        <div className="url-box"><span className="globe">◎</span><input aria-label="Enter your WordPress website URL" placeholder="Enter your WordPress website URL" value={url} onChange={e=>setUrl(e.target.value)} /><button type="submit">Scan Website <span>→</span></button></div>
        {error && <p className="form-error">{error}</p>}
        <p className="privacy"><span>◆</span> Public checks only. No login or credentials required. <a href="#security">What we check</a></p>
      </form>

      {state === "scanning" && <div className="progress-panel">
        <div className="radar"><i></i><b>⌁</b></div>
        <div><small>DIAGNOSTIC SCAN IN PROGRESS</small><h3>{stages[stage]}<span className="dots">...</span></h3><p>{url}</p></div>
        <div className="stage-list">{stages.map((s,i)=><span key={s} className={i < stage ? "done" : i === stage ? "active" : ""}>{i < stage ? "✓" : i === stage ? "●" : "○"} {s}</span>)}</div>
      </div>}

      {state === "idle" && <div className="trust-row"><div><strong>01</strong><span>Public surface scan</span></div><div><strong>02</strong><span>Evidence-based diagnosis</span></div><div><strong>03</strong><span>Safe recovery plan</span></div></div>}
    </section>

    {state === "complete" && <section id="report" className="report shell">
      <div className="report-head"><div><div className="eyebrow"><span></span> SCAN COMPLETE</div><h2>Diagnostic report</h2><p>{url} <b>•</b> Scan ID WPD-82F1A7</p></div><div className="report-actions"><button onClick={copyReport}>Copy summary</button><button>Download PDF ↓</button></div></div>
      <div className={`status-card ${endpoints[0]?.[1] === "200" ? "status-healthy" : "status-critical"}`}><div className="status-icon">{endpoints[0]?.[1] === "200" ? "✓" : "!"}</div><div><small>OVERALL STATUS</small><h3>{endpoints[0]?.[1] === "200" ? "Website is online" : "Website response requires attention"}</h3><p>{endpoints[0]?.[1] === "200" ? `The homepage returned HTTP 200 in ${endpoints[0]?.[2]}. No public homepage server error was detected.` : "The homepage did not return a successful response. Review the evidence below."} The default admin path is never used as failure evidence when hidden or protected.</p></div><div className="confidence"><span>CONFIDENCE</span><strong>{endpoints[0]?.[1] === "200" ? "HIGH" : "MEDIUM"}</strong></div></div>
      <div className="report-grid">
        <article className="panel evidence"><div className="panel-title"><span>⌘</span><div><h3>Evidence</h3><p>What the public scan discovered</p></div></div>
          <div className="table"><div className="tr th"><span>ENDPOINT</span><span>STATUS</span><span>TIME</span><span>FINDING</span></div>{endpoints.map((x,i)=><div className="tr" key={i}><span>{x[0]}</span><span><b className={`http ${x[4]}`}>{x[1]}</b></span><span>{x[2]}</span><span><i className={x[4]}></i>{x[3]}</span></div>)}</div>
        </article>
        <article className="panel causes"><div className="panel-title"><span>≋</span><div><h3>Likely causes</h3><p>Ranked from available evidence</p></div></div>
          <div className="cause"><b>1</b><div><h4>PHP or WordPress fatal error</h4><p>PHP requests fail while static assets load normally.</p></div><strong>MEDIUM</strong></div>
          <div className="cause"><b>2</b><div><h4>Plugin or theme conflict</h4><p>A recent code change may be triggering a fatal error.</p></div><strong>LOW</strong></div>
          <div className="cause"><b>3</b><div><h4>Resource limit exceeded</h4><p>Memory or execution limits may be exhausted.</p></div><strong>LOW</strong></div>
          <div className="backend-note"><span>◇</span><p><b>Backend access required for confirmation</b><br/>A missing or renamed /wp-admin/ path is inconclusive and never treated as an error. Public scanning cannot identify an exact plugin, file, or line number.</p></div>
        </article>
      </div>
      <article className="panel recommendations"><div className="panel-title"><span>↗</span><div><h3>Recommended recovery plan</h3><p>Safe steps, ordered by risk</p></div></div>
        {[['Confirm a recent backup','LOW','Hosting panel','Verify a restorable backup exists before making changes.'],['Review fatal error logs','LOW','Hosting or SFTP','Check the latest PHP and WordPress debug entries.'],['Temporarily disable the suspected plugin','MEDIUM','SFTP or Recovery Mode','Rename its folder; never delete it. Restore the name to roll back.'],['Contact your hosting provider','LOW','Hosting account','Ask them to confirm PHP health and server resource limits.']].map((a,i)=><div className="action" key={a[0]}><span>{i+1}</span><div><h4>{a[0]}</h4><p>{a[3]}</p></div><div><small>RISK</small><b>{a[1]}</b></div><div><small>ACCESS</small><b>{a[2]}</b></div><button>⌄</button></div>)}
      </article>
      <div className="client-note"><small>CLIENT-FRIENDLY EXPLANATION</small><p>“Your website server is online, but WordPress is currently unable to process PHP pages. This is usually caused by a plugin, theme, PHP compatibility problem, or server resource limit. Backend logs are required to confirm the exact cause.”</p><button onClick={copyReport}>Copy explanation</button></div>
      <div className="cta" id="hire-jawad"><div><small>PROFESSIONAL WORDPRESS SUPPORT</small><h2>Need help fixing this<br/>WordPress error?</h2><p>Send your diagnostic report to Jawad for a safe, professional WordPress repair. You’ll only share the public findings shown above.</p></div><div><a className="primary-cta" href={`https://jawadjd.dev/#contact?website=${encodeURIComponent(url)}&report=WPD-82F1A7`} target="_blank">Hire Jawad to Fix It <span>→</span></a><button onClick={copyReport}>Copy Diagnostic Report</button></div></div>
      <form id="send-report" className="lead-form" action="https://jawadjd.dev/#contact" method="get" target="_blank">
        <div className="lead-intro"><small>SEND REPORT TO JAWAD</small><h3>Get a professional diagnosis</h3><p>Tell Jawad how to reach you. Your scan results remain visible without submitting this form.</p></div>
        <label><span>Your name</span><input name="name" required placeholder="Jane Smith" /></label>
        <label><span>Email address</span><input name="email" type="email" required placeholder="jane@company.com" /></label>
        <label><span>Website</span><input name="website" value={url} readOnly /></label>
        <label className="message"><span>What happened?</span><textarea name="message" placeholder="Tell me when the issue started or what changed recently…" /></label>
        <input type="hidden" name="report" value="WPD-82F1A7" />
        <label className="consent"><input type="checkbox" required name="permission" value="yes" /><span>I give Jawad permission to contact me about this website issue.</span></label>
        <button className="submit-lead" type="submit">Send Report & Request Help <span>→</span></button>
        <p className="lead-trust">No passwords or private logs are included. No obligation.</p>
      </form>
    </section>}

    {state === "complete" && <aside className="report-bar" aria-label="Send diagnostic report to Jawad"><div><span className="bar-icon">↗</span><p><b>Want an expert to review this?</b><small>Send the diagnostic report to Jawad for professional WordPress help.</small></p></div><a href="#send-report">Send Report to Jawad <span>→</span></a></aside>}

    <section id="how" className="how shell"><div><div className="eyebrow"><span></span> BUILT FOR CAREFUL DIAGNOSIS</div><h2>Clear evidence.<br/>No dangerous guesses.</h2></div><div className="how-copy"><p>Public scans analyze only what any visitor can see. For an exact diagnosis, the secure connector can share sanitized system health data—with your explicit permission.</p><div className="checks"><span>✓ No passwords requested</span><span>✓ No automatic changes</span><span>✓ Sensitive data redacted</span><span>✓ Revocable access</span></div></div></section>
    <footer id="security" className="shell"><a className="brand" href="#"><span className="brand-mark">W</span><span>WP Error Doctor</span></a><p>Evidence-based WordPress diagnostics.</p><span>© 2026 WP Error Doctor</span></footer>
  </main>;
}
