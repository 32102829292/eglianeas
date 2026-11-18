<?php
    $canManage = auth()->user()->canManageClients();
    $maskTin = function (?string $value): string {
        if (! $value) { return ''; }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };
    $maskName = function (?string $value): string {
        if (! $value) { return ''; }
        return mb_substr($value, 0, 1).str_repeat('•', max(mb_strlen($value) - 1, 0));
    };
?>



<?php $__env->startSection('title', ($client->business_name ?: $client->name).' — Clients — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <a href="<?php echo e(route('admin.clients.index')); ?>" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Clients
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1><?php echo e($client->business_name ?: $client->name); ?></h1>
            <p><?php echo e($client->name); ?> &middot; <a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a></p>
        </div>
        <div class="btn-row">
            <?php if($canManage): ?>
                <form method="POST" action="<?php echo e(route('admin.clients.impersonate', $client)); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'View application as <?php echo e(addslashes($client->business_name ?: $client->name)); ?>?', message: 'You can exit anytime from the top banner.', confirmLabel: 'Login as Client' });">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-outline">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Login as Client
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?php echo e(route('admin.billing.show', $client)); ?>" class="btn btn-outline">Billing Statements</a>
            <a href="<?php echo e(route('admin.distribution.show', $client)); ?>" class="btn btn-outline">Document Distribution</a>
            <?php if($canManage): ?>
                <a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="btn btn-primary">Edit profile</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-meta">
        <div class="profile-meta-chip">
            <span class="meta-k">Taxpayer ID</span>
            <span class="meta-v code-pill"><?php echo e($client->client_code ?? '—'); ?></span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Account Status</span>
            <span class="badge badge-<?php echo e($profile->status); ?>"><?php echo e($profile->statusLabel()); ?></span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Payment Status</span>
            <span class="badge badge-<?php echo e($profile->payment_status ?? 'unpaid'); ?>"><?php echo e($profile->paymentStatusLabel() ?? '—'); ?></span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Date Started</span>
            <span class="meta-v"><?php echo e($profile->date_started?->format('M j, Y') ?? '—'); ?></span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Outstanding</span>
            <span class="meta-v <?php echo e($billingStats['outstanding'] > 0 ? 'text-danger' : ''); ?>">₱<?php echo e(number_format($billingStats['outstanding'], 2)); ?></span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Documents</span>
            <span class="meta-v"><?php echo e($documentCount); ?></span>
        </div>
    </div>

    <?php if($canManage): ?>
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Status</h2>
            <form method="POST" action="<?php echo e(route('admin.clients.update', $client)); ?>" class="inline-form" id="statusForm">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" name="name" value="<?php echo e($client->name); ?>">
                <input type="hidden" name="email" value="<?php echo e($client->email); ?>">
                <input type="hidden" name="status" value="<?php echo e($profile->status); ?>">
                <select class="form-control form-control-sm" name="payment_status" onchange="egliane.confirm.form(this.form, { title: 'Change payment status?', message: 'Change the payment status to ' + this.options[this.selectedIndex].text.trim() + '?' });">
                    <option value="">Payment: —</option>
                    <option value="paid" <?php if($profile->payment_status === 'paid'): echo 'selected'; endif; ?>>Paid</option>
                    <option value="partial" <?php if($profile->payment_status === 'partial'): echo 'selected'; endif; ?>>Partial</option>
                    <option value="unpaid" <?php if($profile->payment_status === 'unpaid'): echo 'selected'; endif; ?>>Unpaid</option>
                </select>
            </form>
        </div>
        <p class="card-sub"><?php echo e($statusNotes[$profile->status] ?? ''); ?></p>
        <div class="status-options">
            <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <form method="POST" action="<?php echo e(route('admin.clients.update', $client)); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    <input type="hidden" name="name" value="<?php echo e($client->name); ?>">
                    <input type="hidden" name="email" value="<?php echo e($client->email); ?>">
                    <input type="hidden" name="payment_status" value="<?php echo e($profile->payment_status); ?>">
                    <input type="hidden" name="status" value="<?php echo e($value); ?>">
                    <button type="submit" class="btn btn-sm <?php echo e($profile->status === $value ? 'btn-primary' : 'btn-outline'); ?>" onclick="return egliane.confirm.button(this, { title: 'Change account status?', message: 'Change the account status to <?php echo e(addslashes($label)); ?>?' });"><?php echo e($label); ?></button>
                </form>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Business information</h3>
                <?php if($canManage): ?><a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="link">Edit</a><?php endif; ?>
            </div>
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Business name</span><span class="profile-v"><?php echo e($client->business_name ?: '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">Taxpayer's name</span><span class="profile-v"><?php echo e($profile->taxpayer_name ?: '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">Business type</span><span class="profile-v"><?php echo e($profile->business_type ?: '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">Type of taxpayer</span><span class="profile-v"><?php echo e($profile->taxpayer_type ?: '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">Line of business</span><span class="profile-v"><?php echo e($profile->line_of_business ?: '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">BIR registration</span><span class="profile-v"><?php echo e($profile->bir_registration_type ?: '—'); ?></span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Address &amp; location</h3>
                <?php if($canManage): ?><a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="link">Edit</a><?php endif; ?>
            </div>
            <div class="profile-grid">
                <div class="profile-row col-span-2"><span class="profile-k">Business address</span><span class="profile-v"><?php if($profile->business_address): ?><a href="https://www.google.com/maps/search/?api=1&query=<?php echo e(urlencode($profile->business_address)); ?>" target="_blank" rel="noopener" class="contact-link"><?php echo e($profile->business_address); ?></a><?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">Latitude</span><span class="profile-v"><?php echo e($profile->latitude ?? '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">Longitude</span><span class="profile-v"><?php echo e($profile->longitude ?? '—'); ?></span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Contact information</h3>
                <?php if($canManage): ?><a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="link">Edit</a><?php endif; ?>
            </div>
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Contact number</span><span class="profile-v"><?php if($profile->contact_no_tel): ?><a href="tel:<?php echo e($profile->contact_no_tel); ?>" class="contact-link"><?php echo e($profile->contact_no); ?></a><?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">Email</span><span class="profile-v"><a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a></span></div>
                <div class="profile-row"><span class="profile-k">Facebook</span><span class="profile-v"><?php if($profile->facebook_url): ?><a href="<?php echo e($profile->facebook_url); ?>" target="_blank" rel="noopener" class="contact-link"><?php echo e($profile->facebook_url); ?></a><?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">Messenger</span><span class="profile-v"><?php if($profile->messenger_url): ?><a href="<?php echo e($profile->messenger_url); ?>" target="_blank" rel="noopener" class="contact-link"><?php echo e($profile->messenger_url); ?></a><?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">Website</span><span class="profile-v"><?php if($profile->website_url): ?><a href="<?php echo e($profile->website_url); ?>" target="_blank" rel="noopener" class="contact-link"><?php echo e($profile->website_url); ?></a><?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">2nd contact name</span><span class="profile-v"><?php echo e($profile->second_contact_name ?: '—'); ?></span></div>
                <div class="profile-row"><span class="profile-k">2nd contact</span><span class="profile-v"><?php if($profile->second_contact_display): ?><?php if($profile->second_contact_channel === \App\Models\ClientProfile::SECOND_CONTACT_CHANNEL_PHONE && $profile->second_contact_no_tel): ?><a href="tel:<?php echo e($profile->second_contact_no_tel); ?>" class="contact-link"><?php echo e($profile->second_contact_display); ?></a><?php else: ?><?php echo e($profile->secondContactChannelLabel()); ?>: <?php if (isset($component)) { $__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.url-link','data' => ['value' => $profile->second_contact_no]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('url-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($profile->second_contact_no)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24)): ?>
<?php $attributes = $__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24; ?>
<?php unset($__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24)): ?>
<?php $component = $__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24; ?>
<?php unset($__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24); ?>
<?php endif; ?><?php endif; ?>
                    <?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">2nd email</span><span class="profile-v"><?php if($profile->second_email): ?><a href="mailto:<?php echo e($profile->second_email); ?>" class="contact-link"><?php echo e($profile->second_email); ?></a><?php else: ?> — <?php endif; ?></span></div>
                <div class="profile-row"><span class="profile-k">Birth date</span><span class="profile-v"><?php echo e($profile->birth_date?->format('M j, Y') ?? '—'); ?></span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">BIR details</h3>
                <?php if($canManage): ?><a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="link">Edit</a><?php endif; ?>
            </div>
            <div class="form-hint mb-2">Sensitive fields are masked.</div>
            <div class="profile-grid">
                <div class="profile-row">
                    <span class="profile-k">TIN number</span>
                    <span class="profile-v">
                        <?php if($profile->tin_no): ?>
                            <span class="reveal-text" data-full="<?php echo e($profile->tin_no); ?>" data-masked="<?php echo e($maskTin($profile->tin_no)); ?>"><?php echo e($maskTin($profile->tin_no)); ?></span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Mother&rsquo;s maiden name</span>
                    <span class="profile-v">
                        <?php if($profile->mother_maiden_name): ?>
                            <span class="reveal-text" data-full="<?php echo e($profile->mother_maiden_name); ?>" data-masked="<?php echo e($maskName($profile->mother_maiden_name)); ?>"><?php echo e($maskName($profile->mother_maiden_name)); ?></span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Father&rsquo;s name</span>
                    <span class="profile-v">
                        <?php if($profile->father_name): ?>
                            <span class="reveal-text" data-full="<?php echo e($profile->father_name); ?>" data-masked="<?php echo e($maskName($profile->father_name)); ?>"><?php echo e($maskName($profile->father_name)); ?></span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">Certificate of Registration</h3>
                <?php if($profile->cor_document_path && $canManageCor): ?>
                    <a href="<?php echo e(route('admin.clients.cor.download', $client)); ?>" class="link">Download</a>
                <?php endif; ?>
            </div>
            <div class="form-hint mb-2">The Certificate of Registration is stored separately from BIR forms, billings, and other client records.</div>
            <?php if($profile->cor_document_path): ?>
                <div class="profile-grid">
                    <div class="profile-row">
                        <span class="profile-k">Status</span>
                        <span class="profile-v"><span class="badge badge-success">Available</span></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-k">File</span>
                        <span class="profile-v"><?php echo e($profile->cor_document_name ?: basename($profile->cor_document_path)); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-k">Uploaded</span>
                        <span class="profile-v"><?php echo e($profile->cor_document_uploaded_at?->format('M j, Y g:i A') ?? '—'); ?></span>
                    </div>
                </div>
                <?php if($canManageCor): ?>
                    <div class="btn-row mt-2">
                        <a href="<?php echo e(route('admin.clients.cor.view', $client)); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">View</a>
                        <a href="<?php echo e(route('admin.clients.cor.download', $client)); ?>" class="btn btn-outline btn-sm">Download</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="form-hint">No Certificate of Registration uploaded.</p>
            <?php endif; ?>
            <?php if($canManageCor): ?>
                <form method="POST" action="<?php echo e(route('admin.clients.cor.upload', $client)); ?>" enctype="multipart/form-data" class="mt-2">
                    <?php echo csrf_field(); ?>
                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="certificate_of_registration"><?php echo e($profile->cor_document_path ? 'Replace Certificate' : 'Upload Certificate'); ?></label>
                            <input class="form-control" id="certificate_of_registration" name="certificate_of_registration" type="file" accept=".pdf,image/jpeg,image/png" required>
                            <div class="form-hint">PDF, JPG, or PNG; maximum 10 MB. Files are stored privately.</div>
                            <?php $__errorArgs = ['certificate_of_registration'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="form-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo e($profile->cor_document_path ? 'Replace Certificate' : 'Upload Certificate'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">BIR forms</h3>
                <?php if($canManage): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBirFormModal">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add BIR Form
                    </button>
                <?php endif; ?>
            </div>
            <?php if($applicableForms->isNotEmpty()): ?>
                <div class="bir-form-badges">
                    <?php $__currentLoopData = $applicableForms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="badge badge-neutral bir-form-badge"><?php echo e($type); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php else: ?>
                <div class="form-hint">No BIR forms configured for this client yet.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Internal remarks</h3>
                <?php if($canManage): ?><a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="link">Edit</a><?php endif; ?>
            </div>
            <?php if($profile->remarks): ?>
                <p class="pre-wrap"><?php echo e($profile->remarks); ?></p>
            <?php else: ?>
                <div class="form-hint">No remarks yet.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Custom info entries</h3>
            </div>
            <?php if($client->infoEntries->isNotEmpty()): ?>
                <div class="profile-grid">
                    <?php $__currentLoopData = $client->infoEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="profile-row">
                            <span class="profile-k"><?php echo e($entry->key); ?></span>
                            <span class="profile-v">
                                <?php if($entry->value): ?>
                                    <?php if (isset($component)) { $__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.url-link','data' => ['value' => $entry->value]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('url-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($entry->value)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24)): ?>
<?php $attributes = $__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24; ?>
<?php unset($__attributesOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24)): ?>
<?php $component = $__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24; ?>
<?php unset($__componentOriginalf3dd7d4b5c900d0fe964c9d0e9e13d24); ?>
<?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                                <?php if($canManage): ?>
                                    <button type="button" class="btn btn-link btn-sm muted edit-info-btn"
                                            data-entry-id="<?php echo e($entry->id); ?>"
                                            data-key="<?php echo e($entry->key); ?>"
                                            data-value="<?php echo e($entry->value); ?>"
                                            class="ms-1">Edit</button>
                                    <form method="POST" action="<?php echo e(route('admin.clients.destroyInfoEntry', [$client, $entry])); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Delete this entry?', message: 'This custom info entry will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-outline danger btn-sm ms-1">Del</button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php else: ?>
                <div class="form-hint">No custom info entries yet.</div>
            <?php endif; ?>

            <?php if($canManage): ?>
                <div class="section-divider"></div>

                <form method="POST" action="<?php echo e(route('admin.clients.storeInfoEntry', $client)); ?>" class="form-inline" id="addInfoForm">
                <?php echo csrf_field(); ?>
                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="info-key">Key</label>
                        <input class="form-control" id="info-key" name="key" type="text" placeholder="e.g. Fiscal Year End" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="info-value">Value</label>
                        <input class="form-control" id="info-value" name="value" type="text" placeholder="e.g. December 31">
                    </div>
                </div>
                <button type="submit" class="btn btn-outline btn-sm mt-2">Add entry</button>
            </form>

            <div id="edit-info-modal" class="edit-info-box">
                <form method="POST" id="edit-info-form" class="form-inline">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label">Key</label>
                            <input class="form-control" id="edit-info-key" name="key" type="text" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Value</label>
                            <input class="form-control" id="edit-info-value" name="value" type="text">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-2">Save</button>
                    <button type="button" class="btn btn-outline btn-sm mt-2 cancel-edit-info">Cancel</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <?php if($canManage): ?>
    <div class="modal fade" id="addBirFormModal" tabindex="-1" aria-labelledby="addBirFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="<?php echo e(route('admin.bir-form-types.store')); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="modal-header">
                        <h5 class="modal-title" id="addBirFormModalLabel">Add BIR Form</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="bir_form_code">Form Code <span class="text-danger">*</span></label>
                            <input class="form-control" id="bir_form_code" name="code" type="text" maxlength="30" required placeholder="e.g. 1800">
                            <div class="form-hint">Unique code/number for the BIR form (e.g., 1701, 2551Q, 1800)</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="bir_form_name">Form Name <span class="text-danger">*</span></label>
                            <input class="form-control" id="bir_form_name" name="name" type="text" maxlength="255" required placeholder="e.g. BIR Form 1800">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="bir_form_description">Description</label>
                            <textarea class="form-control" id="bir_form_description" name="description" rows="3" maxlength="1000" placeholder="Brief description of this form"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bir_form_active" name="active" value="1" checked>
                            <label class="form-check-label" for="bir_form_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add BIR Form</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.reveal-text-btn') : null;
            if (!btn) return;
            var text = btn.parentElement ? btn.parentElement.querySelector('.reveal-text') : null;
            if (!text) return;
            var showing = btn.getAttribute('data-showing') === '1';
            if (showing) {
                text.textContent = text.getAttribute('data-masked') || text.textContent;
                btn.textContent = 'Reveal';
                btn.setAttribute('data-showing', '0');
            } else {
                text.textContent = text.getAttribute('data-full') || text.textContent;
                btn.textContent = 'Hide';
                btn.setAttribute('data-showing', '1');
            }
        });

        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.edit-info-btn');
            if (editBtn) {
                var id = editBtn.getAttribute('data-entry-id');
                var key = editBtn.getAttribute('data-key');
                var value = editBtn.getAttribute('data-value');
                var modal = document.getElementById('edit-info-modal');
                var form = document.getElementById('edit-info-form');
                var base = '<?php echo e(route('admin.clients.updateInfoEntry', [$client, "__ID__"])); ?>'.replace('__ID__', id);
                form.setAttribute('action', base);
                document.getElementById('edit-info-key').value = key;
                document.getElementById('edit-info-value').value = value;
                modal.style.display = 'block';
                return;
            }
            if (e.target.closest('.cancel-edit-info')) {
                document.getElementById('edit-info-modal').style.display = 'none';
                return;
            }
        });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/clients/show.blade.php ENDPATH**/ ?>