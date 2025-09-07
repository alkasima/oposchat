# RAG (Retrieval-Augmented Generation) Setup Guide

## Overview
This guide will help you set up the complete RAG system with vector database support for course-specific AI responses. The system supports both Pinecone (cloud) and local storage options.

## Prerequisites
1. **Required**: OpenAI API key for embeddings
2. **Optional**: Pinecone account (https://pinecone.io) for cloud storage
3. Your existing AI provider (OpenAI or Gemini) for chat completions

## Storage Options

### Option 1: Local Storage (No Pinecone Required)
- ✅ **Free**: No additional costs
- ✅ **Simple**: Works out of the box
- ✅ **Private**: All data stays on your server
- ⚠️ **Limited**: Best for development and small datasets

### Option 2: Pinecone Cloud Storage
- ✅ **Scalable**: Handles large datasets efficiently
- ✅ **Fast**: Optimized for vector search
- ✅ **Reliable**: Managed service with high availability
- 💰 **Cost**: Pay-per-use pricing

## Environment Configuration

### Minimum Required Configuration (Local Storage)
```env
# OpenAI for Embeddings (REQUIRED)
OPENAI_API_KEY=your_openai_api_key_here

# Your existing AI configuration
AI_PROVIDER=openai
AI_PROVIDERS_OPENAI_API_KEY=your_openai_api_key_here
AI_PROVIDERS_OPENAI_MODEL=gpt-3.5-turbo
```

### Optional Pinecone Configuration (Cloud Storage)
```env
# Pinecone Configuration (OPTIONAL - for cloud storage)
PINECONE_API_KEY=your_pinecone_api_key_here
PINECONE_ENVIRONMENT=your_pinecone_environment_here
PINECONE_INDEX_NAME=oposchat
```

**Note**: If Pinecone credentials are not provided or invalid, the system automatically falls back to local storage.

## Setup Steps

### Quick Start (Local Storage)
1. Add your OpenAI API key to `.env`:
   ```env
   OPENAI_API_KEY=your_openai_api_key_here
   ```
2. That's it! The system will automatically use local storage.

### Optional: Pinecone Setup (Cloud Storage)
1. Sign up at https://pinecone.io
2. Create a new project
3. Note your API key and environment (e.g., `us-east-1-aws`)
4. Add these to your `.env` file:
   ```env
   PINECONE_API_KEY=your_pinecone_api_key_here
   PINECONE_ENVIRONMENT=your_pinecone_environment_here
   ```

### Test the Setup
Run this command to test your configuration:
```bash
php artisan tinker
```

Then in tinker:
```php
$vectorStore = app(\App\Services\VectorStoreService::class);
$vectorStore->testConnection(); // Shows which storage is being used
$vectorStore->getStats(); // Shows storage statistics
```

### Upload Course Content
1. Go to `/admin/course-content` (admin access required)
2. Select a course (SAT, GRE, GMAT, or Custom)
3. Upload text content or files (PDF, DOC, TXT, MD)
4. The system will automatically:
   - Chunk the content
   - Generate embeddings
   - Store in your chosen vector storage (local or Pinecone) with course namespace

### Test RAG Functionality
1. Create a new chat
2. Select a course from the dropdown
3. Ask questions related to the uploaded content
4. The AI should now provide course-specific responses

## How It Works

### Document Processing
1. **Chunking**: Documents are split into 1000-character chunks with 200-character overlap
2. **Embedding**: Each chunk is converted to a 1536-dimensional vector using OpenAI's `text-embedding-ada-002`
3. **Storage**: Vectors are stored in your chosen storage (local or Pinecone) with metadata including course namespace

### Retrieval
1. **Query Processing**: User questions are converted to embeddings
2. **Vector Search**: Your storage system searches for similar content using cosine similarity
3. **Context Building**: Top 3 most relevant chunks are retrieved
4. **AI Response**: Context is injected into the AI prompt for course-specific responses

### Course Namespaces
Each course has a unique namespace:
- SAT: `sat-preparation`
- GRE: `gre-preparation`
- GMAT: `gmat-preparation`
- Custom: `custom-preparation`

## Admin Interface

### Course Content Management
- **Upload Text**: Paste course materials directly
- **Upload Files**: Support for PDF, DOC, DOCX, TXT, MD files
- **Content Stats**: View information about stored content
- **Delete Content**: Remove all content for a course

### File Processing
- **PDF**: Basic text extraction (improve with proper PDF library)
- **Word**: Basic text extraction (improve with proper Word library)
- **Text**: Direct content processing
- **Markdown**: Direct content processing

## Troubleshooting

### Common Issues

1. **"Failed to create Pinecone index"**
   - Check your API key and environment
   - Ensure you have sufficient Pinecone credits

2. **"No relevant context found"**
   - Upload course content first
   - Check that course namespace matches

3. **"OpenAI embedding API failed"**
   - Verify your OpenAI API key
   - Check API usage limits

### Logs
Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

## Performance Optimization

### Batch Processing
- Documents are processed in batches of 100 vectors
- Large documents are automatically chunked

### Caching
- Consider implementing Redis caching for frequent queries
- Cache course metadata and stats

### Index Management
- Monitor Pinecone index size and performance
- Consider index optimization for large datasets

## Security Considerations

1. **API Keys**: Store securely in environment variables
2. **Content Privacy**: Course content is stored in Pinecone
3. **Access Control**: Admin interface requires authentication
4. **Data Retention**: Implement content deletion policies

## Next Steps

1. **Improve Document Processing**: Add better PDF/Word extraction
2. **Advanced Chunking**: Implement semantic chunking
3. **Multi-language Support**: Add support for different languages
4. **Analytics**: Track content usage and effectiveness
5. **Content Versioning**: Implement content update mechanisms

## Support

For issues or questions:
1. Check the logs first
2. Verify your API credentials
3. Test with simple text content first
4. Contact support with specific error messages
