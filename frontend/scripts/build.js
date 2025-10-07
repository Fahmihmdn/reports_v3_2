#!/usr/bin/env node
"use strict";
const fs = require("fs");
const fsp = fs.promises;
const path = require("path");
const { spawnSync } = require("child_process");

async function removeDist(distPath) {
  await fsp.rm(distPath, { recursive: true, force: true });
}

async function compileTypeScript(projectRoot) {
  const tsPackagePath = require.resolve("typescript/package.json", { paths: [projectRoot] });
  const tsBin = path.join(path.dirname(tsPackagePath), "bin", "tsc");
  const result = spawnSync(process.execPath, [tsBin, "--project", path.join(projectRoot, "tsconfig.json")], {
    stdio: "inherit",
  });
  if (result.status !== 0) {
    throw new Error("TypeScript compilation failed");
  }
}

async function copyPublicAssets(projectRoot, distPath) {
  const publicPath = path.join(projectRoot, "public");
  try {
    await fsp.access(publicPath, fs.constants.F_OK);
  } catch (error) {
    return;
  }
  await fsp.mkdir(distPath, { recursive: true });
  await fsp.cp(publicPath, distPath, { recursive: true });
}

async function main() {
  const projectRoot = path.resolve(__dirname, '..');
  const distPath = path.join(projectRoot, "dist");

  await removeDist(distPath);
  await compileTypeScript(projectRoot);
  await copyPublicAssets(projectRoot, distPath);
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
});
