<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="{{ route('home') }}" class="brand">
                    <img src="/images/logo-icon.png" alt="Egliane logo" class="brand-logo">
                    <span class="brand-name">Egliane Accounting Services</span>
                </a>
                <p>Trusted bookkeeping and accounting services for growing businesses. We handle your numbers so you can grow your business.</p>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="{{ route('home') }}#services">Services</a></li>
                    <li><a href="{{ route('home') }}#about">About</a></li>
                    <li><a href="{{ route('login') }}">Login</a></li>
                    <li><a href="{{ route('register') }}">Sign Up</a></li>
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
                <h4>Contact</h4>
                <ul class="footer-contact">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.08 4.18 2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span><a href="tel:+639765841391">0976 584 1391</a></span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <span><a href="mailto:eglianeas2017@gmail.com">eglianeas2017@gmail.com</a></span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                        <span><a href="https://www.facebook.com/profile.php?id=100063691286931" target="_blank" rel="noopener">Egliane Accounting Service Messenger</a></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} Egliane Accounting Services. All rights reserved.
    </div>
</footer>
