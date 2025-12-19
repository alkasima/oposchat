import urllib.request
import json

try:
    # 1. List collections and get stats for EACH
    print("--- Collections Stats ---")
    with urllib.request.urlopen('http://localhost:8001/collections') as response:
        data = json.loads(response.read().decode())
        collections = data.get('collections', [])
        
        for col in collections:
            name = col['name']
            try:
                # Url encode the name just in case
                safe_name = urllib.parse.quote(name)
                with urllib.request.urlopen(f'http://localhost:8001/collections/{safe_name}/stats') as stat_response:
                    stats = json.loads(stat_response.read().decode())
                    print(f"Name: {stats.get('name'):<30} | Count: {stats.get('count')}")
            except Exception as e:
                print(f"Name: {name:<30} | Error getting stats: {e}")

except Exception as e:
    print(f"Fatal Error: {e}")
