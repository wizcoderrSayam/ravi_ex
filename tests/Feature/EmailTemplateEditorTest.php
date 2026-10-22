<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\Ecommerce\Models\EmailLayout;
use Peer\Ecommerce\Models\EmailTemplate;
use Peer\Ecommerce\Models\Role;
use Peer\Ecommerce\Services\Mail\TemplateMailer;
use Tests\TestCase;

/**
 * The store's email templates open in the CMS's visual email editor, which saves
 * them back as email-ready HTML.
 */
class EmailTemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_edit_form_links_to_the_visual_editor(): void
    {
        $template = $this->orderPlaced();

        $this->actingAs($this->admin())
            ->get(route('admin.email-templates.edit', $template))
            ->assertOk()
            ->assertSee(route('admin.email-templates.design', $template));
    }

    public function test_a_template_opens_in_a_locked_content_block_inside_the_layouts_editable_header_and_footer(): void
    {
        $template = $this->orderPlaced();

        $this->actingAs($this->admin())
            ->get(route('admin.email-templates.design', $template))
            ->assertOk()
            ->assertViewIs('cms::editor')
            ->assertViewHas('config', function (array $config) use ($template): bool {
                return $config['preset'] === 'email'
                    && str_contains($config['html'], 'Authentic Indian flavours')
                    && str_contains($config['html'], '<div data-email-content data-gjs-name="Email content" data-gjs-removable="false"')
                    && str_contains($config['html'], '<tr data-placeholder="items_rows"><td>{{items_rows}}</td></tr>')
                    && ! str_contains($config['html'], '{{content}}')
                    && $config['shell']['html'] === '<div data-layout-content></div>'
                    && str_contains($config['shell']['bodyAttributes']->style, 'background-color:#fff7ec')
                    && in_array('{{customer_name}}', array_column($config['blocks'], 'label'), true)
                    && in_array('{{store_name}}', array_column($config['blocks'], 'label'), true)
                    && ! in_array('{{items_rows}}', array_column($config['blocks'], 'label'), true)
                    && str_starts_with($config['urls']['save'], route('admin.email-templates.design.save', $template).'?editor=');
            });
    }

    public function test_editing_the_header_and_footer_in_a_template_saves_them_to_the_layout(): void
    {
        $template = $this->orderPlaced();
        preg_match_all('/\{\{\w+\}\}/', $template->body, $before);
        $config = $this->actingAs($this->admin())->get(route('admin.email-templates.design', $template))->viewData('config');
        $edited = str_replace('Authentic Indian flavours, milled fresh since 1974.', 'Fresh spices, every week.', $config['html']);

        $this->postJson($config['urls']['save'], ['html' => base64_encode('<body>'.$edited.'</body>'), 'css' => ''])
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $body = $template->fresh()->body;
        preg_match_all('/\{\{\w+\}\}/', $body, $after);
        $this->assertSame($before[0], $after[0]);
        $this->assertMatchesRegularExpression('/<table\b[^>]*>\s*\{\{items_rows\}\}\s*<\/table>/', $body);
        $this->assertStringNotContainsString('Fresh spices', $body);
        $this->assertStringNotContainsString('data-email-content', $body);

        $layout = EmailLayout::query()->firstOrFail()->html;
        $this->assertStringContainsString('Fresh spices, every week.', $layout);
        $this->assertSame(1, substr_count($layout, '{{content}}'));
        $this->assertStringContainsString('© {{year}} {{store_name}}', $layout);
        $this->assertStringContainsString('<title>{{subject}}</title>', $layout);
    }

    public function test_a_template_editor_keeps_header_changes_made_in_another_editor_since_it_opened(): void
    {
        $template = $this->orderPlaced();
        $this->actingAs($this->admin());
        $first = $this->get(route('admin.email-templates.design', $template))->viewData('config');
        $second = $this->get(route('admin.email-layout.design'))->viewData('config');

        $this->postJson($second['urls']['save'], ['html' => base64_encode('<body><p>Their header</p>{{content}}</body>'), 'css' => ''])
            ->assertExactJson(['status' => 'ok']);
        $this->postJson($first['urls']['save'], ['html' => base64_encode('<body>'.str_replace('Order Confirmed!', 'Thanks!', $first['html']).'</body>'), 'css' => ''])
            ->assertOk()
            ->assertJsonPath('warning', 'Saved, except the header & footer: they were changed in another editor since you opened this one. Reload to edit them.');

        $this->assertStringContainsString('Thanks!', $template->fresh()->body);
        $this->assertStringContainsString('<p>Their header</p>{{content}}', EmailLayout::query()->firstOrFail()->html);
    }

    public function test_an_imported_email_without_the_content_block_becomes_the_template_body_with_its_css_inlined(): void
    {
        $layout = EmailLayout::query()->firstOrFail()->html;
        $template = $this->orderPlaced();
        $html = '<body><table id="card"><tbody><tr data-placeholder="items_rows" id="i9x"><td>{{items_rows}}</td></tr></tbody></table>'
            .'<p id="lead">Hi {{customer_name}}</p><a href="{{shop_url}}"><img src="/media/banner.png"></a></body>';

        $this->actingAs($this->admin())
            ->postJson(route('admin.email-templates.design.save', $template), [
                'html' => base64_encode($html),
                'css' => base64_encode('#lead{color:#b82125;} @media (max-width: 480px){#lead{color:blue;}}'),
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $body = $template->fresh()->body;
        $this->assertStringContainsString('<table id="card"><tbody>{{items_rows}}</tbody></table>', $body);
        $this->assertStringContainsString('<p id="lead" style="color: #b82125;">Hi {{customer_name}}</p>', $body);
        $this->assertStringContainsString('href="{{shop_url}}"', $body);
        $this->assertStringNotContainsString('<body', $body);
        $this->assertSame($layout, EmailLayout::query()->firstOrFail()->html);
    }

    public function test_the_sidebar_links_to_the_email_layout(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.email-layout.design'));
    }

    public function test_the_index_links_to_the_layout_editor(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.email-templates.index'))
            ->assertOk()
            ->assertSee(route('admin.email-layout.design'));
    }

    public function test_the_layout_opens_in_the_visual_editor_with_its_header_footer_and_content_slot(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.email-layout.design'))
            ->assertOk()
            ->assertViewIs('cms::editor')
            ->assertViewHas('config', function (array $config): bool {
                return str_contains($config['html'], '{{content}}')
                    && str_contains($config['html'], 'Authentic Indian flavours')
                    && ! str_contains($config['html'], '<title>')
                    && str_contains($config['shell']['bodyAttributes']->style, 'background-color:#fff7ec')
                    && array_column($config['blocks'], 'label') === ['{{content}}', '{{subject}}', '{{store_name}}', '{{support_email}}', '{{app_url}}', '{{year}}']
                    && str_starts_with($config['urls']['save'], route('admin.email-layout.design.save').'?editor=');
            });
    }

    public function test_a_saved_layout_keeps_its_head_and_wraps_every_mail(): void
    {
        $html = '<body><p id="brand">{{store_name}}</p><div>{{content}}</div><p>New footer</p></body>';
        $saveUrl = $this->actingAs($this->admin())->get(route('admin.email-layout.design'))->viewData('config')['urls']['save'];

        $this->postJson($saveUrl, [
            'html' => base64_encode($html),
            'css' => base64_encode('#brand{color:#b82125;}'),
        ])
            ->assertOk();

        $layout = EmailLayout::query()->firstOrFail()->html;
        $this->assertStringStartsWith('<!DOCTYPE html><html lang="en"><head>', $layout);
        $this->assertStringContainsString('<title>{{subject}}</title>', $layout);
        $this->assertStringContainsString('<p id="brand" style="color: #b82125;">{{store_name}}</p>', $layout);

        $mail = app(TemplateMailer::class)->wrapInLayout('Order <1> confirmed', '<p>Thanks!</p>');
        $this->assertStringContainsString('<title>Order &lt;1&gt; confirmed</title>', $mail);
        $this->assertStringContainsString('<div><p>Thanks!</p></div>', $mail);
        $this->assertStringContainsString('New footer', $mail);
        $this->assertStringNotContainsString('{{', $mail);
    }

    public function test_a_layout_without_its_content_slot_is_rejected_and_kept(): void
    {
        $before = EmailLayout::query()->firstOrFail()->html;

        $this->actingAs($this->admin())
            ->postJson(route('admin.email-layout.design.save'), ['html' => base64_encode('<body><p>Header only</p></body>'), 'css' => ''])
            ->assertStatus(422);

        $this->assertSame($before, EmailLayout::query()->firstOrFail()->html);
    }

    public function test_mails_link_to_media_and_the_logo_with_absolute_urls(): void
    {
        $mail = app(TemplateMailer::class)->wrapInLayout('Hi', '<img src="/media/banner.png"><a href="{{shop_url}}">Shop</a>');

        $this->assertStringContainsString('src="'.url('/media/banner.png').'"', $mail);
        $this->assertStringContainsString('src="'.url('/assets/images/logo.png').'"', $mail);
        $this->assertStringContainsString('href="{{shop_url}}"', $mail);
    }

    public function test_a_shipped_template_keeps_every_placeholder_through_the_editor(): void
    {
        $template = $this->orderPlaced();
        preg_match_all('/\{\{\w+\}\}/', $template->body, $before);
        $editorHtml = $this->actingAs($this->admin())
            ->get(route('admin.email-templates.design', $template))
            ->viewData('config')['html'];

        $this->postJson(route('admin.email-templates.design.save', $template), ['html' => base64_encode($editorHtml), 'css' => ''])
            ->assertOk();

        $body = $template->fresh()->body;
        preg_match_all('/\{\{\w+\}\}/', $body, $after);
        $this->assertSame($before[0], $after[0]);
        $this->assertMatchesRegularExpression('/<table\b[^>]*>\s*\{\{items_rows\}\}\s*<\/table>/', $body);
    }

    public function test_content_that_is_not_base64_is_rejected_and_the_body_kept(): void
    {
        $template = $this->orderPlaced();

        $this->actingAs($this->admin())
            ->postJson(route('admin.email-templates.design.save', $template), ['html' => '<p>not base64</p>'])
            ->assertStatus(422);

        $this->assertSame($template->body, $template->fresh()->body);
    }

    private function orderPlaced(): EmailTemplate
    {
        return EmailTemplate::query()->where('slug', 'order_placed')->firstOrFail();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_active' => true]));

        return $admin;
    }
}
