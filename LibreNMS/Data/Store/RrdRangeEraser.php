<?php

/**
 * RrdRangeEraser.php
 *
 * Permanently clears a time window in RRD files: every consolidated row
 * that overlaps the window becomes unknown (NaN) in every RRA, so a bogus
 * spike disappears from all graphs and statistics. The original file is
 * copied to a backup directory first.
 *
 * With rrdcached the daemon owns the files; the web container must see the
 * same directory (RRD_LOCAL_DIR, default /data/rrdcached) to edit them.
 *
 * @link       https://www.librenms.org
 */

namespace LibreNMS\Data\Store;

use App\Facades\LibrenmsConfig;
use RuntimeException;

class RrdRangeEraser
{
    /** Files named by a graph's DEFs, as rrdtool sees them. */
    public static function filesInOptions(array $options): array
    {
        $files = [];
        foreach ($options as $option) {
            if (is_string($option) && preg_match('/^DEF:[^=]+=(.+):[^:]+:(AVERAGE|MIN|MAX|LAST)(:.*)?$/s', $option, $m)) {
                $files[] = str_replace('\:', ':', $m[1]);
            }
        }

        return array_values(array_unique($files));
    }

    /** Where this server can read and write the RRD files. */
    private static function baseDir(): string
    {
        return LibrenmsConfig::get('rrdcached')
            ? (getenv('RRD_LOCAL_DIR') ?: '/data/rrdcached')
            : LibrenmsConfig::get('rrd_dir');
    }

    /** The local path for a file named in a graph, only inside the RRD directory. */
    public static function localPath(string $file): string
    {
        $base = realpath(self::baseDir());
        if ($base === false) {
            throw new RuntimeException('The RRD directory is not available to the web server.');
        }
        $rrdDir = rtrim((string) LibrenmsConfig::get('rrd_dir'), '/');
        if (str_starts_with($file, $rrdDir . '/')) {
            $file = substr($file, strlen($rrdDir) + 1);
        }
        $path = realpath($base . '/' . ltrim($file, '/'));
        if ($path === false || ! str_starts_with($path, $base . '/') || ! str_ends_with($path, '.rrd')) {
            throw new RuntimeException("Not an RRD file: $file");
        }

        return $path;
    }

    /**
     * Clear [$start, $end] in one file. Returns the number of rows cleared.
     */
    public static function erase(string $file, int $start, int $end): array
    {
        $path = self::localPath($file);
        $rrdtool = LibrenmsConfig::get('rrdtool', 'rrdtool');

        // Write out what rrdcached still holds for this file.
        if ($daemon = LibrenmsConfig::get('rrdcached')) {
            self::run([$rrdtool, 'flushcached', '--daemon', $daemon, $file]);
        }

        $xml = self::run([$rrdtool, 'dump', $path]);
        [$edited, $cleared] = self::clearRows($xml, $start, $end);
        if ($cleared === 0) {
            return ['file' => $file, 'cleared' => 0, 'backup' => null];
        }

        $backupDir = (getenv('RRD_BACKUP_DIR') ?: '/data/febnms-rrd-backups') . '/' . date('Ymd-His');
        $relative = substr($path, strlen(realpath(self::baseDir())) + 1);
        $backup = $backupDir . '/' . $relative;
        if (! is_dir(dirname($backup)) && ! mkdir(dirname($backup), 0775, true)) {
            throw new RuntimeException('Could not create the backup directory.');
        }
        if (! copy($path, $backup)) {
            throw new RuntimeException('Could not back up the file.');
        }

        $tmpXml = tempnam(sys_get_temp_dir(), 'rrd-erase-');
        $tmpRrd = $path . '.erase-' . getmypid();
        try {
            file_put_contents($tmpXml, $edited);
            self::run([$rrdtool, 'restore', '-f', $tmpXml, $tmpRrd]);
            if (! rename($tmpRrd, $path)) {
                throw new RuntimeException('Could not replace the file.');
            }
        } finally {
            @unlink($tmpXml);
            @unlink($tmpRrd);
        }

        return ['file' => $file, 'cleared' => $cleared, 'backup' => $backup];
    }

    /**
     * Set to NaN every row whose consolidation interval overlaps the window.
     *
     * @return array{0: string, 1: int}
     */
    public static function clearRows(string $xml, int $start, int $end): array
    {
        $step = preg_match('/<step>\s*(\d+)\s*<\/step>/', $xml, $m) ? (int) $m[1] : 300;
        $rowStep = $step;
        $cleared = 0;
        $lines = explode("\n", $xml);
        foreach ($lines as $index => $line) {
            if (preg_match('/<pdp_per_row>\s*(\d+)\s*<\/pdp_per_row>/', $line, $m)) {
                $rowStep = $step * (int) $m[1];
                continue;
            }
            if (! preg_match('/\/\s*(\d+)\s*-->\s*<row>/', $line, $m)) {
                continue;
            }
            $time = (int) $m[1];
            // A row covers (time - rowStep, time].
            if ($time > $start && $time - $rowStep < $end) {
                $lines[$index] = preg_replace('/<v>[^<]*<\/v>/', '<v>NaN</v>', $line);
                $cleared++;
            }
        }

        return [implode("\n", $lines), $cleared];
    }

    private static function run(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not run rrdtool.');
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException(trim((string) $error) ?: 'rrdtool failed.');
        }

        return (string) $output;
    }
}
