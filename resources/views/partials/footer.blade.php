<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="{{ route('home') }}" class="brand" aria-label="Egliane Accounting Services — Home">
                    <img src="{{ asset('images/logo-icon.png') }}" alt="" class="brand-logo">
                    <span class="brand-name">Egliane Accounting Services</span>
                </a>
                <span class="footer-tagline">Bookkeeping &middot; Tax &middot; Payroll</span>
                <p>Trusted bookkeeping and accounting services for growing businesses. We handle your numbers so you can grow your business.</p>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="{{ route('home') }}#services">Services</a></li>
                    <li><a href="{{ route('home') }}#about">About</a></li>
                    <li><a href="{{ route('home') }}#contact">Contact</a></li>
                    <li><a href="{{ route('help') }}">Help</a></li>
                    <li><a href="{{ route('terms') }}">Terms &amp; Confidentiality</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Our Services</h4>
                <ul>
                    <li><a href="{{ route('home') }}#services">Bookkeeping</a></li>
                    <li><a href="{{ route('home') }}#services">Tax Filing &amp; BIR Compliance</a></li>
                    <li><a href="{{ route('home') }}#services">Financial Statements</a></li>
                    <li><a href="{{ route('home') }}#services">Payroll</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Get Started</h4>
                <ul>
                    <li><a href="{{ route('login') }}">Log In</a></li>
                    <li><a href="{{ route('register') }}">Sign Up</a></li>
                    <li>
                        <a href="{{ config('contact.facebook_url') }}" target="_blank" rel="noopener noreferrer">Facebook Page</a>
                    </li>
                    <li>
                        <a href="{{ config('contact.messenger_url') }}" target="_blank" rel="noopener">Messenger</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} Egliane Accounting Services. All rights reserved.
    </div>
</footer>