<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class AtomicPrivateFileWriter
{
    public function write(string $destination, string $bytes): void
    {
        $directory = dirname($destination);

        if ((! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory))
            || ! chmod($directory, 0700)) {
            throw new RuntimeException('Unable to prepare the private candidate directory.');
        }

        $temporary = tempnam($directory, '.candidate-');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create a private candidate temporary file.');
        }

        $promoted = false;
        $handle = null;

        try {
            if (! chmod($temporary, 0600)) {
                throw new RuntimeException('Unable to secure the private candidate temporary file.');
            }

            $handle = fopen($temporary, 'wb');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the private candidate temporary file.');
            }

            $offset = 0;
            $length = strlen($bytes);

            while ($offset < $length) {
                $written = fwrite($handle, substr($bytes, $offset));

                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to complete the private candidate write.');
                }

                $offset += $written;
            }

            if (! fflush($handle)) {
                throw new RuntimeException('Unable to flush the private candidate file.');
            }

            if (function_exists('fsync') && ! fsync($handle)) {
                throw new RuntimeException('Unable to synchronize the private candidate file.');
            }

            fclose($handle);
            $handle = null;

            if (file_exists($destination) || ! rename($temporary, $destination)) {
                throw new RuntimeException('Unable to atomically promote the private candidate file.');
            }

            $promoted = true;

            if (! chmod($destination, 0600)) {
                throw new RuntimeException('Unable to secure the private candidate file.');
            }
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (is_file($temporary)) {
                @unlink($temporary);
            }

            if ($promoted && is_file($destination)) {
                @unlink($destination);
            }

            throw $exception;
        }
    }
}
