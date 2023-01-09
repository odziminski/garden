<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use App\Http\Controllers\PlantsController;

class PlantTest extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */

    public function testPrepareWebcamAvatar()
    {
        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
        $tmpFile = fopen($tmpFilePath, 'w');
        fwrite($tmpFile, 'test');
        fclose($tmpFile);

        $webcamImage = 'data:image/png;base64,' . base64_encode(file_get_contents($tmpFilePath));

        $result = (new PlantsController())->prepareWebcamAvatar($webcamImage);

        $this->assertInstanceOf(UploadedFile::class, $result);
        unlink($tmpFilePath);

        $this->assertFileDoesNotExist($tmpFilePath);
    }

    


}
