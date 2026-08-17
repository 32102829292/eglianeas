<section>
    <h3 class="card-title">Profile information</h3>
    <p class="card-sub">Update your name and email address.</p>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name">
            @error('name')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
        @if (session('status') === 'profile-updated')
            <span class="form-hint">Saved.</span>
        @endif
    </form>
</section>
