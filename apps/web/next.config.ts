import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "export", // static site: upload the "out" folder to Hostinger
  images: { unoptimized: true },
  trailingSlash: true,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
