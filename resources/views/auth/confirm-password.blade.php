<x-guest-layout>
    <h1 class="auth-title">Confirm your password</h1>
    <p class="auth-sub">This is a secure area of the app. Please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-block mt-2">Confirm</button>
    </form>
</x-guest-layout>
