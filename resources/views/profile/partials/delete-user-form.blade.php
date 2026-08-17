<section>
    <h3 class="card-title danger">Delete account</h3>
    <p class="card-sub">Deleting your account permanently removes all of its data. Please download anything you want to keep first.</p>

    <button type="button" class="btn btn-outline danger" data-toggle="#deleteAccountModal">Delete account</button>

    <div id="deleteAccountModal" class="modal hidden">
        <div class="modal-card">
            <h3>Delete your account?</h3>
            <p>This action cannot be undone. Enter your password to confirm.</p>
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')
                <div class="form-group">
                    <input class="form-control" name="password" type="password" placeholder="Password" required>
                    @error('password', 'userDeletion')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="btn-group-row">
                    <button type="button" class="btn btn-outline" data-modal-close="#deleteAccountModal">Cancel</button>
                    <button type="submit" class="btn btn-outline danger">Delete account</button>
                </div>
            </form>
        </div>
    </div>
</section>
