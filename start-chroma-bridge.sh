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

# Function to check if port is in use
check_port() {
    # Try ss first (modern, faster, usually default on Ubuntu)
    if command -v ss >/dev/null; then
        if ss -lptn "sport = :$PORT" | grep -q "$PORT"; then return 0; fi
    fi
    
    # Try netstat
    if command -v netstat >/dev/null; then
        if netstat -tulpn 2>/dev/null | grep -q ":$PORT"; then return 0; fi
    fi

    # Try lsof (needs sudo for some processes, but -i usually works for listing)
    if command -v lsof >/dev/null; then
        if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then return 0; fi
    fi
    
    return 1
}

if check_port; then
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
nohup "$VENV_PYTHON" "$APP_FILE" > bridge.log 2>&1 &
PID=$!

sleep 3

if check_port; then
    echo -e "${GREEN}✅ Chroma Bridge started successfully (PID: $PID).${NC}"
else
    echo -e "${RED}❌ Failed to start Chroma Bridge. Checking logs:${NC}"
    tail -n 10 bridge.log
    exit 1
fi
