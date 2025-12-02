from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Dict, Optional, Any
import chromadb
from chromadb import CloudClient
import os
from dotenv import load_dotenv

load_dotenv()

app = FastAPI(title="Chroma Cloud Bridge", version="1.0.0")

# Enable CORS for PHP application
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize Chroma Cloud Client
client = CloudClient(
    api_key=os.getenv('CHROMA_API_KEY'),
    tenant=os.getenv('CHROMA_TENANT'),
    database=os.getenv('CHROMA_DATABASE')
)

# Request Models
class CreateCollectionRequest(BaseModel):
    name: str
    metadata: Optional[Dict[str, Any]] = None

class AddDocumentsRequest(BaseModel):
    ids: List[str]
    embeddings: List[List[float]]
    metadatas: Optional[List[Dict[str, Any]]] = None
    documents: Optional[List[str]] = None

class QueryRequest(BaseModel):
    query_embeddings: List[List[float]]
    n_results: int = 5
    where: Optional[Dict[str, Any]] = None
    include: List[str] = ["metadatas", "documents", "distances"]

class DeleteRequest(BaseModel):
    ids: Optional[List[str]] = None
    where: Optional[Dict[str, Any]] = None

# Health Check
@app.get("/health")
async def health_check():
    return {"status": "healthy", "service": "chroma-cloud-bridge"}

# Test Connection
@app.get("/test")
async def test_connection():
    try:
        collections = client.list_collections()
        return {
            "success": True,
            "message": "Chroma Cloud connection successful",
            "collections_count": len(collections)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# List Collections
@app.get("/collections")
async def list_collections():
    try:
        collections = client.list_collections()
        return {
            "collections": [{"name": col.name, "metadata": col.metadata} for col in collections]
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Get or Create Collection
@app.post("/collections/{collection_name}")
async def get_or_create_collection(collection_name: str, request: CreateCollectionRequest = None):
    try:
        metadata = request.metadata if request else {}
        collection = client.get_or_create_collection(
            name=collection_name,
            metadata=metadata
        )
        return {
            "name": collection.name,
            "metadata": collection.metadata,
            "count": collection.count()
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Get Collection
@app.get("/collections/{collection_name}")
async def get_collection(collection_name: str):
    try:
        collection = client.get_collection(name=collection_name)
        return {
            "name": collection.name,
            "metadata": collection.metadata,
            "count": collection.count()
        }
    except Exception as e:
        raise HTTPException(status_code=404, detail=f"Collection '{collection_name}' not found")

# Add Documents to Collection
@app.post("/collections/{collection_name}/add")
async def add_documents(collection_name: str, request: AddDocumentsRequest):
    try:
        collection = client.get_collection(name=collection_name)
        
        collection.add(
            ids=request.ids,
            embeddings=request.embeddings,
            metadatas=request.metadatas,
            documents=request.documents
        )
        
        return {
            "success": True,
            "count": len(request.ids)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Query Collection
@app.post("/collections/{collection_name}/query")
async def query_collection(collection_name: str, request: QueryRequest):
    try:
        collection = client.get_collection(name=collection_name)
        
        results = collection.query(
            query_embeddings=request.query_embeddings,
            n_results=request.n_results,
            where=request.where,
            include=request.include
        )
        
        return results
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Delete Documents from Collection
@app.post("/collections/{collection_name}/delete")
async def delete_documents(collection_name: str, request: DeleteRequest):
    try:
        collection = client.get_collection(name=collection_name)
        
        if request.ids:
            collection.delete(ids=request.ids)
        elif request.where:
            collection.delete(where=request.where)
        else:
            raise HTTPException(status_code=400, detail="Must provide either 'ids' or 'where'")
        
        return {"success": True}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Delete Collection
@app.delete("/collections/{collection_name}")
async def delete_collection(collection_name: str):
    try:
        client.delete_collection(name=collection_name)
        return {"success": True, "message": f"Collection '{collection_name}' deleted"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Get Collection Stats
@app.get("/collections/{collection_name}/stats")
async def get_collection_stats(collection_name: str):
    try:
        collection = client.get_collection(name=collection_name)
        return {
            "name": collection.name,
            "count": collection.count(),
            "metadata": collection.metadata
        }
    except Exception as e:
        raise HTTPException(status_code=404, detail=f"Collection '{collection_name}' not found")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)
