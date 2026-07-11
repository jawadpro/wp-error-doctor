import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import "./jawad-theme.css";
const sans = Geist({ variable:"--font-sans", subsets:["latin"] });
const mono = Geist_Mono({ variable:"--font-mono", subsets:["latin"] });
export const metadata: Metadata = { title:"WP Error Doctor — Evidence-based WordPress diagnostics", description:"Diagnose WordPress HTTP 500 errors, broken endpoints, and common site failures with a safe public scan.", icons:{icon:"/favicon.svg"} };
export default function RootLayout({children}:{children:React.ReactNode}) { return <html lang="en"><body className={`${sans.variable} ${mono.variable}`}>{children}</body></html> }
