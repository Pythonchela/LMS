import type { Metadata } from "next";
import { Inter, Plus_Jakarta_Sans } from "next/font/google";
import "katex/dist/katex.min.css";
import "./globals.css";
import { brand } from "@/lib/config";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { siteOrigin } from "@/lib/server-api";

// Same families as mymerchantnavy.com (Inter body, Plus Jakarta Sans headings). Variable fonts: any weight, one file.
const inter = Inter({ variable: "--font-inter", subsets: ["latin"], display: "swap" });
const jakarta = Plus_Jakarta_Sans({ variable: "--font-jakarta", subsets: ["latin"], display: "swap" });

export async function generateMetadata(): Promise<Metadata> {
  return {
    // Lets every page use short addresses for its share image and canonical link.
    metadataBase: new URL(await siteOrigin()),
    title: { default: `${brand.name}: Merchant Navy exam preparation`, template: `%s · ${brand.name}` },
    description: `${brand.tagline} Notes, practice quizzes and mock tests for IMU-CET, GP Rating, DNS and more.`,
    applicationName: brand.name,
    openGraph: { siteName: brand.name, type: "website", locale: "en_IN" },
    twitter: { card: "summary_large_image" },
  };
}

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" suppressHydrationWarning className={`${inter.variable} ${jakarta.variable}`}>
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html: `(function(){try{var s=localStorage.getItem('mmn-theme')||'system';var d=s==='dark'||(s==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);if(d){document.documentElement.classList.add('dark');}else{document.documentElement.classList.remove('dark');}}catch(e){}})();`,
          }}
        />
      </head>
      <body className="antialiased">
        <ThemeProvider>{children}</ThemeProvider>
      </body>
    </html>
  );
}
