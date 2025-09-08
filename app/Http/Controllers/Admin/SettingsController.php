<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(SettingsService $settings)
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'ai_provider' => config('ai.provider'),
                'openai_api_key' => $settings->get('OPENAI_API_KEY'),
                'openai_model' => $settings->get('OPENAI_MODEL', config('ai.providers.openai.model')),
                'gemini_api_key' => $settings->get('GEMINI_API_KEY'),
                'gemini_model' => $settings->get('GEMINI_MODEL', config('ai.providers.gemini.model')),
                'pinecone_api_key' => $settings->get('PINECONE_API_KEY'),
                'pinecone_environment' => $settings->get('PINECONE_ENVIRONMENT'),
                'pinecone_index_name' => $settings->get('PINECONE_INDEX_NAME', config('services.pinecone.index_name')),
                'stripe_key' => $settings->get('STRIPE_KEY'),
                'stripe_secret' => $settings->get('STRIPE_SECRET'),
                'stripe_webhook_secret' => $settings->get('STRIPE_WEBHOOK_SECRET'),
            ],
        ]);
    }

    public function updateKeys(Request $request, SettingsService $settings)
    {
        $data = $request->validate([
            'OPENAI_API_KEY' => ['nullable', 'string'],
            'OPENAI_MODEL' => ['nullable', 'string'],
            'GEMINI_API_KEY' => ['nullable', 'string'],
            'GEMINI_MODEL' => ['nullable', 'string'],
            'PINECONE_API_KEY' => ['nullable', 'string'],
            'PINECONE_ENVIRONMENT' => ['nullable', 'string'],
            'PINECONE_INDEX_NAME' => ['nullable', 'string'],
            'STRIPE_KEY' => ['nullable', 'string'],
            'STRIPE_SECRET' => ['nullable', 'string'],
            'STRIPE_WEBHOOK_SECRET' => ['nullable', 'string'],
        ]);

        foreach ($data as $key => $value) {
            $settings->set($key, $value);
        }

        return back()->with('success', 'Settings updated successfully');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return back()->with('success', 'Password updated successfully');
    }
}


