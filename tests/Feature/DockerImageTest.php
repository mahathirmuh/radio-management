<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Docker image: .dockerignore is a list of what is let in, so a folder the application reads that is not on the list
 * works on every machine and is missing only in the image. 1.13.0 to 1.21.2 went out without lang/: production showed the
 * keys of its texts ("login.title") instead of the texts.
 */
class DockerImageTest extends TestCase
{
    public function test_every_folder_the_application_reads_goes_into_the_image(): void
    {
        $lines = array_map('trim', file(base_path('.dockerignore')));
        $autoload = json_decode((string) file_get_contents(base_path('composer.json')), true)['autoload'] ?? [];
        $paths = array_merge(
            [app_path(), base_path('bootstrap'), config_path(), database_path(), lang_path(), public_path(), resource_path(), base_path('routes')],
            array_map(fn (string $path) => base_path($path), array_merge(array_values($autoload['psr-4'] ?? []), $autoload['classmap'] ?? [], $autoload['files'] ?? [])),
        );

        foreach (array_unique($paths) as $path) {
            $folder = preg_split('#[\\\\/]#', ltrim(substr($path, strlen(base_path())), '\\/'))[0];
            $this->assertContains("!$folder/", $lines, "$folder/ is read by the application but .dockerignore leaves it out of the image");
        }
    }
}
