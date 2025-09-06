# AI Provider Setup

This application supports both OpenAI (GPT) and Google Gemini as AI providers. OpenAI with GPT-4o-mini is the default and recommended provider. You can switch between them using environment variables.

## Configuration

### 1. Choose Your Provider

Set the AI provider in your `.env` file:

```env
# For OpenAI (GPT) - Default and Recommended
AI_PROVIDER=openai

# For Google Gemini (Alternative)
AI_PROVIDER=gemini
```

### 2. OpenAI Setup

1. Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
2. Add to your `.env` file:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-actual-openai-api-key-here
OPENAI_MODEL=gpt-4o-mini
```

**Available Models:**
- `gpt-4o-mini` (recommended for cost efficiency)
- `gpt-4o`
- `gpt-3.5-turbo`

### 3. Google Gemini Setup

1. Get your API key from [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Add to your `.env` file:

```env
AI_PROVIDER=gemini
GEMINI_API_KEY=your-actual-gemini-api-key-here
GEMINI_MODEL=gemini-1.5-flash
```

**Available Models:**
- `gemini-1.5-flash` (recommended for speed)
- `gemini-1.5-pro`
- `gemini-1.0-pro`

## Features

### ✅ **Implemented Features:**
- **Multi-Provider Support**: Switch between OpenAI and Gemini
- **Persistent Chat History**: All conversations stored in database
- **Context Awareness**: AI remembers conversation history
- **Usage Tracking**: Monitor API usage and costs
- **Error Handling**: Graceful fallbacks when AI services are unavailable
- **Subscription Integration**: Usage limits for free users

### ✅ **Chat Features:**
- **Real-time Messaging**: Instant AI responses
- **Chat Management**: Create, view, and delete conversations
- **Message History**: Full conversation persistence
- **Title Generation**: Automatic chat titles based on content
- **Export Functionality**: Download chat history (Premium feature)

## Testing

1. Replace the test API keys in `.env` with real ones
2. Start the application: `php artisan serve`
3. Navigate to the dashboard and start chatting
4. The AI will respond using your configured provider

## Switching Providers

You can switch providers anytime by:
1. Updating `AI_PROVIDER` in `.env`
2. Ensuring the corresponding API key is set
3. Restarting the application

## Cost Management

- **OpenAI**: Charges per token (input + output)
- **Gemini**: Has generous free tier, then charges per token
- Monitor usage through the application's analytics (Premium feature)

## Troubleshooting

### Common Issues:

1. **"Invalid API Key"**: Check your API key is correct and has proper permissions
2. **"Rate Limited"**: You've exceeded API limits, wait or upgrade your plan
3. **"Model Not Found"**: Ensure the model name is correct for your provider
4. **"Network Error"**: Check internet connection and API service status

### Debug Mode:

Check Laravel logs at `storage/logs/laravel.log` for detailed error information.