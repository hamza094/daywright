<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\FileType;
use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Override;
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

    /** @test */
    public function store_method_when_file_missing(): void
    {
        $id = 1;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid file upload');

        $invalidFile = UploadedFile::fake()->create('invalid.txt', 0);

        $this->fileService->store($id, $invalidFile, $this->fileType);
    }
}
