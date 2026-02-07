<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;

$targetUrl = $argv[1] ?? 'https://news.yahoo.co.jp/';
$selector = $argv[2] ?? 'main a';
$limit = isset($argv[3]) ? max(1, (int) $argv[3]) : 10;

$client = Client::createChromeClient(
    null,
    null,
    [
        '--headless=new',
        '--disable-gpu',
        '--no-sandbox',
        '--window-size=1280,720',
    ]
);

try {
    $crawler = $client->request('GET', $targetUrl);
    $crawler = $client->waitFor($selector, 10);

    $items = $crawler->filter($selector);
    if ($items->count() === 0) {
        fwrite(STDERR, "No elements matched selector: {$selector}\n");
        exit(1);
    }

    $results = extractTextAndLinks($items, $limit);
    foreach ($results as $index => $row) {
        $number = $index + 1;
        echo "{$number}. {$row['text']}";
        if ($row['href'] !== null) {
            echo " ({$row['href']})";
        }
        echo PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Scraping failed: {$error->getMessage()}\n");
    exit(1);
} finally {
    $client->quit();
}

function extractTextAndLinks(Crawler $items, int $limit): array
{
    $results = [];
    $items->each(function ($node) use (&$results, $limit): void {
        if (count($results) >= $limit) {
            return;
        }

        $text = trim($node->text('', true));
        if ($text === '') {
            return;
        }

        $href = null;
        if ($node->nodeName() === 'a' || $node->filter('a')->count() > 0) {
            $linkNode = $node->nodeName() === 'a' ? $node : $node->filter('a')->first();
            $href = $linkNode->attr('href');
        }

        $results[] = [
            'text' => $text,
            'href' => $href,
        ];
    });

    return $results;
}
