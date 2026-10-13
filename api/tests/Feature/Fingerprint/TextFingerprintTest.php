<?php

use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TextFingerprint\TextFingerprint;

const ZWNJ = "\u{200C}";

function persianBody(): string
{
    return '<p>می'.ZWNJ.'خواهم درباره‌ی ژئوپلیتیک آلمان صحبت کنم و کتاب'.ZWNJ.'ها را مرور کنم.</p>'
        .'<h2>سرفصل مهم</h2>'
        .'<p>پاراگراف دوم با <strong>بخش پررنگ</strong> و <a href="https://example.com/a b">پیوند</a> است.</p>'
        .'<ul><li>مورد اول لیست</li><li>مورد دوم لیست</li></ul>'
        .'<blockquote>نقل قول بلند و جالب</blockquote>';
}

function fingerprint(): TextFingerprint
{
    return app(TextFingerprint::class);
}

it('only adds invisible characters: stripping them gives back the exact original', function () {
    $user = User::factory()->create();

    $marked = fingerprint()->mark(persianBody(), $user);

    expect($marked)->not->toBe(persianBody())->and(withoutMarks($marked))->toBe(persianBody());
});

it('never inserts, removes or alters ZWNJ and never touches tags or attributes', function () {
    $marked = fingerprint()->mark(persianBody(), User::factory()->create());

    expect(substr_count($marked, ZWNJ))->toBe(substr_count(persianBody(), ZWNJ));
    preg_match_all('/<[^>]*>/', $marked, $tagsAfter);
    preg_match_all('/<[^>]*>/', persianBody(), $tagsBefore);
    expect($tagsAfter[0])->toBe($tagsBefore[0]);
});

it('places every mark right after an ordinary space, so it is never next to a ZWNJ', function () {
    $marked = fingerprint()->mark(persianBody(), User::factory()->create());

    preg_match_all('/(.)[\x{200B}\x{2060}]{32}(.)/u', $marked, $matches);

    expect($matches[1])->not->toBeEmpty()->each->toBe(' ')
        ->and($matches[2])->each->not->toBe(ZWNJ);
});

it('marks every block (paragraph, heading, list item, quote) exactly once', function () {
    $marked = fingerprint()->mark(persianBody(), User::factory()->create());

    // p, h2, p, li, li, blockquote = 6 blocks with a space each
    expect(preg_match_all('/[\x{200B}\x{2060}]{32}/u', $marked))->toBe(6);
});

it('is deterministic per user and different between users', function () {
    [$a, $b] = [User::factory()->create(), User::factory()->create()];

    expect(fingerprint()->mark(persianBody(), $a))->toBe(fingerprint()->mark(persianBody(), $a))
        ->and(fingerprint()->mark(persianBody(), $a))->not->toBe(fingerprint()->mark(persianBody(), $b));
});

it('leaves text without any space alone and survives empty input', function () {
    $user = User::factory()->create();

    expect(fingerprint()->mark('<p>تکواژ</p>', $user))->toBe('<p>تکواژ</p>')
        ->and(fingerprint()->mark('', $user))->toBe('');
});

it('identifies the reader from the whole text, from a single paragraph, and from plain copied text', function () {
    $users = User::factory()->count(30)->create();
    $leaker = $users[17];
    $marked = fingerprint()->mark(persianBody(), $leaker);

    $whole = fingerprint()->identify(strip_tags($marked));
    $oneParagraph = fingerprint()->identify(strip_tags(explode('</p>', $marked)[2]));

    expect($whole->pluck('id')->all())->toContain($leaker->id)
        ->and($oneParagraph->pluck('id')->all())->toContain($leaker->id);
});

it('finds nobody in clean text or text with unrelated invisible characters', function () {
    User::factory()->count(3)->create();

    expect(fingerprint()->identify('متن بدون هیچ نشانه‌ای')->all())->toBe([])
        ->and(fingerprint()->identify("a\u{200B}b\u{2060}c")->all())->toBe([]);
});

it('serves subscribers their own marked body and everyone else nothing', function () {
    $post = Post::factory()->create(['body' => persianBody(), 'access' => 'premium']);
    $subscribers = User::factory()->count(2)->create();
    $subscribers->each(fn ($u) => Subscription::factory()->for($u)->create());

    $bodies = $subscribers->map(fn ($u) => asUser($u)->getJson("/api/posts/{$post->id}")->assertOk()->json('data.body'));

    expect($bodies[0])->not->toBe($bodies[1]);
    foreach ($bodies as $i => $body) {
        expect(withoutMarks($body))->toBe($post->body)
            ->and(fingerprint()->identify($body)->pluck('id')->all())->toContain($subscribers[$i]->id);
    }
});

it('does not mark free posts, which are public anyway', function () {
    $post = Post::factory()->free()->create(['body' => persianBody()]);
    $user = User::factory()->create();

    expect(asUser($user)->getJson("/api/posts/{$post->id}")->json('data.body'))->toBe($post->body);
});

it('does not touch the stored text', function () {
    $post = Post::factory()->create(['body' => persianBody()]);
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();

    asUser($user)->getJson("/api/posts/{$post->id}")->assertOk();

    expect($post->fresh()->body)->toBe($post->body)->and($post->fresh()->body)->not->toContain("\u{200B}");
});
