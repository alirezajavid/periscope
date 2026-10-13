<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostAccess;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    /** RSS of free episodes only, for podcast directories (the app is the source of truth). */
    public function __invoke(): Response
    {
        $posts = Post::visible()->where('access', PostAccess::Free)->orderByDesc('published_at')->limit(200)->get();

        $items = $posts->map(fn (Post $post) => $this->item($post))->implode('');
        $title = e(__('posts.feed_title'));
        $description = e(__('posts.feed_description'));
        $link = e(config('periscope.app_url'));

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"><channel>
        <title>{$title}</title><link>{$link}</link><description>{$description}</description><language>fa</language>
        {$items}
        </channel></rss>
        XML;

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
    }

    private function item(Post $post): string
    {
        $link = e(config('periscope.app_url').'/#/posts/'.$post->id);
        $enclosure = $post->hasAudio()
            ? '<enclosure url="'.e(url("/api/posts/{$post->id}/free-audio")).'" length="'.(int) $post->audio_size.'" type="audio/mpeg"/>'
            : '';

        return '<item><title>'.e($post->title).'</title><link>'.$link.'</link><guid isPermaLink="false">post-'.$post->id.'</guid>'
            .'<pubDate>'.$post->published_at->toRfc2822String().'</pubDate>'
            .'<description>'.e((string) $post->excerpt).'</description>'.$enclosure.'</item>';
    }
}
