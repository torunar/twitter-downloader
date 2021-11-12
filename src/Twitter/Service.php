<?php

namespace Torunar\TwitterDownloader\Twitter;

use Abraham\TwitterOAuth\TwitterOAuth;
use DateTimeImmutable;
use GuzzleHttp\Pool;
use stdClass;

class Service
{
    private TwitterOAuth $client;

    public function __construct(TwitterOAuth $client)
    {
        $this->client = $client;
    }

    /**
     * @param string      $user
     * @param string|null $maxId
     *
     * @return array<\Torunar\TwitterDownloader\Twitter\Tweet>
     * @throws \Exception
     */
    public function getFeed(string $user, ?string $maxId = null): array
    {
        $params = [
            'screen_name'     => $user,
            'exclude_replies' => true,
            'tweet_mode'      => 'extended',
            'count'           => 200,
            'trim_user'       => true,
        ];
        if ($maxId !== null) {
            $params['max_id'] = $maxId;
        }

        return array_map(
            function (stdClass $tweetData): Tweet {
                $videos = $this->getVideos($tweetData);
                $photos = $this->getPhotos($tweetData);
                if (isset($tweetData->retweeted_status)) {
                    $videos = array_unique([...$videos, ...$this->getVideos($tweetData->retweeted_status)]);
                    $photos = array_unique([...$photos, ...$this->getPhotos($tweetData->retweeted_status)]);
                }

                // same amount of photos and videos === video + preview image
                for ($i = 0; $i < count($videos); $i++) {
                    unset($photos[$i]);
                }

                return new Tweet(
                    $tweetData->id_str,
                    new DateTimeImmutable($tweetData->created_at),
                    $tweetData->full_text,
                    $photos,
                    $videos
                );
            },
            $this->client->get('statuses/user_timeline', $params)
        );
    }

    public function filterFeed(array $feed, array $keywords)
    {
        if (!$keywords) {
            return $feed;
        }

        return array_filter($feed, function (Tweet $tweet) use ($keywords) {
            return $this->isMatchingTweet($tweet, $keywords);
        });
    }

    private function isMatchingTweet(Tweet $tweet, array $keywords)
    {
        foreach ($keywords as $keyword) {
            if (preg_match('~\b' . preg_quote($keyword) . '\b~ui', $tweet->text)) {
                return true;
            }
        }

        return false;
    }

    private function getBestQualityVideoSource(stdClass $mediaEntity)
    {
        $bitrateToSourceMap = [];
        foreach ($mediaEntity->video_info->variants as $variant) {
            if (!isset($variant->bitrate)) {
                continue;
            }

            $bitrateToSourceMap[$variant->bitrate] = $variant->url;
        }

        $maxBitrate = max(array_keys($bitrateToSourceMap));

        return $bitrateToSourceMap[$maxBitrate];
    }

    private function getBestQualityPhotoSource(stdClass $mediaEntity): string
    {
        return $mediaEntity->media_url_https . ':orig';
    }

    private function getVideos(stdClass $tweetData): array
    {
        if (!isset($tweetData->extended_entities->media)) {
            return [];
        }

        $videos = [];
        foreach ($tweetData->extended_entities->media as $mediaEntity) {
            if ($mediaEntity->type !== 'video') {
                continue;
            }

            $videos[] = $this->getBestQualityVideoSource($mediaEntity);
        }

        return $videos;
    }

    private function getPhotos(stdClass $tweetData)
    {
        if (!isset($tweetData->extended_entities->media)) {
            return [];
        }

        $photos = [];
        foreach ($tweetData->extended_entities->media as $mediaEntity) {
            if ($mediaEntity->type !== 'photo') {
                continue;
            }

            $photos[] = $this->getBestQualityPhotoSource($mediaEntity);
        }

        return $photos;
    }
}
