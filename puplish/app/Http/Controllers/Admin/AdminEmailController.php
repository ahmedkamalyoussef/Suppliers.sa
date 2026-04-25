<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Messages\MailMessage;

class AdminEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            // Only allow admins to access email endpoints
            if (!$user || !($user instanceof \App\Models\Admin)) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            return $next($request);
        });
    }

    /**
     * Send email with custom subject and message
     */
    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $to = $request->input('to');
            $subject = $request->input('subject');
            $message = $request->input('message');

            // Create professional email content
            $emailContent = $this->createProfessionalEmailContent($message);

            // Send email using Laravel's Mail facade with Resend
            Mail::raw($emailContent, function ($mail) use ($to, $subject) {
                $mail->to($to)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            // Log the email sending activity
            Log::info('Email sent by admin', [
                'admin_id' => auth()->id(),
                'to' => $to,
                'subject' => $subject,
                'sent_at' => now()
            ]);

            return response()->json([
                'message' => 'Email sent successfully',
                'to' => $to,
                'subject' => $subject,
                'sent_at' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'admin_id' => auth()->id(),
                'to' => $request->input('to'),
                'subject' => $request->input('subject'),
                'error' => $e->getMessage(),
                'failed_at' => now()
            ]);

            return response()->json([
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk emails to multiple recipients
     */
    public function sendBulkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $recipients = $request->input('recipients');
            $subject = $request->input('subject');
            $message = $request->input('message');

            $sentCount = 0;
            $failedRecipients = [];

            foreach ($recipients as $recipient) {
                try {
                    // Create professional email content
                    $emailContent = $this->createProfessionalEmailContent($message);

                    // Send email using Laravel's Mail facade with Resend
                    Mail::raw($emailContent, function ($mail) use ($recipient, $subject) {
                        $mail->to($recipient)
                             ->subject($subject)
                             ->from(config('mail.from.address'), config('mail.from.name'));
                    });

                    $sentCount++;
                } catch (\Exception $e) {
                    $failedRecipients[] = [
                        'email' => $recipient,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log the bulk email sending activity
            Log::info('Bulk email sent by admin', [
                'admin_id' => auth()->id(),
                'total_recipients' => count($recipients),
                'sent_count' => $sentCount,
                'failed_count' => count($failedRecipients),
                'subject' => $subject,
                'sent_at' => now()
            ]);

            return response()->json([
                'message' => 'Bulk email process completed',
                'total_recipients' => count($recipients),
                'sent_count' => $sentCount,
                'failed_count' => count($failedRecipients),
                'failed_recipients' => $failedRecipients,
                'subject' => $subject,
                'sent_at' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send bulk email', [
                'admin_id' => auth()->id(),
                'subject' => $request->input('subject'),
                'error' => $e->getMessage(),
                'failed_at' => now()
            ]);

            return response()->json([
                'message' => 'Failed to send bulk email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create professional email content with proper formatting
     */
    private function createProfessionalEmailContent(string $message): string
    {
        return "Hello!\n\n" .
               $message . "\n\n" .
               "This is an official message from the administration.\n" .
               "If you have any questions, please contact our support team.\n\n" .
               "Best regards,\n" .
               "Administration Team";
    }
}
