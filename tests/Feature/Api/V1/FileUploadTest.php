<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\FileType;
use App\Exceptions\Integrations\StorageException;
use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    private FileService $fileService;

    private string $fileType;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileService = new FileService;
        $this->fileType = FileType::AVATAR;
    }

    #[Test]
    public function store_method_when_file_missing(): void
    {
        $id = 1;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid file upload');

        $invalidFile = UploadedFile::fake()->create('invalid.txt', 0);

        $this->fileService->store($id, $invalidFile, $this->fileType);
    }

    #[Test]
    public function store_method_throws_storage_exception_when_s3_upload_fails(): void
    {
        $adapter = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $adapter->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($adapter);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('S3 file upload failed.');

        $file = UploadedFile::fake()->image('avatar.jpg');
        $this->fileService->store(1, $file, $this->fileType);
    }
}
