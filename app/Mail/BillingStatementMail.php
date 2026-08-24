<?php

namespace App\Mail;

use App\Models\Billing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Billing $billing)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Billing Statement — '.$this->billing->period_label,
        );
    }

    public function content(): Content
    {
        $client = $this->billing->client;

        return new Content(
            view: 'emails.billing-statement',
            with: [
                'billing' => $this->billing,
                'clientName' => $client?->business_name ?: ($client?->name ?? 'Client'),
                'totalLabel' => $this->billing->money($this->billing->total),
                'dueLabel' => ! $this->billing->isPaid() && $this->billing->due_date
                    ? $this->billing->due_date->format('F j, Y')
                    : null,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            $this->statementPdf(),
        ];
    }

    private function statementPdf()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.billing.statement-pdf', [
            'billing' => $this->billing->loadMissing(['client.profile', 'lineItems']),
            'gcashNumber' => \App\Models\Setting::get('gcash_number', ''),
            'bankAccounts' => \App\Models\Setting::get('bank_accounts', []),
        ])->setPaper('a4', 'portrait');

        return \Illuminate\Mail\Mailables\Attachment::fromData(
            fn () => $pdf->output(),
            'Egliane-Billing-Statement-'.\Illuminate\Support\Str::slug($this->billing->period_label).'.pdf'
        )->withMime('application/pdf');
    }
}
