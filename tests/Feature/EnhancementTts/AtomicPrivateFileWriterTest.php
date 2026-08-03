<?php

namespace Tests\Feature\EnhancementTts;

use App\Services\AtomicPrivateFileWriter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class AtomicPrivateFileWriterTest extends AnnouncementTestCase
{
    public function test_successful_write_is_promoted_without_a_partial_file(): void
    {
        $root = sys_get_temp_dir().'/gss-atomic-'.Str::uuid();
        $destination = $root.'/candidate.mp3';

        try {
            app(AtomicPrivateFileWriter::class)->write($destination, 'ID3-fictional');

            $this->assertSame('ID3-fictional', file_get_contents($destination));
            $this->assertSame([], glob($root.'/.candidate-*') ?: []);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_failed_atomic_promotion_removes_the_partial_file(): void
    {
        $root = sys_get_temp_dir().'/gss-atomic-'.Str::uuid();
        $destination = $root.'/candidate.mp3';
        File::makeDirectory($destination, 0700, true);

        try {
            app(AtomicPrivateFileWriter::class)->write($destination, 'ID3-fictional');
            $this->fail('Writing over an existing directory unexpectedly succeeded.');
        } catch (RuntimeException) {
            $this->assertDirectoryExists($destination);
            $this->assertSame([], glob($root.'/.candidate-*') ?: []);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
