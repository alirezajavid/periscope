<?php

use App\Models\AdminLog;
use App\Models\Setting;

it('serves public settings with nulls for anything never set', function () {
    $this->getJson('/api/settings')->assertOk()
        ->assertJsonPath('data.page_terms', null)->assertJsonPath('data.maintenance', null)
        ->assertJsonMissingPath('data.support_items');
});

it('lets an admin save links, pages and the maintenance switch', function () {
    asAdmin()->putJson('/api/admin/settings', [
        'social_links' => [['title' => 'Instagram', 'url' => 'https://instagram.com/theperiscopepodcast']],
        'page_terms' => '<p>Texts are <strong>watermarked</strong> per user.</p><script>alert(1)</script>',
        'maintenance' => true,
        'maintenance_message' => 'Back soon',
    ])->assertOk();

    $this->getJson('/api/settings')->assertOk()
        ->assertJsonPath('data.social_links.0.title', 'Instagram')
        ->assertJsonPath('data.maintenance', true)
        ->assertJsonPath('data.maintenance_message', 'Back soon');
    expect(Setting::read('page_terms'))->toContain('<strong>watermarked</strong>')->not->toContain('script')
        ->and(AdminLog::where('action', 'settings.update')->exists())->toBeTrue();
});

it('updates only the keys that were sent', function () {
    Setting::write('page_about', '<p>About</p>');

    asAdmin()->putJson('/api/admin/settings', ['maintenance' => false])->assertOk();

    expect(Setting::read('page_about'))->toBe('<p>About</p>');
});

it('ignores unknown keys and rejects bad values', function () {
    asAdmin()->putJson('/api/admin/settings', ['evil_key' => 'x'])->assertOk();
    expect(Setting::find('evil_key'))->toBeNull();

    asAdmin()->putJson('/api/admin/settings', ['social_links' => [['title' => 'x', 'url' => 'javascript:alert(1)']]])
        ->assertUnprocessable();
});

it('gives support items and a QR for crypto addresses only', function () {
    asAdmin()->putJson('/api/admin/settings', ['support_items' => [
        ['title' => 'Hamibash', 'kind' => 'link', 'value' => 'https://hamibash.com/periscope'],
        ['title' => 'USDT', 'kind' => 'crypto', 'value' => 'TLyE5AnYLbPEDnThi1xR339DJL6fAToJiw', 'network' => 'TRC20'],
    ]])->assertOk();

    $items = $this->getJson('/api/support')->assertOk()->json('data');

    expect($items[0]['qr_svg'])->toBeNull()
        ->and($items[1]['qr_svg'])->toStartWith('<?xml')->toContain('<svg')
        ->and($items[1]['network'])->toBe('TRC20');
});

it('is admin-only to change', function () {
    asUser()->putJson('/api/admin/settings', ['maintenance' => true])->assertForbidden();
    asUser()->getJson('/api/admin/settings')->assertForbidden();
});
