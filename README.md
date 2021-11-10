# Twitter Downloader

Downloads content of a specific Twitter account.
Saves photos, videos and tweet texts for offline usage. 

## Installation

```bash
$ composer global require torunar/twitter-downloader
```

## Configuration

To use Twitter Downloader, you'll need to register Twitter REST API application.

Apply for a developer account here: https://developer.twitter.com/en/apply-for-access.

## Usage

```bash
$  twitter-downloader [options] [--] <api-key> <api-secret> <user> [<output-dir>]
```

#### Arguments
* `api-key` — REST API application consumer key.
* `api-secret` — REST API application consumer secret.
* `user` — User handle. E.g.: `kojima_hideo`.
* `output-dir` — Output directory. By default, `/tmp/twitter-downloader/` used.

#### Options
* `-m` or `--media-type[=MEDIA-TYPE]` — Media types to load. Possible media types are `photo`, `video` and `text`. By default `photo` and `video` are downloaded (multiple values allowed: `-m=photo -m=text`).
* `-w` or `--keyword[=KEYWORD]` — Keyword the tweet should contain to be downloaded. E.g.: `death_stranding` (multiple values allowed: `-w=death_stranging -w=mgs`).
