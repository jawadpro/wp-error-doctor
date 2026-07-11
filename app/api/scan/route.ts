import { NextRequest, NextResponse } from "next/server";

const paths = ["/", "/wp-admin/", "/wp-json/", "/robots.txt", "/wp-sitemap.xml"];
const privateHost = /^(localhost|0\.0\.0\.0|127\.|10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|\[?::1\]?$)/i;

export async function POST(request: NextRequest) {
  try {
    const { url } = await request.json() as { url?: string };
    const target = new URL(url || "");
    if (!["http:", "https:"].includes(target.protocol) || privateHost.test(target.hostname)) {
      return NextResponse.json({ error: "Only public HTTP(S) websites can be scanned." }, { status: 400 });
    }
    const origin = `${target.protocol}//${target.host}`;
    const results = await Promise.all(paths.map(async path => {
      const started = Date.now();
      try {
        const response = await fetch(new URL(path, origin), { redirect: "follow", signal: AbortSignal.timeout(10000), headers: { "User-Agent": "WP-Error-Doctor/1.0 (+public-health-check)" } });
        const status = response.status;
        const admin = path === "/wp-admin/";
        const severity = admin && [401,403,404].includes(status) ? "neutral" : status >= 500 ? "critical" : status >= 400 ? "warning" : "healthy";
        const finding = admin && [401,403,404].includes(status) ? "Admin path protected or hidden (inconclusive)" : status >= 500 ? "Server-side request failed" : status >= 400 ? "Endpoint unavailable" : "Endpoint accessible";
        return [path === "/" ? "Homepage" : path, String(status), `${Date.now()-started} ms`, finding, severity];
      } catch (error) {
        return [path === "/" ? "Homepage" : path, "ERR", `${Date.now()-started} ms`, error instanceof Error && error.name === "TimeoutError" ? "Request timed out" : "Connection failed", "critical"];
      }
    }));
    return NextResponse.json({ url: target.href, results });
  } catch { return NextResponse.json({ error: "Invalid website URL." }, { status: 400 }); }
}
