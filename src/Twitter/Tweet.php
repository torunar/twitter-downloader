<?php

namespace Torunar\TwitterDownloader\Twitter;

use DateTimeImmutable;

class Tweet
{
    public string $id;

    public DateTimeImmutable $date;

    public string $text;

    public array $photos;

    public array $videos;

    public function __construct(
        string $id,
        DateTimeImmutable $date,
        string $text,
        array $photos = [],
        array $videos = []
    ) {
        $this->id = $id;
        $this->date = $date;
        $this->text = $text;
        $this->photos = $photos;
        $this->videos = $videos;
    }
}
