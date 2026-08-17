<x-guest-layout>
    <h1 class="auth-title">Forgot password?</h1>
    <p class="auth-sub">No problem. Enter your email and we&rsquo;ll send you a reset link.</p>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" :value="old('email')" required autofocus placeholder="you@gmail.com">
            @error('email')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-block mt-2">Send password reset link</button>
    </form>

    <p class="auth-footer"><a href="{{ route('login') }}">Back to login</a></p>
</x-guest-layout>
