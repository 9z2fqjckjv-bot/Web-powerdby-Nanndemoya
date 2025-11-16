#!/bin/bash
# Paste the entire contents of this file into a single Colab cell and run.
# (In Colab, add "%%bash" on the first line of the cell or run as a bash cell.)
set -euo pipefail

REPO="https://github.com/9z2fqjckjv-bot/Web-powerdby-Nanndemoya.git"
DIR="$(basename "$REPO" .git)"
PORT=3000

echo "==> Installing Node.js 18 and git (may ask for sudo)..."
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash - >/dev/null 2>&1
sudo apt-get update -y >/dev/null
sudo apt-get install -y nodejs git >/dev/null
echo "node $(node -v) npm $(npm -v)"

echo "==> Cloning repository: $REPO"
rm -rf "$DIR"
git clone "$REPO"
cd "$DIR"

if [ ! -f package.json ]; then
  echo "ERROR: package.json not found in repository root. Exiting."
  exit 1
fi

echo "==> Installing dependencies (npm ci preferred)..."
if npm ci --silent; then
  echo "Dependencies installed with npm ci"
else
  echo "npm ci failed, trying npm install..."
  npm install --silent
fi

# Detect available scripts
SCRIPTS="$(npm run 2>/dev/null || true)"
start_cmd=""
if echo "$SCRIPTS" | grep -q "dev"; then
  start_cmd="npm run dev"
elif echo "$SCRIPTS" | grep -q "start"; then
  start_cmd="npm start"
fi

echo "==> Starting application (port $PORT)..."
if [ -n "$start_cmd" ]; then
  echo "Running: PORT=$PORT $start_cmd"
  export PORT="$PORT"
  # start in background and capture logs
  (PORT="$PORT" $start_cmd) > devserver.log 2>&1 &
  sleep 2
elif [ -d dist ] || [ -d build ]; then
  # serve static output if present
  outdir="dist"
  if [ -d build ]; then outdir="build"; fi
  echo "No start/dev script found. Serving static directory: $outdir"
  npx --yes http-server "$outdir" -p "$PORT" > devserver.log 2>&1 &
  sleep 1
else
  echo "No dev/start script and no dist/build found. Attempting build..."
  npm run build --if-present
  if [ -d dist ]; then
    echo "Serving dist/ on port $PORT"
    npx --yes http-server dist -p "$PORT" > devserver.log 2>&1 &
    sleep 1
  else
    echo "Build failed or dist/ not produced. Please check package.json scripts."
    exit 1
  fi
fi

echo
echo "---- Server logs (last 20 lines) ----"
tail -n 20 devserver.log || true
echo "-------------------------------------"
echo

echo "==> Starting localtunnel to expose port $PORT ..."
rm -f lt.out
# run localtunnel in background and capture output to lt.out
npx --yes localtunnel --port "$PORT" > lt.out 2>&1 &
# wait and show the URL
for i in {1..30}; do
  if grep -Eo "https?://[a-z0-9.-]+" lt.out >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

echo "---- localtunnel output ----"
cat lt.out || true
echo "----------------------------"

echo
echo "If you see a URL like https://xxxxx.loca.lt, open it in your browser to use the app."
echo "To stop the server/tunnel: kill $(jobs -p) ; pkill -f localtunnel || true"
