<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

class AudioTranscriptionController extends Controller
{
    /**
     * Transcribe audio using OpenAI Whisper API
     */
    public function transcribe(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'audio' => 'required|file|mimes:webm,mp3,wav,m4a,mp4,ogg|max:25000', // 25MB max
            ]);

            $audioFile = $request->file('audio');
            
            if (!$audioFile) {
                return response()->json([
                    'success' => false,
                    'error' => 'No audio file provided'
                ], 400);
            }

            // Get OpenAI API key from config
            $apiKey = config('ai.providers.openai.api_key');
            
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'OpenAI API key not configured'
                ], 500);
            }

            // Prepare the file for OpenAI Whisper API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->attach(
                'file', file_get_contents($audioFile->getPathname()), $audioFile->getClientOriginalName()
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'en', // You can make this configurable
                'response_format' => 'text',
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI Whisper API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => Auth::id()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Transcription failed: ' . $response->body()
                ], 500);
            }

            $transcribedText = trim($response->body());
            
            if (empty($transcribedText)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No text was transcribed from the audio'
                ], 400);
            }

            Log::info('Audio transcription successful', [
                'user_id' => Auth::id(),
                'text_length' => strlen($transcribedText),
                'file_size' => $audioFile->getSize()
            ]);

            return response()->json([
                'success' => true,
                'text' => $transcribedText
            ]);

        } catch (Exception $e) {
            Log::error('Audio transcription error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred during transcription: ' . $e->getMessage()
            ], 500);
        }
    }
}

