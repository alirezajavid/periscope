<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Demo data for local development: one admin (TOTP already set up), subscribers in every state,
 * plans with PLACEHOLDER prices (the owner has not decided real prices yet), payment methods,
 * settings and about ten Persian posts (audio + text, free + premium).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $this->plans();
        $this->users();
        $this->settings();
        $this->posts();
    }

    private function users(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create([
            'name' => 'مدیر آزمایشی', 'email' => 'admin@periscope.test', 'password' => self::PASSWORD,
        ]);
        $admin->forceFill(['role' => UserRole::Admin])->save();

        $this->subscriber('subscriber@periscope.test', 'مشترک فعال', months: 3);
        $this->subscriber('expired@periscope.test', 'مشترک منقضی', expired: true);
        User::factory()->create(['name' => 'کاربر بدون اشتراک', 'email' => 'free@periscope.test', 'password' => self::PASSWORD]);
        User::factory()->blocked()->create(['name' => 'کاربر مسدود', 'email' => 'blocked@periscope.test', 'password' => self::PASSWORD]);
        User::factory()->create(['name' => 'کاربر موبایلی', 'email' => null, 'phone' => '+989121234567', 'password' => null]);

        foreach (range(1, 5) as $i) {
            $this->subscriber("listener{$i}@periscope.test", "شنونده {$i}", months: 6);
        }
    }

    private function subscriber(string $email, string $name, int $months = 3, bool $expired = false): void
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);
        $plan = Plan::query()->first() ?? Plan::factory()->create(['title' => 'پلن آزمایشی', 'months' => $months]);

        $factory = Subscription::factory()->for($user)->for($plan);
        ($expired ? $factory->expired() : $factory)->create();
    }

    private function plans(): void
    {
        // Placeholder prices (IRT = toman): real prices are an open decision (reference.md §10).
        foreach ([[3, 'سه‌ماهه', 450000, 15, 15], [6, 'شش‌ماهه', 800000, 27, 27], [12, 'یک‌ساله', 1400000, 48, 48]] as $order => [$months, $title, $irt, $usd, $usdt]) {
            $plan = Plan::create(['title' => $title, 'months' => $months, 'sort_order' => $order]);
            $plan->prices()->createMany([
                ['currency' => 'IRT', 'amount' => $irt], ['currency' => 'USD', 'amount' => $usd], ['currency' => 'USDT', 'amount' => $usdt],
            ]);
        }

        PaymentMethod::create(['type' => 'card', 'title' => 'کارت‌به‌کارت', 'currency' => 'IRT', 'sort_order' => 1,
            'instructions' => "مبلغ را به کارت زیر واریز کنید و کد پیگیری و تصویر رسید را ثبت کنید.\n6037-0000-0000-0000 — به نام (نمونه)"]);
        PaymentMethod::create(['type' => 'paypal', 'title' => 'پی‌پل', 'currency' => 'USD', 'sort_order' => 2,
            'instructions' => 'مبلغ را به paypal.me/periscopepodcast پرداخت کنید و رسید را ثبت کنید.']);
        PaymentMethod::create(['type' => 'crypto', 'title' => 'تتر (TRC20)', 'currency' => 'USDT', 'sort_order' => 3,
            'instructions' => 'فقط روی شبکه‌ی TRC20 به آدرس زیر واریز کنید: TLyE5AnYLbPEDnThi1xR339DJL6fAToJiw']);
    }

    private function settings(): void
    {
        Setting::write('social_links', [
            ['title' => 'اینستاگرام', 'url' => 'https://instagram.com/theperiscopepodcast'],
            ['title' => 'یوتیوب', 'url' => 'https://youtube.com/@PeriscopePodcast'],
            ['title' => 'تلگرام', 'url' => 'https://t.me/periscopepodcast'],
        ]);
        Setting::write('support_items', [
            ['title' => 'حامی‌باش', 'kind' => 'link', 'value' => 'https://hamibash.com/periscope'],
            ['title' => 'پی‌پل', 'kind' => 'link', 'value' => 'https://paypal.me/periscopepodcast'],
            ['title' => 'تتر', 'kind' => 'crypto', 'value' => 'TLyE5AnYLbPEDnThi1xR339DJL6fAToJiw', 'network' => 'TRC20', 'note' => 'فقط روی شبکه‌ی TRC20 واریز کنید.'],
        ]);
        Setting::write('page_about', '<p>پادکست پریسکوپ درباره‌ی روابط بین‌الملل و ژئوپلتیک است، به زبان ساده.</p>');
        Setting::write('page_terms', '<p>با ثبت‌نام می‌پذیرید که فایل‌های صوتی و متن‌ها برای هر کاربر نشانه‌گذاری می‌شوند و انتشار یا بازنشر آن‌ها ممنوع است.</p>');
        Setting::write('page_install_guide', '<p>در آیفون: Safari ← دکمه‌ی اشتراک‌گذاری ← «افزودن به صفحه‌ی اصلی». در اندروید: منوی مرورگر ← «نصب برنامه».</p>');
        Setting::write('maintenance', false);
    }

    private function posts(): void
    {
        $cover = $this->copyCover();
        $episodes = [
            [145, 'ژئوپلتیک آلمان: گرفتار جغرافیا', 'premium', true, ['آلمان', 'اروپا']],
            [144, 'ژئوپلتیک استرالیا: تنهای قدرتمند', 'premium', true, ['استرالیا']],
            [143, 'مسابقه‌ی ایران با زمان', 'free', true, ['ایران']],
            [142, 'پرسش و پاسخ', 'premium', true, ['پرسش و پاسخ']],
            [141, 'ژئوپلتیک ترکیه', 'premium', true, ['ترکیه', 'خاورمیانه']],
            [140, 'تنگه‌ی هرمز و انرژی جهان', 'free', true, ['ایران', 'انرژی']],
            [139, 'چین و دریای جنوبی', 'premium', true, ['چین']],
            [138, 'تاریخ کوتاه ناتو', 'premium', true, ['اروپا', 'تاریخ']],
        ];

        foreach ($episodes as $i => [$number, $title, $access, $audio, $tags]) {
            $post = Post::create([
                'title' => "اپیزود {$number}/ {$title}", 'episode_number' => $number, 'access' => $access, 'status' => 'published',
                'excerpt' => "در این اپیزود درباره‌ی «{$title}» صحبت می‌کنیم؛ چرا مهم است و چه چیزی را از دست داده‌ایم.",
                'body' => $this->body($title), 'published_at' => now()->subWeeks($i * 2)->subDays(1),
                'sponsor_name' => $i % 3 === 0 ? 'اسپانسر نمونه' : null, 'sponsor_url' => $i % 3 === 0 ? 'https://example.com' : null,
            ]);
            $post->forceFill(['cover_path' => $cover, 'notified_at' => now()])->save();
            $post->syncTagNames($tags);

            if ($audio) {
                $this->attachAudio($post, 60 + $i * 5);
            }
        }

        // Text-only articles
        foreach ([['چرا جغرافیا سرنوشت است؟', 'free'], ['راهنمای خواندن نقشه‌های ژئوپلتیک', 'premium']] as $i => [$title, $access]) {
            $post = Post::create([
                'title' => $title, 'access' => $access, 'status' => 'published', 'excerpt' => "مقاله‌ای کوتاه با عنوان «{$title}».",
                'body' => $this->body($title), 'published_at' => now()->subDays($i + 1),
            ]);
            $post->forceFill(['cover_path' => $cover, 'notified_at' => now()])->save();
            $post->syncTagNames(['مقاله']);
        }

        Post::create(['title' => 'پیش‌نویس اپیزود بعد', 'access' => 'premium', 'status' => 'draft', 'body' => '<p>هنوز آماده نیست.</p>']);
    }

    private function body(string $title): string
    {
        return "<p>این متن نمونه برای «{$title}» است. جغرافیا مرزهای ممکن را تعیین می‌کند و سیاست‌مداران در همین چارچوب تصمیم می‌گیرند.</p>"
            .'<h2>چرا مهم است؟</h2>'
            .'<p>کتاب‌ها و نقشه‌ها نشان می‌دهند که <strong>قدرت</strong> بدون جغرافیا معنا ندارد؛ هر کشور با <em>محدودیت‌ها</em> و فرصت‌های خودش روبه‌روست.</p>'
            .'<ul><li>موقعیت و همسایگان</li><li>منابع و راه‌های ارتباطی</li><li>تاریخ و حافظه‌ی جمعی</li></ul>'
            .'<blockquote>جغرافیا سرنوشت نیست، اما فهرست گزینه‌ها را کوتاه می‌کند.</blockquote>'
            .'<p>برای ادامه‌ی بحث به اپیزود گوش دهید و نظرتان را بفرستید.</p>';
    }

    /** Use the prototype logo as a cover so the app has something to show. */
    private function copyCover(): ?string
    {
        $logo = base_path('../docs/design/Logo.png');
        if (! File::exists($logo)) {
            return null;
        }

        Storage::disk('public')->put('covers/demo-logo.png', File::get($logo));

        return 'covers/demo-logo.png';
    }

    /** A short generated tone, so the player has real audio to play. Falls back to a stub without ffmpeg. */
    private function attachAudio(Post $post, int $seconds): void
    {
        $path = "audio/original/demo-{$post->id}.mp3";
        $disk = Storage::disk('local');
        $disk->makeDirectory('audio/original');

        $result = Process::run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'sine=frequency='.(220 + $post->id * 20).":duration={$seconds}", '-ac', '1', '-b:a', '64k', $disk->path($path)]);
        if ($result->failed() || ! $disk->exists($path)) {
            $disk->put($path, 'demo-audio');
        }

        $post->forceFill(['audio_path' => $path, 'audio_size' => $disk->size($path), 'audio_duration' => $seconds])->save();
    }
}
