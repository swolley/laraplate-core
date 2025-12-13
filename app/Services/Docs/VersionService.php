<?php

declare(strict_types=1);

namespace Modules\Core\Services\Docs;

class VersionService
{
    public function __construct(private readonly ?string $gitBasePath = null)
    {
    }

    public function getCurrentPackageVersion(): string
    {
        return $this->getCurrentTag() ?? version();
    }

    public function getCurrentCommitHash(): ?string
    {
        $gitDir = $this->getGitDirectory();

        if (! is_dir($gitDir)) {
            return null;
        }

        $headFile = $gitDir . '/HEAD';

        if (! file_exists($headFile)) {
            return null;
        }

        $headContent = mb_trim(file_get_contents($headFile));

        if (preg_match('/^[0-9a-f]{40}$/', $headContent)) {
            return $headContent;
        }

        if (preg_match('/^ref: refs\/heads\/(.+)$/', $headContent, $matches)) {
            $branchFile = $gitDir . '/refs/heads/' . $matches[1];

            if (file_exists($branchFile)) {
                return mb_trim(file_get_contents($branchFile));
            }
        }

        return null;
    }

    public function getCurrentTag(): ?string
    {
        $gitDir = $this->getGitDirectory();

        if (! is_dir($gitDir)) {
            return null;
        }

        $currentCommit = $this->getCurrentCommitHash();

        if (in_array($currentCommit, [null, '', '0'], true)) {
            return null;
        }

        $tagsDir = $gitDir . '/refs/tags';

        if (! is_dir($tagsDir)) {
            return null;
        }

        $tags = scandir($tagsDir);
        $lastTag = null;
        $lastTagVersion = '0.0.0';

        foreach ($tags as $tag) {
            if (in_array($tag, ['.', '..'], true)) {
                continue;
            }

            $tagFile = $tagsDir . '/' . $tag;

            if (! is_file($tagFile)) {
                continue;
            }

            $tagCommit = mb_trim(file_get_contents($tagFile));

            if ($tagCommit === $currentCommit) {
                return $tag;
            }

            if (file_exists($gitDir . '/objects/' . mb_substr($tagCommit, 0, 2) . '/' . mb_substr($tagCommit, 2))) {
                $tagContent = file_get_contents($gitDir . '/objects/' . mb_substr($tagCommit, 0, 2) . '/' . mb_substr($tagCommit, 2));

                if (mb_strpos($tagContent, $currentCommit) !== false) {
                    return $tag;
                }
            }

            $tagVersion = mb_ltrim($tag, 'v');

            if (version_compare($tagVersion, $lastTagVersion, '>')) {
                $lastTag = $tag;
                $lastTagVersion = $tagVersion;
            }
        }

        return $lastTag;
    }

    public function getCommitDate(?string $commitHash): ?string
    {
        if ($commitHash === null) {
            return null;
        }

        $gitDir = $this->getGitDirectory();

        if (! is_dir($gitDir)) {
            return null;
        }

        $objectPath = $gitDir . '/objects/' . mb_substr($commitHash, 0, 2) . '/' . mb_substr($commitHash, 2);

        if (! file_exists($objectPath)) {
            return null;
        }

        $commitContent = file_get_contents($objectPath);

        if ($commitContent === false) {
            return null;
        }

        $commitContent = gzuncompress($commitContent);

        if ($commitContent === false) {
            return null;
        }

        if (preg_match('/committer .*? (\d+) ([\+\-]\d{4})/', $commitContent, $matches)) {
            $timestamp = (int) $matches[1];

            return date('Y-m-d H:i:s', $timestamp);
        }

        return null;
    }

    private function getGitDirectory(): string
    {
        return ($this->gitBasePath ?? base_path()) . '/.git';
    }
}

