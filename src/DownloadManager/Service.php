<?php

namespace Torunar\TwitterDownloader\DownloadManager;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;

class Service
{
    private string $outputDirectory;

    /**
     * @var callable
     */
    private $successCallback;

    /**
     * @var callable
     */
    private $failureCallback;

    public function __construct(string $outputDirectory, callable $successCallback, callable $failureCallback)
    {
        $this->outputDirectory = $outputDirectory;
        $this->successCallback = $successCallback;
        $this->failureCallback = $failureCallback;
    }

    /**
     * @param array<\Torunar\TwitterDownloader\Twitter\Tweet> $feed
     * @param array<string>                                   $mediaTypes
     */
    public function download(array $feed, array $mediaTypes): void
    {
        if (!$feed) {
            return;
        }

        $this->queueDownload($feed, $mediaTypes)->wait();
    }

    /**
     * @param array<\Torunar\TwitterDownloader\Twitter\Tweet> $feed
     * @param array<string>                                   $mediaTypes
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function queueDownload(array $feed, array $mediaTypes): PromiseInterface
    {
        $client = new Client();

        $arePhotosDownloaded = in_array('photo', $mediaTypes);
        $areVideosDownloaded = in_array('video', $mediaTypes);
        $areTextsDownloaded = in_array('text', $mediaTypes);

        $queue = [];
        foreach ($feed as $tweet) {
            $prefix = $tweet->date->format('Ymd-His-') . $tweet->id;
            $counter = 0;

            if ($areTextsDownloaded && $tweet->text) {
                $promise = new Promise(function () use (&$promise, $prefix, $tweet) {
                    file_put_contents($this->outputDirectory . '/' . $prefix . '.txt', $tweet->text);
                    $promise->resolve($tweet);
                });
                $queue[] = &$promise;
                unset($promise);
            }

            if ($arePhotosDownloaded) {
                $addCounter = count($tweet->photos) > 1;
                foreach ($tweet->photos as $url) {
                    $queue[] = $client->getAsync(
                        $url,
                        ['sink' => $this->getPhotoDownloadPath($url, $prefix, $addCounter, $counter)]
                    );
                }
            }

            if ($areVideosDownloaded) {
                $addCounter = count($tweet->videos) > 1;
                foreach ($tweet->videos as $url) {
                    $queue[] = $client->getAsync(
                        $url,
                        ['sink' => $this->getVideoDownloadPath($url, $prefix, $addCounter, $counter)]
                    );
                }
            }
        };

        return (new EachPromise(
            $queue,
            [
                'concurrency' => 10,
                'fulfilled'   => $this->successCallback,
                'rejected'    => $this->failureCallback,
            ]
        ))->promise();
    }

    private function getPhotoDownloadPath(string $url, string $prefix, bool $addCounter, int &$counter = 0): string
    {
        $extension = str_replace(':orig', '', pathinfo($url, PATHINFO_EXTENSION));

        return $this->outputDirectory . '/' .
            $prefix .
            ($addCounter
                ? '-' . $counter++
                : ''
            )
            . '.' . $extension;
    }

    private function getVideoDownloadPath(string $url, string $prefix, bool $addCounter, int &$counter = 0): string
    {
        return $this->outputDirectory . '/' .
            $prefix .
            ($addCounter
                ? '-' . $counter++
                : ''
            )
            . '.mp4';
    }
}
