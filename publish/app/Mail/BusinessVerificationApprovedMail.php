<?php

namespace App\Mail;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BusinessVerificationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Supplier $supplier;
    public string $supplierName;
    public string $appName;
    public string $visitUrl;
    public string $refCode;

    /**
     * Create a new message instance.
     */
    public function __construct(Supplier $supplier)
    {
        $this->supplier = $supplier;
        $this->supplierName = $supplier->name ?? ($supplier->profile->business_name ?? 'Valued Partner');
        
        $appName = config('app.name');
        if (empty($appName) || $appName === 'Laravel') {
            $appName = 'Supplier.sa';
        }
        $this->appName = $appName;
        
        $this->visitUrl = 'https://supplier.sa/';
        $this->refCode = 'REF-' . strtoupper(substr(md5($supplier->id . time() . rand()), 0, 8));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->appName} Account Has Been Verified ✅",
        );
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        return new Headers(
            messageId: md5(uniqid(microtime(), true)) . '@supplier.sa',
            text: [
                'X-Entity-Ref-ID' => uniqid(),
                'X-Auto-Response-Suppress' => 'All',
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification_approved',
            with: [
                'supplierName' => $this->supplierName,
                'appName' => $this->appName,
                'visitUrl' => $this->visitUrl,
                'refCode' => $this->refCode,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
