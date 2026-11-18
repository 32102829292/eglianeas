<?php $__env->startSection('title', ($client->business_name ?: $client->name).' — Document Distribution — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <a href="<?php echo e(route('admin.distribution.index')); ?>" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Document Distribution
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1><?php echo e($client->business_name ?: $client->name); ?></h1>
            <p><?php echo e($client->name); ?> &middot; <a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a> <?php if($client->client_code): ?> &middot; <span class="code-pill"><?php echo e($client->client_code); ?></span> <?php endif; ?></p>
        </div>
        <a href="<?php echo e(route('admin.clients.show', $client)); ?>" class="btn btn-outline">Client record</a>
    </div>

    <?php if(session('status')): ?>
        <div class="alert alert-success"><?php echo e(session('status')); ?></div>
    <?php endif; ?>

    
    <?php if (isset($component)) { $__componentOriginal65f196d74402b020f3b225ff78358233 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65f196d74402b020f3b225ff78358233 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.address-map','data' => ['latitude' => null,'longitude' => null,'id' => 'distMapLoader','hidden' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('address-map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['latitude' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null),'longitude' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null),'id' => 'distMapLoader','hidden' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65f196d74402b020f3b225ff78358233)): ?>
<?php $attributes = $__attributesOriginal65f196d74402b020f3b225ff78358233; ?>
<?php unset($__attributesOriginal65f196d74402b020f3b225ff78358233); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65f196d74402b020f3b225ff78358233)): ?>
<?php $component = $__componentOriginal65f196d74402b020f3b225ff78358233; ?>
<?php unset($__componentOriginal65f196d74402b020f3b225ff78358233); ?>
<?php endif; ?>
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Business address &amp; location</h2>
        </div>
        <?php if($profile?->latitude !== null && $profile?->longitude !== null && (float) $profile->latitude != 0 && (float) $profile->longitude != 0): ?>
            <div class="profile-grid">
                <div class="profile-row col-span-2"><span class="profile-k">Address</span><span class="profile-v"><?php echo e($profile->business_address ?: '—'); ?></span></div>
                <div class="profile-row col-span-2" id="coordToggleRow">
                    <span class="profile-k">Coordinates</span>
                    <span class="profile-v">
                        <button type="button" class="btn btn-outline btn-sm" id="coordToggleBtn" onclick="var el=document.getElementById('coordValues'); el.hidden=!el.hidden; this.textContent=el.hidden?'Show':'Hide';">Show</button>
                        <span id="coordValues" hidden><?php echo e($profile->latitude); ?>, <?php echo e($profile->longitude); ?></span>
                    </span>
                </div>
                <div class="profile-row col-span-2"><span class="profile-k">Map</span><?php if (isset($component)) { $__componentOriginal65f196d74402b020f3b225ff78358233 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65f196d74402b020f3b225ff78358233 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.address-map','data' => ['latitude' => $profile->latitude,'longitude' => $profile->longitude,'id' => 'clientMap']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('address-map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['latitude' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($profile->latitude),'longitude' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($profile->longitude),'id' => 'clientMap']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65f196d74402b020f3b225ff78358233)): ?>
<?php $attributes = $__attributesOriginal65f196d74402b020f3b225ff78358233; ?>
<?php unset($__attributesOriginal65f196d74402b020f3b225ff78358233); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65f196d74402b020f3b225ff78358233)): ?>
<?php $component = $__componentOriginal65f196d74402b020f3b225ff78358233; ?>
<?php unset($__componentOriginal65f196d74402b020f3b225ff78358233); ?>
<?php endif; ?></div>
                <div class="profile-row col-span-2" id="trackRow">
                    <span class="profile-k">Distance tracker</span>
                    <span class="profile-v">
                        <button type="button" class="btn btn-primary btn-sm" id="trackStartBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/></svg>
                            Track distance to this client
                        </button>
                        <div id="trackPanel" hidden>
                            <div class="track-readout">
                                <span class="track-arrow" id="trackArrow" title="Direction to client">&#9650;</span>
                                <span class="track-distance" id="trackDistance">—</span>
                                <span class="track-direction" id="trackDirection"></span>
                            </div>
                            <div class="track-actions">
                                <a id="trackGmaps" href="#" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Get directions</a>
                                <button type="button" class="btn btn-outline btn-sm" id="trackStopBtn">Stop tracking</button>
                            </div>
                        </div>
                        <div class="track-error" id="trackError" hidden></div>
                    </span>
                </div>
            </div>
        <?php else: ?>
            <p class="card-sub">No location pinned yet.</p>
            <div class="form-hint mb-2">Ask the client to pin their business address in their Profile, or set it manually below.</div>
            <form method="POST" action="<?php echo e(route('admin.distribution.update-location', $client)); ?>" id="locationForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="business_address" value="<?php echo e($profile->business_address ?? ''); ?>">
                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="bizAddress">Business address</label>
                        <input class="form-control" id="bizAddress" type="text" value="<?php echo e($profile->business_address ?? ''); ?>" placeholder="e.g. 123 Rizal Avenue, Quezon City">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Locate on map</label>
                        <button type="button" class="btn btn-outline btn-sm" id="distLocateBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            Locate address
                        </button>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline btn-sm" id="distTrackBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/></svg>
                                Use my current location
                            </button>
                            <div class="form-hint">Use this only if you're currently at the client's business location - it sets the pin to wherever you are right now.</div>
                        </div>
                        <div class="form-hint geo-status" id="distGeoStatus" hidden></div>
                    </div>
                    <input type="hidden" id="distLat" name="latitude">
                    <input type="hidden" id="distLng" name="longitude">
                    <div class="form-group col-span-2">
                        <button type="button" class="btn btn-outline btn-sm" onclick="var el=document.getElementById('distCoordValues'); el.hidden=!el.hidden; this.textContent=el.hidden?'Show coordinates':'Hide coordinates';">Show coordinates</button>
                        <div id="distCoordValues" class="mt-2" hidden>
                            <div class="form-grid two">
                                <div class="form-group">
                                    <label class="form-label" for="distLatDisplay">Latitude</label>
                                    <input class="form-control" id="distLatDisplay" type="text" placeholder="14.5995" inputmode="decimal">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="distLngDisplay">Longitude</label>
                                    <input class="form-control" id="distLngDisplay" type="text" placeholder="120.9842" inputmode="decimal">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-span-2">
                        <div class="map-preview" id="distMap" hidden></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save location</button>
            </form>
        <?php endif; ?>
    </div>

    
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">BIR Form Checklist</h2>
            <?php ($filedCount = collect($birStatuses)->filter(fn ($s) => $s === 'filed')->count()); ?>
            <span class="badge badge-<?php echo e($filedCount > 0 ? 'current' : 'neutral'); ?>"><?php echo e($filedCount); ?>/<?php echo e(count($formTypes)); ?> filed</span>
        </div>
        <p class="card-sub">Track which BIR forms have been filed for this client. Click a status to update.</p>
        <div class="bir-checklist">
            <?php $__currentLoopData = $formTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($current = $birStatuses[$ft] ?? 'not_filed'); ?>
                <div class="bir-checklist-item">
                    <span class="bir-checklist-type"><?php echo e($ft); ?></span>
                    <div class="bir-checklist-actions">
                        <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $statusVal => $statusLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <form method="POST" action="<?php echo e(route('admin.distribution.bir-status', $client)); ?>" class="inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="form_type" value="<?php echo e($ft); ?>">
                                <input type="hidden" name="status" value="<?php echo e($statusVal); ?>">
                                <button type="submit" class="btn btn-sm <?php echo e($current === $statusVal ? 'btn-'.($statusVal === 'filed' ? 'primary' : ($statusVal === 'not_applicable' ? 'outline' : 'outline')) : 'btn-outline'); ?> bir-status-btn bir-<?php echo e($statusVal); ?>"><?php echo e($statusLabel); ?></button>
                            </form>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Delivery Log</h2>
            <span class="badge badge-neutral"><?php echo e($deliveries->count()); ?> entries</span>
        </div>
        <p class="card-sub">Record how documents were delivered to the client (face-to-face or online).</p>

        <form method="POST" action="<?php echo e(route('admin.distribution.store-delivery', $client)); ?>" class="delivery-form">
            <?php echo csrf_field(); ?>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="delFormType">Form type</label>
                    <select class="form-control" id="delFormType" name="form_type" required>
                        <?php $__currentLoopData = $formTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($ft); ?>"><?php echo e($ft); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="delMethod">Delivery method</label>
                    <select class="form-control" id="delMethod" name="delivery_method" required>
                        <?php $__currentLoopData = $methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="delDate">Date received <span class="opt-tag">(optional)</span></label>
                    <input class="form-control" id="delDate" name="date_received" type="date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="delTime">Time received <span class="opt-tag">(optional)</span></label>
                    <input class="form-control" id="delTime" name="time_received" type="time">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label" for="delRemarks">Remarks <span class="opt-tag">(optional)</span></label>
                    <input class="form-control" id="delRemarks" name="remarks" type="text" maxlength="500" placeholder="e.g. Handed to the client at their office">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="no_file_flag" value="1"> No file (just in case)
                    </label>
                    <div class="form-hint">Check this if there&rsquo;s no digital record of the document.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Log delivery</button>
        </form>

        <?php if($deliveries->isNotEmpty()): ?>
            <div class="table-wrap table-card-view mt-2">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Form Type</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Remarks</th>
                            <th>No File</th>
                            <th class="actions-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $deliveries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $del): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td data-col="Form Type"><span class="code-pill"><?php echo e($del->form_type); ?></span></td>
                                <td data-col="Method"><?php echo e($del->methodLabel()); ?></td>
                                <td data-col="Date"><?php echo e($del->date_received?->format('M j, Y') ?? '—'); ?></td>
                                <td data-col="Time"><?php echo e($del->timeLabel() ?? '—'); ?></td>
                                <td data-col="Remarks"><?php echo e($del->remarks ?: '—'); ?></td>
                                <td data-col="No File"><?php echo e($del->no_file_flag ? 'Yes' : '—'); ?></td>
                                <td data-col="Actions" class="actions-cell">
                                    <form method="POST" action="<?php echo e(route('admin.distribution.destroy-delivery', [$client, $del])); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Remove this delivery entry?', message: 'This delivery record will be permanently removed.', danger: true, confirmLabel: 'Remove' });">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-outline danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>

                <div class="card-view-list">
                    <?php $__empty_1 = true; $__currentLoopData = $deliveries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $del): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="cv-card">
                            <div class="cv-card-head">
                                <div class="cv-head-main">
                                    <div class="cv-head-title"><span class="code-pill"><?php echo e($del->form_type); ?></span></div>
                                    <div class="cv-head-sub"><?php echo e($del->methodLabel()); ?><?php echo e($del->no_file_flag ? ' · No File' : ''); ?></div>
                                </div>
                                <span class="badge badge-neutral"><?php echo e($del->date_received?->format('M j, Y') ?? '—'); ?></span>
                            </div>
                            <div class="cv-card-body">
                                <div class="cv-pair"><span class="cv-label">Date</span><span class="cv-value"><?php echo e($del->date_received?->format('M j, Y') ?? '—'); ?></span></div>
                                <div class="cv-pair"><span class="cv-label">Time</span><span class="cv-value"><?php echo e($del->timeLabel() ?? '—'); ?></span></div>
                                <div class="cv-pair cv-full"><span class="cv-label">Remarks</span><span class="cv-value"><?php echo e($del->remarks ?: '—'); ?></span></div>
                            </div>
                            <div class="cv-card-actions">
                                <form method="POST" action="<?php echo e(route('admin.distribution.destroy-delivery', [$client, $del])); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Remove this delivery entry?', message: 'This delivery record will be permanently removed.', danger: true, confirmLabel: 'Remove' });">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-outline danger btn-sm">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <p class="cv-card cv-empty">No deliveries logged yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="form-hint mt-2">No deliveries logged yet.</div>
        <?php endif; ?>
    </div>

    
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Softcopy files</h2>
        </div>
        <p class="card-sub">Upload digital copies of documents per form type. The client can view and download these from their Document Distribution page.</p>
        <form method="POST" action="<?php echo e(route('admin.distribution.store-softcopy', $client)); ?>" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="scFormType">Form type</label>
                    <select class="form-control" id="scFormType" name="form_type" required>
                        <?php $__currentLoopData = $formTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($ft); ?>"><?php echo e($ft); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scFile">File</label>
                    <input class="form-control" id="scFile" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-hint">PDF, JPG, or PNG. Max 20 MB.</div>
                    <?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="form-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Upload softcopy</button>
        </form>

        <?php if($softcopies->isNotEmpty()): ?>
            <?php $__currentLoopData = $softcopies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $formType => $files): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="softcopy-group">
                    <h3 class="softcopy-group-title"><span class="code-pill"><?php echo e($formType); ?></span></h3>
                    <?php $__currentLoopData = $files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="softcopy-row">
                            <div>
                                <div class="cell-name"><?php echo e($doc->original_name); ?></div>
                                <small class="muted"><?php echo e($doc->sizeLabel()); ?> &middot; <?php echo e($doc->created_at?->format('M j, Y')); ?></small>
                            </div>
                            <div class="btn-row">
                                <a href="<?php echo e(route('admin.distribution.view', $doc)); ?>" class="btn btn-outline btn-sm">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    View
</a>
                                <a href="<?php echo e(route('admin.distribution.download', $doc)); ?>" class="btn btn-outline btn-sm">Download</a>
                                <form method="POST" action="<?php echo e(route('admin.distribution.destroy-softcopy', [$client, $doc])); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Delete this softcopy?', message: 'This uploaded document will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
            <div class="form-hint mt-2">No softcopies uploaded yet.</div>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        (function () {
            'use strict';

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfToken ? csrfToken.getAttribute('content') : '';

            var distMapEl = document.getElementById('distMap');
            var distLat = document.getElementById('distLat');
            var distLng = document.getElementById('distLng');
            var distLatDisplay = document.getElementById('distLatDisplay');
            var distLngDisplay = document.getElementById('distLngDisplay');
            var distGeoStatus = document.getElementById('distGeoStatus');
            var distLocateBtn = document.getElementById('distLocateBtn');
            var distTrackBtn = document.getElementById('distTrackBtn');
            var distAddress = document.getElementById('bizAddress');
            var distInstance = null;

            function distSyncDisplay() {
                if (distLatDisplay && distLat) distLatDisplay.value = distLat.value || '';
                if (distLngDisplay && distLng) distLngDisplay.value = distLng.value || '';
            }

            function distSyncHidden() {
                if (distLat && distLatDisplay && distLatDisplay.value) distLat.value = distLatDisplay.value;
                if (distLng && distLngDisplay && distLngDisplay.value) distLng.value = distLngDisplay.value;
            }

            if (distLatDisplay) {
                distLatDisplay.addEventListener('input', distSyncHidden);
                distLatDisplay.addEventListener('change', distSyncHidden);
            }
            if (distLngDisplay) {
                distLngDisplay.addEventListener('input', distSyncHidden);
                distLngDisplay.addEventListener('change', distSyncHidden);
            }

            function distShowGeo(msg, isError) {
                if (!distGeoStatus) return;
                distGeoStatus.className = isError ? 'form-hint geo-status geo-error' : 'form-hint geo-status';
                distGeoStatus.textContent = msg;
                distGeoStatus.hidden = false;
            }

            function distMapPin(lat, lng) {
                if (distLat) distLat.value = Number(lat).toFixed(6);
                if (distLng) distLng.value = Number(lng).toFixed(6);
                distSyncDisplay();
            }

            function distSetPin(lat, lng) {
                if (distLat) distLat.value = Number(lat).toFixed(6);
                if (distLng) distLng.value = Number(lng).toFixed(6);
                distSyncDisplay();
                if (distMapEl) distMapEl.hidden = false;

                if (typeof window.AddressMap === 'object' && typeof window.AddressMap.initInteractive === 'function') {
                    if (!distInstance) {
                        distInstance = window.AddressMap.initInteractive(distMapEl, lat, lng, distMapPin);
                    } else {
                        distInstance.setMarker(lat, lng);
                    }
                }
            }

            if (distLocateBtn) {
                distLocateBtn.addEventListener('click', function () {
                    var bizAddrHidden = document.querySelector('input[name="business_address"]');
                    if (bizAddrHidden && distAddress) bizAddrHidden.value = distAddress.value;
                    var q = (distAddress ? distAddress.value : '').trim();
                    if (!q) { distShowGeo('Enter an address first.', true); return; }
                    distShowGeo('Locating…', false);
                    fetch('<?php echo e(route("admin.distribution.geocode")); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ q: q }),
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (res) {
                        var d = res.data || {};
                        if (typeof d.lat === 'number' && typeof d.lng === 'number') {
                            distSetPin(d.lat, d.lng);
                            distShowGeo(d.display_name ? ('Located: ' + d.display_name.slice(0, 90)) : 'Located.', false);
                        } else {
                            distShowGeo(d.error || 'Address not found.', true);
                        }
                    })
                    .catch(function () { distShowGeo('Request failed.', true); });
                });
            }

            if (distTrackBtn) {
                distTrackBtn.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        distShowGeo('Your device does not support location. Try "Locate address" or enter coordinates manually.', true);
                        return;
                    }
                    distShowGeo('Getting your current GPS position...', false);
                    navigator.geolocation.getCurrentPosition(function (pos) {
                        distSetPin(pos.coords.latitude, pos.coords.longitude);
                        distShowGeo('Pin set to your current location. Review it and click "Save location" to keep it.', false);
                    }, function (err) {
                        if (err.code === err.PERMISSION_DENIED) {
                            distShowGeo('Location access is needed, please allow location permission, or use "Locate address" / enter coordinates manually.', true);
                        } else if (err.code === err.POSITION_UNAVAILABLE) {
                            distShowGeo('Your location could not be determined. Make sure GPS is enabled, or use "Locate address" / enter coordinates manually.', true);
                        } else {
                            distShowGeo('Could not get your location. Try "Locate address" or enter coordinates manually.', true);
                        }
                    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 });
                });
            }

            var locForm = document.getElementById('locationForm');
            if (locForm) {
                locForm.addEventListener('submit', function () {
                    var bizAddrHidden = document.querySelector('input[name="business_address"]');
                    if (bizAddrHidden && distAddress) bizAddrHidden.value = distAddress.value;
                });
            }

            /* ===== Distance tracker ===== */
            var clientLat = <?php echo json_encode((float) $profile->latitude, 15, 512) ?>;
            var clientLng = <?php echo json_encode((float) $profile->longitude, 15, 512) ?>;

            var trackStartBtn = document.getElementById('trackStartBtn');
            var trackStopBtn = document.getElementById('trackStopBtn');
            var trackPanel = document.getElementById('trackPanel');
            var trackError = document.getElementById('trackError');
            var trackDistanceEl = document.getElementById('trackDistance');
            var trackDirectionEl = document.getElementById('trackDirection');
            var trackArrow = document.getElementById('trackArrow');
            var trackGmaps = document.getElementById('trackGmaps');

            var watchId = null;
            var adminMarker = null;
            var trackMap = null;
            var legendControl = null;

            function trackShowError(msg) {
                if (!trackError) return;
                trackError.textContent = msg;
                trackError.hidden = false;
            }
            function trackHideError() { if (trackError) trackError.hidden = true; }

            function haversineKm(lat1, lon1, lat2, lon2) {
                var R = 6371;
                var toRad = function (d) { return d * Math.PI / 180; };
                var dLat = toRad(lat2 - lat1);
                var dLon = toRad(lon2 - lon1);
                var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                        Math.sin(dLon / 2) * Math.sin(dLon / 2);
                return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }

            function formatDistance(km) {
                if (km < 1) return Math.round(km * 1000) + ' m away';
                return km.toFixed(1) + ' km away';
            }

            function bearingDeg(lat1, lon1, lat2, lon2) {
                var toRad = function (d) { return d * Math.PI / 180; };
                var toDeg = function (r) { return r * 180 / Math.PI; };
                var dLon = toRad(lon2 - lon1);
                var y = Math.sin(dLon) * Math.cos(toRad(lat2));
                var x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2)) -
                        Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(dLon);
                return (toDeg(Math.atan2(y, x)) + 360) % 360;
            }

            function bearingCompass(deg) {
                var dirs = ['North', 'Northeast', 'East', 'Southeast', 'South', 'Southwest', 'West', 'Northwest'];
                return dirs[Math.round(deg / 45) % 8];
            }

            function updateTrackReadout(adminLat, adminLng) {
                var dist = haversineKm(adminLat, adminLng, clientLat, clientLng);
                var bear = bearingDeg(adminLat, adminLng, clientLat, clientLng);

                if (trackDistanceEl) trackDistanceEl.textContent = formatDistance(dist);
                if (trackDirectionEl) trackDirectionEl.textContent = 'to the ' + bearingCompass(bear);
                if (trackArrow) {
                    trackArrow.style.transform = 'rotate(' + bear + 'deg)';
                    trackArrow.setAttribute('data-bearing', bear);
                }

                var gmapsUrl = 'https://www.google.com/maps/dir/' +
                    encodeURIComponent(adminLat + ',' + adminLng) + '/' +
                    encodeURIComponent(clientLat + ',' + clientLng);
                if (trackGmaps) trackGmaps.href = gmapsUrl;

                if (trackMap && adminMarker) {
                    adminMarker.setLatLng([adminLat, adminLng]);
                }
            }

            function startTracking() {
                trackHideError();
                if (!navigator.geolocation) {
                    trackShowError('Geolocation is not supported by your browser.');
                    return;
                }

                trackMap = (typeof window.AddressMap === 'object') ? window.AddressMap.getMap('clientMap') : null;
                if (!trackMap) {
                    trackShowError('Map not loaded — please refresh the page.');
                    return;
                }

                if (trackStartBtn) trackStartBtn.hidden = true;
                if (trackPanel) trackPanel.hidden = false;

                var adminIcon = L.divIcon({
                    className: 'track-admin-marker',
                    html: '<span class="track-admin-dot"></span>',
                    iconSize: [18, 18],
                    iconAnchor: [9, 9]
                });

                legendControl = L.control({ position: 'bottomleft' });
                legendControl.onAdd = function () {
                    var div = L.DomUtil.create('div', 'track-legend');
                    div.innerHTML =
                        '<span class="track-legend-item">' +
                            '<span class="track-legend-icon track-legend-pin"></span> ' +
                            '<?php echo e($client->business_name ?: $client->name); ?>' +
                        '</span>' +
                        '<span class="track-legend-item">' +
                            '<span class="track-legend-icon track-legend-dot"></span> Your location' +
                        '</span>';
                    return div;
                };

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var aLat = pos.coords.latitude;
                        var aLng = pos.coords.longitude;
                        adminMarker = L.marker([aLat, aLng], { icon: adminIcon, interactive: false }).addTo(trackMap);
                        legendControl.addTo(trackMap);
                        updateTrackReadout(aLat, aLng);

                        var midLat = (aLat + clientLat) / 2;
                        var midLng = (aLng + clientLng) / 2;
                        var dist = haversineKm(aLat, aLng, clientLat, clientLng);
                        var fitZoom = dist < 1 ? 15 : dist < 5 ? 13 : dist < 20 ? 12 : 11;
                        trackMap.setView([midLat, midLng], fitZoom);

                        watchId = navigator.geolocation.watchPosition(
                            function (p) { updateTrackReadout(p.coords.latitude, p.coords.longitude); },
                            function (err) {
                                if (err.code === err.PERMISSION_DENIED) {
                                    trackShowError('Location access is needed to track distance — please allow location permission.');
                                }
                            },
                            { enableHighAccuracy: true, maximumAge: 5000 }
                        );
                    },
                    function (err) {
                        if (err.code === err.PERMISSION_DENIED) {
                            trackShowError('Location access is needed to track distance — please allow location permission.');
                        } else if (err.code === err.POSITION_UNAVAILABLE) {
                            trackShowError('Your location could not be determined. Make sure GPS is enabled.');
                        } else {
                            trackShowError('Could not get your location. Please try again.');
                        }
                        if (trackStartBtn) trackStartBtn.hidden = false;
                        if (trackPanel) trackPanel.hidden = true;
                    },
                    { enableHighAccuracy: true, timeout: 15000 }
                );
            }

            function stopTracking() {
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                if (adminMarker && trackMap) {
                    trackMap.removeLayer(adminMarker);
                    adminMarker = null;
                }
                if (legendControl && trackMap) {
                    trackMap.removeControl(legendControl);
                }
                if (trackStartBtn) trackStartBtn.hidden = false;
                if (trackPanel) trackPanel.hidden = true;
                trackHideError();
            }

            if (trackStartBtn) trackStartBtn.addEventListener('click', startTracking);
            if (trackStopBtn) trackStopBtn.addEventListener('click', stopTracking);

            /* ===== DeviceOrientation for compass-relative arrow (optional) ===== */
            function tryDeviceOrientation() {
                if (typeof DeviceOrientationEvent !== 'function') return;
                if (typeof DeviceOrientationEvent.requestPermission === 'function') {
                    DeviceOrientationEvent.requestPermission().then(function (state) {
                        if (state === 'granted') window.addEventListener('deviceorientation', applyOrientation);
                    }).catch(function () {});
                } else {
                    window.addEventListener('deviceorientation', applyOrientation);
                }
            }
            function applyOrientation(e) {
                if (e.alpha == null || !trackArrow || (trackPanel && trackPanel.hidden)) return;
                var bear = parseFloat(trackArrow.getAttribute('data-bearing') || '0');
                trackArrow.style.transform = 'rotate(' + (bear - e.alpha) + 'deg)';
            }
            tryDeviceOrientation();
        })();
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/distribution/show.blade.php ENDPATH**/ ?>