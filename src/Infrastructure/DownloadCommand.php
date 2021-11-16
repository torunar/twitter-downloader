<?php

namespace Torunar\TwitterDownloader\Infrastructure;

use Abraham\TwitterOAuth\TwitterOAuth;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Torunar\TwitterDownloader\DownloadManager\Service as DownloadManager;
use Torunar\TwitterDownloader\Twitter\Service;

class DownloadCommand extends Command
{
    protected static $defaultName = 'twitter-downloader:download';

    protected static $defaultDescription = 'Downloads content from Twitter user feed';

    protected function configure(): void
    {
        $this->addArgument(
            'api-key',
            InputArgument::REQUIRED,
            'REST API application consumer key'
        );
        $this->addArgument(
            'api-secret',
            InputArgument::REQUIRED,
            'REST API application consumer secret'
        );
        $this->addArgument(
            'user',
            InputArgument::REQUIRED,
            'User handle' . PHP_EOL . ' <info>E.g.: kojima_hideo</info>'
        );
        $this->addArgument(
            'output-dir',
            InputArgument::OPTIONAL,
            'Output directory',
            '/tmp/twitter-downloader/'
        );
        $this->addOption(
            'id',
            'i',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Tweet ID' . PHP_EOL . '<info>E.g.: 1460373093533511680</info>',
            []
        );
        $this->addOption(
            'media-type',
            'm',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Media types to load' . PHP_EOL . '<info>Possible values: photo, video, text</info>',
            ['photo', 'video']
        );
        $this->addOption(
            'keyword',
            'w',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Keyword Tweet should contain' . PHP_EOL . '<info>E.g.: death_stranding</info>',
            []
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputDirectory = $this->initOutputDirectory($input->getArgument('output-dir'));
        $downloadManager = $this->initDownloadManager($outputDirectory, $output);
        $twitterService = $this->initTwitterService(
            $input->getArgument('api-key'),
            $input->getArgument('api-secret'),
        );

        $maxId = null;
        do {
            $idsToLoad = $input->getOption('id');
            $feed = $idsToLoad
                ? $twitterService->getFeedByIds($idsToLoad)
                : $twitterService->getFeed($input->getArgument('user'), $maxId);

            if (!$feed) {
                break;
            }

            $oldestTweet = end($feed);
            $maxId = $oldestTweet->id - 1;

            $feed = $twitterService->filterFeed($feed, $input->getOption('keyword'));
            $downloadManager->download($feed, $input->getOption('media-type'));

            if ($idsToLoad) {
                break;
            }
        } while (true);

        return self::SUCCESS;
    }

    private function initOutputDirectory(string $directoryPath): string
    {
        if (file_exists($directoryPath) && !is_dir($directoryPath)) {
            throw new Exception("{$directoryPath} is not a directory");
        }

        if (is_dir($directoryPath) && !is_writable($directoryPath)) {
            throw new Exception("{$directoryPath} is not writable");
        }

        if (is_dir($directoryPath) && is_writable($directoryPath)) {
            return realpath($directoryPath);
        }

        $isDirCreated = mkdir($directoryPath, 0777, true);
        if (!$isDirCreated) {
            throw new Exception("Can't create {$directoryPath} directory");
        }

        return realpath($directoryPath);
    }

    private function initTwitterService(string $key, string $secret): Service
    {
        return new Service(
            new TwitterOAuth($key, $secret)
        );
    }

    private function initDownloadManager(string $outputDirectory, OutputInterface $output): DownloadManager
    {
        return new DownloadManager(
            $outputDirectory,
            fn() => $output->write('.'),
            fn() => $output->write('E')
        );
    }
}
