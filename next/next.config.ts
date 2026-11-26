import type { NextConfig } from "next";

/**
 * كل الصفحات تُرسم عند الطلب: سياسة CSP تحمل nonce جديدًا لكل طلب (src/proxy.ts)،
 * والصفحة الثابتة لا يمكنها حمله. لذلك cacheComponents معطَّل.
 */
const nextConfig: NextConfig = {
  poweredByHeader: false,
  outputFileTracingRoot: import.meta.dirname,
  turbopack: {
    root: import.meta.dirname,
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
