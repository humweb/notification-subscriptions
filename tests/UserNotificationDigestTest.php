<?php

use Humweb\Notifications\Notifications\UserNotificationDigest;
use Humweb\Notifications\Tests\Stubs\ArrayFormatNotification;
use Humweb\Notifications\Tests\Stubs\DigestFormatNotification;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\StructuredDigestNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

it('returns the specified channel via()', function () {
    $digest = new UserNotificationDigest('mail', collect());
    expect($digest->via(null))->toBe(['mail']);
});

it('converts strings to title case with spaces', function () {
    $digest = new UserNotificationDigest('mail', collect());
    expect($digest->toTitleCase('NotifyCommentCreated'))->toBe('Notify Comment Created');
    expect($digest->toTitleCase('notify_comment_created'))->toBe('Notify Comment Created');
});

it('maps items correctly in toArray()', function () {
    $createdAt = Carbon::parse('2023-01-01 08:30:00');
    $items = collect([
        [
            'class' => NotifyCommentCreated::class,
            'data' => ['my-comment'],
            'created_at' => $createdAt,
        ],
    ]);

    $digest = new UserNotificationDigest('database', $items);
    $array = $digest->toArray(null);

    expect($array['title'])->toBe('Your Notification Digest');
    expect($array['summary'])->toBe('You have 1 new notifications.');
    expect($array['items'])->toHaveCount(1);
    expect($array['items'][0]['type'])->toBe('Notify Comment Created');
    expect($array['items'][0]['data'])->toBe(['my-comment']);
    expect($array['items'][0]['received_at'])->toBe($createdAt->toIso8601String());
});

it('toMail() uses toDigestFormat when available (positional args)', function () {
    $items = collect([
        [
            'class' => DigestFormatNotification::class,
            'data' => ['alpha', 'beta'], // positional args
            'created_at' => Carbon::parse('2023-01-01 08:30:00'),
        ],
    ]);

    $digest = new UserNotificationDigest('mail', $items);
    $mail = $digest->toMail((object) ['id' => 1]);
    $arr = $mail->toArray();

    expect($arr['subject'])->toBe('Your Notification Digest');
    expect($arr['introLines'])->toContain('Here is a summary of your recent notifications:');

    // Ensure compileComponents contains expected summary line
    [$components, $usedStructured] = $digest->compileComponents((object) ['id' => 1]);
    $texts = array_map(fn ($c) => $c['type'] === 'line' ? $c['text'] : ($c['type'] === 'separator' ? '---' : ''), $components);
    $joined = implode("\n", array_filter($texts));
    expect($joined)->toContain('Digest for alpha and beta');
    expect($joined)->toContain('---');
});

it('toMail() falls back to toArray() when toDigestFormat is not present (associative args)', function () {
    $items = collect([
        [
            'class' => ArrayFormatNotification::class,
            'data' => ['title' => 'Title X', 'message' => 'Message Y'], // associative args via container
            'created_at' => Carbon::parse('2023-01-01 08:30:00'),
        ],
    ]);

    $digest = new UserNotificationDigest('mail', $items);
    $mail = $digest->toMail((object) ['id' => 2]);
    $arr = $mail->toArray();

    expect($arr['introLines'])->toContain('Update: Title X - Message Y');
});

it('toMail() uses default summary when notification has no special methods', function () {
    $createdAt = Carbon::parse('2023-01-01 08:30:00');
    $items = collect([
        [
            'class' => NotifyCommentCreated::class,
            'data' => ['my-comment'],
            'created_at' => $createdAt,
        ],
    ]);

    $digest = new UserNotificationDigest('mail', $items);
    $mail = $digest->toMail(null);
    $arr = $mail->toArray();

    $expectedSummary = 'Notification: Notify Comment Created (Received: '.$createdAt->format('Y-m-d H:i').')';
    expect($arr['introLines'])->toContain($expectedSummary);
});

it('toMail() renders structured components when a notification uses toDigest()', function () {
    // Use the package view
    Config::set('notification-subscriptions.digest_markdown_view', 'notification-subscriptions::digest');

    $items = collect([
        [
            'class' => StructuredDigestNotification::class,
            'data' => ['alpha', 'beta'],
            'created_at' => Carbon::parse('2023-01-01 08:30:00'),
        ],
    ]);

    $digest = new UserNotificationDigest('mail', $items);
    // Directly inspect compiled components (source of truth for rendering)
    [$components, $usedStructured] = $digest->compileComponents((object) ['id' => 1]);
    expect($usedStructured)->toBeTrue();
    $types = array_values(array_unique(array_map(fn ($c) => $c['type'], $components)));
    expect($types)->toContain('heading');
    expect($types)->toContain('button');
    expect($types)->toContain('list');
});

it('toMail() handles invalid class gracefully using default summary', function () {
    $createdAt = Carbon::parse('2023-01-01 08:30:00');
    $items = collect([
        [
            'class' => 'NonExisting\\Notifications\\FakeNotice',
            'data' => ['anything'],
            'created_at' => $createdAt,
        ],
    ]);

    $digest = new UserNotificationDigest('mail', $items);
    $mail = $digest->toMail(null);
    $arr = $mail->toArray();

    $expectedSummary = 'Notification: Fake Notice (Received: '.$createdAt->format('Y-m-d H:i').')';
    expect($arr['introLines'])->toContain($expectedSummary);
});
