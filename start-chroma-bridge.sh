#!/bin/bash

# Configuration
PORT=8001
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
BRIDGE_DIR="$SCRIPT_DIR/chroma-bridge"
VENV_PYTHON="$BRIDGE_DIR/venv/bin/python"
APP_FILE="app.py"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "Checking status of Chroma Bridge on port $PORT..."

# Check if port is in use
if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
    echo -e "${GREEN}✅ Chroma Bridge is already running on port $PORT.${NC}"
    exit 0
fi

echo -e "${YELLOW}⚠️ Port $PORT is not in use. Starting Chroma Bridge...${NC}"

# Check if python exists in venv
if [ ! -f "$VENV_PYTHON" ]; then
    echo -e "${RED}❌ Virtual environment python not found at $VENV_PYTHON${NC}"
    echo "Please ensure you have created the virtual environment: cd chroma-bridge && python3 -m venv venv && source venv/bin/activate && pip install -r requirements.txt"
    exit 1
fi

# Start payload
cd "$BRIDGE_DIR"
nohup "$VENV_PYTHON" "$APP_FILE" > /dev/null 2>&1 &
PID=$!

sleep 3

if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
    echo -e "${GREEN}✅ Chroma Bridge started successfully (PID: $PID).${NC}"
else
    echo -e "${RED}❌ Failed to start Chroma Bridge. Please check logs.${NC}"
    # Try to show error if it failed immediately
    "$VENV_PYTHON" "$APP_FILE" &
    exit 1
fi
