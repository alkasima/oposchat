import urllib.request
import json
import time

BASE_URL = 'http://localhost:8001'

def make_request(endpoint, method='GET', data=None):
    url = f"{BASE_URL}{endpoint}"
    req = urllib.request.Request(url, method=method)
    req.add_header('Content-Type', 'application/json')
    
    if data:
        json_data = json.dumps(data).encode('utf-8')
        req.data = json_data

    try:
        with urllib.request.urlopen(req) as response:
            return json.loads(response.read().decode())
    except urllib.error.HTTPError as e:
        print(f"HTTP Error {e.code}: {e.read().decode()}")
        return None
    except Exception as e:
        print(f"Error: {e}")
        return None

# 1. List collections
print("Listing collections...")
collections_data = make_request('/collections')
if not collections_data:
    exit(1)

collections = collections_data.get('collections', [])
print(f"Found {len(collections)} collections")

target_collection = 'oposchat_vectors'
found = False
for col in collections:
    print(f" - {col['name']} (metadata: {col['metadata']})")
    if col['name'] == target_collection:
        found = True

# 2. Create or Get collection
if not found:
    print(f"Collection '{target_collection}' not found. Creating...")
    # Just use a test one to avoid messing with prod if needed, 
    # but user wants to know if "My uploads go to chroma", so testing the actual one is better if we are careful.
    # Actually, let's use a specific test verification collection to be safe.
    target_collection = "verification_test_collection"
    make_request(f'/collections/{target_collection}', method='POST', data={"name": target_collection})
else:
    print(f"Using existing collection: {target_collection}")

# 3. Get initial count
stats = make_request(f'/collections/{target_collection}/stats')
initial_count = stats['count']
print(f"Initial count: {initial_count}")

# 4. Add document
print("Adding test document...")
doc_id = f"test_id_{time.time()}"
add_data = {
    "ids": [doc_id],
    "embeddings": [[0.1] * 1536], # Dummy embedding
    "metadatas": [{"source": "verification_script"}],
    "documents": ["This is a test document to verify cloud upload."]
}
make_request(f'/collections/{target_collection}/add', method='POST', data=add_data)

# 5. Verify count
stats = make_request(f'/collections/{target_collection}/stats')
new_count = stats['count']
print(f"New count: {new_count}")

if new_count > initial_count:
    print("SUCCESS: Count increased!")
else:
    print("FAILURE: Count did not increase.")
