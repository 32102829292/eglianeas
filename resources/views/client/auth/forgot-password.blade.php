<x-guest-layout>
    <h1 class="auth-title">Forgot password?</h1>
    <p class="auth-sub">Enter your email and we&rsquo;ll send you a verification code.</p>

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

    <form method="POST" action="{{ route('client.password.send') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" value="{{ old('email', session('email_for_reset')) }}" required autofocus placeholder="you@gmail.com">
            @error('email')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-block mt-2">Send verification code</button>
    </form>

    <p class="auth-footer"><a href="{{ route('login') }}">Back to login</a></p>
</x-guest-layout>
