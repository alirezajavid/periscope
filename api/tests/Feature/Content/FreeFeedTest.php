<?php

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

it('serves a valid RSS feed with free episodes only', function () {
    Post::factory()->free()->withAudio()->create(['title' => 'Free & open', 'audio_size' => 2048]);
    Post::factory()->withAudio()->create(['title' => 'Premium only']);
    Post::factory()->free()->draft()->create(['title' => 'Free draft']);

    $response = $this->get('/api/feed/free.xml')->assertOk();
    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and((string) $xml->channel->item[0]->title)->toBe('Free & open')
        ->and(count($xml->channel->item))->toBe(1)
        ->and((string) $xml->channel->item[0]->enclosure['length'])->toBe('2048');
    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml');
});

it('streams the audio of a free episode and refuses premium or hidden ones', function () {
    Storage::fake('local');
    Storage::disk('local')->put('audio/original/free.mp3', str_repeat('a', 100));
    Storage::disk('local')->put('audio/original/premium.mp3', 'secret');

    $free = Post::factory()->free()->withAudio('audio/original/free.mp3')->create();
    $premium = Post::factory()->withAudio('audio/original/premium.mp3')->create();
    $draft = Post::factory()->free()->draft()->withAudio('audio/original/free.mp3')->create();

    $this->get("/api/posts/{$free->id}/free-audio")->assertOk();
    $this->getJson("/api/posts/{$premium->id}/free-audio")->assertNotFound();
    $this->getJson("/api/posts/{$draft->id}/free-audio")->assertNotFound();
});

it('supports Range requests on free audio', function () {
    Storage::fake('local');
    Storage::disk('local')->put('audio/original/free.mp3', str_repeat('a', 100));
    $free = Post::factory()->free()->withAudio('audio/original/free.mp3')->create();

    $this->get("/api/posts/{$free->id}/free-audio", ['Range' => 'bytes=0-9'])->assertStatus(206);
});
