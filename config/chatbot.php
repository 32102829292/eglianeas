<?php

return [

    'enabled' => true,

    'name' => 'Egliane Assistant',

    'welcome_message' => 'Hello! I am the Egliane Assistant. I can help you with filing status, documents, services, pricing and more. How can I help you today?',

    'messenger_url' => 'https://www.facebook.com/profile.php?id=100063691286931',

    'fallback_message' => "I'm not sure about that one yet. For anything else, message us on Facebook Messenger and our team will help you right away.",

    'rules' => [
        [
            'keywords' => ['filing', 'bir', 'tax return', 'deadline', 'due', 'status'],
            'response' => 'You can check your filing status anytime in the Filings section of your dashboard. BIR filing deadlines are usually on the 15th of the following month. If you are unsure about a specific return, just message us on Messenger and our accountants will confirm your due date.',
        ],
        [
            'keywords' => ['transaction', 'income', 'expense', 'record', 'history', 'activity'],
            'response' => 'All your recorded transactions appear in the Transactions section of your dashboard. You can filter by date and category there. If something looks off, let us know on Messenger and we will check it for you.',
        ],
        [
            'keywords' => ['document', 'upload', 'receipt', 'file', 'attachment'],
            'response' => 'You can upload documents (receipts, invoices, forms) from the Documents section of your dashboard. We will review them and attach them to your records. Uploads work even when you are offline — they sync when you reconnect.',
        ],
        [
            'keywords' => ['price', 'pricing', 'cost', 'rate', 'fee', 'how much'],
            'response' => 'Our service plans vary depending on your business size and needs. Message us on Messenger for a personalized quote — it is free to ask and we usually reply within the day.',
        ],
        [
            'keywords' => ['service', 'bookkeeping', 'tax', 'payroll', 'consultation', 'offer', 'what do you do'],
            'response' => 'Egliane Accounting Services offers bookkeeping, tax filing and BIR compliance, financial statements, payroll, business registration assistance, and consulting. Which one are you interested in? I can point you to the right contact.',
        ],
        [
            'keywords' => ['hours', 'open', 'schedule', 'office'],
            'response' => 'We generally operate on weekdays, 8:00 AM to 5:00 PM (Philippine time). If you need urgent help, send us a message on Messenger and we will get back to you as soon as possible.',
        ],
        [
            'keywords' => ['consultation', 'book', 'appointment', 'meet', 'schedule meeting'],
            'response' => 'You can book a consultation by sending us a message on Messenger or emailing eglianeas2017@gmail.com. Tell us your business name and what you need help with, and we will set you up.',
        ],
        [
            'keywords' => ['payment', 'pay', 'bill', 'invoice', 'charge'],
            'response' => 'Payment arrangements are handled directly with your accountant. Send us a message on Messenger with your account name and we will confirm your balance and payment options.',
        ],
        [
            'keywords' => ['login', 'pin', 'password', 'forgot', 'biometric', 'face'],
            'response' => 'You can log in with your email and password, a 4-digit PIN, or Face / biometric login if you have enrolled it in your security settings. Need to reset your password? Use the "Forgot password" link on the login page.',
        ],
        [
            'keywords' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'],
            'response' => 'Hello! How can I help you today? You can ask me about filing status, transactions, documents, services, pricing, or business hours.',
        ],
        [
            'keywords' => ['offline', 'no internet', 'connection'],
            'response' => 'This app works offline! You can still browse your last-loaded pages while you are offline. Actions like messages and uploads are queued and sent automatically when you reconnect.',
        ],
    ],
];
