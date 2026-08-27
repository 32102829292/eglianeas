<x-guest-layout>
    <h1 class="auth-title">Enter verification code</h1>
    <p class="auth-sub">We sent a 6-digit code to <strong>{{ session('email_for_reset', 'your email') }}</strong>. It expires in 15 minutes.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('client.password.verify.post') }}">
        @csrf
        <input type="hidden" name="email" value="{{ session('email_for_reset') }}">
        <div class="form-group">
            <label class="form-label" for="code">6-digit code</label>
            <input class="form-control" id="code" type="text" name="code" value="{{ old('code') }}" required autofocus autocomplete="one-time-code" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]*" style="text-align:center; font-size:24px; letter-spacing:8px;">
            @error('code')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-block mt-2">Verify code</button>
    </form>

    <p class="auth-footer">
        <form method="POST" action="{{ route('client.password.send') }}" style="display:inline;">
            @csrf
            <input type="hidden" name="email" value="{{ session('email_for_reset') }}">
            <button type="submit" class="link-style">Resend code</button>
        </form>
        &middot; <a href="{{ route('client.password.forgot') }}">Use a different email</a>
    </p>
</x-guest-layout>
