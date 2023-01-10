<?php

namespace Tests\Unit;

use App\Http\Controllers\PlantsController;
use App\Http\Requests\StorePlantRequest;
use App\Models\History;
use App\Models\Needs;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;


class PlantTest extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */

    public function testPrepareWebcamAvatar(): void
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

    public function testGetPercentageOfCareDate() : void
    {
        $from = Carbon::parse('2022-01-01')->valueOf();
        $to = Carbon::parse('2022-02-01')->valueOf();

        $result = (new PlantsController())->getPercentageOfCareDate($from, $to);


        $this->assertIsInt($result);

        $this->assertEquals(100, $result);
    }

    public function testStore() : void
    {
        $request = new StorePlantRequest([
            'name' => 'Test plant',
            'watering_frequency' => 5,
            'fertilizing_frequency' => 10
        ]);
        $controller = new PlantsController();

        $result = $controller->store($request, 1);

        $this->assertInstanceOf(RedirectResponse::class, $result);

        $plant = Plant::where('name', 'Test plant')->first();
        $this->assertNotNull($plant);

        $history = History::where('plant_id', $plant->id)->first();
        $this->assertNotNull($history);
        $needs = Needs::where('plant_id', $plant->id)->first();
        $this->assertNotNull($needs);
        $plant->delete();

    }

}
