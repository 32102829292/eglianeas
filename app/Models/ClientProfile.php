<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfile extends Model
{
    use HasFactory;

    public const STATUS_CURRENT = 'current';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELINQUENT = 'delinquent';
    public const STATUS_CRITICAL = 'critical';

    public const STATUSES = [
        self::STATUS_CURRENT => 'Current',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_DELINQUENT => 'Delinquent',
        self::STATUS_CRITICAL => 'Critical',
    ];

    public const STATUS_NOTES = [
        self::STATUS_CURRENT => 'All filings and payments are up to date.',
        self::STATUS_PENDING => 'Some requirements are awaiting completion.',
        self::STATUS_DELINQUENT => 'Action is needed on your filings or payments.',
        self::STATUS_CRITICAL => 'Immediate attention is required.',
    ];

    public const BUSINESS_TYPES = [
        'Sole Proprietorship',
        'Partnership',
        'Corporation',
        'Cooperative',
        'One Person Corporation (OPC)',
    ];

    public const TAXPAYER_TYPES = [
        'Mixed Income Earner',
        'Self-Employed',
        'Corporation',
        'Cooperative',
    ];

    public const LINE_OF_BUSINESS_OPTIONS = [
        'Retail & Wholesale',
        'Food & Beverage',
        'Professional Services',
        'Manufacturing',
        'Construction',
        'Real Estate',
        'Transportation & Logistics',
        'Technology/IT Services',
        'Health & Wellness',
        'Other',
    ];

    public const BIR_REGISTRATION_TYPES = [
        'VAT',
        'Non-VAT',
        'Exempt',
    ];

    public const SECOND_CONTACT_CHANNEL_PHONE = 'phone';
    public const SECOND_CONTACT_CHANNEL_VIBER = 'viber';
    public const SECOND_CONTACT_CHANNEL_FACEBOOK = 'facebook';
    public const SECOND_CONTACT_CHANNEL_TELEGRAM = 'telegram';

    public const SECOND_CONTACT_CHANNELS = [
        self::SECOND_CONTACT_CHANNEL_PHONE,
        self::SECOND_CONTACT_CHANNEL_VIBER,
        self::SECOND_CONTACT_CHANNEL_FACEBOOK,
        self::SECOND_CONTACT_CHANNEL_TELEGRAM,
    ];

    public const SECOND_CONTACT_URL_CHANNELS = [
        self::SECOND_CONTACT_CHANNEL_FACEBOOK,
        self::SECOND_CONTACT_CHANNEL_TELEGRAM,
    ];

    public const SECOND_CONTACT_CHANNEL_LABELS = [
        self::SECOND_CONTACT_CHANNEL_PHONE => 'Phone',
        self::SECOND_CONTACT_CHANNEL_VIBER => 'Viber',
        self::SECOND_CONTACT_CHANNEL_FACEBOOK => 'Facebook',
        self::SECOND_CONTACT_CHANNEL_TELEGRAM => 'Telegram',
    ];

    protected $fillable = [
        'user_id',
        'taxpayer_name',
        'business_type',
        'taxpayer_type',
        'line_of_business',
        'bir_registration_type',
        'business_address',
        'latitude',
        'longitude',
        'contact_no',
        'second_contact_name',
        'second_contact_channel',
        'second_contact_no',
        'second_email',
        'facebook_url',
        'messenger_url',
        'website_url',
        'birth_date',
        'tin_no',
        'tin_document_path',
        'valid_id_document_path',
        'mother_maiden_name',
        'father_name',
        'status',
        'payment_status',
        'date_started',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'date_started' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function paymentStatusLabel(): ?string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'partial' => 'Partial',
            'unpaid' => 'Unpaid',
            default => null,
        };
    }

    public function secondContactChannelLabel(): string
    {
        return self::SECOND_CONTACT_CHANNEL_LABELS[$this->second_contact_channel]
            ?? self::SECOND_CONTACT_CHANNEL_LABELS[self::SECOND_CONTACT_CHANNEL_PHONE];
    }

    public function getSecondContactDisplayAttribute(): string
    {
        if (empty($this->second_contact_no)) {
            return '';
        }

        return $this->secondContactChannelLabel().': '.$this->second_contact_no;
    }

    /**
     * Get the contact number formatted for tel: link
     */
    public function getContactNoTelAttribute(): ?string
    {
        if (empty($this->contact_no)) {
            return null;
        }

        // Normalize phone number for tel: link (remove spaces, dashes, parentheses)
        $clean = preg_replace('/[\s\-()]/', '', $this->contact_no);

        // Convert 09xx to +639xx for international format
        if (str_starts_with($clean, '09') && strlen($clean) === 11) {
            return '+63'.substr($clean, 1);
        }

        return $clean;
    }

    /**
     * Get the second contact number formatted for tel: link (if it's a phone)
     */
    public function getSecondContactNoTelAttribute(): ?string
    {
        if (empty($this->second_contact_no) || $this->second_contact_channel !== self::SECOND_CONTACT_CHANNEL_PHONE) {
            return null;
        }

        $clean = preg_replace('/[\s\-()]/', '', $this->second_contact_no);

        if (str_starts_with($clean, '09') && strlen($clean) === 11) {
            return '+63'.substr($clean, 1);
        }

        return $clean;
    }

    /**
     * Get Facebook URL with proper protocol
     */
    public function getFacebookUrlAttribute(): ?string
    {
        if (empty($this->facebook_url)) {
            return null;
        }

        $url = $this->facebook_url;
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * Get Messenger URL with proper protocol
     */
    public function getMessengerUrlAttribute(): ?string
    {
        if (empty($this->messenger_url)) {
            return null;
        }

        $url = $this->messenger_url;
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * Get website URL with proper protocol
     */
    public function getWebsiteUrlAttribute(): ?string
    {
        if (empty($this->website_url)) {
            return null;
        }

        $url = $this->website_url;
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
