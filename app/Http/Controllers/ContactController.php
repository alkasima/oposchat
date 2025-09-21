<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Handle contact form submission
     */
    public function submitContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:2000',
            'terms_accepted' => 'required|boolean|accepted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill in all fields correctly.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Here you would typically send an email or store in database
            // For now, we'll just log the contact form submission
            \Log::info('Contact form submission', [
                'subject' => $request->subject,
                'name' => $request->name,
                'email' => $request->email,
                'message' => $request->message,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // TODO: Send email notification to admin
            // Mail::to('admin@oposchat.com')->send(new ContactFormMail($request->all()));

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully. We will respond soon.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending message. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle new oppositions request form submission
     */
    public function submitOppositionsRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request' => 'required|string|max:2000',
            'terms_accepted' => 'required|boolean|accepted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill in all fields correctly.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Here you would typically send an email or store in database
            // For now, we'll just log the oppositions request
            \Log::info('New oppositions request', [
                'request' => $request->request,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // TODO: Send email notification to admin
            // Mail::to('admin@oposchat.com')->send(new OppositionsRequestMail($request->all()));

            return response()->json([
                'success' => true,
                'message' => 'Request sent successfully. We will review it and inform you.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Oppositions request submission failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending request. Please try again.'
            ], 500);
        }
    }
}
