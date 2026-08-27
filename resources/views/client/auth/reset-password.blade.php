<x-guest-layout>
    <h1 class="auth-title">Set a new password</h1>
    <p class="auth-sub">Choose a strong password you haven&rsquo;t used before.</p>

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (! session('reset_token'))
        <div class="alert alert-error">Session expired. Please start the password reset process again.</div>
        <p class="auth-footer"><a href="{{ route('client.password.forgot') }}">Reset password</a></p>
    @else
        <form method="POST" action="{{ route('client.password.reset.post') }}">
            @csrf
            <input type="hidden" name="email" value="{{ session('reset_email') }}">

            <div class="form-group">
                <label class="form-label" for="password">New password</label>
                <input class="form-control" id="password" type="password" name="password" required autofocus autocomplete="new-password" placeholder="Min. 8 characters">
                @error('password')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                @error('password_confirmation')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-2">Reset password</button>
        </form>
    @endif

    <p class="auth-footer"><a href="{{ route('login') }}">Back to login</a></p>
</x-guest-layout>
