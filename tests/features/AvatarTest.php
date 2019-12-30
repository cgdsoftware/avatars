<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LaravelEnso\Core\App\Models\User;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling();

        $this->seed()
            ->actingAs($this->user = User::first());

        $this->createTestFolder();

        $this->user->generateAvatar();
    }

    public function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    /** @test */
    public function can_display_avatar()
    {
        $this->get(route('core.avatars.show', [$this->user->avatar->id], false))
            ->assertStatus(200)
            ->assertHeader(
                'content-disposition',
                'inline; filename='.$this->user->avatar->file->saved_name
            );
    }

    /** @test */
    public function can_update_avatar()
    {
        $this->user->load('avatar.file');

        $oldAvatar = $this->user->avatar;

        $this->patch(route('core.avatars.update', $this->user->avatar->id, false));

        Storage::assertMissing(
            $this->filePath($oldAvatar)
        );

        $this->assertNotNull($this->user->avatar->fresh());
        $this->assertNotEquals($oldAvatar->id, $this->user->avatar->id);

        Storage::assertExists(
            $this->user->avatar->folder().DIRECTORY_SEPARATOR.$this->user->avatar->file->saved_name
        );
    }

    /** @test */
    public function can_store_avatar()
    {
        $this->user->load('avatar');

        $this->post(route('core.avatars.store', [], false), [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $this->user->load('avatar');

        $this->assertNotNull($this->user->avatar);

        Storage::assertExists(
            $this->filePath($this->user->avatar)
        );
    }

    private function filePath($avatar)
    {
        return $avatar->folder()
            .DIRECTORY_SEPARATOR
            .$avatar->file->saved_name;
    }

    private function createTestFolder()
    {
        if (! Storage::has(config('enso.files.paths.testing'))) {
            Storage::makeDirectory(config('enso.files.paths.testing'));
        }
    }

    private function cleanUp()
    {
        $this->user->avatar->delete();

        Storage::deleteDirectory(config('enso.files.paths.testing'));
    }
}
