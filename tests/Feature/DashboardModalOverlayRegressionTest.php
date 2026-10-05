<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\KaizenConcern;
use App\Models\PriorityItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the dashboard "frozen" bug.
 *
 * app.css is loaded after bootstrap.min.css and defines its own global
 * `.modal` overlay (position:fixed; inset:0; display:flex) for the custom
 * `.modal` + `.modal-card` dialogs. That rule used to beat Bootstrap's
 * `.modal { display: none }`, so every *closed* Bootstrap modal on the page
 * was painted as a full-viewport overlay that swallowed clicks on the sidebar
 * while the page still scrolled behind it.
 *
 * These tests pin the fix so it cannot silently regress.
 */
class DashboardModalOverlayRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Modal Regression Admin',
            'email' => 'modal.regression.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function priorityItem(User $creator): PriorityItem
    {
        return PriorityItem::create([
            'task_lesson' => 'Regression item',
            'type' => 'priority_task',
            'description' => 'Checklist overlay regression',
            'priority' => 'medium',
            'due_date' => now()->addWeek(),
            'status' => 'pending',
            'created_by' => $creator->id,
        ]);
    }

    private function kaizenConcern(User $creator): KaizenConcern
    {
        return KaizenConcern::create([
            'date_identified' => now(),
            'challenge' => 'Regression concern',
            'recommended_solution' => 'Checklist overlay regression',
            'target_date' => now()->addWeek(),
            'status' => 'pending',
            'created_by' => $creator->id,
        ]);
    }

    /**
     * A closed Bootstrap modal must not be given a rendering box by app.css.
     * If app.css ever wins the cascade again, `display:flex` + `position:fixed`
     * reappear and the invisible full-screen overlay returns.
     */
    public function test_closed_bootstrap_modal_is_kept_hidden_by_the_css_contract(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.modal\.fade\s*\{[^}]*display:\s*none\s*;?[^}]*\}/',
            $css,
            'app.css must force display:none on .modal.fade so closed Bootstrap modals stay hidden.'
        );

        $this->assertMatchesRegularExpression(
            '/\.modal\.fade\.show\s*\{[^}]*display:\s*block/',
            $css,
            'app.css must let Bootstrap modals display again once .show is present.'
        );
    }

    /**
     * The same override also used to force `z-index: 80` on every `.modal`,
     * which sits *below* Bootstrap's `.modal-backdrop` (1050). The backdrop then
     * painted on top of the open dialog and swallowed every click inside it, so
     * opening "Add Checklist Item" and pressing its submit button just closed
     * the modal again. Bootstrap modals must be restored above their backdrop.
     */
    public function test_bootstrap_modal_is_stacked_above_its_backdrop(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.modal\.fade\s*\{[^}]*z-index:\s*var\(--bs-modal-zindex,\s*1055\s*\)/',
            $css,
            '.modal.fade must be stacked above the 1050 backdrop using Bootstrap own z-index.'
        );
    }

    /**
     * The custom modal system must keep its full-screen overlay behaviour.
     * Guarding against an over-broad fix that would "solve" the bug by
     * disabling every modal on the site.
     */
    public function test_custom_modal_system_keeps_its_overlay_behaviour(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/(?<!fade )\.modal\s*\{[^}]*position:\s*fixed/',
            $css,
            'The custom .modal overlay rule must still exist for the app custom modals.'
        );
        $this->assertStringContainsString(
            '.hidden',
            $css,
            'Custom modals rely on .hidden to stay closed; that helper must remain.'
        );
    }

    /**
     * The override only works if app.css is actually served *after* Bootstrap.
     */
    public function test_app_css_is_served_after_bootstrap(): void
    {
        $head = file_get_contents(resource_path('views/layouts/head.blade.php'));

        $bootstrapPos = strpos($head, 'bootstrap');
        $appCssPos = strpos($head, '/css/app.css');

        $this->assertNotFalse($bootstrapPos, 'head layout must load Bootstrap CSS.');
        $this->assertNotFalse($appCssPos, 'head layout must load app.css.');
        $this->assertLessThan(
            $appCssPos,
            $bootstrapPos,
            'Bootstrap must load before app.css, otherwise app.css wins the cascade and .modal.fade breaks.'
        );
    }

    /**
     * The two reported pages must serve the current asset version, so browsers
     * and the service worker actually pick up the fix.
     */
    public function test_both_reported_pages_serve_the_current_css_version(): void
    {
        $admin = $this->admin();

        $priority = $this->actingAs($admin)->get(route('admin.priority-items.show', $this->priorityItem($admin)));
        $priority->assertOk();
        $this->assertStringContainsString(
            '/css/app.css?v=54',
            $priority->getContent(),
            'Priority Item View must request the CSS version containing the modal fix.'
        );

        $kaizen = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $this->kaizenConcern($admin)));
        $kaizen->assertOk();
        $this->assertStringContainsString(
            '/css/app.css?v=54',
            $kaizen->getContent(),
            'Kaizen Concern View must request the CSS version containing the modal fix.'
        );
    }

    /**
     * Both pages still ship the hidden Bootstrap checklist modal (it is a
     * legitimate feature) and it must be a Bootstrap-style `.fade` modal so
     * the new rule applies to it.
     */
    public function test_both_reported_pages_still_render_the_checklist_modal_as_bootstrap_fade(): void
    {
        $admin = $this->admin();

        $priority = $this->actingAs($admin)->get(route('admin.priority-items.show', $this->priorityItem($admin)));
        $priority->assertOk();
        $this->assertChecklistModalIsBootstrapFade(
            $priority->getContent(),
            'Priority Item View'
        );

        $kaizen = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $this->kaizenConcern($admin)));
        $kaizen->assertOk();
        $this->assertChecklistModalIsBootstrapFade(
            $kaizen->getContent(),
            'Kaizen Concern View'
        );
    }

    /**
     * Pull the opening <div> that carries id="addChecklistModal" and assert it is
     * a Bootstrap modal (has both `modal` and `fade` classes). Attribute order is
     * irrelevant, so the tag is matched loosely and then inspected.
     */
    private function assertChecklistModalIsBootstrapFade(string $html, string $page): void
    {
        $this->assertSame(
            1,
            preg_match('/<div\b[^>]*\bid="addChecklistModal"[^>]*>/i', $html, $m),
            $page.' must render exactly one #addChecklistModal element.'
        );

        $tag = $m[0];
        $this->assertSame(
            1,
            preg_match('/\bclass="([^"]*)"/i', $tag, $cm),
            $page.' #addChecklistModal must have a class attribute: '.$tag
        );

        $classes = preg_split('/\s+/', trim($cm[1]));
        $this->assertContains('modal', $classes, $page.' #addChecklistModal must keep the `modal` class: '.$tag);
        $this->assertContains(
            'fade',
            $classes,
            $page.' #addChecklistModal must keep the `fade` class, otherwise the app.css '
                .'.modal.fade override cannot hide it and the frozen overlay returns: '.$tag
        );
    }

    /**
     * The shared confirm modal is used on both pages for destructive actions
     * (e.g. delete). It must keep working after the fix.
     */
    public function test_confirm_modal_still_present_on_both_reported_pages(): void
    {
        $admin = $this->admin();

        $priority = $this->actingAs($admin)->get(route('admin.priority-items.show', $this->priorityItem($admin)));
        $this->assertStringContainsString('confirm-modal', $priority->getContent());

        $kaizen = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $this->kaizenConcern($admin)));
        $this->assertStringContainsString('confirm-modal', $kaizen->getContent());
    }

    /**
     * The dashboard sidebar must remain independently scrollable; the bug was
     * never about these properties, and a careless fix must not disturb them.
     */
    public function test_dashboard_sidebar_keeps_independent_scrolling(): void
    {
        $css = file_get_contents(public_path('css/dashboard.css'));

        $this->assertMatchesRegularExpression(
            '/\.dash-nav\s*\{[^}]*overflow-y:\s*auto/',
            $css,
            '.dash-nav must keep its own vertical scrolling.'
        );
        $this->assertMatchesRegularExpression(
            '/\.dash-nav\s*\{[^}]*max-height:\s*calc\(/',
            $css,
            '.dash-nav must keep a viewport-relative max-height so it can scroll internally.'
        );
    }
}
