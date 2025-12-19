import urllib.request
import json

try:
    # 1. List collections
    print("--- Collections ---")
    with urllib.request.urlopen('http://localhost:8001/collections') as response:
        data = json.loads(response.read().decode())
        print(json.dumps(data, indent=2))

    # 2. Get Count for Main Collection
    print("\n--- Main Collection Stats (oposchat_vectors) ---")
    with urllib.request.urlopen('http://localhost:8001/collections/oposchat_vectors/stats') as response:
        stats = json.loads(response.read().decode())
        print(f"Collection Name: {stats.get('name')}")
        print(f"Total Vectors (Chunks): {stats.get('count')}")
        print(f"Metadata: {stats.get('metadata')}")

except Exception as e:
    print(f"Error: {e}")
    print("Note: If 'oposchat_vectors' is not found, check the collection name in the list above.")
