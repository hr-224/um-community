// Prisma 7 configuration — reads from .env.local for Next.js compatibility
import { config } from "dotenv";
import { defineConfig } from "prisma/config";

// Load .env.local (Next.js convention) before .env
config({ path: ".env.local" });
config(); // fallback to .env

export default defineConfig({
  schema: "prisma/schema.prisma",
  migrations: {
    path: "prisma/migrations",
  },
  datasource: {
    url: process.env["DATABASE_URL"],
  },
});
