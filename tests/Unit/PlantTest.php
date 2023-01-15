<?php

namespace Tests\Unit;

use App\Http\Controllers\PlantsController;
use App\Http\Requests\StorePlantRequest;
use App\Models\History;
use App\Models\Needs;
use App\Models\Plant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
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

    public function testGetPercentageOfCareDate(): void
    {
        $from = Carbon::parse('2022-01-01')->valueOf();
        $to = Carbon::parse('2022-02-01')->valueOf();

        $result = (new PlantsController())->getPercentageOfCareDate($from, $to);


        $this->assertIsInt($result);

        $this->assertEquals(100, $result);
    }

    public function testStore(): void
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


    public function testPlantAdditionPageIsAccessible()
    {
        $response = $this->get(route('add-plant'));

        $response->assertStatus(200);
    }

    public function testPlantAdditionFormSubmission()
    {
        $testUser = User::find(1);
        Auth::login($testUser);

        $name = 'Integration Test Rose';

        $plantData = [
            'name' => $name,
            'watering_frequency' => 3,
            'fertilizing_frequency' => 15,
        ];

        $response = $this->post(route('add-plant-query'), $plantData);
        $response->assertStatus(302);
        $this->assertDatabaseHas('plants', [
            'name' => $name,
        ]);
        Plant::where('name',$name)->delete();

    }


    public function testWebcamFunctionality()
    {
        $agent = new Agent();
        if (!$agent->isMobile()) {
            $response = $this->get(route('add-plant-query'));
            $response->assertSee('Open a webcam');
        }
    }

    public function testPlantPageContainsCorrectData()
    {
        $plant =  Plant::factory()->create();

        $response = $this->get(route('welcome'));

        $response->assertSee($plant->name);
        $response->assertSee('scheduled watering');
        $response->assertSee('scheduled fertilizing');
    }

    public function testPlantPageShowsCorrectMessagesForAuthenticatedUser()
    {
        $testUser = User::find(1);
        Auth::login($testUser);

        $plant = Plant::factory()->create();
        $response = $this->get(route('welcome'));

        $response->assertSee('visit it');
    }

    public function testPlantPageShowsCorrectMessagesForGuest()
    {
        Auth::logout();
        $response = $this->get(route('welcome'));


        $response->assertSee('visit it');
    }


}
